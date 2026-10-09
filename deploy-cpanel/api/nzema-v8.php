<?php
// V8 recordings live below the protected data directory, never in public uploads.
function nzemaV8Seed(string $name): array {
    static $cache=[];
    return $cache[$name]??=json_decode((string)file_get_contents(dirname(__DIR__).'/assets/nzema-v8/'.$name.'.json'),true)??[];
}
function nzemaAudioPublishable(array $r): bool {
    return !empty($r['normalFile']) && ($r['consent_status']??'')==='accepted'
        && ($r['quality_status']??'')==='validated' && ($r['validation_status']??'')==='validated_grand_bassam'
        && count(array_unique(array_column((array)($r['validators']??[]),'username')))>=2;
}
function nzemaPublicAudios(): array {
    $out=[];
    foreach((array)readJsonFile('nzema_audio',[]) as $id=>$r)if(($r['published']??false) && nzemaAudioPublishable($r)){
        $row=array_intersect_key($r,array_flip(['reference_id','speaker_name','speaker_locality','target_variant','updatedAt']));
        $row['url']='/appli/api/nzema/audio-file/'.rawurlencode($id).'/normal';
        $row['slowUrl']=empty($r['slowFile'])?'':'/appli/api/nzema/audio-file/'.rawurlencode($id).'/slow';
        $row['validated']=true;$out[$id]=$row;
    }
    return $out;
}
if($route==='nzema/audio' && $method==='GET')jsonResponse(['items'=>nzemaPublicAudios()]);
if(preg_match('~^(admin/)?nzema/audio-file/([A-Za-z0-9-]+)/(normal|slow)$~',$route,$m) && $method==='GET'){
    $private=!empty($m[1]);if($private)requirePermission('NZEMA_DICT');
    $r=readJsonFile('nzema_audio',[])[$m[2]]??[];
    if(!$private && (!( $r['published']??false)||!nzemaAudioPublishable($r)))jsonResponse(['error'=>'Audio non publié.'],404);
    $file=$r[$m[3].'File']??'';
    if(!preg_match('/^[a-f0-9]{32}\.(mp3|wav|ogg|m4a|webm)$/',$file))jsonResponse(['error'=>'Audio indisponible.'],404);
    $path=$dataDir.'/nzema-audio/'.$file;if(!is_file($path))jsonResponse(['error'=>'Audio indisponible.'],404);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');readfile($path);exit;
}
if(str_starts_with($route,'admin/nzema/audio')){
    $actor=requirePermission('NZEMA_DICT',$method!=='GET');
    $all=(array)readJsonFile('nzema_audio',[]);$missions=array_column(nzemaV8Seed('audio-missions'),null,'reference_id');
    if($route==='admin/nzema/audio' && $method==='GET'){
        $q=mb_strtolower(cleanText($_GET['q']??'',100),'UTF-8');$status=cleanText($_GET['status']??'',40);$rows=[];
        foreach($missions as $id=>$mission){$r=array_merge($mission,$all[$id]??[]);$r['published']=($r['published']??false)===true;
            if($q!==''&&!str_contains(mb_strtolower($id.' '.$r['display_nzema'].' '.$r['display_french'],'UTF-8'),$q))continue;
            if($status==='published'&&!$r['published'])continue;
            if($status==='review'&&($r['validation_status']??'')!=='pending_review')continue;
            if($status==='missing'&&!empty($r['normalFile']))continue;
            if($status==='rejected'&&($r['validation_status']??'')!=='rejected')continue;
            $rows[]=$r;
        }
        $page=max(1,(int)($_GET['page']??1));jsonResponse(['items'=>array_slice($rows,($page-1)*30,30),'total'=>count($rows),'page'=>$page,'published'=>count(nzemaPublicAudios())]);
    }
    if(!preg_match('~^admin/nzema/audio/([A-Za-z0-9-]+)(?:/(upload|submit|validate|publish|unpublish))?$~',$route,$m))jsonResponse(['error'=>'Route audio inconnue.'],404);
    $id=$m[1];if(!isset($missions[$id]))jsonResponse(['error'=>'Référence inconnue.'],404);
    $r=array_merge($missions[$id],$all[$id]??[]);$r['published']=($r['published']??false)===true;$action=$m[2]??'';
    if($method==='GET'&&$action==='')jsonResponse($r);
    $reset=function()use(&$r){$r['published']=false;$r['validators']=[];$r['validation_status']='draft';};
    if($method==='PUT'&&$action===''){
        $body=requestJson();foreach(['speaker_name','speaker_locality','target_variant','notes'] as $key)$r[$key]=cleanText($body[$key]??$r[$key]??'', $key==='notes'?1500:180);
        foreach(['consent_status'=>['pending','accepted','refused'],'quality_status'=>['pending','validated','rejected']] as $key=>$values){$v=$body[$key]??$r[$key]??'pending';if(!in_array($v,$values,true))jsonResponse(['error'=>'Statut invalide.'],422);$r[$key]=$v;}
        $reset();
    }elseif($method==='POST'&&$action==='upload'){
        $f=$_FILES['file']??null;$variant=$_POST['variant']??'normal';
        if(!in_array($variant,['normal','slow'],true)||!$f||$f['error']!==UPLOAD_ERR_OK)jsonResponse(['error'=>'Choisissez un audio normal ou lent.'],422);
        if($f['size']>5*1024*1024)jsonResponse(['error'=>'Audio : 5 Mo maximum.'],413);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/ogg'=>'ogg','application/ogg'=>'ogg','audio/mp4'=>'m4a','video/mp4'=>'m4a','audio/x-m4a'=>'m4a','audio/webm'=>'webm','video/webm'=>'webm'][$mime]??null;
        if(!$ext)jsonResponse(['error'=>'Format audio non accepté.'],415);
        $dir=$dataDir.'/nzema-audio';if(!is_dir($dir))mkdir($dir,0750,true);$name=bin2hex(random_bytes(16)).'.'.$ext;
        if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name))jsonResponse(['error'=>'Stockage audio indisponible.'],500);
        chmod($dir.'/'.$name,0640);$r[$variant.'File']=$name;$reset();
    }elseif($method==='POST'&&$action==='submit'){
        if(empty($r['normalFile'])||empty($r['speaker_name'])||empty($r['speaker_locality'])||$r['consent_status']!=='accepted'||$r['quality_status']!=='validated')jsonResponse(['error'=>'Audio, locuteur, localité, consentement et qualité validée sont nécessaires.'],422);
        $reset();$r['validation_status']='pending_review';
    }elseif($method==='POST'&&$action==='validate'){
        if(($r['validation_status']??'')!=='pending_review')jsonResponse(['error'=>'Soumettez cet audio en validation.'],409);
        $b=requestJson();$decision=$b['decision']??'';
        if($decision==='reject'){$reset();$r['validation_status']='rejected';}
        elseif($decision==='approve'){
            $name=(string)$actor['username'];$validators=(array)($r['validators']??[]);
            if(in_array($name,array_column($validators,'username'),true))jsonResponse(['error'=>'La seconde validation doit venir d’un autre compte habilité.'],409);
            $validators[]=['username'=>$name,'date'=>gmdate('c')];$r['validators']=$validators;
            if(count($validators)>=2)$r['validation_status']='validated_grand_bassam';
        }else jsonResponse(['error'=>'Décision invalide.'],422);
    }elseif($method==='POST'&&$action==='publish'){
        if(!nzemaAudioPublishable($r))jsonResponse(['error'=>'Consentement, qualité et deux validations linguistiques distinctes sont requis.'],422);
        $r['published']=true;
    }elseif($method==='POST'&&$action==='unpublish')$r['published']=false;
    else jsonResponse(['error'=>'Méthode non autorisée.'],405);
    $r['reference_id']=$id;$r['updatedAt']=gmdate('c');$r['updatedBy']=$actor['username'];$all[$id]=$r;writeJsonFile('nzema_audio',$all);jsonResponse($r);
}
