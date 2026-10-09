<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
// New modules work with the existing private server configuration.
define('PUBLIC_CONTENT_KEYS',array_values(array_unique(array_merge(PUBLIC_STATE_KEYS,['ADVERTISEMENTS']))));
require __DIR__ . '/committee-model.php';

$dataDir = dirname(__DIR__) . '/data';
$uploadDir = dirname(__DIR__) . '/uploads';
if (!is_dir($dataDir)) mkdir($dataDir, 0750, true);
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$route = trim((string)($_GET['route'] ?? ($_SERVER['PATH_INFO'] ?? '')), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD']);
// Serialize read-modify-write transactions for shared JSON storage.
$requestLock=fopen($dataDir.'/.transaction.lock','c+');
if(!$requestLock||!flock($requestLock,LOCK_EX))jsonResponse(['error'=>'Stockage temporairement occupé.'],503);
register_shutdown_function(static function()use($requestLock){flock($requestLock,LOCK_UN);fclose($requestLock);});

function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function readJsonFile(string $name, mixed $fallback): mixed {
    global $dataDir;
    $path = $dataDir . '/' . $name . '.json';
    if (!is_file($path)) return $fallback;
    $handle = fopen($path, 'rb');
    if (!$handle) jsonResponse(['error'=>'Lecture du stockage indisponible.'],503);
    try {
        if (!flock($handle, LOCK_SH)) jsonResponse(['error'=>'Lecture du stockage indisponible.'],503);
        $raw = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally { fclose($handle); }
    $value = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $raw ?: ''), true);
    if (json_last_error() !== JSON_ERROR_NONE) jsonResponse(['error'=>'Un fichier de données est illisible. Restaurez sa sauvegarde avant de poursuivre.'],503);
    return $value;
}

function writeJsonFile(string $name, mixed $value): void {
    global $dataDir;
    $path = $dataDir . '/' . $name . '.json';
    $tmp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    if(function_exists('backupDataFile'))backupDataFile($name);
    $oldValue=readJsonFile($name,null);
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || strlen($json) > MAX_JSON_BYTES) jsonResponse(['error' => 'Données trop volumineuses'], 413);
    $handle = fopen($tmp, 'wb');
    if (!$handle) jsonResponse(['error' => 'Stockage indisponible'], 500);
    try {
        if (!flock($handle, LOCK_EX)) jsonResponse(['error' => 'Stockage occupé'], 503);
        if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) jsonResponse(['error'=>'Enregistrement incomplet.'],503);
        flock($handle, LOCK_UN);
    } finally { fclose($handle); }
    chmod($tmp, 0640);
    if (!rename($tmp, $path)) jsonResponse(['error' => 'Enregistrement impossible'], 500);
    if(function_exists('activityJournal') && !empty($_SESSION['admin']) && $oldValue!==$value && in_array($name,['state','submissions','admins','users','credential_prints','special_profiles','qrcodes','admin_navigation','directory_meta','admin_preferences'],true)) { $changed=[];if($name==='state')foreach($value as $k=>$v)if($v!==($oldValue[$k]??null))$changed[]=$k;activityJournal('Modification : '.$name,['route'=>$GLOBALS['route']??'','sections'=>$changed]); }
}

function requestJson(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > MAX_JSON_BYTES) jsonResponse(['error' => 'Requête trop volumineuse'], 413);
    $value = json_decode($raw, true);
    if (!is_array($value)) jsonResponse(['error' => 'JSON invalide'], 400);
    return $value;
}

function publicState(array $state): array {
    $state = abissaEnrichState($state);
    $clean = [];
    foreach (PUBLIC_CONTENT_KEYS as $key) if (array_key_exists($key, $state)) $clean[$key] = $state[$key];
    return $clean;
}

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('abissa_admin');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Strict']);
    session_start();
}

