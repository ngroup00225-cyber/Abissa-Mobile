<?php
function qrColorLuminance(string $color): float {
    $rgb=[];
    foreach ([1,3,5] as $offset) { $v=hexdec(substr($color,$offset,2))/255; $rgb[]=$v<=0.04045?$v/12.92:pow(($v+0.055)/1.055,2.4); }
    return $rgb[0]*0.2126+$rgb[1]*0.7152+$rgb[2]*0.0722;
}
if ($route==='admin/qrcodes') {
    requirePermission('QRCODES', $method!=='GET');
    $items=(array)readJsonFile('qrcodes',[]);
    if ($method==='GET') jsonResponse(array_values($items));
    if ($method==='DELETE') {
        $id=cleanText($_GET['id']??'',40);
        $items=array_values(array_filter($items,fn($i)=>$i['id']!==$id));
        writeJsonFile('qrcodes',$items); jsonResponse(['ok'=>true]);
    }
    if (in_array($method,['POST','PUT'],true)) {
        $body=requestJson(); $id=cleanText($body['id']??'',40); $old=null;
        foreach ($items as $item) if ($item['id']===$id) $old=$item;
        if ($method==='PUT' && !$old) jsonResponse(['error'=>'QR Code introuvable'],404);
        $kind=cleanText($body['kind']??'url',10);
        if (!in_array($kind,['url','text'],true) || ($old && $kind!==$old['kind'])) jsonResponse(['error'=>'Type de QR Code invalide'],422);
        $title=cleanText($body['title']??'',100); $content=trim((string)($body['content']??''));
        if (!$title || !$content || strlen($content)>1200) jsonResponse(['error'=>'Titre et contenu requis (1 200 octets maximum).'],422);
        if ($kind==='url' && (!filter_var($content,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($content,PHP_URL_SCHEME)?:''),['http','https'],true) || parse_url($content,PHP_URL_USER))) jsonResponse(['error'=>'Saisissez une adresse HTTP ou HTTPS valide.'],422);
        $dark=(string)($body['dark']??'#073e59'); $light=(string)($body['light']??'#ffffff');
        if (!preg_match('/^#[a-f0-9]{6}$/i',$dark) || !preg_match('/^#[a-f0-9]{6}$/i',$light) || (qrColorLuminance($light)+0.05)/(qrColorLuminance($dark)+0.05)<4.5) jsonResponse(['error'=>'Choisissez un code foncé sur un fond clair, avec un contraste suffisant.'],422);
        $size=(int)($body['size']??1024);
        if (!in_array($size,[512,1024,2048],true)) jsonResponse(['error'=>'Taille invalide'],422);
        $item=['id'=>$old['id']??bin2hex(random_bytes(12)),'title'=>$title,'kind'=>$kind,'content'=>$content,'dark'=>$dark,'light'=>$light,'size'=>$size,'caption'=>cleanText($body['caption']??'',100),'active'=>($body['active']??true)!==false,'createdAt'=>$old['createdAt']??gmdate('c'),'updatedAt'=>gmdate('c')];
        if ($old) foreach ($items as &$entry) { if ($entry['id']===$id) $entry=$item; }
        else $items[]=$item;
        writeJsonFile('qrcodes',$items); jsonResponse(['item'=>$item],$old?200:201);
    }
    jsonResponse(['error'=>'Méthode non autorisée'],405);
}
