<?php
if($route==='users/learning-progress'){
    $user=authenticatedUser();$all=(array)readJsonFile('learning_progress',[]);$id=(string)$user['id'];
    if($method==='GET')jsonResponse($all[$id]??['favorites'=>[],'learned'=>[],'mistakes'=>[],'history'=>[]]);
    if($method==='PUT'){
        $body=requestJson();$known=array_fill_keys(array_column(abissaLearningEntries((array)(readJsonFile('state',[])['NZEMA_DICT']??[])),'id'),true);$result=[];
        foreach(['favorites','learned','mistakes'] as $key)$result[$key]=array_values(array_unique(array_filter(array_slice((array)($body[$key]??[]),0,5000),fn($v)=>is_string($v)&&isset($known[$v]))));
        $result['history']=[];
        foreach(array_slice((array)($body['history']??[]),-200) as $event)if(is_array($event)&&isset($known[$event['id']??'']))$result['history'][]=['id'=>$event['id'],'action'=>in_array($event['action']??'',['learned','quiz_correct','quiz_wrong'],true)?$event['action']:'learned','date'=>cleanText($event['date']??'',40)];
        // Old clients do not erase the V8 progress of newer clients.
        $v=(array)($body['v8']??($all[$id]['v8']??[]));$safe=[];
        foreach(['xp','streak','bestStreak'] as $key)$safe[$key]=max(0,min(10000000,(int)($v[$key]??0)));
        $safe['completedLessons']=array_values(array_unique(array_filter(array_slice((array)($v['completedLessons']??[]),0,144),fn($x)=>is_string($x)&&preg_match('/^L(?:00[1-9]|0[1-9][0-9]|1[0-3][0-9]|14[0-4])$/',$x))));
        $safe['skills']=[];foreach(array_slice((array)($v['skills']??[]),0,100,true) as $key=>$row)if(preg_match('/^[a-z_]{1,60}$/',(string)$key)&&is_array($row)){$total=max(0,min(1000000,(int)($row['total']??0)));$safe['skills'][$key]=['total'=>$total,'correct'=>max(0,min($total,(int)($row['correct']??0)))];}
        $safe['review']=[];foreach(array_slice((array)($v['review']??[]),0,1000,true) as $key=>$row)if(preg_match('/^(NZV6-[0-9]{4}|C[0-9]{3})$/',(string)$key)&&is_array($row))$safe['review'][$key]=['errors'=>max(0,min(100000,(int)($row['errors']??0))),'interval'=>max(0,min(30,(int)($row['interval']??0))),'due'=>max(0,min(8640000000000000,(float)($row['due']??0)))];
        $result['v8']=$safe;
        $result['updatedAt']=gmdate('c');$all[$id]=$result;writeJsonFile('learning_progress',$all);jsonResponse($result);
    }
    jsonResponse(['error'=>'Méthode non autorisée.'],405);
}
if($route==='admin/learning-audio'&&$method==='POST'){
    requirePermission('NZEMA_DICT',true);$file=$_FILES['file']??null;
    if(!$file||$file['error']!==UPLOAD_ERR_OK)jsonResponse(['error'=>'Choisissez un enregistrement audio.'],422);
    if($file['size']>5*1024*1024)jsonResponse(['error'=>'Audio : 5 Mo maximum.'],413);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/ogg'=>'ogg','application/ogg'=>'ogg','audio/mp4'=>'m4a','video/mp4'=>'m4a','audio/x-m4a'=>'m4a','audio/webm'=>'webm','video/webm'=>'webm'][$mime]??null;
    if(!$ext)jsonResponse(['error'=>'Format audio accepté : MP3, WAV, OGG, M4A ou WebM.'],415);
    $name='audio-'.bin2hex(random_bytes(16)).'.'.$ext;
    if(!move_uploaded_file($file['tmp_name'],$uploadDir.'/'.$name))jsonResponse(['error'=>'Enregistrement impossible.'],500);
    jsonResponse(['url'=>'/appli/uploads/'.$name],201);
}