function requireAdmin(bool $write = false): array {
    startSecureSession();
    if(isset($_SESSION['admin'])){$minutes=max(1,min(1440,(float)(readJsonFile('state',[])['SETTINGS']['adminIdleMinutes']??3)));if(time()-(int)($_SESSION['lastActive']??time())>$minutes*60){unset($_SESSION['admin']);}else{$_SESSION['lastActive']=time();}}
    if (empty($_SESSION['admin'])) jsonResponse(['error' => 'Authentification requise'], 401);
    if(($_SESSION['admin']['role']??'')!=='founder'){ $fresh=null;foreach((array)readJsonFile('admins',[]) as $a)if(($a['id']??'')===($_SESSION['admin']['id']??'')&&($a['active']??true)){$fresh=$a;break;}if(!$fresh){unset($_SESSION['admin']);jsonResponse(['error'=>'Compte administrateur désactivé.'],401);}$_SESSION['admin']['permissions']=$fresh['permissions']??[];$_SESSION['admin']['username']=$fresh['username']; }
    if ($write) {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$csrf || !hash_equals((string)($_SESSION['csrf'] ?? ''), $csrf)) jsonResponse(['error' => 'Jeton de sécurité invalide'], 403);
    }
    return (array)$_SESSION['admin'];
}

function requirePermission(string $permission, bool $write = false): array {
    $admin = requireAdmin($write);
    $permissions = (array)($admin['permissions'] ?? []);
    if (($admin['role'] ?? '') !== 'founder' && !in_array('*', $permissions, true) && !in_array($permission, $permissions, true)) {
        jsonResponse(['error' => 'Vous n’avez pas accès à cette rubrique'], 403);
    }
    return $admin;
}

function cleanText(mixed $value, int $max = 180): string {
    if (!is_scalar($value) && $value !== null) jsonResponse(['error'=>'Une valeur textuelle est attendue.'],422);
    return trim(mb_substr((string)$value, 0, $max));
}

function founderPasswordMatches(string $password): bool {
    $hash = hash_pbkdf2('sha256', $password, FOUNDER_SALT, FOUNDER_HASH_ITERATIONS, 64, false);
    return hash_equals(FOUNDER_PASSWORD_HASH, $hash);
}

function isRateLimited(string $ip): bool {
    $attempts = readJsonFile('login_attempts', []);
    $now = time();
    foreach ($attempts as $key => $values) {
        $attempts[$key] = array_values(array_filter((array)$values, fn($t) => (int)$t > $now - 900));
        if (!$attempts[$key]) unset($attempts[$key]);
    }
    writeJsonFile('login_attempts', $attempts);
    return count($attempts[$ip] ?? []) >= 8;
}

function recordFailedLogin(string $ip): void {
    $attempts = readJsonFile('login_attempts', []);
    $attempts[$ip] = array_values(array_filter((array)($attempts[$ip] ?? []), fn($t) => (int)$t > time() - 900));
    $attempts[$ip][] = time();
    writeJsonFile('login_attempts', $attempts);
}

function normalizePhone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
    if (strlen($digits) === 10 && str_starts_with($digits, '0')) $digits = '225' . substr($digits, 1);
    return $digits;
}

function sendWhatsAppCode(string $phone, string $code, string $purpose): void {
    if (WHATSAPP_PHONE_NUMBER_ID === '' || WHATSAPP_ACCESS_TOKEN === '') {
        jsonResponse(['error'=>'L\u2019envoi WhatsApp n\u2019est pas encore configuré. Ajoutez les identifiants WhatsApp Business Cloud dans la configuration serveur.','code'=>'whatsapp_not_configured'],503);
    }
    if (!function_exists('curl_init')) jsonResponse(['error'=>'Le module serveur cURL est indisponible'],503);
    $label = $purpose === 'reset' ? 'réinitialiser votre mot de passe' : 'confirmer votre inscription';
    $payload = ['messaging_product'=>'whatsapp','to'=>$phone,'type'=>'text','text'=>['preview_url'=>false,'body'=>"Votre code Abissa Mobile pour {$label} est : {$code}. Il expire dans 10 minutes."]];
    $curl = curl_init('https://graph.facebook.com/' . WHATSAPP_API_VERSION . '/' . rawurlencode(WHATSAPP_PHONE_NUMBER_ID) . '/messages');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.WHATSAPP_ACCESS_TOKEN,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload)]);
    $response = curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); $error=curl_error($curl); curl_close($curl);
    if ($response === false || $status < 200 || $status >= 300) jsonResponse(['error'=>'Impossible d\u2019envoyer le code WhatsApp pour le moment','detail'=>$error?:'WhatsApp HTTP '.$status],502);
}

