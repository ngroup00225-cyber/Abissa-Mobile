<?php
// Additive migration: never replace existing QR secrets or identities.
function passAppearanceDefaults(array $p=[]): array {
    return ['title'=>$p['profile']['title']??'LAISSEZ-PASSER','color'=>$p['profile']['color']??'#008000','heading'=>'ACCÈS AUTORISÉ','topImage'=>null,'bottomImage'=>'','bottomText'=>'ABISSA 2026 · GRAND-BASSAM','fields'=>[
        ['icon'=>'people','label'=>'','value'=>'Ce laissez-passer permet l’accès aux zones autorisées pendant l’événement.'],
        ['icon'=>'document','label'=>'','value'=>'Présenter ce document à tout contrôle.'],
        ['icon'=>'qr','label'=>'','value'=>'Le QR code doit rester visible.'],
        ['icon'=>'shield','label'=>'','value'=>'Document à usage strictement professionnel.']
    ]];
}
function passAppearanceValidate(mixed $body): array {
    global $uploadDir;
    if(!is_array($body))jsonResponse(['error'=>'Présentation invalide.'],422);
    $a=[];foreach(['title'=>100,'color'=>7,'heading'=>160,'bottomText'=>240] as $k=>$max)$a[$k]=accessText($body,$k,$max);
    if(!$a['title']||!preg_match('/^#[0-9a-f]{6}$/iD',$a['color']))jsonResponse(['error'=>'Titre et couleur HEX requis.'],422);
    foreach(['topImage','bottomImage'] as $k){
        if($k==='topImage'&&($body[$k]??null)===null){$a[$k]=null;continue;}
        $url=accessText($body,$k,2000);$path=parse_url($url,PHP_URL_PATH)?:'';
        if($url!==''&&(!preg_match('~/uploads/([a-f0-9]{32}\.(?:png|jpg|webp|gif))$~D',$path,$m)||!is_file($uploadDir.'/'.$m[1])))jsonResponse(['error'=>'Importez une image depuis votre appareil.'],422);
        $a[$k]=$url===''?'':$path;
    }
    $fields=$body['fields']??[];
    if(!is_array($fields)||!array_is_list($fields)||count($fields)>8)jsonResponse(['error'=>'Huit lignes au maximum.'],422);
    $a['fields']=[];foreach($fields as $f){if(!is_array($f))jsonResponse(['error'=>'Ligne invalide.'],422);$icon=accessText($f,'icon',20);if(!in_array($icon,['people','document','qr','shield','pin','star','calendar'],true))jsonResponse(['error'=>'Icône invalide.'],422);$a['fields'][]=['icon'=>$icon,'label'=>accessText($f,'label',80),'value'=>accessText($f,'value',300)];}
    return $a;
}
function passConfigure(array $p,array $b,array $admin): array {
    $profileId=accessText($b,'profileId',60);$profile=accessFind(accessConfig()['profiles'],$profileId);
    if(!$profile||!$profile['active'])jsonResponse(['error'=>'Choisissez une catégorie active.'],422);
    $from=accessDate(accessText($b,'validFrom',30));$until=accessDate(accessText($b,'validUntil',30),true);
    if(!$from||($until!==''&&strtotime($from)>strtotime($until)))jsonResponse(['error'=>'Période de validité invalide.'],422);
    $type=accessText($b,'accessType',20)?:'pedestrian';$orientation=accessText($b,'orientation',20)?:'landscape';
    if(!in_array($type,['pedestrian','vehicle'],true)||!in_array($orientation,['portrait','landscape'],true))jsonResponse(['error'=>'Format ou type d’accès invalide.'],422);
    foreach(['allowedZones','deniedZones'] as $k){if(array_key_exists($k,$b)&&$b[$k]!==null)$p[$k]=accessList($b[$k],array_column(accessConfig()['zones'],'id'),$k);else unset($p[$k]);}
    if(array_key_exists('appearance',$b))$p['appearance']=passAppearanceValidate($b['appearance']);
    $p=array_replace($p,['profileId'=>$profileId,'validFrom'=>$from,'validUntil'=>$until,'accessType'=>$type,'orientation'=>$orientation,'singleUse'=>accessBool($b,'singleUse'),'updatedAt'=>gmdate('c'),'updatedBy'=>$admin['id']??'founder']);
    if($p['status']==='unconfigured')$p['status']='active';
    return $p;
}
function passCustomizationMigrate(): void {
    global $dataDir;$m=(array)readJsonFile('migrations',[]);$key='2026-10-05-pass-print-v1';if(isset($m[$key]))return;
    $dir=$dataDir.'/backups/before-'.$key;if(!is_dir($dir)&&!mkdir($dir,0750,true))jsonResponse(['error'=>'Sauvegarde migration indisponible.'],503);
    foreach(['access_passes','state','submissions'] as $n){$f=$dataDir.'/'.$n.'.json';if(is_file($f)&&!is_file($dir.'/'.$n.'.json')&&!copy($f,$dir.'/'.$n.'.json'))jsonResponse(['error'=>'Sauvegarde impossible.'],503);}
    $passes=(array)readJsonFile('access_passes',[]);foreach($passes as &$p){if(!isset($p['appearance'])){$profile=accessFind(accessConfig()['profiles'],$p['profileId']);$p['appearance']=passAppearanceDefaults(['profile'=>$profile]);}$p['createdBy']??='legacy';}unset($p);
    if($passes)writeJsonFile('access_passes',$passes);
    $s=(array)readJsonFile('state',[]);$s['BADGE_SETTINGS']=array_replace(['widthMm'=>95,'heightMm'=>135,'orientation'=>'portrait','marginMm'=>6,'layout'=>'a4'],(array)($s['BADGE_SETTINGS']??[]));writeJsonFile('state',$s);
    $m[$key]=['appliedAt'=>gmdate('c'),'backup'=>'backups/before-'.$key];writeJsonFile('migrations',$m);
}

