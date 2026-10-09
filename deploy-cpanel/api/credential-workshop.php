<?php
function leaderCredential(string $id): ?array {
    $committee=abissaCommittee((array)readJsonFile('state',[]));
    foreach($committee['leaders'] as $leader)if('leader-'.$leader['id']===$id)return ['id'=>$id,'type'=>'member','status'=>'validated','source'=>'leader','data'=>['name'=>$leader['name'],'role'=>$leader['role'],'commission'=>'BUREAU EXÉCUTIF','photo'=>$leader['img'],'commissionId'=>$leader['id'],'poleId'=>'bureau','commissionColor'=>$leader['color'],'commissionIcon'=>$leader['icon']]];
    return null;
}
function decorateCredential(array $item): array {
    $committee=abissaCommittee((array)readJsonFile('state',[]));$d=&$item['data'];
    $d['joinedYear']=memberYear($d);
    foreach($committee['commissions'] as $c)if(abissaCommissionMatches($c,(string)($d['commission']??''))){$d['commissionColor']=$c['color'];$d['commissionIcon']=$c['icon'];$d['commissionId']=$c['id'];$d['poleId']=$c['poleId'];break;}
    return $item;
}
function credentialFingerprint(array $item): string {
    $fields=array_intersect_key($item['data'],array_flip(['name','fullName','role','commission','organization','photo','vehicle','plate','validUntil','commissionColor','commissionIcon']));$fields['design']=readJsonFile('badge_designs',[])[$item['id']]??null;if($item['type']==='pass'){$p=accessPass($item['id']);$fields['access']=$p?array_intersect_key($p,array_flip(['profileId','generation','orientation','validFrom','validUntil','allowedZones','deniedZones','accessType','singleUse'])):null;$fields['profile']=$p?accessFind(accessConfig()['profiles'],$p['profileId']):null;}ksort($fields);
    return hash('sha256',json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
function credentialPrintStatus(array $item,array $records): array {
    $record=$records[$item['id']]??null;$printed=$record&&!empty($record['printed']);
    return ['state'=>$printed?($record['fingerprint']===credentialFingerprint($item)?'printed':'changed'):'unprinted','printedAt'=>$printed?$record['printedAt']:null,'count'=>(int)($record['count']??0)];
}
function workshopCredentials(): array {
    $items=[];$committee=abissaCommittee((array)readJsonFile('state',[]));
    foreach($committee['leaders'] as $leader)$items[]=leaderCredential('leader-'.$leader['id']);
    foreach((array)readJsonFile('submissions',[]) as $item)if(in_array($item['type']??'',['member','press','pass'],true)&&($item['status']??'')==='validated')$items[]=decorateCredential(withMemberRegistration($item));
    foreach((array)readJsonFile('special_profiles',[]) as $profile) {
        // The automatic shared guest badge added in 2.0.4 is no longer offered.
        // Keep its stored profile intact so an existing printed link still opens.
        if(($profile['id']??'')==='special-guest-template')continue;
        if($special=specialCredential((string)($profile['id']??'')))$items[]=$special;
    }
    $records=(array)readJsonFile('credential_prints',[]);
    foreach($items as &$item)$item['printStatus']=credentialPrintStatus($item,$records);
    unset($item);return $items;
}
require_once __DIR__.'/credentials.php';
if($route==='admin/credentials'&&$method==='GET'){requireAnyPermission(['BADGES_MANAGE','BADGES_PRINT']);jsonResponse(workshopCredentials());}
if($route==='admin/credential-prints'&&$method==='PATCH'){
    $admin=requirePermission('BADGES_PRINT',true);$body=requestJson();$ids=array_values(array_unique((array)($body['ids']??[])));
    if(!$ids||count($ids)>200||!is_bool($body['printed']??null))jsonResponse(['error'=>'Sélection ou statut invalide.'],422);
    $items=[];foreach($ids as $id){$items[]=credentialRecord((string)$id);$q=$body['quantities'][$id]??1;if(!is_int($q)||$q<1||$q>100)jsonResponse(['error'=>'Quantité invalide.'],422);}
    $records=(array)readJsonFile('credential_prints',[]);
    foreach($items as $item){$old=$records[$item['id']]??[];$records[$item['id']]=['printed'=>$body['printed'],'printedAt'=>$body['printed']?gmdate('c'):null,'by'=>$admin['username'],'count'=>(int)($old['count']??0)+($body['printed']?($body['quantities'][$item['id']]??1):0),'fingerprint'=>credentialFingerprint($item)];}
    writeJsonFile('credential_prints',$records);jsonResponse(['ok'=>true]);
}