function issueVerificationCode(string $phone, string $purpose): void {
    $records=(array)readJsonFile('verification_codes',[]); $now=time();
    foreach($records as $key=>$record) if((int)($record['expiresAt']??0)<$now) unset($records[$key]);
    $key=hash('sha256',$purpose.'|'.$phone); $existing=$records[$key]??null;
    if($existing && (int)($existing['sentAt']??0)>$now-60) jsonResponse(['error'=>'Patientez une minute avant de demander un nouveau code'],429);
    $code=(string)random_int(100000,999999);
    $records[$key]=['phone'=>$phone,'purpose'=>$purpose,'codeHash'=>password_hash($code,PASSWORD_DEFAULT),'sentAt'=>$now,'expiresAt'=>$now+600,'attempts'=>0];
    sendWhatsAppCode($phone,$code,$purpose);
    writeJsonFile('verification_codes',$records);
}

function consumeVerificationToken(string $phone, string $purpose, string $token): bool {
    $tokens=(array)readJsonFile('verification_tokens',[]); $key=hash('sha256',$purpose.'|'.$phone.'|'.$token); $record=$tokens[$key]??null;
    if(!$record || (int)($record['expiresAt']??0)<time() || !empty($record['used'])) return false;
    $tokens[$key]['used']=true; writeJsonFile('verification_tokens',$tokens); return true;
}

require_once __DIR__.'/evolution.php';

if ($route === 'health' && $method === 'GET') jsonResponse(['ok'=>true, 'app'=>APP_NAME, 'time'=>gmdate('c')]);

if ($route === 'auth/request-code' && $method === 'POST') {
    $body=requestJson(); $phone=normalizePhone(cleanText($body['phone']??'',40)); $purpose=cleanText($body['purpose']??'',20);
    if(strlen($phone)<10 || !in_array($purpose,['signup','reset'],true)) jsonResponse(['error'=>'Numéro ou usage invalide'],422);
    if($purpose==='reset') { $found=false; foreach((array)readJsonFile('users',[]) as $u) if(normalizePhone((string)($u['phone']??''))===$phone){$found=true;break;} if(!$found) jsonResponse(['error'=>'Aucun compte ne correspond à ce numéro'],404); }
    issueVerificationCode($phone,$purpose); jsonResponse(['ok'=>true,'expiresIn'=>600]);
}

if ($route === 'auth/verify-code' && $method === 'POST') {
    $body=requestJson(); $phone=normalizePhone(cleanText($body['phone']??'',40)); $purpose=cleanText($body['purpose']??'',20); $code=cleanText($body['code']??'',10);
    $records=(array)readJsonFile('verification_codes',[]); $key=hash('sha256',$purpose.'|'.$phone); $record=$records[$key]??null;
    if(!$record || (int)($record['expiresAt']??0)<time()) jsonResponse(['error'=>'Code expiré ou introuvable'],422);
    if((int)($record['attempts']??0)>=5) jsonResponse(['error'=>'Trop de tentatives. Demandez un nouveau code.'],429);
    if(!password_verify($code,(string)($record['codeHash']??''))) { $records[$key]['attempts']=(int)($record['attempts']??0)+1; writeJsonFile('verification_codes',$records); jsonResponse(['error'=>'Code incorrect'],422); }
    unset($records[$key]); writeJsonFile('verification_codes',$records); $token=bin2hex(random_bytes(24));
    $tokens=(array)readJsonFile('verification_tokens',[]); $tokens[hash('sha256',$purpose.'|'.$phone.'|'.$token)]=['phone'=>$phone,'purpose'=>$purpose,'expiresAt'=>time()+900,'used'=>false]; writeJsonFile('verification_tokens',$tokens);
    jsonResponse(['ok'=>true,'verificationToken'=>$token]);
}

