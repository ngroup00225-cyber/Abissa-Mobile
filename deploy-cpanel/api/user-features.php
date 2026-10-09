<?php
function issueUserToken(array $user): string {
    $token=bin2hex(random_bytes(32));$sessions=(array)readJsonFile('user_sessions',[]);
    $sessions=array_filter($sessions,fn($s)=>(int)($s['expiresAt']??0)>time());
    $sessions[hash('sha256',$token)]=['userId'=>$user['id'],'expiresAt'=>time()+30*86400,'issuedAt'=>gmdate('c')];
    writeJsonFile('user_sessions',$sessions);return $token;
}
function authenticatedUser(): array {
    $header=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
    if(!preg_match('/^Bearer ([a-f0-9]{64})$/',$header,$m))jsonResponse(['error'=>'Connectez-vous pour continuer.'],401);
    $session=readJsonFile('user_sessions',[])[hash('sha256',$m[1])]??null;
    if(!$session||($session['expiresAt']??0)<time())jsonResponse(['error'=>'Session expirée. Reconnectez-vous.'],401);
    foreach((array)readJsonFile('users',[]) as $u)if(($u['id']??'')===$session['userId']&&($u['status']??'')==='active'&&($u['passwordChangedAt']??'')<=($session['issuedAt']??''))return $u;
    jsonResponse(['error'=>'Compte indisponible.'],401);
}
if($route==='users/me'&&$method==='GET'){$user=authenticatedUser();unset($user['passwordHash']);jsonResponse(['user'=>$user]);}
if($route==='users/photo'&&$method==='POST'){
    $user=authenticatedUser();$file=$_FILES['file']??null;
    if(!$file||$file['error']!==UPLOAD_ERR_OK)jsonResponse(['error'=>'Choisissez une photo.'],422);
    if($file['size']>3*1024*1024)jsonResponse(['error'=>'Photo : 3 Mo maximum.'],413);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
    if(!$ext)jsonResponse(['error'=>'Choisissez une image JPEG, PNG ou WebP.'],415);
    $name=bin2hex(random_bytes(16)).'.'.$ext;
    if(!move_uploaded_file($file['tmp_name'],$uploadDir.'/'.$name))jsonResponse(['error'=>'Enregistrement impossible.'],500);
    $url='https://abissamobile.com/appli/uploads/'.$name;$users=(array)readJsonFile('users',[]);
    foreach($users as &$u)if($u['id']===$user['id'])$u['photo']=$url;
    writeJsonFile('users',$users);jsonResponse(['photo'=>$url]);
}

if($route==='admin/preferences'){
    $admin=requireAdmin($method!=='GET');
    if($method==='GET')jsonResponse((object)array_merge((array)readJsonFile('admin_preferences',['accentColor'=>'#008def']),['sessionMinutes'=>3]));
    if($method==='PUT'){
        if($admin['role']!=='founder')jsonResponse(['error'=>'Réservé au compte principal.'],403);
        $body=requestJson();$color=(string)($body['accentColor']??'#008def');
        if(!preg_match('/^#[0-9a-f]{6}$/i',$color))jsonResponse(['error'=>'Couleur invalide.'],422);
        writeJsonFile('admin_preferences',['accentColor'=>$color,'sessionMinutes'=>3]);jsonResponse(['ok'=>true]);
    }
}
