<?php $pageId='membres';$pageTitle='Comité Abissa';require __DIR__.'/includes/header.php'; ?>
<link rel="stylesheet" href="/appli/committee-view.css?v=203">
<section class="page-hero committee-banner"><span class="eyebrow">Abissa 2026</span><h1><?=e(pageHeading('Comité Abissa'))?></h1><p><?=e(pageSubtitle('Tradition, culture, rayonnement et développement.'))?></p><div class="committee-toolbar"><button type="button" class="committee-profile" id="openCommitteeChart">Voir l’organigramme officiel</button><a class="committee-profile" href="#inscription">Inscription des membres</a></div><?=bannerLink()?></section>
<div id="publicCommittee"></div>
<noscript><section class="section"><?php foreach([0,1,2,4,3] as $index):$person=$state['COMMITTEE']['leaders'][$index]; ?><h2><?=e($person['role'])?></h2><p><?=e($person['name'])?></p><?php endforeach; ?><?php foreach($state['COMMITTEE']['commissions'] as $commission):?><details><summary><?=e($commission['name'])?> — Afficher les membres</summary><?php foreach($commission['members'] as $member):?><p><?=e($member['name'])?> · <?=e($member['role']??'Membre')?></p><?php endforeach;?></details><?php endforeach;?></section></noscript>
<section id="inscription" class="section form-section"><div><span class="kicker dark">Adhésion</span><h2>Devenir membre</h2><p>Renseignez votre année d’adhésion, votre commission et votre fonction. Votre dossier sera vérifié avant validation.</p></div><div id="memberForm"></div></section>
<script src="/appli/committee-data.js?v=203"></script><script src="/appli/committee-view.js?v=203"></script><script src="/appli/request-forms.js?v=203"></script>
<script type="application/json" id="publicCommitteeData"><?=json_encode($state['COMMITTEE'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<script>
const committeeData=JSON.parse(document.getElementById('publicCommitteeData').textContent);
AbissaCommittee.mount(document.getElementById('publicCommittee'),committeeData,{base:new URL('/appli/',location.href).href});
document.querySelector('#publicCommittee .committee-intro').hidden=true;
document.getElementById('openCommitteeChart').onclick=()=>document.querySelector('#publicCommittee [data-committee-chart]')?.click();
AbissaRequests.mount(document.getElementById('memberForm'),'member',{base:'/appli',token:()=>{try{return JSON.parse(localStorage.getItem('abissa_user_session')||'{}').token||'';}catch{return '';}},controls:<?=json_encode($state['SETTINGS']['requestControls']['member']??['open'=>true],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>,loginUrl:'/appli/mobile.html?request=member&auth=login',registerUrl:'/appli/mobile.html?request=member&auth=register',roles:<?=json_encode($state['SETTINGS']['memberRoles']??['Membre','Président','Bénévole'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>,commissions:committeeData.commissions.map(c=>c.name)});
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
