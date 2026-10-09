<?php $pageId='partenaires';$pageTitle='Partenaires';require __DIR__.'/includes/header.php';
$partners=activeItems('PARTNERS');
$groups=['official'=>array_values(array_filter($partners,fn($p)=>($p['partnerType']??'partner')==='official')),'partner'=>array_values(array_filter($partners,fn($p)=>($p['partnerType']??'partner')!=='official'))];
?>
<section class="page-hero compact"><span class="kicker">Ils nous accompagnent</span><h1><?=e(pageHeading($website['partnersTitle']))?></h1><p><?=e(pageSubtitle('Institutions et entreprises engagées aux côtés d’Abissa Mobile.'))?></p><?=bannerLink()?></section>
<section class="section">
<?php foreach(['official'=>'Partenaires officiels','partner'=>'Partenaires'] as $type=>$label):if(!$groups[$type])continue;?>
<section class="partner-section" data-partner-type="<?=e($type)?>"><h2><?=e($label)?></h2><div class="partner-grid">
<?php foreach($groups[$type] as $item):?><article><img src="<?=e(imageOf($item,'/assets/images/abissa-2026.png'))?>" alt="Logo <?=e($item['name']??'du partenaire')?>" loading="lazy"><div><span><?=e($type==='official'?'Partenaire officiel':($item['category']??'Partenaire'))?></span><h3><?=e($item['name']??'Partenaire')?></h3><p class="content-paragraph"><?=e($item['desc']??'')?></p><?php if($url=publicUrl($item['website']??'')):?><a href="<?=e($url)?>" rel="noopener noreferrer" target="_blank">Visiter le site ↗</a><?php endif;?></div></article><?php endforeach;?></div></section>
<?php endforeach;?>
<?php if(!$partners):?><p class="site-empty">Les partenaires seront publiés prochainement.</p><?php endif;?></section>
<?php require __DIR__.'/includes/footer.php';?>
