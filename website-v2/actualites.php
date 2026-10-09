<?php $pageId='actualites';$pageTitle='Actualités';require __DIR__.'/includes/header.php'; ?>
<nav class="breadcrumbs" aria-label="Fil d’Ariane"><a href="/abissa.php">L’Abissa</a><span aria-hidden="true"> / </span><span><?=e($pageTitle)?></span></nav>
<section class="page-hero compact"><span class="kicker">Journal</span><h1><?=e(pageHeading($website['newsTitle']))?></h1><p><?=e(pageSubtitle('Les annonces, coulisses et informations officielles d’Abissa Mobile.'))?></p><?=bannerLink()?></section>
<section class="section"><div class="cards three"><?php foreach(activeItems('NEWS') as $item): ?><article class="story-card"><div class="card-image" style="background-image:<?=e(cssImage(imageOf($item)))?>"></div><div><span><?=e($item['date']??'Actualité')?></span><h3><?=e($item['title']??'Abissa Mobile')?></h3><p><?=e($item['text']??$item['desc']??'')?></p></div></article><?php endforeach; ?></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
