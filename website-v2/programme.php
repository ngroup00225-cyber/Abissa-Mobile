<?php $pageId='programme';$pageTitle='Programme';require __DIR__.'/includes/header.php'; ?>
<nav class="breadcrumbs" aria-label="Fil d’Ariane"><a href="/abissa.php">L’Abissa</a><span aria-hidden="true"> / </span><span>Programme</span></nav>
<section class="page-hero compact"><span class="kicker">Agenda officiel</span><h1><?=e(pageHeading($website['programmeTitle']))?></h1><p><?=e(pageSubtitle('Du vendredi 23 octobre au dimanche 8 novembre 2026 · Grand-Bassam'))?></p><?=bannerLink()?></section>
<link rel="stylesheet" href="/appli/programme-view.css?v=203"><section class="section"><div id="publicProgramme"></div></section>
<script src="/appli/programme-data.js?v=203"></script><script src="/appli/programme-view.js?v=203"></script><script type="application/json" id="publicProgrammeData"><?=json_encode($state['PROGRAMME'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script><script>AbissaProgramme.mount(document.getElementById('publicProgramme'),JSON.parse(document.getElementById('publicProgrammeData').textContent),{base:new URL('/appli/',location.href).href});</script>
<?php require __DIR__.'/includes/footer.php'; ?>
