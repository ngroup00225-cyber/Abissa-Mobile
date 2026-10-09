<?php
// CLI only. Run from a scheduler once APNs credentials have been configured in the host environment.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!function_exists('curl_init')||!defined('CURL_HTTP_VERSION_2_0')){fwrite(STDERR,"cURL avec HTTP/2 requis.\n");exit(1);}
$cert=getenv('APNS_CERTIFICATE');$key=getenv('APNS_PRIVATE_KEY');$topic=getenv('APNS_TOPIC')?:'ci.abissa';
if(!$cert||!$key||!is_readable($cert)||!is_readable($key)){fwrite(STDERR,"Configurez APNS_CERTIFICATE et APNS_PRIVATE_KEY hors du dossier public.\n");exit(1);}
$dir=dirname(__DIR__).'/data';$workerLock=fopen($dir.'/.push-worker.lock','c+');if(!$workerLock||!flock($workerLock,LOCK_EX|LOCK_NB))exit(0);$lock=fopen($dir.'/.transaction.lock','c+');if(!$lock||!flock($lock,LOCK_EX))exit(1);
try{
    $read=function($n)use($dir){$p=$dir.'/'.$n.'.json';if(!is_file($p))return [];return json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR);};
    $notifications=$read('community_notifications');$devices=$read('push_devices');$sent=$read('push_deliveries');$users=$read('users');$active=array_column(array_filter($users,fn($u)=>$u['status']==='active'),null,'id');$count=0;$outcomes=[];$invalid=[];
    flock($lock,LOCK_UN); // Network calls must not block ordinary API writes.
    foreach($notifications as $n){if(strtotime($n['date'])<time()-86400)continue;
        foreach($devices as $deviceId=>$d){if(!isset($active[$d['userId']])||($n['userId']!=='*'&&$n['userId']!==$d['userId']))continue;$id=$n['id'].':'.$deviceId;if(isset($sent[$id]))continue;
            $host=$d['environment']==='development'?'https://api.sandbox.push.apple.com':'https://api.push.apple.com';
            $url='https://abissamobile.com/appli/mobile.html?content='.rawurlencode($n['type']).'&id='.rawurlencode($n['target']);
            if($n['type']==='feedback')$url='https://abissamobile.com/appli/mobile.html?page=my-feedback';
            $payload=json_encode(['aps'=>['alert'=>['title'=>$n['title'],'body'=>mb_substr($n['text'],0,200)],'sound'=>'default'],'url'=>$url],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $c=curl_init($host.'/3/device/'.$d['token']);curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_2_0,CURLOPT_SSLCERT=>$cert,CURLOPT_SSLKEY=>$key,CURLOPT_HTTPHEADER=>['content-type: application/json','apns-topic: '.$topic,'apns-push-type: alert','apns-priority: 10','apns-expiration: '.(time()+86400)]]);
            $response=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
            if($status===200||$status===410){$outcomes[$id]=['date'=>gmdate('c'),'status'=>$status,'userId'=>$d['userId']];if($status===410)$invalid[$deviceId]=$d['userId'];}
            elseif($status>=400&&$status<500&&$status!==429){$outcomes[$id]=['date'=>gmdate('c'),'status'=>$status,'userId'=>$d['userId']];fwrite(STDERR,"APNs refuse une livraison HTTP {$status}. Vérifiez certificat/topic/environnement.\n");}
            $count++;if($count>=50)break 2;
        }
    }
    if(!flock($lock,LOCK_EX))throw new RuntimeException('Stockage occupé');
    $active=array_column($read('users'),null,'id');$sent=$read('push_deliveries');$devices=$read('push_devices');foreach($outcomes as $id=>$entry)if(isset($active[$entry['userId']]))$sent[$id]=$entry;foreach($invalid as $id=>$owner)if(($devices[$id]['userId']??'')===$owner)unset($devices[$id]);
    foreach(['push_deliveries'=>$sent,'push_devices'=>$devices] as $name=>$value){$p=$dir.'/'.$name.'.json';$tmp=$p.'.'.bin2hex(random_bytes(8)).'.tmp';file_put_contents($tmp,json_encode($value,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);chmod($tmp,0640);if(!rename($tmp,$p))throw new RuntimeException('Écriture impossible');}
    echo "Livraisons APNs traitées : {$count}\n";
}finally{flock($lock,LOCK_UN);fclose($lock);flock($workerLock,LOCK_UN);fclose($workerLock);}
