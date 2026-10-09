<?php
// Shared Android, iOS and back-office community API. JSON writes use index.php's transaction lock.
const COMMUNITY_PERMISSIONS=['BLOG','FEEDBACK','REPORTS','NOTIFICATIONS','BADGE_TEMPLATES','SLIDER'];
const FEEDBACK_CATEGORIES=['Avis','Suggestion','Critique','Question','Réclamation','Félicitations','Problème rencontré','Autre'];
const REPORT_REASONS=['Contenu offensant','Spam','Harcèlement','Information trompeuse','Contenu inapproprié','Autre'];
function communityId(): string {return bin2hex(random_bytes(12));}
function communityToken(): string {return (string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');}
function communityText(mixed $b,string $key,int $max,bool $required=false): string {
    if(!is_array($b))jsonResponse(['error'=>'Structure de formulaire invalide.'],422);
    $v=$b[$key]??'';
    if(!is_string($v)||mb_strlen($v)>$max||($required&&trim($v)===''))jsonResponse(['error'=>'Champ invalide : '.$key],422);
    return trim($v);
}
function communityLimit(string $action,string $owner,int $max): void {
    $limits=(array)readJsonFile('community_limits',[]);$now=time();
    foreach($limits as $k=>$times){$limits[$k]=array_values(array_filter($times,fn($t)=>$t>$now-3600));if(!$limits[$k])unset($limits[$k]);}
    $key=$action.':'.$owner;$times=$limits[$key]??[];
    if(count($times)>=$max)jsonResponse(['error'=>'Trop de demandes. Réessayez dans une heure.'],429);
    $limits[$key][]=$now;writeJsonFile('community_limits',$limits);
}
function communityContent(string $type,string $id): array {
    if(!in_array($type,['news','event','blog'],true)||$id==='')jsonResponse(['error'=>'Contenu invalide.'],422);
    $items=$type==='blog'?readJsonFile('blog_posts',[]):(readJsonFile('state',[])[$type==='news'?'NEWS':'EVENTS']??[]);
    foreach($items as $x)if((string)($x['id']??$x['title']??'')===$id&&($x['active']??true)!==false&&($type!=='blog'||($x['status']??'')==='published'))return $x;
    jsonResponse(['error'=>'Contenu introuvable.'],404);
}
function communityNotification(string $userId,string $title,string $text,string $type,string $target): void {
    $items=(array)readJsonFile('community_notifications',[]);
    $items[]=['id'=>communityId(),'userId'=>$userId,'title'=>$title,'text'=>$text,'type'=>$type,'target'=>$target,'date'=>gmdate('c'),'read'=>false];
    writeJsonFile('community_notifications',$items);
}
function communityCommentPublic(array $c): array {
    $v=array_intersect_key($c,array_flip(['id','newsId','contentType','parentId','author','userId','text','status','date']));
    $likes=readJsonFile('comment_likes',[])[$c['id']]??[];$v['likes']=count($likes);$v['liked']=in_array($GLOBALS['communityViewerId']??null,$likes,true);return $v;
}
function communityPostPublic(array $p): array {return array_intersect_key($p,array_flip(['id','title','category','summary','content','image','images','author','userId','status','createdAt','updatedAt','publishedAt']));}
function communityFind(array $items,string $id): int {foreach($items as $i=>$x)if(($x['id']??'')===$id)return $i;jsonResponse(['error'=>'Élément introuvable.'],404);}
function communityProof(string $id,string $owner): void {if($id!==''&&(readJsonFile('proof_files',[])[$id]['ownerId']??null)!==$owner)jsonResponse(['error'=>'Pièce jointe invalide.'],422);}
function communityMigrate(): void {
    global $dataDir;$m=(array)readJsonFile('migrations',[]);$key='2026-10-04-community-v1';if(isset($m[$key]))return;
    $dir=$dataDir.'/backups/before-'.$key;if(!is_dir($dir)&&!mkdir($dir,0750,true))jsonResponse(['error'=>'Sauvegarde migration indisponible.'],503);
    foreach(['state','users','submissions','admins'] as $n){$p=$dataDir.'/'.$n.'.json';if(is_file($p)&&!is_file($dir.'/'.$n.'.json')&&!copy($p,$dir.'/'.$n.'.json'))jsonResponse(['error'=>'Sauvegarde impossible.'],503);}
    $s=(array)readJsonFile('state',[]);
    foreach($s['COMMENTS']??[] as $i=>$c)if(($c['status']??'')==='pending')$s['COMMENTS'][$i]['status']='validated';
    $s['BADGE_SETTINGS']=array_replace(['widthMm'=>95,'heightMm'=>135],(array)($s['BADGE_SETTINGS']??[]));
    writeJsonFile('state',$s);
    foreach(['blog_posts','blog_categories','comment_reports','feedback_messages','badge_templates','badge_print_jobs'] as $n)if(!is_file($dataDir.'/'.$n.'.json'))writeJsonFile($n,[]);
    $m[$key]=['appliedAt'=>gmdate('c'),'backup'=>'backups/before-'.$key];writeJsonFile('migrations',$m);
    activityJournal('Migration communauté appliquée',['version'=>$key]);
}
communityMigrate();

if($route==='devices'&&in_array($method,['POST','DELETE'],true)){
    $u=authenticatedUser();$b=requestJson();$token=communityText($b,'token',512,true);$platform=$b['platform']??'ios';$environment=$b['environment']??'production';
    if($platform!=='ios'||!preg_match('/^[a-f0-9]{32,512}$/i',$token)||!in_array($environment,['development','production'],true))jsonResponse(['error'=>'Appareil invalide.'],422);
    $ds=readJsonFile('push_devices',[]);$key=hash('sha256',$token);
    if($method==='DELETE'){if(isset($ds[$key])&&$ds[$key]['userId']===$u['id'])unset($ds[$key]);}
    else $ds[$key]=['token'=>$token,'platform'=>$platform,'environment'=>$environment,'userId'=>$u['id'],'updatedAt'=>gmdate('c'),'sessionHash'=>hash('sha256',substr(communityToken(),7))];writeJsonFile('push_devices',$ds);jsonResponse(['ok'=>true]);
}
function communityFilter(string $text): void {
    $words=readJsonFile('state',[])['SETTINGS']['communityBlockedWords']??[];
    foreach((array)$words as $word)if(is_string($word)&&trim($word)!==''&&mb_stripos($text,trim($word))!==false)jsonResponse(['error'=>'Ce contenu contient une expression interdite par les règles de la communauté.'],422);
    if(preg_match_all('~https?://~i',$text)>5)jsonResponse(['error'=>'Réduisez le nombre de liens dans ce message.'],422);
}
if($route==='admin/slider'){
    requirePermission('SLIDER',$method!=='GET');$s=readJsonFile('state',[]);if($method==='GET')jsonResponse($s['SETTINGS']['homeSlides']??[]);
    if($method==='PUT'){$b=requestJson();$slides=$b['slides']??null;if(!is_array($slides)||count($slides)>30)jsonResponse(['error'=>'Diapositives invalides.'],422);$clean=[];foreach($slides as $x){$v=[];foreach(['title'=>200,'subtitle'=>180,'image'=>2000,'targetType'=>20,'targetId'=>120,'url'=>2000] as $k=>$max)$v[$k]=communityText($x,$k,$max,$k==='title');if(!in_array($v['targetType'],['news','event','blog','page','url'],true)||!validPublicUrl($v['image'],true)||($v['targetType']==='url'&&(!filter_var($v['url'],FILTER_VALIDATE_URL)||parse_url($v['url'],PHP_URL_SCHEME)!=='https')))jsonResponse(['error'=>'Destination ou image invalide.'],422);if($v['targetType']==='page'&&!in_array($v['targetId'],['accueil','programme','actualites','blog','feedback','decouvrir','tourisme','boutique','histoire','dictionnaire','profil'],true))jsonResponse(['error'=>'Page interne invalide.'],422);$clean[]=$v;}$s['SETTINGS']['homeSlides']=$clean;writeJsonFile('state',$s);activityJournal('Slider modifié');jsonResponse(['ok'=>true]);}
}

if($route==='users/logout'&&$method==='POST'){
    authenticatedUser();$h=communityToken();$sessionHash=hash('sha256',substr($h,7));$sessions=readJsonFile('user_sessions',[]);unset($sessions[$sessionHash]);writeJsonFile('user_sessions',$sessions);$devices=readJsonFile('push_devices',[]);$devices=array_filter($devices,fn($d)=>($d['sessionHash']??'')!==$sessionHash);writeJsonFile('push_devices',$devices);jsonResponse(['ok'=>true]);
}
if($route==='comments'){
    $s=(array)readJsonFile('state',[]);$items=$s['COMMENTS']??[];
    if($method==='GET'){
        if(communityToken()!=='')$GLOBALS['communityViewerId']=authenticatedUser()['id'];
        $id=cleanText($_GET['newsId']??'',120);$type=cleanText($_GET['contentType']??'news',10);communityContent($type,$id);
        jsonResponse(array_values(array_map('communityCommentPublic',array_filter($items,fn($c)=>(($c['status']??'')==='validated')&&($c['newsId']??'')===$id&&($c['contentType']??'news')===$type))));
    }
    if($method==='POST'){
        $u=authenticatedUser();$b=requestJson();$text=communityText($b,'text',2000,true);$id=communityText($b,'newsId',120,true);$type=communityText($b,'contentType',10)?:'news';communityContent($type,$id);
        $parent=communityText($b,'parentId',80);if($parent!==''){$p=$items[communityFind($items,$parent)];if(($p['newsId']??'')!==$id||($p['contentType']??'news')!==$type||($p['status']??'')!=='validated')jsonResponse(['error'=>'Réponse à un commentaire indisponible.'],422);}
        communityFilter($text);communityLimit('comments',$u['id'],60);
        $c=['id'=>communityId(),'newsId'=>$id,'contentType'=>$type,'parentId'=>$parent,'author'=>$u['name'],'userId'=>$u['id'],'text'=>$text,'status'=>'validated','date'=>gmdate('c')];$s['COMMENTS'][]=$c;writeJsonFile('state',$s);
        if($parent!==''&&!empty($p['userId'])&&$p['userId']!==$u['id'])communityNotification($p['userId'],'Réponse à votre commentaire',$u['name'].' vous a répondu.',$type,$id);
        jsonResponse(['ok'=>true,'comment'=>communityCommentPublic($c)],201);
    }
}
if(preg_match('~^comments/([a-f0-9]+)/(?<action>like|report)$~',$route,$m)&&in_array($method,['POST','DELETE'],true)){
    $u=authenticatedUser();$cs=readJsonFile('state',[])['COMMENTS']??[];$c=$cs[communityFind($cs,$m[1])];if(($c['status']??'')!=='validated')jsonResponse(['error'=>'Commentaire indisponible.'],404);communityContent($c['contentType']??'news',$c['newsId']);
    if($m['action']==='like'){$likes=(array)readJsonFile('comment_likes',[]);$set=$likes[$c['id']]??[];$set=array_values(array_diff($set,[$u['id']]));if($method==='POST')$set[]=$u['id'];$likes[$c['id']]=$set;writeJsonFile('comment_likes',$likes);jsonResponse(['likes'=>count($set),'liked'=>$method==='POST']);}
    if($method==='POST'){$b=requestJson();$reason=communityText($b,'reason',80,true);if(!in_array($reason,REPORT_REASONS,true))jsonResponse(['error'=>'Motif invalide.'],422);$reports=readJsonFile('comment_reports',[]);foreach($reports as $r)if($r['commentId']===$c['id']&&$r['userId']===$u['id']&&$r['status']==='new')jsonResponse(['error'=>'Déjà signalé.'],409);communityLimit('reports',$u['id'],20);$reports[]=['id'=>communityId(),'commentId'=>$c['id'],'userId'=>$u['id'],'reason'=>$reason,'details'=>communityText($b,'details',1000),'status'=>'new','date'=>gmdate('c')];writeJsonFile('comment_reports',$reports);jsonResponse(['ok'=>true],201);}
}
if($route==='admin/comments'){
    requirePermission('COMMENTS',$method!=='GET');$s=readJsonFile('state',[]);$cs=$s['COMMENTS']??[];
    if($method==='GET'){$q=mb_strtolower(cleanText($_GET['q']??'',160));jsonResponse(array_values(array_filter($cs,fn($c)=>$q===''||str_contains(mb_strtolower(implode(' ',[$c['text'],$c['author'],$c['userId']??'',$c['newsId']??''])),$q))));}
    $b=requestJson();$ids=$b['ids']??[$b['id']??''];if(!is_array($ids)||!$ids||count($ids)>200)jsonResponse(['error'=>'Sélection invalide.'],422);foreach($ids as $id)communityFind($cs,(string)$id);
    $status=$b['status']??'hidden';if(!in_array($status,['hidden','validated'],true))jsonResponse(['error'=>'Statut invalide.'],422);
    if($method==='DELETE')$cs=array_values(array_filter($cs,fn($c)=>!in_array($c['id'],$ids,true)));elseif($method==='PATCH'){foreach($cs as &$c)if(in_array($c['id'],$ids,true))$c['status']=$status;unset($c);}else jsonResponse(['error'=>'Méthode invalide.'],405);
    $s['COMMENTS']=$cs;writeJsonFile('state',$s);activityJournal($method==='DELETE'?'Commentaires supprimés':'Visibilité commentaires modifiée',['ids'=>$ids,'status'=>$status]);jsonResponse(['ok'=>true]);
}
if($route==='admin/reports'){
    requirePermission('REPORTS',$method!=='GET');$rs=readJsonFile('comment_reports',[]);
    if($method==='GET'){ $cs=readJsonFile('state',[])['COMMENTS']??[];foreach($rs as &$r){$r['comment']=null;foreach($cs as $c)if($c['id']===$r['commentId'])$r['comment']=$c;}unset($r);jsonResponse($rs);}
    if($method==='PATCH'){$b=requestJson();$i=communityFind($rs,communityText($b,'id',80,true));if(!in_array($b['status']??'',['reviewed','closed'],true))jsonResponse(['error'=>'Statut invalide.'],422);$rs[$i]['status']=$b['status'];writeJsonFile('comment_reports',$rs);activityJournal('Signalement traité',['id'=>$b['id']]);jsonResponse(['ok'=>true]);}
}
if(preg_match('~^admin/users/([^/]+)/(block|unblock|suspend)$~',$route,$m)&&$method==='POST'){
    requirePermission('users',true);$us=readJsonFile('users',[]);$i=communityFind($us,$m[1]);$us[$i]['status']=['block'=>'blocked','unblock'=>'active','suspend'=>'suspended'][$m[2]];writeJsonFile('users',$us);activityJournal('Statut utilisateur modifié',['id'=>$m[1],'status'=>$us[$i]['status']]);jsonResponse(['ok'=>true]);
}
// Personal blocking required for safe community use; it does not change the account's global status.
if($route==='users/blocked'){
    $u=authenticatedUser();$all=readJsonFile('user_blocks',[]);$list=$all[$u['id']]??[];
    if($method==='GET')jsonResponse($list);
    if(in_array($method,['POST','DELETE'],true)){$b=requestJson();$id=communityText($b,'userId',80,true);communityFind(readJsonFile('users',[]),$id);if($id===$u['id'])jsonResponse(['error'=>'Utilisateur invalide.'],422);$list=array_values(array_diff($list,[$id]));if($method==='POST')$list[]=$id;$all[$u['id']]=$list;writeJsonFile('user_blocks',$all);jsonResponse($list);}
}
if($route==='blog/categories'&&$method==='GET')jsonResponse(readJsonFile('blog_categories',[])?:['Culture','Traditions','Grand-Bassam','Communauté','Autre']);
if($route==='blog'&&$method==='GET')jsonResponse(array_values(array_map('communityPostPublic',array_filter(readJsonFile('blog_posts',[]),fn($p)=>$p['status']==='published'))));
if($route==='my-articles'&&$method==='GET'){$u=authenticatedUser();jsonResponse(array_values(array_filter(readJsonFile('blog_posts',[]),fn($p)=>$p['userId']===$u['id'])));}
if($route==='blog'&&$method==='POST'||preg_match('~^blog/([a-f0-9]+)$~',$route,$m)&&$method==='PUT'){
    $u=authenticatedUser();$b=requestJson();$ps=readJsonFile('blog_posts',[]);$old=null;$index=null;
    if($method==='PUT'){$index=communityFind($ps,$m[1]);$old=$ps[$index];if($old['userId']!==$u['id'])jsonResponse(['error'=>'Accès interdit.'],403);if(!in_array($old['status'],['draft','rejected'],true))jsonResponse(['error'=>'Cet article ne peut plus être modifié.'],409);}
    $p=['id'=>$old['id']??communityId(),'title'=>communityText($b,'title',200,true),'category'=>communityText($b,'category',100,true),'summary'=>communityText($b,'summary',600),'content'=>communityText($b,'content',30000,true),'image'=>communityText($b,'image',2000),'images'=>[],'author'=>$u['name'],'userId'=>$u['id'],'status'=>'draft','createdAt'=>$old['createdAt']??gmdate('c'),'updatedAt'=>gmdate('c')];
    if(!validPublicUrl($p['image'],true))jsonResponse(['error'=>'Image invalide.'],422);$imgs=$b['images']??[];if(!is_array($imgs)||count($imgs)>8)jsonResponse(['error'=>'Images invalides.'],422);foreach($imgs as $im){if(!is_string($im)||!validPublicUrl($im,true)||strlen($im)>2000)jsonResponse(['error'=>'Image invalide.'],422);$p['images'][]=$im;}
    communityLimit('blog',$u['id'],30);if($index===null)$ps[]=$p;else $ps[$index]=$p;writeJsonFile('blog_posts',$ps);jsonResponse(['post'=>$p],$method==='POST'?201:200);
}
if(preg_match('~^blog/([a-f0-9]+)/submit$~',$route,$m)&&$method==='POST'){
    $u=authenticatedUser();$ps=readJsonFile('blog_posts',[]);$i=communityFind($ps,$m[1]);if($ps[$i]['userId']!==$u['id'])jsonResponse(['error'=>'Accès interdit.'],403);if(!in_array($ps[$i]['status'],['draft','rejected'],true))jsonResponse(['error'=>'Article déjà envoyé.'],409);$ps[$i]['status']='pending';$ps[$i]['updatedAt']=gmdate('c');writeJsonFile('blog_posts',$ps);jsonResponse(['post'=>$ps[$i]]);
}
if(preg_match('~^blog/([a-f0-9]+)/like$~',$route,$m)&&in_array($method,['GET','POST','DELETE'],true)){
    communityContent('blog',$m[1]);$likes=readJsonFile('post_likes',[]);$set=$likes[$m[1]]??[];$liked=false;
    if($method==='GET'&&communityToken()!=='')$liked=in_array(authenticatedUser()['id'],$set,true);
    if($method!=='GET'){$u=authenticatedUser();$set=array_values(array_diff($set,[$u['id']]));if($method==='POST')$set[]=$u['id'];$likes[$m[1]]=$set;writeJsonFile('post_likes',$likes);$liked=$method==='POST';}jsonResponse(['likes'=>count($set),'liked'=>$liked]);
}
if($route==='admin/blog'||preg_match('~^admin/blog/([a-f0-9]+)/(approve|reject|archive)$~',$route,$m)){
    requirePermission('BLOG',$method!=='GET');$ps=readJsonFile('blog_posts',[]);if($route==='admin/blog'&&$method==='GET')jsonResponse($ps);
    $b=requestJson();$id=$route==='admin/blog'?communityText($b,'id',80,true):$m[1];$i=communityFind($ps,$id);
    if($method==='DELETE')array_splice($ps,$i,1);
    elseif($method==='PUT'){foreach(['title'=>200,'category'=>100,'summary'=>600,'content'=>30000] as $k=>$max)if(isset($b[$k]))$ps[$i][$k]=communityText($b,$k,$max,in_array($k,['title','category','content'],true));$ps[$i]['updatedAt']=gmdate('c');}
    elseif($method==='POST'&&isset($m[2])){$action=$m[2];if($action==='approve'&&!in_array($ps[$i]['status'],['pending','archived'],true))jsonResponse(['error'=>'Envoyez le brouillon pour validation avant publication.'],409);$ps[$i]['status']=['approve'=>'published','reject'=>'rejected','archive'=>'archived'][$action];$ps[$i]['rejectionReason']=$action==='reject'?communityText($b,'reason',1000):'';if($action==='approve')$ps[$i]['publishedAt']=gmdate('c');$ps[$i]['updatedAt']=gmdate('c');if($action!=='archive')communityNotification($ps[$i]['userId'],$action==='approve'?'Article Blog accepté':'Article Blog refusé',$ps[$i]['title'].($ps[$i]['rejectionReason']?' — '.$ps[$i]['rejectionReason']:''),'blog',$id);}
    else jsonResponse(['error'=>'Méthode invalide.'],405);
    writeJsonFile('blog_posts',$ps);activityJournal('Article Blog : '.($m[2]??strtolower($method)),['id'=>$id]);jsonResponse(['ok'=>true]);
}
if($route==='feedback'&&$method==='POST'){
    $u=communityToken()!==''?authenticatedUser():['id'=>'guest:'.hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown')];$b=requestJson();$data=[];foreach(['name'=>160,'firstName'=>160,'phone'=>40,'email'=>180,'category'=>80,'subject'=>200,'message'=>10000,'proofId'=>80] as $k=>$max)$data[$k]=communityText($b,$k,$max,in_array($k,['name','firstName','phone','category','subject','message'],true));
    if(!in_array($data['category'],FEEDBACK_CATEGORIES,true)||!preg_match('/^[+0-9 ()-]{8,40}$/',$data['phone'])||($data['email']!==''&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL)))jsonResponse(['error'=>'Catégorie ou coordonnées invalides.'],422);
    communityProof($data['proofId'],$u['id']);communityLimit('feedback',$u['id'],12);$items=readJsonFile('submissions',[]);$item=['id'=>communityId(),'type'=>'contact','channel'=>'feedback','userId'=>$u['id'],'status'=>'new','data'=>$data,'createdAt'=>gmdate('c')];$items[]=$item;writeJsonFile('submissions',$items);jsonResponse(['feedback'=>$item],201);
}
if(in_array($route,['my-feedback','admin/feedback'],true)&&$method==='GET'){
    $isAdmin=$route==='admin/feedback';$u=$isAdmin?requirePermission('FEEDBACK'):authenticatedUser();$messages=readJsonFile('feedback_messages',[]);$items=array_values(array_filter(readJsonFile('submissions',[]),fn($s)=>($s['type']??'')==='contact'&&($isAdmin||($s['userId']??'')===$u['id'])));
    foreach($items as &$s)$s['messages']=array_values(array_filter($messages,fn($m)=>$m['feedbackId']===$s['id']));unset($s);jsonResponse($items);
}
if(preg_match('~^(admin/feedback|feedback)/([a-f0-9]+)/reply$~',$route,$m)&&$method==='POST'){
    $isAdmin=$m[1]==='admin/feedback';$u=$isAdmin?requirePermission('FEEDBACK',true):authenticatedUser();$b=requestJson();$items=readJsonFile('submissions',[]);$i=communityFind($items,$m[2]);$s=$items[$i];if($s['type']!=='contact'||(!$isAdmin&&($s['userId']??'')!==$u['id']))jsonResponse(['error'=>'Accès interdit.'],403);
    if($s['status']==='closed')jsonResponse(['error'=>'Demande fermée.'],409);$text=communityText($b,'message',10000,true);$msgs=readJsonFile('feedback_messages',[]);$msgs[]=['id'=>communityId(),'feedbackId'=>$s['id'],'author'=>$isAdmin?$u['username']:$u['name'],'admin'=>$isAdmin,'message'=>$text,'date'=>gmdate('c')];writeJsonFile('feedback_messages',$msgs);$items[$i]['status']=$isAdmin?'replied':'new';writeJsonFile('submissions',$items);if($isAdmin){if(!empty($s['userId']))communityNotification($s['userId'],'Réponse à votre demande',$s['data']['subject']??'Votre avis compte','feedback',$s['id']);activityJournal('Réponse à une demande',['id'=>$s['id']]);}jsonResponse(['ok'=>true],201);
}
if($route==='admin/feedback'&&$method==='PATCH'){
    requirePermission('FEEDBACK',true);$b=requestJson();$items=readJsonFile('submissions',[]);$i=communityFind($items,communityText($b,'id',80,true));if($items[$i]['type']!=='contact'||!in_array($b['status']??'',['new','read','in_progress','replied','closed'],true))jsonResponse(['error'=>'Statut invalide.'],422);$items[$i]['status']=$b['status'];writeJsonFile('submissions',$items);activityJournal('Statut demande modifié',['id'=>$items[$i]['id'],'status'=>$b['status']]);jsonResponse(['ok'=>true]);
}
if($route==='feedback/attachment'&&$method==='GET'){
    $id=cleanText($_GET['id']??'',80);$proof=readJsonFile('proof_files',[])[$id]??null;
    if(!preg_match('/^[a-f0-9]{32}$/',$id)||!$proof)jsonResponse(['error'=>'Pièce introuvable.'],404);
    if(communityToken()!==''){$u=authenticatedUser();if($proof['ownerId']!==$u['id'])jsonResponse(['error'=>'Accès interdit.'],403);}else requirePermission('FEEDBACK');
    $path=$dataDir.'/proofs/'.$id.'.'.$proof['ext'];if(!is_file($path))jsonResponse(['error'=>'Fichier introuvable.'],404);header('Content-Type: '.$proof['mime']);header('Content-Security-Policy: sandbox');header('Content-Disposition: attachment; filename=piece-jointe.'.$proof['ext']);readfile($path);exit;
}
if($route==='notifications'){
    $u=authenticatedUser();$ns=readJsonFile('community_notifications',[]);if($method==='GET')jsonResponse(array_values(array_filter($ns,fn($n)=>$n['userId']===$u['id']||$n['userId']==='*')));
    if($method==='PATCH'){$b=requestJson();$i=communityFind($ns,communityText($b,'id',80,true));if($ns[$i]['userId']!==$u['id'])jsonResponse(['error'=>'Accès interdit.'],403);$ns[$i]['read']=true;writeJsonFile('community_notifications',$ns);jsonResponse(['ok'=>true]);}
}
if($route==='admin/notifications'){
    requirePermission('NOTIFICATIONS',$method!=='GET');if($method==='GET')jsonResponse(array_values(array_filter(readJsonFile('community_notifications',[]),fn($n)=>$n['userId']==='*')));
    if($method==='POST'){$b=requestJson();$type=communityText($b,'type',20,true);$target=communityText($b,'target',120,true);communityContent($type,$target);communityNotification('*',communityText($b,'title',200,true),communityText($b,'text',1000,true),$type,$target);activityJournal('Notification officielle publiée',['target'=>$target]);jsonResponse(['ok'=>true],201);}
}
if($route==='admin/community-stats'&&$method==='GET'){
    $a=requireAdmin();$stats=[];$s=readJsonFile('state',[]);
    if(adminHas($a,'users')){$us=readJsonFile('users',[]);$stats+=['Utilisateurs inscrits'=>count($us),'Utilisateurs actifs'=>count(array_filter($us,fn($u)=>$u['status']==='active')),'Utilisateurs bloqués'=>count(array_filter($us,fn($u)=>$u['status']==='blocked'))];}
    if(adminHas($a,'COMMENTS'))$stats['Commentaires']=count($s['COMMENTS']??[]);
    if(adminHas($a,'BLOG')){$ps=readJsonFile('blog_posts',[]);$stats+=['Articles Blog'=>count($ps),'Blog en attente'=>count(array_filter($ps,fn($p)=>$p['status']==='pending'))];}
    if(adminHas($a,'FEEDBACK'))$stats['Nouveaux avis']=count(array_filter(readJsonFile('submissions',[]),fn($s)=>$s['type']==='contact'&&in_array($s['status'],['new','pending'],true)));
    foreach(['NEWS'=>'Actualités','EVENTS'=>'Événements'] as $k=>$label)if(adminHas($a,$k))$stats[$label]=count($s[$k]??[]);
    if(adminHas($a,'BADGES_PRINT'))$stats+=['Badges disponibles'=>count(workshopCredentials()),'Travaux d’impression'=>count(readJsonFile('badge_print_jobs',[])),'Exemplaires confirmés imprimés'=>array_sum(array_column(readJsonFile('credential_prints',[]),'count'))];jsonResponse($stats);
}
