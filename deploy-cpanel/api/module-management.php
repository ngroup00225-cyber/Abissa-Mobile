<?php
function specialCredential(string $id): ?array {
    foreach((array)readJsonFile('special_profiles',[]) as $p)if(($p['id']??'')===$id&&($p['active']??false))return ['id'=>$id,'type'=>'member','status'=>'validated','source'=>'special','data'=>['name'=>$p['name'],'role'=>$p['role'],'commission'=>$p['category'],'photo'=>$p['photo'],'poleId'=>'special','commissionId'=>'special','commissionColor'=>$p['color'],'commissionIcon'=>'ri-vip-crown-line']];
    return null;
}
if($route==='admin/navigation'){
    if($method==='GET'){requireAdmin();jsonResponse((object)readJsonFile('admin_navigation',[]));}
    if($method==='PUT'){
        requirePermission('MENU_ITEMS',true);$body=requestJson();$result=[];
        foreach(['application','website'] as $space){$result[$space]=[];$seen=[];foreach(array_slice((array)($body[$space]??[]),0,100) as $item){$key=cleanText($item['key']??'',50);$label=cleanText($item['label']??'',60);if(!preg_match('/^[A-Za-z_]+$/',$key)||!$label||isset($seen[$key]))jsonResponse(['error'=>'Nom ou module invalide.'],422);$result[$space][]=['key'=>$key,'label'=>$label];$seen[$key]=true;}}
        writeJsonFile('admin_navigation',$result);jsonResponse(['ok'=>true]);
    }
}
if($route==='admin/special-profiles'){
    requirePermission('BADGES_MANAGE',$method!=='GET');
    if($method==='GET')jsonResponse((array)readJsonFile('special_profiles',[]));
    if($method==='POST'||$method==='PUT'){
        $body=requestJson();$items=(array)readJsonFile('special_profiles',[]);$p=['id'=>$method==='POST'?'special-'.bin2hex(random_bytes(10)):cleanText($body['id']??'',80)];
        foreach(['name'=>160,'role'=>180,'category'=>120,'phone'=>40,'photo'=>2000] as $key=>$max)$p[$key]=cleanText($body[$key]??'',$max);
        if(!$p['name']||!$p['role'])jsonResponse(['error'=>'Renseignez le nom et la fonction.'],422);
        $p['category']=$p['category']?:'Profils spéciaux';$p['color']=preg_match('/^#[0-9a-fA-F]{6}$/',(string)($body['color']??''))?$body['color']:'#b98b20';$p['active']=($body['active']??true)===true;$p['updatedAt']=gmdate('c');
        if($p['photo']!==''&&!preg_match('~^(?:https?://[^/]+)?/appli/uploads/[a-f0-9]{32}\.(?:png|jpg|webp|gif)$~',$p['photo']))jsonResponse(['error'=>'Importez la photo depuis votre appareil.'],422);
        if($method==='POST')$items[]=$p;else{$found=false;foreach($items as &$item)if(($item['id']??'')===$p['id']){$item=$p;$found=true;}unset($item);if(!$found)jsonResponse(['error'=>'Profil introuvable.'],404);}
        writeJsonFile('special_profiles',$items);jsonResponse($p,$method==='POST'?201:200);
    }
}
