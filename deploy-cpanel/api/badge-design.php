<?php

function badgePrintSettings(array $b): array {
    $s=readJsonFile('state',[])['BADGE_SETTINGS']??[];$out=[];
    foreach(['widthMm'=>95,'heightMm'=>135] as $k=>$fixed){if(isset($b[$k])&&(!is_numeric($b[$k])||(float)$b[$k]!== (float)$fixed))jsonResponse(['error'=>'Format fixe : 9,5 × 13,5 cm.'],422);$out[$k]=$fixed;}
    $margin=$b['marginMm']??$s['marginMm']??6;if(!is_numeric($margin)||!is_finite((float)$margin)||$margin<0||$margin>30)jsonResponse(['error'=>'Marge invalide.'],422);$out['marginMm']=(float)$margin;
    foreach(['orientation'=>['portrait','landscape'],'layout'=>['a4','badge']] as $k=>$values){$v=$b[$k]??$s[$k]??$values[0];if(!in_array($v,$values,true))jsonResponse(['error'=>'Paramètre invalide : '.$k],422);$out[$k]=$v;}
    // Member and press badges always use four 95 × 135 mm cells on A4.
    $out['orientation']='portrait';$out['layout']='a4';$out['marginMm']=6;$out['gapMm']=8;
    $w=$out['orientation']==='portrait'?210:297;$h=$out['orientation']==='portrait'?297:210;
    if($out['layout']==='a4'&&($out['widthMm']>$w-2*$out['marginMm']||$out['heightMm']>$h-2*$out['marginMm']))jsonResponse(['error'=>'Le badge ne tient pas sur la feuille avec ces marges.'],422);
    return $out;
}
if($route==='admin/badge-print-settings'){
    requireAnyPermission(['BADGES_PRINT','BADGES_MANAGE'],$method!=='GET');
    if($method==='GET')jsonResponse(badgePrintSettings([]));
    if($method==='PUT'){$out=badgePrintSettings(requestJson());$s=(array)readJsonFile('state',[]);$s['BADGE_SETTINGS']=array_replace((array)($s['BADGE_SETTINGS']??[]),$out);writeJsonFile('state',$s);jsonResponse($out);}
}

