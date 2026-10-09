<?php
// Server-authoritative pass credentials. Included under index.php's transaction lock.
const ACCESS_PERMISSIONS = ['ACCESS_SETTINGS','ACCESS_SCAN','ACCESS_HISTORY'];

function accessDefaults(): array {
    $zones=[];
    foreach(['public'=>'Espace public','organisation'=>'Organisation','stands'=>'Stands','logistique'=>'Logistique','presse'=>'Salle de presse','vip'=>'Zone VIP','backstage'=>'Backstage','protocole'=>'Protocole'] as $id=>$label)$zones[]=['id'=>$id,'label'=>$label,'active'=>true];
    $profiles=[];
    foreach([
        ['rouge','Rouge','#c92332','Accès total',[],true],
        ['bleu','Bleu','#0057b8','Organisation',['organisation','backstage','logistique'],false],
        ['vert','Vert','#168451','Stands / logistique',['stands','logistique'],false],
        ['jaune','Jaune','#f3bd21','Zone limitée',['public'],false],
        ['violet','Violet','#6c3195','Presse',['presse','public'],false],
        ['noir','Noir','#152238','VIP / protocole',['vip','protocole'],false],
        ['orange','Orange','#e66b16','Logistique',['logistique'],false]
    ] as [$id,$label,$color,$level,$allowed,$all])$profiles[]=['id'=>$id,'name'=>$label,'title'=>'LAISSEZ-PASSER '.mb_strtoupper($label),'color'=>$color,'level'=>$level,'allowedZones'=>$allowed,'deniedZones'=>[],'allZones'=>$all,'accessTypes'=>['pedestrian','vehicle'],'dates'=>[],'weekdays'=>[],'eventIds'=>[],'timeFrom'=>'','timeUntil'=>'','active'=>true];
    return ['zones'=>$zones,'profiles'=>$profiles,'checkpoints'=>[],'events'=>[],'duplicateWindowSeconds'=>30,'abnormalScanLimit'=>10,'abnormalScanWindowSeconds'=>60,'timezone'=>'Africa/Abidjan'];
}
function accessConfig(): array {return (array)readJsonFile('pass_access_config',accessDefaults());}
function accessId(string $prefix=''): string {return $prefix.bin2hex(random_bytes(12));}
function accessFind(array $items,string $id): ?array {foreach($items as $item)if(($item['id']??'')===$id)return $item;return null;}
function accessText(array $body,string $key,int $max=160): string {
    $value=$body[$key]??'';if(!is_string($value)||mb_strlen($value)>$max)jsonResponse(['error'=>'Champ invalide : '.$key],422);return trim($value);
}
function accessList(mixed $value,array $known,string $field): array {
    if(!is_array($value)||!array_is_list($value)||count($value)>100)jsonResponse(['error'=>'Liste invalide : '.$field],422);
    foreach($value as $id)if(!is_string($id)||!in_array($id,$known,true))jsonResponse(['error'=>'Valeur inconnue : '.$field],422);
    return array_values(array_unique($value));
}
function accessBool(array $body,string $key,bool $default=false): bool {
    $value=$body[$key]??$default;if(!is_bool($value))jsonResponse(['error'=>'Valeur booléenne attendue : '.$key],422);return $value;
}
function accessDate(string $value,bool $end=false): string {
    if($value==='')return '';
    $format=preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)?'!Y-m-d':'!Y-m-d\TH:i:s\Z';
    $date=DateTimeImmutable::createFromFormat($format,$value,new DateTimeZone('UTC'));
    $errors=DateTimeImmutable::getLastErrors();
    if(!$date||($errors&&($errors['warning_count']||$errors['error_count'])))jsonResponse(['error'=>'Date invalide.'],422);
    if($format==='!Y-m-d')$date=$date->setTime($end?23:0,$end?59:0,$end?59:0);
    return $date->format('Y-m-d\TH:i:s\Z');
}
function accessValidateConfig(array $body): array {
    $out=accessDefaults();$out['zones']=[];$out['profiles']=[];$out['checkpoints']=[];$out['events']=[];
    foreach(['zones','profiles','checkpoints','events'] as $key)if(!is_array($body[$key]??null)||!array_is_list($body[$key])||count($body[$key])>100)jsonResponse(['error'=>'Configuration invalide : '.$key],422);
    foreach(['zones','events'] as $key){$seen=[];foreach($body[$key] as $item){if(!is_array($item))jsonResponse(['error'=>'Configuration invalide.'],422);$id=accessText($item,'id',60);$label=accessText($item,'label',100);if(!preg_match('/^[a-z0-9-]+$/D',$id)||!$label||isset($seen[$id]))jsonResponse(['error'=>'Nom ou identifiant de zone/journée invalide.'],422);$seen[$id]=true;$out[$key][]=['id'=>$id,'label'=>$label,'active'=>accessBool($item,'active',true)];}}
    $zones=array_column($out['zones'],'id');$events=array_column($out['events'],'id');$seen=[];
    foreach($body['profiles'] as $item){
        if(!is_array($item))jsonResponse(['error'=>'Profil invalide.'],422);
        $p=[];foreach(['id'=>60,'name'=>80,'title'=>100,'level'=>120,'color'=>7,'timeFrom'=>5,'timeUntil'=>5] as $key=>$max)$p[$key]=accessText($item,$key,$max);
        if(!preg_match('/^[a-z0-9-]+$/D',$p['id'])||isset($seen[$p['id']])||!$p['name']||!$p['title']||!$p['level']||!preg_match('/^#[0-9a-f]{6}$/iD',$p['color']))jsonResponse(['error'=>'Renseignez le nom, le titre, la couleur et le niveau du profil.'],422);$seen[$p['id']]=true;
        foreach(['allowedZones'=>$zones,'deniedZones'=>$zones,'eventIds'=>$events,'accessTypes'=>['pedestrian','vehicle']] as $key=>$known)$p[$key]=accessList($item[$key]??[],$known,$key);
        foreach(['active'=>true,'allZones'=>false] as $key=>$default)$p[$key]=accessBool($item,$key,$default);
        $dates=$item['dates']??[];if(!is_array($dates)||count($dates)>100)jsonResponse(['error'=>'Dates invalides.'],422);$p['dates']=[];foreach($dates as $date){if(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))jsonResponse(['error'=>'Jour invalide.'],422);accessDate($date);$p['dates'][]=$date;}
        $weekdays=$item['weekdays']??[];if(!is_array($weekdays)||count($weekdays)>7)jsonResponse(['error'=>'Jours de semaine invalides.'],422);foreach($weekdays as $day)if(!is_int($day)||$day<1||$day>7)jsonResponse(['error'=>'Jour de semaine invalide.'],422);$p['weekdays']=array_values(array_unique($weekdays));
        if(($p['timeFrom']==='')!==($p['timeUntil']===''))jsonResponse(['error'=>'Renseignez les deux horaires ou laissez-les vides.'],422);
        if($p['timeFrom']!==''&&(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$p['timeFrom'])||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$p['timeUntil'])||$p['timeFrom']>=$p['timeUntil']))jsonResponse(['error'=>'La fin de la plage horaire doit suivre le début dans la même journée.'],422);
        $out['profiles'][]=$p;
    }
    $agents=array_column((array)readJsonFile('admins',[]),'id');$agents[]='founder';$seen=[];
    foreach($body['checkpoints'] as $item){if(!is_array($item))jsonResponse(['error'=>'Point de contrôle invalide.'],422);$p=[];foreach(['id'=>60,'label'=>100,'zoneId'=>60,'eventId'=>60,'accessType'=>20] as $key=>$max)$p[$key]=accessText($item,$key,$max);
        if(!preg_match('/^[a-z0-9-]+$/D',$p['id'])||isset($seen[$p['id']])||!$p['label']||!in_array($p['zoneId'],$zones,true)||($p['eventId']!==''&&!in_array($p['eventId'],$events,true))||!in_array($p['accessType'],['pedestrian','vehicle'],true))jsonResponse(['error'=>'Point de contrôle, zone, journée ou type d’accès invalide.'],422);$seen[$p['id']]=true;$p['agents']=accessList($item['agents']??[],$agents,'agents');$p['active']=accessBool($item,'active',true);$out['checkpoints'][]=$p;
    }
    $window=$body['duplicateWindowSeconds']??30;if(!is_int($window)||$window<0||$window>600)jsonResponse(['error'=>'Fenêtre de suspicion : 0 à 600 secondes.'],422);$out['duplicateWindowSeconds']=$window;
    foreach(['abnormalScanLimit'=>[10,100],'abnormalScanWindowSeconds'=>[60,600]] as $key=>$range){$v=$body[$key]??$range[0];if(!is_int($v)||$v<0||$v>$range[1])jsonResponse(['error'=>'Limite de scans anormaux invalide.'],422);$out[$key]=$v;}
    return $out;
}
function accessDossier(string $id): ?array {foreach((array)readJsonFile('submissions',[]) as $item)if(($item['id']??'')===$id&&($item['type']??'')==='pass')return $item;return null;}
function accessSecret(): string {
    $secret=readJsonFile('pass_signing_secret',null);
    if($secret===null){if(readJsonFile('access_passes',[]))jsonResponse(['error'=>'Clé de signature absente. Restaurez sa sauvegarde avant de poursuivre.'],503);$secret=['key'=>bin2hex(random_bytes(32))];writeJsonFile('pass_signing_secret',$secret);}
    if(!is_array($secret)||!preg_match('/^[a-f0-9]{64}$/D',$secret['key']??''))jsonResponse(['error'=>'Clé de signature indisponible. Restaurez sa sauvegarde.'],503);
    return hex2bin($secret['key']);
}
function accessToken(array $pass): string {
    return $pass['nonce'].'.'.hash_hmac('sha256',$pass['id'].'|'.$pass['nonce'].'|'.$pass['generation'],accessSecret());
}
function accessPass(string $id,bool $create=false): ?array {
    $registry=(array)readJsonFile('access_passes',[]);if(isset($registry[$id]))return $registry[$id];
    $dossier=accessDossier($id);if(!$create||!$dossier)return null;
    accessSecret();
    $p=['id'=>$id,'number'=>'AB26-'.strtoupper(bin2hex(random_bytes(5))),'profileId'=>'','status'=>'unconfigured','accessType'=>'vehicle','validFrom'=>gmdate('Y-m-d\T00:00:00\Z'),'validUntil'=>accessDate((string)($dossier['data']['validUntil']??''),true),'singleUse'=>false,'generation'=>1,'nonce'=>bin2hex(random_bytes(32)),'retiredTokens'=>[],'createdAt'=>gmdate('c'),'updatedAt'=>gmdate('c'),'scanCount'=>0,'useCount'=>0,'lastAllowed'=>null,'lastUsedAt'=>null,'orientation'=>'landscape'];
    $registry[$id]=$p;writeJsonFile('access_passes',$registry);return $p;
}
function accessSavePass(array $pass): void {$all=(array)readJsonFile('access_passes',[]);$all[$pass['id']]=$pass;writeJsonFile('access_passes',$all);}
function accessUrl(array $pass): string {return 'https://abissamobile.com/appli/access-control.html?id='.rawurlencode($pass['id']).'&token='.rawurlencode(accessToken($pass));}
function accessPublicPass(array $pass,bool $private=false): array {
    $profile=accessFind(accessConfig()['profiles'],$pass['profileId']);
    $out=array_intersect_key($pass,array_flip(['id','number','profileId','status','validFrom','validUntil','singleUse','generation','scanCount','useCount','lastUsedAt','orientation','accessType']));
    if($private){$out['appearance']=$pass['appearance']??passAppearanceDefaults(['profile'=>$profile]);foreach(['createdAt','updatedAt','createdBy','updatedBy'] as $k)$out[$k]=$pass[$k]??null;}
    if($profile&&isset($pass['appearance'])){$profile['color']=$pass['appearance']['color'];$profile['title']=$pass['appearance']['title'];}
    $out['profile']=$profile;$out['zones']=array_values(array_filter(accessConfig()['zones'],fn($z)=>($z['active']??false)));
    $dossier=accessDossier($pass['id']);if($private){foreach(['allowedZones','deniedZones'] as $k)if(isset($pass[$k]))$out[$k]=$pass[$k];}if($private)$out['identity']=array_intersect_key((array)($dossier['data']??[]),array_flip(['name','organization','commission','vehicle','plate']));
    return $out;
}
function accessAuthenticateToken(string $id,string $token): array {
    $pass=accessPass($id);if(!$pass||!preg_match('/^[a-f0-9]{64}\.[a-f0-9]{64}$/D',$token))return ['valid'=>false,'code'=>'invalid','message'=>'QR CODE INVALIDE'];
    if(isset($pass['retiredTokens'][hash('sha256',$token)]))return ['valid'=>false,'revokedPass'=>$pass,'code'=>'reissued','message'=>'ACCÈS REFUSÉ — Ce QR code a été remplacé.'];
    if(!hash_equals(accessToken($pass),$token))return ['valid'=>false,'code'=>'invalid','message'=>'QR CODE INVALIDE'];
    return ['valid'=>true,'pass'=>$pass];
}
function accessCredential(array $item): array {
    if($item['type']!=='pass')return $item;
    $pass=accessPass($item['id'],true);$item['access']=accessPublicPass($pass,true);$item['verifyUrl']=accessUrl($pass);return $item;
}
function accessDecision(array $pass,?array $checkpoint,int $now): array {
    $dossier=accessDossier($pass['id']);$config=accessConfig();$profile=accessFind($config['profiles'],$pass['profileId']);if($profile){if(isset($pass['allowedZones'])){$profile['allowedZones']=$pass['allowedZones'];$profile['allZones']=false;}if(isset($pass['deniedZones']))$profile['deniedZones']=array_unique(array_merge($profile['deniedZones'],$pass['deniedZones']));}
    $allowed=[];$denied=[];foreach($config['zones'] as $zone)if($zone['active']){if($profile&&($profile['allZones']||in_array($zone['id'],$profile['allowedZones'],true))&&!in_array($zone['id'],$profile['deniedZones'],true))$allowed[]=$zone;else $denied[]=$zone;}
    $code='authorized';$message='ACCÈS AUTORISÉ';
    if(!$dossier||($dossier['status']??'')!=='validated'){$code='not_validated';$message='ACCÈS REFUSÉ — Dossier non validé ou retiré.';}
    elseif($pass['status']!=='active'){$code=$pass['status'];$message=['suspect'=>'LAISSEZ-PASSER SUSPECT — COPIE / DUPLICATION DÉTECTÉE','suspended'=>'ACCÈS REFUSÉ — Pass suspendu.','cancelled'=>'ACCÈS REFUSÉ — Pass annulé.','deleted'=>'ACCÈS REFUSÉ — Pass supprimé.','unconfigured'=>'ACCÈS REFUSÉ — Pass non configuré.'][$code]??'ACCÈS REFUSÉ';}
    elseif(!$profile||!$profile['active']){$code='profile_disabled';$message='ACCÈS REFUSÉ — Profil désactivé.';}
    elseif(($pass['validUntil']!==''&&strtotime($pass['validUntil'])<$now)||(!empty($dossier['data']['validUntil'])&&strtotime($dossier['data']['validUntil'].'T23:59:59Z')<$now)){$code='expired';$message='ACCÈS REFUSÉ — Pass expiré.';}
    elseif(strtotime($pass['validFrom'])>$now){$code='not_started';$message='ACCÈS REFUSÉ — Validité non commencée.';}
    elseif($pass['singleUse']&&$pass['useCount']>0){$code='used';$message='ACCÈS REFUSÉ — Pass déjà utilisé.';}
    else {
        $date=(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone($config['timezone']));
        if(($profile['dates']&&!in_array($date->format('Y-m-d'),$profile['dates'],true))||($profile['weekdays']&&!in_array((int)$date->format('N'),$profile['weekdays'],true))||($profile['timeFrom']!==''&&($date->format('H:i')<$profile['timeFrom']||$date->format('H:i')>$profile['timeUntil']))){$code='schedule';$message='ACCÈS REFUSÉ — Hors journée ou horaires autorisés.';}
        elseif(!in_array($pass['accessType'],$profile['accessTypes'],true)){$code='access_type';$message='ACCÈS REFUSÉ — Type d’accès non autorisé.';}
        elseif($checkpoint){
            $zone=accessFind($config['zones'],$checkpoint['zoneId']);$event=$checkpoint['eventId']!==''?accessFind($config['events'],$checkpoint['eventId']):null;
            if(!$zone||!$zone['active']||!in_array($zone['id'],array_column($allowed,'id'),true)){$code='zone';$message='ACCÈS REFUSÉ — Ce laissez-passer ne donne pas accès à cette zone.';}
            elseif($checkpoint['accessType']!==$pass['accessType']){$code='access_type';$message='ACCÈS REFUSÉ — Type d’accès incompatible avec ce point de contrôle.';}
            elseif(($event&&!$event['active'])||($profile['eventIds']&&!in_array($checkpoint['eventId'],$profile['eventIds'],true))){$code='event';$message='ACCÈS REFUSÉ — Journée ou événement non autorisé.';}
        }
    }
    return ['allowed'=>$code==='authorized','code'=>$code,'message'=>$message,'pass'=>accessPublicPass($pass,true),'allowedZones'=>$allowed,'deniedZones'=>$denied,'checkedAt'=>gmdate('c',$now)];
}
