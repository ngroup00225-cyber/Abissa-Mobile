/* Shared public committee presentation: website and Android use the same data and views. */
window.AbissaCommittee = (() => {
  const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const key = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/commission/g,'').replace(/&/g,'et').replace(/[^a-z0-9]/g,'');
  const clone = value => JSON.parse(JSON.stringify(value));
  function normalize(saved = {}) {
    const defaults = clone(window.AbissaCommitteeDefaults);
    if (saved.schemaVersion === 2) {
      defaults.leaders = defaults.leaders.map(person => ({...person,...(saved.leaders || []).find(p => p.id === person.id)}));
    } else if (key(saved.president?.name).includes('amichia')) defaults.leaders[1].img = saved.president.img || '';
    defaults.poles=defaults.poles.map(p=>({...p,icon:(saved.poles||[]).find(s=>s.id===p.id)?.icon||p.icon}));
    defaults.commissions.forEach(commission => {
      const names = [commission.name,...commission.aliases].map(key);
      const old = (saved.commissions || []).find(c => c.id === commission.id || names.includes(key(c.name)));
      commission.members = clone(old?.members || []).filter(person => person.sourceId);
      commission.icon=old?.icon||commission.icon;commission.color=old?.color||defaults.poles.find(p=>p.id===commission.poleId).color;
    });
    defaults.additionalMembers=clone(saved.additionalMembers||[]).filter(person=>person.sourceId);
    defaults.leaders.forEach((person,i)=>person.role=window.AbissaCommitteeDefaults.leaders[i].role);
    defaults.president = defaults.leaders[1]; defaults.vicePresident = defaults.leaders[3];
    return defaults;
  }
  function safeImage(src, base) {
    if (!src) return '';
    if (/^data:image\/(png|jpe?g|webp);base64,/i.test(src)) return src;
    try {const url = new URL(src, base); return ['http:','https:'].includes(url.protocol) ? url.href : '';} catch {return '';}
  }
  function mount(container, saved, options = {}) {
    if (!container) return;
    const data = normalize(saved), base = options.base || new URL('.',location.href).href;
    const imageUrl = options.chartImage || safeImage(data.chartImage,base);
    const profiles = new Map(); let personIndex = 0;
    function portrait(person, cls = '') {
      const src = safeImage(person.img || person.photo, base) || window.ABISSA_OFFLINE_BADGE_LOGO || safeImage('assets/logo-badge-default-2026.png', base);
      return `<img class="committee-avatar ${cls}" src="${escape(src)}" alt="${escape(person.name)}" loading="lazy">`;
    }
    function profileButton(person, commission = '') {
      const id = String(++personIndex); profiles.set(id,{...person,commission});
      return `<button type="button" class="committee-profile" data-committee-person="${id}">Voir le profil</button>`;
    }
    function leader(person) {
      return `<article class="committee-person" style="--committee-accent:${escape(person.color)}">${portrait(person)}<span class="committee-role">${escape(person.role)}</span><h3>${escape(person.name)}</h3>${person.description ? `<p>${escape(person.description)}</p>` : ''}${profileButton(person)}</article>`;
    }
    function commissionCard(commission,pole) {
      const members = commission.members.filter(m => m.name && !/à renseigner|nom du/i.test(m.name));
      const president = members.find(m => /^(président|president|coordonnateur|coordinateur|responsable)\b/i.test(m.role || ''));
      return `<article class="committee-commission" style="--committee-accent:${escape(commission.color||pole.color)}"><header><i class="${escape(commission.icon||pole.icon)}" aria-hidden="true"></i><h4>${escape(commission.name)}</h4></header><div class="committee-president"><span class="committee-role">Présidence de commission</span>${president ? `${portrait(president)}<strong>${escape(president.name)}</strong>${profileButton(president,commission.name)}` : '<p>À renseigner</p>'}</div><details class="committee-members"><summary><span class="when-closed">Afficher les membres ▼</span><span class="when-open">Masquer les membres ▲</span></summary>${members.length ? `<ul>${members.filter(m=>m!==president).map(m=>`<li>${portrait(m,'small')}<div><strong>${escape(m.name)}</strong><small>${escape(m.role || 'Membre')}</small>${profileButton(m,commission.name)}</div></li>`).join('')}</ul>` : '<p>Les profils seront publiés prochainement.</p>'}</details></article>`;
    }
    container.classList.add('committee-view');
    container.innerHTML = `<section class="committee-intro"><h2>Comité Abissa</h2><span class="committee-year">ABISSA ${escape(data.year)}</span><p>Tradition, culture, rayonnement et développement.</p><div class="committee-toolbar"><button type="button" class="committee-profile" data-committee-chart>Voir l’organigramme officiel</button>${options.onJoin?'<button type="button" class="committee-profile" data-committee-join>Devenir membre</button>':''}${options.joinHref ? `<a class="committee-profile" href="${escape(options.joinHref)}">Inscription des membres</a>` : ''}</div></section><section class="committee-executive" aria-label="COMITE ABISSA"><h2>COMITE ABISSA</h2><div class="committee-top">${leader(data.leaders[0])}${leader(data.leaders[1])}</div><div class="committee-office">${[data.leaders[2],data.leaders[4]].map(leader).join('')}</div><div class="committee-external">${leader(data.leaders[3])}</div></section><section class="committee-poles"><div class="committee-section-heading"><h2>5 pôles opérationnels</h2><p>${data.commissions.length} commissions au service de l’Abissa</p><label>Afficher un pôle<select data-committee-filter><option value="all">Tous les pôles</option>${data.poles.map(p=>`<option value="${escape(p.id)}">Pôle ${p.number} — ${escape(p.name)}</option>`).join('')}</select></label></div>${data.poles.map(p=>`<section class="committee-pole" data-pole="${escape(p.id)}" style="--committee-accent:${escape(p.color)}"><h3><i class="${escape(p.icon)}" aria-hidden="true"></i><span>Pôle ${p.number} — ${escape(p.name)}</span></h3><div class="committee-commission-grid">${data.commissions.filter(c=>c.poleId===p.id).map(c=>commissionCard(c,p)).join('')}</div></section>`).join('')}</section><section class="committee-chart-section"><h2>Organigramme officiel de l’association Abissa</h2><button type="button" class="committee-profile" data-committee-chart>Agrandir l’organigramme</button><div class="committee-structure">${[ [data.leaders[0]], [data.leaders[1]], [data.leaders[2],data.leaders[4]], [data.leaders[3]] ].map(level=>`<div class="committee-level">${level.map(p=>`<div><strong>${escape(p.role)}</strong><span>${escape(p.name)}</span></div>`).join('')}</div>`).join('')}</div></section>`;
    if(data.additionalMembers.length)container.querySelector('.committee-chart-section').insertAdjacentHTML('beforebegin',`<section class="committee-executive"><h2>Autres membres de l’association</h2><div class="committee-office">${data.additionalMembers.map(leader).join('')}</div></section>`);
    if(options.hideIntro)container.querySelector('.committee-intro')?.remove();
    const join=container.querySelector('[data-committee-join]');if(join)join.onclick=options.onJoin;
    container.querySelector('[data-committee-filter]').onchange = event => {
      container.querySelectorAll('[data-pole]').forEach(section => section.hidden = event.target.value !== 'all' && section.dataset.pole !== event.target.value);
    };
    let dialog;
    function openDialog(content, chart=false) {
      if (dialog) dialog.remove();
      const focused = document.activeElement;
      dialog = document.createElement('dialog'); dialog.className = 'committee-dialog'+(chart?' committee-chart-dialog':'');
      dialog.setAttribute('aria-label', chart ? 'Organigramme officiel' : 'Profil du comité');
      dialog.innerHTML = `<button class="committee-close" type="button" aria-label="Fermer">Fermer</button>${content}`;
      document.body.appendChild(dialog);
      dialog.querySelector('.committee-close').onclick = () => dialog.close();
      dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
      dialog.addEventListener('close',()=>{dialog.remove(); focused?.focus();},{once:true});
      dialog.showModal();
    }
    container.querySelectorAll('[data-committee-person]').forEach(button => button.onclick = () => {
      const person = profiles.get(button.dataset.committeePerson);
      openDialog(`<div class="committee-profile-detail">${portrait(person)}<span class="committee-role">${escape(person.role || 'Membre')}</span><h2>${escape(person.name)}</h2>${person.commission?`<p>${escape(person.commission)}</p>`:''}${person.description?`<p class="committee-bio">${escape(person.description)}</p>`:''}</div>`);
    });
    container.querySelectorAll('[data-committee-chart]').forEach(button => button.onclick = () => {
      openDialog(`<h2>Organigramme officiel</h2>${container.querySelector('.committee-structure').outerHTML}`,true);

    });
    container.querySelectorAll('img.committee-avatar').forEach(img => img.onerror = () => {img.onerror=null;img.src=window.ABISSA_OFFLINE_BADGE_LOGO||safeImage('assets/logo-badge-default-2026.png',base);});
  }
  return {normalize,mount};
})();
