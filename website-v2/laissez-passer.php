<?php $pageId='laissez-passer';$pageTitle='Laissez-passer';require __DIR__.'/includes/header.php';?>
<section class="page-hero compact"><span class="kicker">Circuler pendant l’Abissa</span><h1><?=e(pageHeading('Demande de laissez-passer'))?></h1><p><?=e(pageSubtitle('Renseignez le véhicule et la période souhaitée. Le comité examinera votre demande.'))?></p><?=bannerLink()?></section>
<section class="section form-section"><div><span class="kicker dark">Accès véhicule</span><h2>Votre laissez-passer</h2><p>Votre demande sera examinée par l’équipe Abissa Mobile.</p><p><a href="/accreditation.php">Demander une accréditation presse</a></p></div><div data-request-form="pass"></div></section>
<?php require __DIR__.'/includes/footer.php';?>
