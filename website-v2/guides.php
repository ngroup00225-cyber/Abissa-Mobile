<?php
$pageId='guides'; $pageTitle='Guide touristique'; require __DIR__.'/includes/header.php';
$guides=moduleEnabled('guides') ? array_values(array_filter((array)($state['SETTINGS']['touristGuides']??[]),fn($guide)=>is_array($guide)&&($guide['active']??true)!==false)) : [];
?>
<section class="page-hero compact"><span class="kicker">Découvrir Grand-Bassam</span><h1><?=e(pageHeading('Guide touristique'))?></h1><p><?=e(pageSubtitle('Rencontrez les guides disponibles pour découvrir l’Abissa et le patrimoine de Grand-Bassam.'))?></p><?=bannerLink()?></section>
<section class="section"><div class="cards three">
<?php foreach($guides as $guide):
    $name=trim((string)($guide['name']??'').' '.(string)($guide['firstName']??''));
    $photo=imageOf($guide,''); $phone=phoneUrl($guide['phone']??'');
    $whatsapp=phoneUrl($guide['whatsapp']??' ',true); $email=emailUrl($guide['email']??'');
    $languages=$guide['languages']??''; if(is_array($languages))$languages=implode(', ',array_filter($languages,'is_string'));
    $zone=$guide['zone']??$guide['area']??'';
    $presentation=$guide['presentation']??$guide['description']??$guide['desc']??'';
?>
<article class="story-card guide-card">
<?php if($photo):?><img class="guide-photo" src="<?=e($photo)?>" alt="<?=e($name)?>" loading="lazy"><?php else:?><div class="guide-photo guide-initials" aria-hidden="true"><i class="ri-user-location-line"></i></div><?php endif;?>
<div><span>Guide touristique</span><h2><?=e($name)?></h2><?php if($presentation!==''):?><p class="content-paragraph"><?=e($presentation)?></p><?php endif;?>
<dl class="guide-meta"><?php foreach(['Langues parlées'=>$languages,'Zone d’intervention'=>$zone,'Disponibilité'=>$guide['availability']??'','Téléphone'=>$guide['phone']??'','E-mail'=>$guide['email']??''] as $label=>$value): if(!is_string($value)||trim($value)==='')continue;?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?></dl>
<div class="directory-actions"><?php if($phone):?><a href="<?=e($phone)?>"><i class="ri-phone-line" aria-hidden="true"></i> Appeler</a><?php endif;?><?php if($whatsapp):?><a href="<?=e($whatsapp)?>" target="_blank" rel="noopener noreferrer"><i class="ri-whatsapp-line" aria-hidden="true"></i> WhatsApp</a><?php endif;?><?php if($contact=$email?:$phone):?><a href="<?=e($contact)?>"><i class="ri-mail-line" aria-hidden="true"></i> Contacter</a><?php endif;?></div>
</div></article>
<?php endforeach;?></div><?php if(!$guides):?><p class="site-empty"><?=moduleEnabled('guides')?'Les fiches des guides touristiques seront publiées prochainement.':'Le guide touristique est actuellement indisponible.'?></p><?php endif;?></section>
<?php require __DIR__.'/includes/footer.php';?>
