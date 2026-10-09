<?php
const EVOLUTION_PERMISSIONS=['BADGES_MANAGE','BADGES_PRINT','MEMBERS_MANAGE','ACCREDITATIONS_MANAGE','PASSES_MANAGE','REQUESTS_MANAGE','ACTIVITY_LOGS','APP_BANNERS','GUIDES','PALACE','OFFICIAL_WEBSITE','MEMBER_ROLES'];
function adminHas(array $admin,string $key): bool {return ($admin['role']??'')==='founder'||in_array('*',(array)($admin['permissions']??[]),true)||in_array($key,(array)($admin['permissions']??[]),true);}
function requireAnyPermission(array $keys,bool $write=false): array {
    $admin=requireAdmin($write);foreach($keys as $key)if(adminHas($admin,$key))return $admin;
    jsonResponse(['error'=>'Vous n’avez pas la permission nécessaire pour cette opération.'],403);
}
function submissionPermission(string $type): string {return ['member'=>'MEMBERS_MANAGE','press'=>'ACCREDITATIONS_MANAGE','pass'=>'PASSES_MANAGE'][''.$type]??'submissions';}
function appendJournal(string $name,array $entry): void {
    global $dataDir;
    $entry=['id'=>bin2hex(random_bytes(12)),'date'=>gmdate('c')]+$entry;
    $line=json_encode($entry,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    if(file_put_contents($dataDir.'/'.$name.'.jsonl',$line,FILE_APPEND|LOCK_EX)===false)jsonResponse(['error'=>'Journalisation indisponible. Opération interrompue.'],503);
}
function activityJournal(string $action,array $details=[]): void {
    $admin=$_SESSION['admin']??null;
    appendJournal('activity_log',['user'=>$admin['username']??'Système','userId'=>$admin['id']??'system','action'=>$action,'details'=>$details]);
}
function backupDataFile(string $name): void {
    global $dataDir;
    if(!in_array($name,['state','submissions','admins','users','credential_prints','member_registry','special_profiles','admin_navigation','directory_meta','qrcodes','admin_preferences','blog_posts','blog_categories','comment_reports','feedback_messages','badge_templates','badge_designs','badge_print_jobs','community_notifications','comment_likes','post_likes','user_blocks','pass_access_config','access_passes','pass_signing_secret'],true))return;
    $path=$dataDir.'/'.$name.'.json';if(!is_file($path))return;
    $folder=$dataDir.'/backups/'.gmdate('Y-m-d');if(!is_dir($folder)&&!mkdir($folder,0750,true))jsonResponse(['error'=>'Sauvegarde indisponible.'],503);
    // One daily snapshot per file, plus an immutable snapshot before schema migration.
    if(!is_file($folder.'/'.$name.'.json')&&!copy($path,$folder.'/'.$name.'.json'))jsonResponse(['error'=>'Sauvegarde impossible.'],503);
}
function migrateEvolution(): void {
    global $dataDir;
    $migrations=(array)readJsonFile('migrations',[]);if(isset($migrations['2026-09-29-v3']))return;
    $folder=$dataDir.'/backups/before-2026-09-29-v3';if(!is_dir($folder)&&!mkdir($folder,0750,true))jsonResponse(['error'=>'La sauvegarde de migration est indisponible.'],503);
    foreach(glob($dataDir.'/*.json') as $file)if(!is_file($folder.'/'.basename($file))&&!copy($file,$folder.'/'.basename($file)))jsonResponse(['error'=>'La sauvegarde de migration a échoué.'],503);
    $state=(array)readJsonFile('state',[]);$state=abissaEvolutionState($state);
    // Preserve old editorial profiles in the backup, but never publish them as members.
    foreach((array)($state['COMMITTEE']['commissions']??[]) as $i=>$c)$state['COMMITTEE']['commissions'][$i]['members']=[];
    $state['SETTINGS']['schemaVersion']=3;
    writeJsonFile('state',$state);
    $admins=(array)readJsonFile('admins',[]);
    foreach($admins as &$admin){$p=$admin['permissions']??[];
        if(in_array('submissions',$p,true))$p=array_merge($p,['BADGES_MANAGE','BADGES_PRINT','MEMBERS_MANAGE','ACCREDITATIONS_MANAGE','PASSES_MANAGE']);
        if(in_array('SETTINGS',$p,true))$p=array_merge($p,['APP_BANNERS','MEMBER_ROLES','OFFICIAL_WEBSITE']);
        $admin['permissions']=array_values(array_unique($p));
    }unset($admin);writeJsonFile('admins',$admins);
    $migrations['2026-09-29-v3']=['appliedAt'=>gmdate('c'),'backup'=>'backups/before-2026-09-29-v3'];writeJsonFile('migrations',$migrations);
    activityJournal('Migration 2026-09-29 v3 appliquée');
}
function validPublicUrl(mixed $value,bool $image=false): bool {
    if(!is_string($value))return false;if($value==='')return true;
    if($image&&preg_match('~^data:image/(?:png|jpeg|webp);base64,[a-zA-Z0-9+/=]+$~',$value))return true;
    if(preg_match('~^(?:/appli/|assets/|uploads/)[a-zA-Z0-9_./%-]+$~',$value)&&!str_contains($value,'..'))return true;
    return filter_var($value,FILTER_VALIDATE_URL)!==false&&in_array(strtolower(parse_url($value,PHP_URL_SCHEME)??''),['https','http'],true)&&!parse_url($value,PHP_URL_USER);
}
function validateCoordinates(array $item): void {
    $lat=$item['latitude']??'';$lon=$item['longitude']??'';
    if($lat===''&&$lon==='')return;
    if(!is_numeric($lat)||!is_numeric($lon)||abs((float)$lat)>90||abs((float)$lon)>180)jsonResponse(['error'=>'Renseignez une latitude (−90 à 90) et une longitude (−180 à 180).'],422);
}
function validateBadgeSettings(array $settings): void {
    foreach(['widthMm'=>[20,297],'heightMm'=>[20,420],'marginMm'=>[0,30]] as $key=>$range)if(isset($settings[$key])&&(!is_numeric($settings[$key])||!is_finite((float)$settings[$key])||$settings[$key]<$range[0]||$settings[$key]>$range[1]))jsonResponse(['error'=>'Dimension invalide : '.$key],422);
    foreach(['commissionSize'=>[6,10],'commissionX'=>[15,85],'commissionY'=>[8,22]] as $key=>$range) {
        if(!is_numeric($settings[$key]??null)||$settings[$key]<$range[0]||$settings[$key]>$range[1])jsonResponse(['error'=>'Position ou taille de commission hors limites.'],422);
    }
    foreach(['commissionFont'=>['Arial','Georgia','AbissaBadge'],'commissionAlign'=>['left','center','right']] as $key=>$allowed)if(!in_array($settings[$key]??null,$allowed,true))jsonResponse(['error'=>'Police ou alignement invalide.'],422);
    $color=$settings['commissionColor']??'';
    if(!is_string($color)||($color!==''&&!preg_match('/^#[0-9a-f]{6}$/i',$color)))jsonResponse(['error'=>'Couleur invalide.'],422);
    $names=$settings['commissionNames']??[];
    if(!is_array($names)||count($names)>100)jsonResponse(['error'=>'Libellés invalides.'],422);
    foreach($names as $name)if(!is_string($name)||mb_strlen($name)>160)jsonResponse(['error'=>'Nom de commission invalide.'],422);
}
function validatedAdminPermissions(mixed $permissions): array {
    if(!is_array($permissions)||count($permissions)>100)jsonResponse(['error'=>'Liste de permissions invalide.'],422);
    // Older accounts may still submit the retired badge-control permissions.
    // Ignore those grants when saving an account with the current editor.
    $permissions=array_values(array_filter($permissions,fn($p)=>!in_array($p,['QR_MANAGE','QR_SCAN','SECURITY_LOGS','PRINT_LOGS'],true)));
    $allowed=array_merge(PUBLIC_CONTENT_KEYS,EVOLUTION_PERMISSIONS,ACCESS_PERMISSIONS,array_merge(COMMUNITY_PERMISSIONS,['*','users','submissions','directory','QRCODES']));
    foreach($permissions as $p)if(!is_string($p)||!in_array($p,$allowed,true))jsonResponse(['error'=>'Permission inconnue.'],422);
    return array_values(array_unique($permissions));
}
function validateStateShape(array $state): void {
    foreach(PUBLIC_CONTENT_KEYS as $key)if(array_key_exists($key,$state)&&!is_array($state[$key]))jsonResponse(['error'=>'Structure de rubrique invalide : '.$key],422);
    foreach(['MENU_ITEMS','SITES','HOTELS','PARTNERS','ADVERTISEMENTS','PROGRAMME','GALLERY'] as $key)foreach($state[$key]??[] as $item)if(!is_array($item))jsonResponse(['error'=>'Fiche invalide : '.$key],422);
    foreach(['touristGuides','royalPalace','requestControls','appBanners','memberRoles'] as $key)if(isset($state['SETTINGS'][$key])&&!is_array($state['SETTINGS'][$key]))jsonResponse(['error'=>'Paramètre invalide : '.$key],422);
    if(isset($state['SETTINGS']['royalPalace']))validateRoyalPalace($state['SETTINGS']['royalPalace']);
    foreach($state['PROGRAMME']??[] as $day) {
        if(isset($day['items'])&&!is_array($day['items']))jsonResponse(['error'=>'Liste des activités invalide.'],422);
        foreach($day['items']??[] as $item)if(!is_array($item)||isset($item['photos'])&&!is_array($item['photos']))jsonResponse(['error'=>'Images de programme invalides.'],422);
    }
}
function validateRoyalPalace(array $palace): void {
    $strings=function(array $row,array $keys,int $limit=30000): void {
        foreach($keys as $key)if(array_key_exists($key,$row)&&(!is_string($row[$key])||mb_strlen($row[$key])>$limit))jsonResponse(['error'=>'Palais Royal : texte invalide ou trop long ('.$key.').'],422);
    };
    $media=function(array $row): void {
        if(isset($row['image'])&&!validPublicUrl($row['image'],true))jsonResponse(['error'=>'Palais Royal : image invalide.'],422);
        foreach(['photos'=>30,'videos'=>20] as $key=>$maximum) {
            $values=$row[$key]??[];
            if(!is_array($values)||count($values)>$maximum)jsonResponse(['error'=>'Palais Royal : '.$key.' doit contenir au maximum '.$maximum.' médias.'],422);
            foreach($values as $value)if(!validPublicUrl($value,$key==='photos'))jsonResponse(['error'=>'Palais Royal : lien de média invalide.'],422);
        }
    };
    $strings($palace,['presentation','history','king','royaltyHistory','traditions','architecture','visits','authorities','culture']);
    $strings($palace,['title','address','phone','email','website'],2000);$media($palace);
    if(isset($palace['email'])&&$palace['email']!==''&&!filter_var($palace['email'],FILTER_VALIDATE_EMAIL))jsonResponse(['error'=>'Palais Royal : adresse e-mail invalide.'],422);
    if(!validPublicUrl($palace['website']??''))jsonResponse(['error'=>'Palais Royal : adresse du site invalide.'],422);
    $profile=$palace['kingProfile']??[];
    if(!is_array($profile))jsonResponse(['error'=>'Palais Royal : profil du roi invalide.'],422);
    $strings($profile,['name','reignTitle'],500);$strings($profile,['biography']);$media($profile);
    if(array_key_exists('active',$profile)&&!is_bool($profile['active']))jsonResponse(['error'=>'Palais Royal : statut du profil invalide.'],422);
    $articles=$palace['articles']??[];
    if(!is_array($articles)||count($articles)>100)jsonResponse(['error'=>'Palais Royal : maximum 100 articles.'],422);
    foreach($articles as $article) {
        if(!is_array($article))jsonResponse(['error'=>'Palais Royal : article invalide.'],422);
        $strings($article,['title'],500);$strings($article,['text']);$media($article);
        if(array_key_exists('active',$article)&&!is_bool($article['active']))jsonResponse(['error'=>'Palais Royal : statut de publication invalide.'],422);
    }
}
function validateEvolutionState(array $state,array $previous,array $admin): void {
    $settingsMap=['touristGuides'=>'GUIDES','royalPalace'=>'PALACE','officialWebsite'=>'OFFICIAL_WEBSITE','requestControls'=>'REQUESTS_MANAGE','appBanners'=>'APP_BANNERS','memberRoles'=>'MEMBER_ROLES'];
    foreach($settingsMap as $field=>$permission)if(($state['SETTINGS'][$field]??null)!==($previous['SETTINGS'][$field]??null)&&!adminHas($admin,$permission))jsonResponse(['error'=>'Permission requise : '.$permission],403);
    if(($state['BADGE_SETTINGS']??[])!==($previous['BADGE_SETTINGS']??[])&&!adminHas($admin,'BADGES_MANAGE'))jsonResponse(['error'=>'Permission requise : BADGES_MANAGE'],403);
    foreach(array_unique(array_merge(array_keys($previous),array_keys($state))) as $key){
        $value=$state[$key]??null;
        if($key==='SETTINGS'){$a=$value;$b=$previous[$key]??[];foreach($settingsMap as $f=>$p){unset($a[$f],$b[$f]);}if($a!==$b&&!adminHas($admin,'SETTINGS'))jsonResponse(['error'=>'Modification des paramètres non autorisée.'],403);}
        elseif($key!=='BADGE_SETTINGS'&&$value!==($previous[$key]??null)&&!adminHas($admin,$key))jsonResponse(['error'=>'Modification non autorisée : '.$key],403);
    }
    validateBadgeSettings($state['BADGE_SETTINGS']??[]);
    if(($state['SETTINGS']['royalPalace']??[])!==($previous['SETTINGS']['royalPalace']??[]))validateRoyalPalace((array)($state['SETTINGS']['royalPalace']??[]));
    foreach(['member','press','pass'] as $type){$c=$state['SETTINGS']['requestControls'][$type]??[];if(!is_bool($c['open']??null)||!is_string($c['message']??null)||mb_strlen($c['message'])>1000||trim($c['message'])==='')jsonResponse(['error'=>'Indiquez un état et un message de fermeture pour chaque demande.'],422);}
    if(!validPublicUrl($state['SETTINGS']['officialWebsite']??''))jsonResponse(['error'=>'Adresse du site officiel invalide.'],422);
    $guides=$state['SETTINGS']['touristGuides']??[];if(!is_array($guides)||count($guides)>500)jsonResponse(['error'=>'Liste des guides invalide.'],422);
    foreach($guides as $g){
        if(!is_array($g))jsonResponse(['error'=>'Fiche guide invalide.'],422);
        foreach(['name','firstName','phone','whatsapp','email','languages','zone','presentation','availability'] as $field)if(isset($g[$field])&&!is_string($g[$field]))jsonResponse(['error'=>'Champ guide invalide : '.$field],422);
        if(!trim($g['name']??'')||!trim($g['firstName']??'')||!preg_match('/^[+0-9 ()-]{8,30}$/',$g['phone']??'')||!is_bool($g['active']??null))jsonResponse(['error'=>'Guide : nom, prénoms, téléphone et statut requis.'],422);
        if(!empty($g['whatsapp'])&&!preg_match('/^[+0-9 ()-]{8,30}$/',$g['whatsapp']))jsonResponse(['error'=>'Numéro WhatsApp du guide invalide.'],422);
        if(!empty($g['email'])&&!filter_var($g['email'],FILTER_VALIDATE_EMAIL))jsonResponse(['error'=>'E-mail du guide invalide.'],422);
    }
    foreach((array)($state['SITES']??[]) as $s)validateCoordinates($s);foreach((array)($state['HOTELS']??[]) as $s)validateCoordinates($s);validateCoordinates($state['SETTINGS']['royalPalace']??[]);
    foreach($state['ADVERTISEMENTS']??[] as $advert){
        foreach(['name','stand','category','desc','phone','phone2','whatsapp','email','website','address'] as $field)if(isset($advert[$field])&&(!is_string($advert[$field])||mb_strlen($advert[$field])>20000))jsonResponse(['error'=>'Publicité : champ invalide : '.$field],422);
        if(!is_bool($advert['active']??true)||!is_array($advert['photos']??[])||count($advert['photos']??[])>10)jsonResponse(['error'=>'Publicité : statut invalide ou plus de 10 photos.'],422);
    }
    foreach($state['PARTNERS']??[] as $p)if(!in_array($p['partnerType']??'partner',['official','partner'],true))jsonResponse(['error'=>'Type de partenaire invalide.'],422);
    foreach($state['SETTINGS']['appBanners']??[] as $b)if(!is_array($b)||!is_bool($b['active']??true)||!validPublicUrl($b['link']??''))jsonResponse(['error'=>'Bannière ou lien invalide.'],422);
    foreach($state['MENU_ITEMS']??[] as $m)if(!is_array($m)||!is_bool($m['active']??null)||!is_bool($m['hidden']??null))jsonResponse(['error'=>'Visibilité du module invalide.'],422);
    foreach($state['PROGRAMME']??[] as $d){if(!empty($d['legacy']))continue;if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d['date']??'')||!trim($d['title']??''))jsonResponse(['error'=>'Date et titre de journée requis.'],422);foreach($d['items']??[] as $i)if(!is_bool($i['active']??true)||count($i['photos']??[])>10)jsonResponse(['error'=>'Programme : statut invalide ou plus de 10 images.'],422);}
    // Block executable URLs in every editorial image/media field, including nested collections.
    $visit=function($value,$old=null,$key='',$path='')use(&$visit){
        if($value===$old)return;
        if(is_array($value)){foreach($value as $k=>$v)$visit($v,is_array($old)?($old[$k]??null):null,is_int($k)?$key:(string)$k,$path.'.'.$k);return;}
        if(in_array($key,['img','image','photo','photos','logo','backgroundImage','frontBackground','website','url','embedUrl','videos','link'],true)&&!validPublicUrl($value,in_array($key,['img','image','photo','photos','logo','backgroundImage','frontBackground'],true)))jsonResponse(['error'=>'Lien ou média invalide : '.$key.' ('.$path.')'],422);
    };
    // Validate changed fields, so an unchanged legacy URL cannot block an unrelated input.
    foreach($state as $key=>$value)if($value!==($previous[$key]??null))$visit($value,$previous[$key]??null,$key,$key);
}
function assertRequestsOpen(string $type): void {
    if(!in_array($type,['member','press','pass'],true))return;
    $state=abissaEvolutionState((array)readJsonFile('state',[]));$c=$state['SETTINGS']['requestControls'][$type];
    if(!$c['open'])jsonResponse(['error'=>$c['message'],'code'=>'requests_closed','type'=>$type],403);
}
function journalPage(string $name): array {
    global $dataDir;$file=$dataDir.'/'.$name.'.jsonl';$rows=[];$offset=max(0,(int)($_GET['offset']??0));$limit=100;
    if(is_file($file)){$f=new SplFileObject($file);$f->seek(PHP_INT_MAX);$last=$f->key();for($i=$last-$offset;$i>=0&&count($rows)<$limit;$i--){$f->seek($i);$row=json_decode($f->current(),true);if(is_array($row))$rows[]=$row;}}
    return ['items'=>$rows,'nextOffset'=>count($rows)===$limit?$offset+$limit:null];
}
migrateEvolution();
if($route==='request-controls'&&$method==='GET')jsonResponse(abissaEvolutionState((array)readJsonFile('state',[]))['SETTINGS']['requestControls']);
if($route==='admin/activity-log'){requirePermission('ACTIVITY_LOGS');if($method!=='GET')jsonResponse(['error'=>'Le journal est en lecture seule.'],405);jsonResponse(journalPage('activity_log'));}
