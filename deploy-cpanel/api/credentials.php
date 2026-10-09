<?php
// Included after API helpers; only validated, current dossiers can produce badges.
function credentialRecord(string $id): array {
    if(str_starts_with($id,'leader-')){ $leader=leaderCredential($id); if($leader)return $leader; }
    if(str_starts_with($id,'special-')){ $special=specialCredential($id); if($special)return $special; jsonResponse(['valid'=>false,'error'=>'Ce profil spécial est désactivé.'],403); }
    foreach ((array)readJsonFile('submissions', []) as $item) {
        if (($item['id'] ?? '') !== $id) continue;
        if (!in_array($item['type'] ?? '', ['member','press','pass'], true) || ($item['status'] ?? '') !== 'validated') jsonResponse(['valid'=>false,'error'=>'Ce document n’est pas validé ou a été suspendu.'], 403);
        if (($item['type'] ?? '') === 'pass' && !empty($item['data']['validUntil']) && $item['data']['validUntil'] < gmdate('Y-m-d')) jsonResponse(['valid'=>false,'error'=>'Ce laissez-passer a expiré.'], 403);
        $data = array_intersect_key((array)($item['data'] ?? []), array_flip(['name','fullName','role','commission','organization','photo','joinedYear','vehicle','plate','validUntil']));
        foreach((array)(readJsonFile('state',[])['COMMISSIONS']??[]) as $commission)if(($commission['name']??'')===($data['commission']??''))$data['commissionColor']=$commission['color']??'#d6a024';
        return accessCredential(decorateCredential(['id'=>$item['id'],'type'=>$item['type'],'data'=>$data]));
    }
    jsonResponse(['valid'=>false,'error'=>'Document introuvable.'], 404);
}
if ($route === 'admin/badge-settings' && $method === 'PUT') {
    requirePermission('BADGES_MANAGE', true); $body=requestJson();
    $settings=array_merge((array)(readJsonFile('state',[])['BADGE_SETTINGS']??[]),['templateVersion'=>5]);
    foreach(['commissionSize'=>[6,10],'commissionX'=>[15,85],'commissionY'=>[8,22]] as $key=>$range){$v=$body[$key]??$settings[$key]??['commissionSize'=>7.5,'commissionX'=>50,'commissionY'=>15][$key];if(!is_numeric($v)||$v<$range[0]||$v>$range[1])jsonResponse(['error'=>'Position ou taille de commission hors limites.'],422);$settings[$key]=(float)$v;}
    foreach(['commissionFont'=>['Arial','Georgia','AbissaBadge'],'commissionAlign'=>['left','center','right']] as $key=>$allowed){$v=$body[$key]??$settings[$key]??$allowed[0];if(!in_array($v,$allowed,true))jsonResponse(['error'=>'Police ou alignement invalide.'],422);$settings[$key]=$v;}
    $color=$body['commissionColor']??$settings['commissionColor']??'';if($color!==''&&!preg_match('/^#[0-9a-f]{6}$/i',$color))jsonResponse(['error'=>'Couleur invalide.'],422);$settings['commissionColor']=$color;
    $names=$body['commissionNames']??$settings['commissionNames']??[];if(!is_array($names)||count($names)>100)jsonResponse(['error'=>'Libellés invalides.'],422);foreach($names as $k=>$v)if(!is_string($v)||mb_strlen($v)>160)jsonResponse(['error'=>'Nom de commission invalide.'],422);$settings['commissionNames']=$names;

    foreach(['frontBackground'] as $key) {
        $url=(string)($body[$key]??''); $path=parse_url($url,PHP_URL_PATH) ?: '';
        if($url!=='' && (!preg_match('~/uploads/([a-f0-9]{32}\.(?:png|jpg|webp|gif))$~',$path,$match) || !is_file($uploadDir.'/'.$match[1]))) jsonResponse(['error'=>'Importez une image depuis votre appareil.'],422);
        $settings[$key]=$url===''?'':$path;
    }
    $state=(array)readJsonFile('state',[]);$state['BADGE_SETTINGS']=$settings;writeJsonFile('state',$state);jsonResponse(['ok'=>true]);
}

function credentialVerifyUrl(string $id): string {
    return 'https://abissamobile.com/appli/credential.html?id='.rawurlencode($id);
}

// Keep already printed QR links readable. Their old signatures and control
// states are no longer used; the current validated dossier is the reference.
function legacyCredentialId(string $token): ?string {
    $parts=explode('.',$token,2);
    if(count($parts)!==2||$parts[0]==='')return null;
    if(preg_match('/^[a-f0-9]{64}$/',$parts[0])) {
        foreach((array)(readJsonFile('credential_registry',[])['documents']??[]) as $record) {
            if(is_array($record)&&($record['nonce']??'')===$parts[0]&&is_string($record['id']??null))return $record['id'];
        }
        return null;
    }
    $payload=json_decode(base64_decode(strtr($parts[0],'-_','+/'),true)?:'',true);
    $id=is_array($payload)?($payload['id']??null):null;
    return is_string($id)&&$id!==''&&strlen($id)<=80?$id:null;
}

if ($route === 'admin/credential-token' && $method === 'POST') {
    requireAnyPermission(['BADGES_MANAGE','BADGES_PRINT'],true);
    $body=requestJson();$item=credentialRecord(cleanText($body['id']??'',80));$item['design']=readJsonFile('badge_designs',[])[$item['id']]??null;
    jsonResponse(['verifyUrl'=>$item['verifyUrl']??credentialVerifyUrl($item['id']),'document'=>$item]);
}
if ($route === 'verify-document' && $method === 'GET') {
    $id=cleanText($_GET['id']??'',80);
    if($id==='')$id=legacyCredentialId(cleanText($_GET['token']??'',400))??'';
    if($id==='')jsonResponse(['valid'=>false,'error'=>'Document illisible.'],422);
    $item=credentialRecord($id);$item['design']=readJsonFile('badge_designs',[])[$id]??null;$state=(array)readJsonFile('state',[]);
    if($item['type']==='pass')jsonResponse(['valid'=>false,'error'=>'QR signé et contrôle agent requis pour ce laissez-passer.'],403);
    $settings=array_intersect_key((array)($state['BADGE_SETTINGS']??[]),array_flip(['frontBackground','widthMm','heightMm','templateVersion','commissionSize','commissionX','commissionY','commissionFont','commissionAlign','commissionColor','commissionNames']));
    jsonResponse(['valid'=>true,'document'=>$item,'settings'=>(object)$settings]);
}
