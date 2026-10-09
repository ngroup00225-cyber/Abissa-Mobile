<?php
// Mutations require the admin cookie, explicit permissions and CSRF. No offline grant.
function accessAdminCheckpoints(array $admin): array {
    return array_values(array_filter(accessConfig()['checkpoints'],fn($p)=>$p['active']&&(adminHas($admin,'ACCESS_SETTINGS')||in_array((string)($admin['id']??'founder'),$p['agents'],true))));
}
if($route==='admin/access-config'){
    $admin=requirePermission('ACCESS_SETTINGS',$method!=='GET');
    if($method==='GET'){ $config=accessConfig();$config['agents']=array_map(fn($a)=>['id'=>$a['id'],'username'=>$a['username']],(array)readJsonFile('admins',[]));jsonResponse($config); }
    if($method==='PUT'){
        $config=accessValidateConfig(requestJson());
        activityJournal('Paramétrage des accès modifié',['zones'=>count($config['zones']),'profiles'=>count($config['profiles'])]);
        writeJsonFile('pass_access_config',$config);jsonResponse($config);
    }
}
if($route==='admin/access-checkpoints'&&$method==='GET'){ $admin=requirePermission('ACCESS_SCAN');jsonResponse(accessAdminCheckpoints($admin)); }
if($route==='admin/access-zones'&&$method==='GET'){requireAnyPermission(['PASSES_MANAGE','ACCESS_SETTINGS']);jsonResponse(array_values(array_filter(accessConfig()['zones'],fn($z)=>$z['active'])));}
if($route==='admin/access-profiles'&&$method==='GET'){requireAnyPermission(['PASSES_MANAGE','BADGES_MANAGE','BADGES_PRINT']);jsonResponse(array_values(array_filter(accessConfig()['profiles'],fn($p)=>$p['active'])));}
if($route==='admin/access-passes'){
    $admin=requirePermission('PASSES_MANAGE',$method!=='GET');
    if($method==='GET'){
        $items=[];foreach((array)readJsonFile('submissions',[]) as $d)if(($d['type']??'')==='pass'){
            $p=accessPass($d['id']);$items[]=['id'=>$d['id'],'dossierStatus'=>$d['status'],'identity'=>array_intersect_key((array)$d['data'],array_flip(['name','organization','vehicle','plate','validUntil'])),'access'=>$p?accessPublicPass($p,true):null];
        }jsonResponse($items);
    }
    if($method==='POST'){
        $body=requestJson();$data=[];foreach(['name'=>160,'organization'=>160,'vehicle'=>120,'plate'=>40,'validUntil'=>10] as $key=>$max)$data[$key]=accessText($body,$key,$max);
        if(!$data['name'])jsonResponse(['error'=>'Nom requis.'],422);accessDate($data['validUntil'],true);
        // Validate the entire new pass before writing any dossier. Legacy POST remains supported.
        $configured=null;if(!empty($body['profileId']))$configured=passConfigure(['status'=>'unconfigured'], $body,$admin);
        $id=accessId();$items=(array)readJsonFile('submissions',[]);$items[]=['id'=>$id,'type'=>'pass','status'=>'validated','data'=>$data,'createdAt'=>gmdate('c')];
        activityJournal('Laissez-passer créé',['id'=>$id]);writeJsonFile('submissions',$items);
        $p=accessPass($id,true);$p['createdBy']=$admin['id']??'founder';if($configured)$p=array_replace($p,$configured);accessSavePass($p);
        jsonResponse(['id'=>$id,'access'=>accessPublicPass($p,true)],201);
    }
    if($method==='PUT'){
        $body=requestJson();$id=accessText($body,'id',80);$d=accessDossier($id);if(!$d)jsonResponse(['error'=>'Dossier introuvable.'],404);
        $p=accessPass($id,true);if(in_array($p['status'],['suspect','cancelled','deleted'],true))jsonResponse(['error'=>'Réémettez le QR avant de modifier ce pass.'],409);
        $p=passConfigure($p,$body,$admin);$until=$p['validUntil'];$profileId=$p['profileId'];
        $data=$d['data'];foreach(['name'=>160,'organization'=>160,'vehicle'=>120,'plate'=>40] as $key=>$max)if(array_key_exists($key,$body))$data[$key]=accessText($body,$key,$max);if(empty($data['name']))jsonResponse(['error'=>'Nom requis.'],422);$data['validUntil']=substr($until,0,10);
        activityJournal('Laissez-passer modifié',['id'=>$id,'profileId'=>$profileId]);accessSavePass($p);$items=(array)readJsonFile('submissions',[]);foreach($items as &$item)if($item['id']===$id)$item['data']=$data;unset($item);writeJsonFile('submissions',$items);jsonResponse(accessPublicPass($p,true));
    }
    if($method==='PATCH'){
        $body=requestJson();$id=accessText($body,'id',80);$action=accessText($body,'action',30);$p=accessPass($id);if(!$p)jsonResponse(['error'=>'Pass non configuré.'],404);
        if(!in_array($action,['suspend','cancel','reactivate','reissue','delete'],true))jsonResponse(['error'=>'Action invalide.'],422);
        if($action==='reactivate'&&$p['status']!=='suspended')jsonResponse(['error'=>'Seul un pass suspendu peut être réactivé. Pour une annulation ou suspicion, réémettez un nouveau QR.'],409);
        if($action==='reissue'){$p['number']='AB26-'.strtoupper(bin2hex(random_bytes(5)));$retired=$p['retiredTokens'];$retired[hash('sha256',accessToken($p))]=gmdate('c');$p['retiredTokens']=array_slice($retired,-50,null,true);$p['nonce']=bin2hex(random_bytes(32));$p['generation']++;$p['status']=$p['profileId']?'active':'unconfigured';$p['useCount']=0;$p['lastAllowed']=null;$p['lastUsedAt']=null;$p['recentAdmissions']=[];}
        else $p['status']=['suspend'=>'suspended','cancel'=>'cancelled','reactivate'=>'active','delete'=>'deleted'][$action];
        $p['updatedAt']=gmdate('c');activityJournal('Laissez-passer : '.$action,['id'=>$id,'generation'=>$p['generation']]);accessSavePass($p);jsonResponse(accessPublicPass($p,true));
    }
}
if($route==='access/inspect'&&$method==='GET'){
    communityLimit('pass-inspect',$_SERVER['REMOTE_ADDR']??'unknown',600);
    $auth=accessAuthenticateToken(cleanText($_GET['id']??'',80),cleanText($_GET['token']??'',150));
    if(!$auth['valid']){unset($auth['revokedPass']);jsonResponse($auth);}
    $decision=accessDecision($auth['pass'],null,time());$decision['pass']=accessPublicPass($auth['pass']);unset($decision['pass']['profile']['allowedZones'],$decision['pass']['profile']['deniedZones'],$decision['pass']['zones'],$decision['allowedZones'],$decision['deniedZones']);
    // A public view is never an admission decision and never consumes a pass.
    $decision['credentialActive']=$decision['allowed'];$decision['allowed']=false;$decision['message']='Contrôle agent requis pour autoriser l’accès.';$decision['valid']=true;jsonResponse($decision);
}
if($route==='admin/access-scan'&&$method==='POST'){
    $admin=requirePermission('ACCESS_SCAN',true);$body=requestJson();$requestId=accessText($body,'requestId',80);if(!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$requestId))jsonResponse(['error'=>'Identifiant de contrôle requis.'],422);
    $checkpoint=accessFind(accessAdminCheckpoints($admin),accessText($body,'checkpointId',60));if(!$checkpoint)jsonResponse(['error'=>'Point de contrôle non attribué à cet agent.'],403);
    $raw=accessText($body,'code',600);$id='';$token='';
    $parts=parse_url($raw);if(is_array($parts)&&($parts['scheme']??'')==='https'&&($parts['host']??'')==='abissamobile.com'&&($parts['path']??'')==='/appli/access-control.html'){parse_str($parts['query']??'',$query);$id=is_string($query['id']??null)?$query['id']:'';$token=is_string($query['token']??null)?$query['token']:'';}
    if(preg_match('/^AB26-[A-F0-9]{10}$/D',strtoupper($raw))){communityLimit('pass-manual',(string)($admin['id']??'founder'),120);foreach((array)readJsonFile('access_passes',[]) as $candidate)if($candidate['number']===strtoupper($raw)){$id=$candidate['id'];$token=accessToken($candidate);break;}}
    if(strlen($id)>80||strlen($token)>150){$id='';$token='';}
    $fingerprint=hash('sha256',$raw.'|'.$checkpoint['id']);$receiptKey=hash('sha256',($admin['id']??'founder').'|'.$requestId);$receipts=(array)readJsonFile('access_scan_receipts',[]);
    if(isset($receipts[$receiptKey])){
        if(!hash_equals($receipts[$receiptKey]['fingerprint'],$fingerprint))jsonResponse(['error'=>'Identifiant de contrôle déjà utilisé pour un autre scan.'],409);
        $result=$receipts[$receiptKey]['result'];
        if($result['allowed']){
            $fresh=accessAuthenticateToken($id,$token);
            if(!$fresh['valid'])jsonResponse(['allowed'=>false,'code'=>$fresh['code'],'message'=>$fresh['message'],'pass'=>null,'allowedZones'=>[],'deniedZones'=>[],'checkedAt'=>gmdate('c')]);
            $current=$fresh['pass'];
            // An identical retry is the same admission, not a second use.
            if($current['singleUse'])$current['useCount']=0;
            $currentDecision=accessDecision($current,$checkpoint,time());
            if(!$currentDecision['allowed'])jsonResponse($currentDecision);
            if($current['generation']!==$result['pass']['generation'])jsonResponse(['allowed'=>false,'code'=>'reissued','message'=>'ACCÈS REFUSÉ — QR remplacé.']);
        }
        jsonResponse($result);
    }
    communityLimit('pass-scan',(string)($admin['id']??'founder'),2000);
    $now=time();$auth=accessAuthenticateToken($id,$token);$p=$auth['pass']??$auth['revokedPass']??null;$decision=($auth['valid']&&$p)?accessDecision($p,$checkpoint,$now):['allowed'=>false,'code'=>$auth['code'],'message'=>$auth['message'],'pass'=>$p?accessPublicPass($p,true):null,'allowedZones'=>[],'deniedZones'=>[],'checkedAt'=>gmdate('c')];
    if($auth['valid']&&$p&&$decision['allowed']&&$p['lastAllowed']&&accessConfig()['duplicateWindowSeconds']>0&&$now-(int)$p['lastAllowed']['timestamp']<=accessConfig()['duplicateWindowSeconds']&&$p['lastAllowed']['checkpointId']!==$checkpoint['id']){
        $p['status']='suspect';$decision=accessDecision($p,$checkpoint,$now);activityJournal('Copie de pass suspecte',['id'=>$id,'checkpointId'=>$checkpoint['id']]);
    }
    $scanConfig=accessConfig();$burstWindow=$scanConfig['abnormalScanWindowSeconds']??60;$burstLimit=$scanConfig['abnormalScanLimit']??10;
    if($p){$p['recentAdmissions']=array_values(array_filter($p['recentAdmissions']??[],fn($t)=>$t>$now-$burstWindow));
        if($auth['valid']&&$decision['allowed']&&$burstWindow>0&&$burstLimit>0&&count($p['recentAdmissions'])>=$burstLimit){$p['status']='suspect';$decision=accessDecision($p,$checkpoint,$now);activityJournal('Scans anormaux du laissez-passer',['id'=>$id]);}}
    $entry=['agent'=>$admin['username'],'agentId'=>$admin['id']??'founder','checkpointId'=>$checkpoint['id'],'checkpoint'=>$checkpoint['label'],'zoneId'=>$checkpoint['zoneId'],'passId'=>$p?$p['id']:null,'number'=>$p?$p['number']:null,'generation'=>$p?$p['generation']:null,'allowed'=>$decision['allowed'],'code'=>$decision['code'],'requestId'=>$requestId,'credentialDigest'=>hash('sha256',$raw)];
    // A logging failure must deny access, never grant an unrecorded admission.
    appendJournal('access_scans',$entry);
    if($p){$p['scanCount']++;if($decision['allowed']){$p['recentAdmissions'][]=$now;$p['useCount']++;$p['lastUsedAt']=gmdate('c');$p['lastAllowed']=['timestamp'=>$now,'checkpointId'=>$checkpoint['id'],'zoneId'=>$checkpoint['zoneId']];}$p['updatedAt']=gmdate('c');accessSavePass($p);$decision['pass']=accessPublicPass($p,true);}
    $decision['checkpoint']=['id'=>$checkpoint['id'],'label'=>$checkpoint['label'],'zoneId'=>$checkpoint['zoneId']];$decision['requestId']=$requestId;
    $receipts=array_filter($receipts,fn($r)=>($r['timestamp']??0)>$now-86400);$receipts[$receiptKey]=['fingerprint'=>$fingerprint,'timestamp'=>$now,'result'=>$decision];writeJsonFile('access_scan_receipts',$receipts);jsonResponse($decision);
}
if($route==='admin/access-history'&&$method==='GET'){
    requirePermission('ACCESS_HISTORY');$items=[];$path=$dataDir.'/access_scans.jsonl';$q=cleanText($_GET['q']??'',160);
    if(is_file($path)){ $handle=fopen($path,'rb');if(!$handle)jsonResponse(['error'=>'Historique indisponible.'],503);while(($line=fgets($handle))!==false){$entry=json_decode($line,true);if(!is_array($entry))continue;if($q!==''&&!str_contains(mb_strtolower(json_encode($entry,JSON_UNESCAPED_UNICODE)),mb_strtolower($q)))continue;unset($entry['credentialDigest']);$items[]=$entry;if(count($items)>500)array_shift($items);}fclose($handle); }
    jsonResponse(array_reverse($items));
}