// User-requested reset, applied once. Future passes are never removed on reload.
function printRestorationMigrate(): void {
    global $dataDir;
    $m=(array)readJsonFile('migrations',[]);$key='2026-10-05-print-restoration-v1';if(isset($m[$key]))return;
    $dir=$dataDir.'/backups/before-'.$key;
    if(!is_dir($dir)&&!mkdir($dir,0750,true))jsonResponse(['error'=>'Sauvegarde du rétablissement indisponible.'],503);
    foreach(['submissions','access_passes','badge_designs','state'] as $n){
        $f=$dataDir.'/'.$n.'.json';
        if(is_file($f)&&!is_file($dir.'/'.$n.'.json')&&!copy($f,$dir.'/'.$n.'.json'))jsonResponse(['error'=>'Sauvegarde impossible.'],503);
    }
    $submissions=(array)readJsonFile('submissions',[]);
    $remaining=array_values(array_filter($submissions,fn($item)=>($item['type']??'')!=='pass'));
    $passes=(array)readJsonFile('access_passes',[]);
    foreach($passes as &$p){$p['status']='deleted';$p['updatedAt']=gmdate('c');$p['updatedBy']='print-restoration';}unset($p);
    // Revoke first, then remove the old dossiers. Keep scan records and QR secrets.
    if($passes)writeJsonFile('access_passes',$passes);
    writeJsonFile('submissions',$remaining);
    // Restore the layout while retaining names, roles and uploaded portraits.
    $designs=[];
    foreach((array)readJsonFile('badge_designs',[]) as $id=>$design){
        $content=array_filter(array_intersect_key((array)($design['overrides']??[]),array_flip(['name','firstName','role','commission','number','photo','logo'])),fn($v)=>is_string($v)&&trim($v)!=='');
        if($content)$designs[$id]=['standard'=>true,'overrides'=>$content];
    }
    writeJsonFile('badge_designs',(object)$designs);
    $s=(array)readJsonFile('state',[]);
    $s['BADGE_SETTINGS']=array_replace((array)($s['BADGE_SETTINGS']??[]),['widthMm'=>95,'heightMm'=>135,'orientation'=>'portrait','marginMm'=>6,'layout'=>'a4']);
    writeJsonFile('state',$s);
    $m[$key]=['appliedAt'=>gmdate('c'),'removedPasses'=>count($submissions)-count($remaining),'revokedPasses'=>count($passes),'backup'=>'backups/before-'.$key];
    writeJsonFile('migrations',$m);
}