require __DIR__ . '/user-features.php';
require __DIR__ . '/account-deletion.php';
require __DIR__ . '/registration.php';
require __DIR__ . '/request-files.php';
require __DIR__ . '/qrcodes.php';
require __DIR__ . '/committee.php';
require __DIR__ . '/directory.php';
require_once __DIR__.'/module-management.php';
require __DIR__ . '/access-model.php';
require __DIR__ . '/pass-customization.php';
passCustomizationMigrate();
printRestorationMigrate();
require __DIR__ . '/credential-workshop.php';
require __DIR__ . '/mobile-profile.php';
require __DIR__ . '/learning.php';
require __DIR__ . '/nzema-v8.php';
require __DIR__ . '/community.php';
require __DIR__ . '/badge-design.php';
require __DIR__ . '/access-control.php';

if ($route === 'data' && $method === 'GET') {
    header('Cache-Control: no-store');
    $public=publicState((array)readJsonFile('state', []));
    $public['COMMENTS']=array_values(array_map(fn($c)=>array_intersect_key($c,array_flip(['id','newsId','author','text','status','date'])),array_filter((array)($public['COMMENTS']??[]),fn($c)=>($c['status']??'')==='validated')));
    jsonResponse($public);
}

if ($route === 'auth/login' && $method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (isRateLimited($ip)) jsonResponse(['error'=>'Trop de tentatives. Réessayez dans 15 minutes.'], 429);
    $body = requestJson();
    $username = cleanText($body['username'] ?? '', 80);
    $password = (string)($body['password'] ?? '');
    $admin = null;
    if (hash_equals(strtolower(FOUNDER_USERNAME), strtolower($username)) && founderPasswordMatches($password)) {
        $admin = ['id'=>'founder', 'username'=>FOUNDER_USERNAME, 'role'=>'founder', 'permissions'=>['*']];
    } else {
        foreach ((array)readJsonFile('admins', []) as $candidate) {
            if (($candidate['active'] ?? true) === true && strtolower((string)($candidate['username'] ?? '')) === strtolower($username) && password_verify($password, (string)($candidate['passwordHash'] ?? ''))) {
                $admin = ['id'=>$candidate['id'], 'username'=>$candidate['username'], 'role'=>'admin', 'permissions'=>$candidate['permissions'] ?? []];
                break;
            }
        }
    }
    if (!$admin) { recordFailedLogin($ip); usleep(350000); jsonResponse(['error'=>'Identifiants incorrects'], 401); }
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['admin'] = $admin;
    $_SESSION['lastActive'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
    jsonResponse(['admin'=>$admin, 'csrf'=>$_SESSION['csrf']]);
}

if ($route === 'auth/activity' && $method === 'POST') { requireAdmin(true); jsonResponse(['ok'=>true]); }

if ($route === 'auth/me' && $method === 'GET') {
    startSecureSession();
    if(isset($_SESSION['admin']))requireAdmin();
    jsonResponse(['admin'=>$_SESSION['admin'] ?? null, 'csrf'=>$_SESSION['csrf'] ?? null]);
}

if ($route === 'auth/logout' && $method === 'POST') {
    requireAdmin(true);
    $_SESSION = [];
    session_destroy();
    jsonResponse(['ok'=>true]);
}

if ($route === 'users/signup' && $method === 'POST') {
    $body = requestJson();
    $name = cleanText($body['name'] ?? '', 160);
    $username = cleanText($body['username'] ?? '', 40);
    $phone = cleanText($body['phone'] ?? '', 40);
    $contractAccepted = filter_var($body['contractAccepted'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $contractVersion = cleanText($body['contractVersion'] ?? '', 40);
    $password = (string)($body['password'] ?? '');
    if ($name === '' || $username === '' || $phone === '' || strlen($password) < 6) jsonResponse(['error'=>'Informations incomplètes ou mot de passe trop court'], 422);
    if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) jsonResponse(['error'=>'Identifiant invalide'],422);
    if (!$contractAccepted || $contractVersion === '') jsonResponse(['error'=>'Vous devez lire et accepter le contrat utilisateur'], 422);
    $users = (array)readJsonFile('users', []);
    foreach ($users as $user) if (($user['phone'] ?? '') === $phone) jsonResponse(['error'=>'Ce numéro est déjà utilisé'], 409);
    foreach ($users as $user) if (strtolower((string)($user['username'] ?? '')) === strtolower($username)) jsonResponse(['error'=>'Cet identifiant est déjà utilisé'],409);
    $user = [
        'id'=>bin2hex(random_bytes(10)), 'name'=>$name, 'username'=>$username, 'phone'=>$phone,
        'passwordHash'=>password_hash($password, PASSWORD_DEFAULT), 'createdAt'=>gmdate('c'), 'status'=>'active',
        'contract'=>['version'=>$contractVersion, 'accepted'=>true, 'acceptedAt'=>gmdate('c')]
    ];
    $users[] = $user;
    writeJsonFile('users', $users);
    unset($user['passwordHash']);
    jsonResponse(['user'=>$user,'token'=>issueUserToken($user)], 201);
}

if ($route === 'users/reset-password' && $method === 'POST') {
    $body=requestJson(); $phone=normalizePhone(cleanText($body['phone']??'',40)); $password=(string)($body['password']??''); $token=cleanText($body['verificationToken']??'',100);
    if(strlen($password)<6) jsonResponse(['error'=>'Le mot de passe doit contenir au moins 6 caractères'],422);
    if(!consumeVerificationToken($phone,'reset',$token)) jsonResponse(['error'=>'Vérification WhatsApp invalide ou expirée'],422);
    $users=(array)readJsonFile('users',[]); $found=false; foreach($users as &$u) if(normalizePhone((string)($u['phone']??''))===$phone){$u['passwordHash']=password_hash($password,PASSWORD_DEFAULT);$u['passwordChangedAt']=gmdate('c');$found=true;}
    if(!$found) jsonResponse(['error'=>'Compte introuvable'],404); writeJsonFile('users',$users); jsonResponse(['ok'=>true]);
}

if ($route === 'users/login' && $method === 'POST') {
    $ip='user:'.($_SERVER['REMOTE_ADDR']??'unknown');if(isRateLimited($ip))jsonResponse(['error'=>'Trop de tentatives. Réessayez dans 15 minutes.'],429);
    $body = requestJson();
    $id = cleanText($body['id'] ?? '', 120);
    $password = (string)($body['password'] ?? '');
    foreach ((array)readJsonFile('users', []) as $user) {
        if ((($user['phone'] ?? '') === $id || strtolower((string)($user['username'] ?? '')) === strtolower($id)) && ($user['status'] ?? 'active') === 'active' && password_verify($password, (string)($user['passwordHash'] ?? ''))) {
            unset($user['passwordHash']);
            jsonResponse(['user'=>$user,'token'=>issueUserToken($user)]);
        }
    }
    recordFailedLogin($ip);jsonResponse(['error'=>'Identifiants incorrects'], 401);
}

if ($route === 'submissions' && $method === 'POST') {
    $body = requestJson();
    $type = cleanText($body['type'] ?? '', 40);
    if (!in_array($type, ['press','reservation','order','contact','member','pass'], true)) jsonResponse(['error'=>'Type invalide'], 422);
    if (!is_array($body['data']??null)) jsonResponse(['error'=>'Dossier invalide'],422);
    startSecureSession();$actor=null;if(!empty($_SESSION['admin'])&&!empty($_SERVER['HTTP_X_CSRF_TOKEN'])){ $a=requirePermission(submissionPermission($type),true); $actor=['id'=>'admin:'.$a['username']]; }elseif(in_array($type,['member','press','pass','reservation','order'],true)||($type==='contact'&&!empty($_SERVER['HTTP_AUTHORIZATION'])))$actor=authenticatedUser();
    if(!str_starts_with((string)($actor['id']??''),'admin:'))assertRequestsOpen($type);
    if($type==='contact'&&preg_match('/^accr[eé]ditation/iu',cleanText($body['data']['subject']??'',200))&&!str_starts_with((string)($actor['id']??''),'admin:')){$actor=authenticatedUser();assertRequestsOpen('press');}
    $body['data']=validateRequestData($type,$body['data'],$actor);
    $items = (array)readJsonFile('submissions', []);
    $item = ['id'=>bin2hex(random_bytes(10)), 'type'=>$type, 'status'=>'pending', 'data'=>$body['data'] ?? [], 'createdAt'=>gmdate('c')];
    $item=withMemberRegistration($item);
    if($actor)$item['userId']=$actor['id'];
    $items[] = $item;
    writeJsonFile('submissions', $items);
    jsonResponse(['submission'=>$item], 201);
}

if ($route === 'public/upload' && $method === 'POST') {
    $ip=$_SERVER['REMOTE_ADDR']??'unknown'; $limits=(array)readJsonFile('public_upload_attempts',[]); $now=time();
    $limits[$ip]=array_values(array_filter((array)($limits[$ip]??[]),fn($t)=>(int)$t>$now-3600));
    if(count($limits[$ip])>=12) jsonResponse(['error'=>'Limite d’envoi atteinte. Réessayez plus tard.'],429);
    if(empty($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK) jsonResponse(['error'=>'Photo manquante'],422);
    $file=$_FILES['file']; if((int)$file['size']>3*1024*1024) jsonResponse(['error'=>'Photo trop volumineuse (3 Mo maximum)'],413);
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
    if(!$ext) jsonResponse(['error'=>'Format photo non autorisé'],415);
    $name='public-'.bin2hex(random_bytes(16)).'.'.$ext; if(!move_uploaded_file($file['tmp_name'],$uploadDir.'/'.$name)) jsonResponse(['error'=>'Enregistrement impossible'],500);
    $limits[$ip][]=$now; writeJsonFile('public_upload_attempts',$limits); $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'); $base=rtrim(str_replace('\\','/',dirname(dirname($_SERVER['SCRIPT_NAME']))),'/');
    jsonResponse(['url'=>($secure?'https':'http').'://'.$_SERVER['HTTP_HOST'].$base.'/uploads/'.$name],201);
}

require_once __DIR__ . '/credentials.php';

if ($route === 'admin/state') {
    if ($method === 'GET') { requireAdmin(); jsonResponse(abissaEnrichState((array)readJsonFile('state', []))); }
    if ($method === 'PUT') {
        $admin=requireAdmin(true);
        $previous=abissaEnrichState((array)readJsonFile('state',[]));
        // Preserve extensions not represented by the current administration form.
        $incoming=normalizeEditorialLinks(requestJson());validateStateShape($incoming);
        $state=array_replace($previous,publicState($incoming));$state['COMMENTS']=$previous['COMMENTS']??[];$state['SETTINGS']['homeSlides']=$previous['SETTINGS']['homeSlides']??[];
        if(isset($state['SETTINGS']['memberRoles'])) {
            $roles=$state['SETTINGS']['memberRoles'];
            if(!is_array($roles)||count($roles)>100)jsonResponse(['error'=>'Liste de fonctions invalide.'],422);
            foreach($roles as $role)if(!is_string($role)||trim($role)===''||mb_strlen($role)>100)jsonResponse(['error'=>'Chaque fonction doit contenir 1 à 100 caractères.'],422);
            $state['SETTINGS']['memberRoles']=array_values(array_unique(array_map('trim',$roles)));
        }
        $idle=$state['SETTINGS']['adminIdleMinutes']??3;
        if(!is_numeric($idle)||(float)$idle<1||(float)$idle>1440)jsonResponse(['error'=>'Durée de déconnexion invalide (1 à 1 440 minutes).'],422);
        foreach((array)($state['GALLERY']??[]) as $album)if(count(array_filter((array)($album['photos']??[])))>10)jsonResponse(['error'=>'Maximum 10 images par catégorie.'],422);
        validateEvolutionState($state,$previous,$admin);
        writeJsonFile('state', $state); jsonResponse(['ok'=>true,'updatedAt'=>gmdate('c'),'by'=>$admin['username']]);
    }
}

if ($route === 'admin/users') {
    if ($method === 'GET') { requirePermission('users'); $users=(array)readJsonFile('users',[]); foreach($users as &$u) unset($u['passwordHash']); jsonResponse($users); }
    if ($method === 'PATCH') { requirePermission('users',true); $body=requestJson(); if(isset($body['status'])&&!in_array($body['status'],['active','blocked','suspended'],true))jsonResponse(['error'=>'Statut utilisateur invalide.'],422); $users=(array)readJsonFile('users',[]); foreach($users as &$u) if(($u['id']??'')===($body['id']??'')) { if(isset($body['status'])) $u['status']=in_array($body['status'], ['active','blocked','suspended'], true)?$body['status']:'active'; foreach(['name','username','phone'] as $field) if(array_key_exists($field,$body)) $u[$field]=cleanText($body[$field],160); } writeJsonFile('users',$users); jsonResponse(['ok'=>true]); }
    if ($method === 'DELETE') { requirePermission('users',true); $id=cleanText($_GET['id']??'',80); $users=array_values(array_filter((array)readJsonFile('users',[]),fn($u)=>($u['id']??'')!==$id)); writeJsonFile('users',$users); jsonResponse(['ok'=>true]); }
}

if ($route === 'admin/submissions') {
    $admin=requireAnyPermission(['submissions','MEMBERS_MANAGE','ACCREDITATIONS_MANAGE','PASSES_MANAGE'],$method!=='GET');
    $items=(array)readJsonFile('submissions',[]);
    if($method==='GET')jsonResponse(array_map('withMemberRegistration',array_values(array_filter($items,fn($s)=>adminHas($admin,submissionPermission($s['type']??''))))));
    $body=$method==='PATCH'?requestJson():[];$id=cleanText($body['id']??$_GET['id']??'',80);$index=null;
    foreach($items as $i=>$item)if(($item['id']??'')===$id){$index=$i;break;}
    if($index===null)jsonResponse(['error'=>'Dossier introuvable.'],404);
    requirePermission(submissionPermission($items[$index]['type']),true);
    if($method==='DELETE'){array_splice($items,$index,1);writeJsonFile('submissions',$items);jsonResponse(['ok'=>true]);}
    if($method==='PATCH'){
        $item=&$items[$index];
        if(isset($body['status'])){if(!in_array($body['status'],['pending','validated','rejected','suspended','blocked','processed'],true))jsonResponse(['error'=>'Statut invalide.'],422);if($body['status']==='validated'&&$item['status']!=='validated')$item['validatedAt']=gmdate('c');$item['status']=$body['status'];}
        if(isset($body['data'])){if(!is_array($body['data']))jsonResponse(['error'=>'Dossier invalide.'],422);$item['data']=validateRequestData($item['type'],$body['data'],['id'=>$item['userId']??'admin:'.$admin['username']],true);}
        $item=withMemberRegistration($item);$item['updatedAt']=gmdate('c');unset($item);writeJsonFile('submissions',$items);jsonResponse(['ok'=>true]);
    }
}

if ($route === 'admin/admins') {
    $current = requireAdmin($method !== 'GET');
    if (($current['role'] ?? '') !== 'founder') jsonResponse(['error'=>'Réservé au super administrateur'],403);
    if ($method === 'GET') { $admins=(array)readJsonFile('admins',[]); foreach($admins as &$a) unset($a['passwordHash']); jsonResponse($admins); }
    if (in_array($method,['POST','PUT'],true)) {
        $body=requestJson();$admins=(array)readJsonFile('admins',[]);$id=$method==='POST'?bin2hex(random_bytes(8)):cleanText($body['id']??'',80);$record=null;$index=null;
        foreach($admins as $i=>$a)if(($a['id']??'')===$id){$record=$a;$index=$i;break;}
        if($method==='PUT'&&!$record)jsonResponse(['error'=>'Administrateur introuvable'],404);
        $username=cleanText($body['username']??$record['username']??'',80);
        $password=$body['password']??'';
        if(!$username||!is_string($password)||(($method==='POST'||$password!=='')&&strlen($password)<6))jsonResponse(['error'=>'Identifiant ou mot de passe invalide'],422);
        foreach($admins as $a)if(($a['id']??'')!==$id&&strtolower($a['username'])===strtolower($username))jsonResponse(['error'=>'Identifiant déjà utilisé'],409);
        if(strtolower($username)===strtolower(FOUNDER_USERNAME))jsonResponse(['error'=>'Cet identifiant est réservé au compte principal.'],409);
        $active=$body['active']??$record['active']??true;
        if(!is_bool($active))jsonResponse(['error'=>'Statut administrateur invalide.'],422);
        $record=array_merge($record??[],['id'=>$id,'username'=>$username,'active'=>$active,'permissions'=>validatedAdminPermissions($body['permissions']??$record['permissions']??[]),'updatedAt'=>gmdate('c')]);
        if($password!=='')$record['passwordHash']=password_hash($password,PASSWORD_DEFAULT);
        if($method==='POST'){$record['createdAt']=gmdate('c');$admins[]=$record;}else $admins[$index]=$record;
        writeJsonFile('admins',$admins);jsonResponse(['ok'=>true],$method==='POST'?201:200);
    }
    if ($method === 'DELETE') { $body=[]; if(str_contains(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json')) $body=requestJson(); $ids=array_values(array_filter(array_map(fn($id)=>cleanText($id,80),(array)($body['ids']??[])))); $single=cleanText($_GET['id']??'',80); if($single!=='') $ids[]=$single; if(!$ids) jsonResponse(['error'=>'Aucun administrateur sélectionné'],422); $admins=array_values(array_filter((array)readJsonFile('admins',[]),fn($a)=>!in_array((string)($a['id']??''),$ids,true))); writeJsonFile('admins',$admins); jsonResponse(['ok'=>true,'deleted'=>count($ids)]); }
}

if ($route === 'admin/upload' && $method === 'POST') {
    requireAdmin(true);
    if(in_array($_FILES['file']['error']??null,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))jsonResponse(['error'=>'Image trop volumineuse pour la configuration PHP du serveur.'],413);
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) jsonResponse(['error'=>'Fichier manquant'],422);
    $file=$_FILES['file'];
    if ((int)$file['size'] > MAX_UPLOAD_BYTES) jsonResponse(['error'=>'Image trop volumineuse (5 Mo maximum)'],413);
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']);
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime]??null;
    if(!$ext) jsonResponse(['error'=>'Format d’image non autorisé'],415);
    $name=bin2hex(random_bytes(16)).'.'.$ext;
    if(!move_uploaded_file($file['tmp_name'],$uploadDir.'/'.$name)) jsonResponse(['error'=>'Enregistrement impossible'],500);
    $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
    $base=rtrim(str_replace('\\','/',dirname(dirname($_SERVER['SCRIPT_NAME']))),'/');
    $url=($secure?'https':'http').'://'.$_SERVER['HTTP_HOST'].$base.'/uploads/'.$name;
    jsonResponse(['url'=>$url],201);
}

jsonResponse(['error'=>'Route introuvable','route'=>$route],404);
