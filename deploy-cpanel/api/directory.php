<?php
// The directory is derived from existing records and stays behind its own permission.
function directoryPhone(string $value): string {
    $digits=preg_replace('/\D/','',$value);
    if(str_starts_with($digits,'00'))$digits=substr($digits,2);
    return strlen($digits)===10?'225'.$digits:$digits;
}
function directoryContacts(): array {
    $rows=[];$aliases=[];$meta=(array)readJsonFile('directory_meta',[]);
    $add=function(array $data,string $source,string $sourceId,string $createdAt,string $accountId='') use (&$rows,&$aliases,$meta) {
        $name=trim((string)($data['name']??$data['fullName']??''));$phone=trim((string)($data['phone']??''));
        if($name===''&&$phone==='')return;
        $match=directoryPhone($phone).'|'.abissaCommitteeKey($name);
        $canMerge=$phone!==''&&$name!=='';
        $id=$accountId!==''?'user-'.$accountId:($canMerge&&isset($aliases[$match])?$aliases[$match]:'contact-'.substr(hash('sha256',$canMerge?$match:$source.':'.$sourceId),0,24));
        if($canMerge)$aliases[$match]=$id;
        if(!isset($rows[$id]))$rows[$id]=['id'=>$id,'name'=>$name,'phone'=>$phone,'email'=>(string)($data['email']??''),'sources'=>[],'years'=>[],'commissions'=>[],'createdAt'=>$createdAt,'sourceCount'=>0];
        $r=&$rows[$id];$r['sourceCount']++;if(!in_array($source,$r['sources'],true))$r['sources'][]=$source;
        $year=memberYear($data);if($year!==''&&!in_array($year,$r['years'],true))$r['years'][]=$year;
        $commission=(string)($data['commission']??'');if($commission!==''&&!in_array($commission,$r['commissions'],true))$r['commissions'][]=$commission;
        if($r['phone']===''&&$phone!=='')$r['phone']=$phone;
        if($r['email']===''&&!empty($data['email']))$r['email']=$data['email'];
        foreach(['firstName','lastName','smsConsent','note'] as $key)$r[$key]=(string)($meta[$id][$key]??($key==='smsConsent'?'unknown':''));
    };
    foreach((array)readJsonFile('users',[]) as $u)$add($u,'Compte application',(string)$u['id'],(string)($u['createdAt']??''),(string)$u['id']);
    $known=array_fill_keys(array_keys($rows),true);
    foreach((array)readJsonFile('submissions',[]) as $s){$id=(string)($s['userId']??'');if(!isset($known['user-'.$id]))$id='';$add((array)($s['data']??[]),['member'=>'Membre','press'=>'Accréditation','pass'=>'Laissez-passer','reservation'=>'Réservation','order'=>'Commande','contact'=>'Contact'][$s['type']??'']??'Dossier',(string)$s['id'],(string)($s['createdAt']??''),$id);}
    $rows=array_values($rows);usort($rows,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));return $rows;
}
if($route==='admin/directory'){
    requirePermission('directory',$method!=='GET');
    if($method==='GET')jsonResponse(directoryContacts());
    if($method==='PATCH'){
        $body=requestJson();$id=(string)($body['id']??'');$contacts=directoryContacts();
        if(!array_filter($contacts,fn($c)=>$c['id']===$id))jsonResponse(['error'=>'Contact introuvable.'],404);
        $consent=(string)($body['smsConsent']??'unknown');if(!in_array($consent,['unknown','allowed','denied'],true))jsonResponse(['error'=>'Statut SMS invalide.'],422);
        $meta=(array)readJsonFile('directory_meta',[]);$meta[$id]=['firstName'=>cleanText($body['firstName']??'',100),'lastName'=>cleanText($body['lastName']??'',100),'note'=>cleanText($body['note']??'',500),'smsConsent'=>$consent,'updatedAt'=>gmdate('c')];
        writeJsonFile('directory_meta',$meta);jsonResponse(['ok'=>true]);
    }
    jsonResponse(['error'=>'Méthode non autorisée.'],405);
}
