<?php
function requestActor(): array {
    startSecureSession();
    if (!empty($_SESSION['admin']) && !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) { $admin=requireAnyPermission(['MEMBERS_MANAGE','ACCREDITATIONS_MANAGE','PASSES_MANAGE'],true); return ['id'=>'admin:'.$admin['username']]; }
    return authenticatedUser();
}
function validateRequestData(string $type, array $data, ?array $actor, bool $editing=false): array {
    foreach(['name'=>160,'fullName'=>160,'role'=>180,'roleChoice'=>100,'roleOther'=>100,'commission'=>160,'organization'=>160,'phone'=>40,'email'=>180,'vehicle'=>120,'plate'=>80,'validUntil'=>10,'photo'=>2000,'accreditationType'=>120,'proofId'=>80] as $field=>$max) {
        if(array_key_exists($field,$data))$data[$field]=cleanText($data[$field],$max);
    }
    if(!empty($data['photo'])&&!validPublicUrl($data['photo'],true))jsonResponse(['error'=>'Adresse de la photo invalide.'],422);
    if(!empty($data['email'])&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))jsonResponse(['error'=>'Adresse e-mail invalide.'],422);
    if ($type==='member') return validateMemberData($data,$editing);
    if (!in_array($type,['press','pass'],true)) return $data;
    $required=$type==='press'?['name','organization','phone','accreditationType']:['name','phone','vehicle','plate','validUntil'];
    foreach ($required as $field) if ((!$editing||array_key_exists($field,$data))&&trim((string)($data[$field]??''))==='') jsonResponse(['error'=>'Complétez les champs obligatoires.'],422);
    if ($type==='pass'&&(!empty($data['validUntil'])||!$editing)) {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$data['validUntil']);
        if (!$date || $date->format('Y-m-d')!==$data['validUntil'] || (!$editing&&$data['validUntil']<gmdate('Y-m-d'))) jsonResponse(['error'=>'La date de validité demandée doit être aujourd’hui ou une date future.'],422);
    }
    if ($type==='press'&&(!$editing||!empty($data['proofId']))) {
        $proofId=(string)($data['proofId']??''); $proofs=(array)readJsonFile('proof_files',[]); $proof=$proofs[$proofId]??null;
        if (!$proof || ($proof['ownerId']??'')!==($actor['id']??'')) jsonResponse(['error'=>'Ajoutez votre carte de presse ou un justificatif (PDF ou image).'],422);
        $data['proofName']=$proof['name'];
    }
    return $data;
}
if ($route==='public/proof' && $method==='POST') {
    $actor=requestActor();
    $attempts=(array)readJsonFile('proof_upload_attempts',[]); $owner=(string)$actor['id']; $now=time();
    $attempts[$owner]=array_values(array_filter((array)($attempts[$owner]??[]),fn($time)=>(int)$time>$now-3600));
    if (count($attempts[$owner])>=15) jsonResponse(['error'=>'Limite d’envoi atteinte. Réessayez plus tard.'],429);
    $file=$_FILES['file']??null;
    if (!$file || $file['error']!==UPLOAD_ERR_OK) jsonResponse(['error'=>'Justificatif manquant ou trop volumineux.'],422);
    if ((int)$file['size']>5*1024*1024) jsonResponse(['error'=>'Le justificatif doit peser au maximum 5 Mo.'],413);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
    if (!$ext) jsonResponse(['error'=>'Formats acceptés : PDF, JPEG, PNG et WebP.'],415);
    $folder=$dataDir.'/proofs'; if (!is_dir($folder)) mkdir($folder,0750,true);
    $id=bin2hex(random_bytes(16)); $name=cleanText(basename(str_replace('\\','/',(string)$file['name'])),160);
    if (!move_uploaded_file($file['tmp_name'],$folder.'/'.$id.'.'.$ext)) jsonResponse(['error'=>'Enregistrement du justificatif impossible'],500);
    $proofs=(array)readJsonFile('proof_files',[]); $proofs[$id]=['name'=>$name,'mime'=>$mime,'ext'=>$ext,'ownerId'=>$owner,'createdAt'=>gmdate('c')];
    writeJsonFile('proof_files',$proofs); $attempts[$owner][]=$now; writeJsonFile('proof_upload_attempts',$attempts);
    jsonResponse(['id'=>$id,'name'=>$name],201);
}
if ($route==='admin/proof' && $method==='GET') {
    requirePermission('ACCREDITATIONS_MANAGE'); $id=(string)($_GET['id']??'');
    $proof=(readJsonFile('proof_files',[])[$id]??null);
    if (!preg_match('/^[a-f0-9]{32}$/',$id) || !$proof) jsonResponse(['error'=>'Justificatif introuvable'],404);
    $path=$dataDir.'/proofs/'.$id.'.'.$proof['ext'];
    if (!is_file($path)) jsonResponse(['error'=>'Fichier introuvable'],404);
    header('Content-Type: '.$proof['mime']); header('Cache-Control: private, no-store');
    header("Content-Security-Policy: sandbox");
    header("Content-Disposition: inline; filename=justificatif.".$proof['ext']."; filename*=UTF-8''".rawurlencode($proof['name']));
    header('Content-Length: '.filesize($path)); readfile($path); exit;
}
