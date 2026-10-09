<?php
$pageId='palais';$pageTitle='Palais Royal de Grand-Bassam';require __DIR__.'/includes/header.php';
$palace=moduleEnabled('palais')?abissaRoyalPalace((array)($state['SETTINGS']['royalPalace']??[])):[];
$title=trim((string)($palace['title']??''))?:'Palais Royal de Grand-Bassam';
function palace204Text(mixed $text): string { return is_string($text)&&trim($text)!==''?'<p class="palace204-text">'.e($text).'</p>':''; }
function palace204Photos(array $values,string $alt): string {
    $photos=[];foreach($values as $value){$url=publicUrl(is_array($value)?($value['url']??$value['image']??''):$value,true);if($url)$photos[$url]=$url;}
    if(!$photos)return '';
    $html='<div class="palace204-gallery">';foreach($photos as $url)$html.='<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" aria-label="Agrandir une photo"><img src="'.e($url).'" alt="'.e($alt).'" loading="lazy"></a>';
    return $html.'</div>';
}
function palace204Videos(array $values): string {
    $html='';foreach($values as $index=>$value){$url=publicUrl(is_array($value)?($value['url']??''):$value);if(!$url)continue;
        $html.=preg_match('~\.(mp4|webm|ogg)(?:[?#]|$)~i',$url)?'<video class="palace204-video" controls preload="metadata" aria-label="Vidéo du Palais Royal"><source src="'.e($url).'"></video>':'<a class="button navy" href="'.e($url).'" target="_blank" rel="noopener noreferrer">Voir la vidéo '.($index+1).' ↗</a>';
    }
    return $html?'<div class="palace204-videos">'.$html.'</div>':'';
}
function palace204Section(string $title,string $content,bool $open=false): string {
    return $content!==''?'<details class="palace204-section"'.($open?' open':'').'><summary>'.e($title).'<span aria-hidden="true">+</span></summary><div class="palace204-section-body">'.$content.'</div></details>':'';
}
$profile=(array)($palace['kingProfile']??[]);$biography=$profile['biography']??$palace['king']??'';$portrait=publicUrl($profile['image']??'',true);
$king='';if(($profile['active']??true)!==false&&($portrait||trim((string)($profile['name']??''))!==''||trim((string)($profile['reignTitle']??''))!==''||trim((string)$biography)!=='')){
    $king='<div class="palace204-king">'.($portrait?'<img src="'.e($portrait).'" alt="'.e($profile['name']??'Portrait du roi').'" loading="lazy">':'').'<div><span class="palace204-eyebrow">Royauté N’zima Kotoko</span>'.(!empty($profile['name'])?'<h3>'.e($profile['name']).'</h3>':'').(!empty($profile['reignTitle'])?'<p class="palace204-reign">'.e($profile['reignTitle']).'</p>':'').palace204Text($biography).'</div></div>';
}
$sections='';foreach(['history'=>'Histoire du Palais Royal','royaltyHistory'=>'Histoire de la royauté','traditions'=>'Traditions et cérémonies','architecture'=>'Architecture et patrimoine','authorities'=>'Autorités traditionnelles','culture'=>'Culture et transmission','visits'=>'Préparer votre visite'] as $key=>$label)$sections.=palace204Section($label,palace204Text($palace[$key]??''));
$articles='';foreach((array)($palace['articles']??[]) as $article){if(!is_array($article)||($article['active']??true)===false)continue;$articleTitle=trim((string)($article['title']??''))?:'Autour du Palais Royal';$articles.=palace204Section($articleTitle,palace204Text($article['text']??'').palace204Photos(array_merge([$article['image']??''],(array)($article['photos']??[])),$articleTitle).palace204Videos((array)($article['videos']??[])));}
$photos=palace204Photos((array)($palace['photos']??[]),$title);$videos=palace204Videos((array)($palace['videos']??[]));$image=imageOf($palace,'');$presentation=palace204Text($palace['presentation']??'');
$coordinates=coordinateTarget($palace);$phone=phoneUrl($palace['phone']??'');$email=emailUrl($palace['email']??'');$websiteUrl=publicUrl($palace['website']??'');
$hasContent=$presentation||$king||$sections||$articles||$image||$photos||$videos;
?>
<link rel="stylesheet" href="/appli/palace-204.css?v=208">
<section class="page-hero compact"><span class="kicker">Patrimoine N’zima Kotoko</span><h1><?=e(pageHeading($title))?></h1><p><?=e(pageSubtitle('Le roi, la royauté et le patrimoine du Palais Royal de Grand-Bassam.'))?></p><?=bannerLink()?></section>
<section class="section"><div class="palace204">
<?php if(moduleEnabled('palais')):?>
<section class="palace204-intro"><?php if($image):?><img class="palace204-cover" src="<?=e($image)?>" alt="<?=e($title)?>" loading="lazy"><?php endif;?><span class="palace204-eyebrow">Mémoire · Royauté · Patrimoine</span><h2><?=e($title)?></h2><?=$presentation?></section>
<?php if(!$hasContent):?><p class="site-empty">Les contenus du Palais Royal seront publiés par l’organisation.</p><?php endif;?>
<?=palace204Section('Le roi',$king,true)?><?=$sections?><?=$articles?><?=palace204Section('Galerie du Palais Royal',$photos)?><?=palace204Section('Vidéos',$videos)?>
<?php if($coordinates||!empty($palace['address'])||$phone||$email||$websiteUrl):?><details class="palace204-section" open><summary>Localisation et contacts<span aria-hidden="true">+</span></summary><div class="palace204-section-body"><?=palace204Text($palace['address']??'')?><div class="directory-actions palace204-actions"><?php if($coordinates):?><a href="<?=e('https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($coordinates))?>" target="_blank" rel="noopener noreferrer"><i class="ri-route-line" aria-hidden="true"></i> Itinéraire</a><?php endif;?><?php if($websiteUrl):?><a href="<?=e($websiteUrl)?>" target="_blank" rel="noopener noreferrer">Site du Palais Royal</a><?php endif;?><?php if($phone):?><a href="<?=e($phone)?>">Appeler</a><?php endif;?><?php if($email):?><a href="<?=e($email)?>">Contacter</a><?php endif;?></div><?php if($coordinates):?><iframe class="palace204-map" title="Carte du Palais Royal" src="<?=e('https://maps.google.com/maps?q='.rawurlencode($coordinates).'&z=16&output=embed')?>" loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe><?php endif;?></div></details><?php endif;?>
<?php else:?><p class="site-empty">Le module Palais Royal est actuellement indisponible.</p><?php endif;?></div></section>
<?php require __DIR__.'/includes/footer.php';?>