function badgeDesignValidate(mixed $b): array {
    if(!is_array($b))jsonResponse(['error'=>'Structure de modèle invalide.'],422);
    $result=['widthMm'=>95,'heightMm'=>135,'background'=>communityText($b,'background',2000),'backgroundColor'=>communityText($b,'backgroundColor',7)?:'#ffffff','elements'=>[]];
    if(!validPublicUrl($result['background'],true)||!preg_match('/^#[a-f0-9]{6}$/i',$result['backgroundColor']))jsonResponse(['error'=>'Fond invalide.'],422);
    if(!is_array($b['elements']??null)||count($b['elements'])>40)jsonResponse(['error'=>'Éléments invalides.'],422);
    foreach($b['elements'] as $e){
        if(!is_array($e)||!in_array($e['kind']??'',['text','image','qr','barcode'],true))jsonResponse(['error'=>'Type d’élément invalide.'],422);
        $v=['kind'=>$e['kind'],'text'=>communityText($e,'text',1000),'field'=>communityText($e,'field',20),'source'=>communityText($e,'source',2000)];
        if(!in_array($v['field'],['','name','firstName','role','commission','number','photo','logo'],true)||!validPublicUrl($v['source'],true))jsonResponse(['error'=>'Champ ou image invalide.'],422);
        foreach(['x'=>[0,95],'y'=>[0,135],'w'=>[2,95],'h'=>[2,135],'size'=>[6,80]] as $k=>$range){$n=$e[$k]??($k==='size'?18:null);if(!is_numeric($n)||$n<$range[0]||$n>$range[1])jsonResponse(['error'=>'Dimension invalide : '.$k],422);$v[$k]=(float)$n;}
        if($v['x']+$v['w']>95.01||$v['y']+$v['h']>135.01)jsonResponse(['error'=>'Un élément dépasse du badge.'],422);
        $v['color']=communityText($e,'color',7)?:'#003b93';$v['font']=$e['font']??'Arial';$v['align']=$e['align']??'center';$v['bold']=($e['bold']??true)===true;
        if(!preg_match('/^#[a-f0-9]{6}$/i',$v['color'])||!in_array($v['font'],['Arial','Georgia','Verdana'],true)||!in_array($v['align'],['left','center','right'],true))jsonResponse(['error'=>'Style invalide.'],422);
        $result['elements'][]=$v;
    }
    return $result;
}
if($route==='admin/badge-templates'){
    requirePermission('BADGE_TEMPLATES',$method!=='GET');$ts=readJsonFile('badge_templates',[]);if($method==='GET')jsonResponse($ts);
    $b=requestJson();if($method==='DELETE'){$i=communityFind($ts,communityText($b,'id',80,true));array_splice($ts,$i,1);}
    elseif(in_array($method,['POST','PUT'],true)){$t=['id'=>$method==='POST'?communityId():communityText($b,'id',80,true),'name'=>communityText($b,'name',160,true),'design'=>badgeDesignValidate($b['design']??[]),'updatedAt'=>gmdate('c')];if($method==='POST')$ts[]=$t;else $ts[communityFind($ts,$t['id'])]=$t;}
    else jsonResponse(['error'=>'Méthode invalide.'],405);
    writeJsonFile('badge_templates',$ts);activityJournal('Modèle badge : '.strtolower($method),['id'=>$b['id']??$t['id']??'']);jsonResponse(['ok'=>true,'template'=>$t??null],$method==='POST'?201:200);
}
if($route==='admin/badge-design'){
    requirePermission('BADGES_MANAGE',$method!=='GET');$id=cleanText($_GET['id']??'',80);$b=$method==='PUT'?requestJson():[];if($method==='PUT')$id=communityText($b,'id',80,true);$item=credentialRecord($id);if($item['type']==='pass')jsonResponse(['error'=>'Laissez-passer : utiliser son éditeur A4.'],422);
    $ds=readJsonFile('badge_designs',[]);if($method==='GET')jsonResponse(['design'=>$ds[$id]??null,'document'=>$item]);
    if($method==='PUT'){$design=badgeDesignValidate($b['design']??[]);$overrides=[];foreach(['name','firstName','role','commission','number','photo','logo'] as $k){$overrides[$k]=communityText($b['overrides']??[],$k,in_array($k,['photo','logo'],true)?2000:160);if(in_array($k,['photo','logo'],true)&&!validPublicUrl($overrides[$k],true))jsonResponse(['error'=>'Image invalide.'],422);}$ds[$id]=$design+['overrides'=>$overrides];writeJsonFile('badge_designs',$ds);activityJournal('Badge modifié',['id'=>$id]);jsonResponse(['ok'=>true]);}
}
if($route==='admin/badges/print-preview'&&$method==='POST'){
    requirePermission('BADGES_PRINT',true);$b=requestJson();$ps=badgePrintSettings($b);$items=$b['items']??[];$margin=$ps['marginMm'];$orientation=$ps['orientation'];$layout=$ps['layout'];$width=$ps['widthMm'];$height=$ps['heightMm'];
    if(!is_array($items)||!$items||count($items)>200||!is_numeric($margin)||$margin<0||$margin>30||!in_array($orientation,['portrait','landscape'],true)||!in_array($layout,['a4','badge'],true))jsonResponse(['error'=>'Paramètres d’impression invalides.'],422);
    $total=0;$ids=[];foreach($items as $i=>$it){if(!is_array($it)||!is_int($it['quantity']??null)||$it['quantity']<1||$it['quantity']>100)jsonResponse(['error'=>'Quantité : entier de 1 à 100.'],422);$doc=credentialRecord(communityText($it,'id',80,true));if($doc['type']==='pass')jsonResponse(['error'=>'Imprimez les laissez-passer séparément.'],422);if(in_array($doc['id'],$ids,true))jsonResponse(['error'=>'Badge dupliqué dans la sélection.'],422);$ids[]=$doc['id'];$total+=$it['quantity'];$items[$i]=['id'=>$doc['id'],'quantity'=>$it['quantity']];}
    if($total>500)jsonResponse(['error'=>'500 exemplaires maximum par travail.'],422);
    $gap=$ps['gapMm'];$w=$orientation==='portrait'?210:297;$h=$orientation==='portrait'?297:210;$cols=(int)floor(($w-2*$margin+$gap)/($width+$gap));$rows=(int)floor(($h-2*$margin+$gap)/($height+$gap));$per=$cols*$rows;if($per<1)jsonResponse(['error'=>'Marges incompatibles avec le format.'],422);
    jsonResponse(['items'=>$items,'total'=>$total,'widthMm'=>$width,'heightMm'=>$height,'orientation'=>$orientation,'marginMm'=>(float)$margin,'gapMm'=>$gap,'layout'=>$layout,'columns'=>$cols,'rows'=>$rows,'perPage'=>$per,'pages'=>(int)ceil($total/$per)]);
}
if($route==='admin/badge-print-jobs'){
    requirePermission('BADGES_PRINT',$method!=='GET');$jobs=readJsonFile('badge_print_jobs',[]);if($method==='GET')jsonResponse($jobs);
    if($method==='POST'){$b=requestJson();$ids=$b['items']??[];if(!is_array($ids)||!$ids||count($ids)>200)jsonResponse(['error'=>'Sélection invalide.'],422);$total=0;foreach($ids as $it){credentialRecord(communityText($it,'id',80,true));if(!is_int($it['quantity']??null)||$it['quantity']<1||$it['quantity']>100)jsonResponse(['error'=>'Quantité invalide.'],422);$total+=$it['quantity'];}if($total>500)jsonResponse(['error'=>'500 exemplaires maximum.'],422);$ps=badgePrintSettings($b);$job=$ps+['id'=>communityId(),'items'=>$ids,'total'=>$total,'date'=>gmdate('c'),'status'=>'prepared'];$jobs[]=$job;writeJsonFile('badge_print_jobs',$jobs);activityJournal('Impression préparée',['id'=>$job['id'],'total'=>$total]);jsonResponse(['job'=>$job],201);}
}
