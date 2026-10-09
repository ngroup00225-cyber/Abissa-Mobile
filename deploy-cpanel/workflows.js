// Editable dossiers and a single PNG-based badge/printing workflow.
WORKFLOWS.member.fields = [['name','Nom et prénoms'],['phone','Téléphone'],['email','E-mail'],['joinedYear','Année d’adhésion'],['commission','Commission'],['role','Fonction'],['photo','Photo']];
WORKFLOWS.press.fields=[['name','Nom et prénoms'],['organization','Organisation ou média'],['accreditationType','Type de média'],['role','Fonction'],['phone','Téléphone'],['email','E-mail'],['days','Jours souhaités'],['message','Motif de la demande'],['photo','Photo pour le badge']];
WORKFLOWS.pass.fields=[['name','Nom et prénoms'],['phone','Téléphone'],['email','E-mail'],['organization','Organisation'],['vehicle','Véhicule (marque et modèle)'],['plate','Immatriculation du véhicule'],['validUntil','Validité demandée jusqu’au'],['message','Motif de la demande']];
window.showWorkflowForm = (type, id = '') => {
  const item = submissions.find(s => s.id === id), cfg = WORKFLOWS[type], data = item?.data || {}, box = $('#workflowForm');
  if(type==='member'&&!data.role)data.role='Membre';
  const fields = new Map(cfg.fields);if(type==='member'){fields.set('registration','Matricule');data.registration=data.registration||'Attribué à l’enregistrement';}
  Object.keys(data).forEach(key => {if (!fields.has(key) && !['proofId','proofName','joinedAt','roleChoice','roleOther'].includes(key)) fields.set(key, LABELS[key] || key)});
  box.classList.remove('hidden');
  box.innerHTML = `<div class="panel-head"><h3>${id?'Modifier le dossier':'Ajouter '+cfg.singular}</h3></div><form id="dossierForm" class="form-grid">${[...fields].map(([key,label]) => type==='member'&&key==='role'?`<label class="field">Fonction<select name="roleChoice">${[...new Set([...(state.SETTINGS?.memberRoles||['Membre','Président','Bénévole']),data.role,'Autre'].filter(Boolean))].map(v=>`<option ${v===(data.role||'Membre')?'selected':''}>${v}</option>`).join('')}</select></label><label class="field">Autre fonction (si nécessaire)<input name="roleOther" value="${esc(['Membre','Président','Bénévole'].includes(data.role)?'':data.role||'')}"></label>`:key==='accreditationType'?`<label class="field">Type de média<select name="accreditationType">${['Presse écrite','Télévision','Radio','Média en ligne','Photographe / Vidéaste indépendant'].map(value=>`<option ${value===data[key]?'selected':''}>${value}</option>`).join('')}</select></label>`:key==='commission'?`<label class="field">Commission<select name="commission"><option value="">Choisir une commission</option>${[...new Set([data.commission,...(state.COMMISSIONS||[]).map(x=>x.name)].filter(Boolean))].map(name=>`<option ${name===data.commission?'selected':''}>${esc(name)}</option>`).join('')}</select></label>`:/photo|image|img|logo|background/i.test(key) ? `<label class="field">${esc(label)}${data[key]?`<img src="${esc(data[key])}" style="height:90px;object-fit:contain" alt="Image actuelle">`:''}<input type="file" name="${esc(key)}" accept="image/jpeg,image/png,image/webp,image/gif"><span><input type="checkbox" name="remove_${esc(key)}"> Retirer l’image</span></label>` : `<label class="field">${esc(label)}<input name="${esc(key)}" value="${esc(typeof data[key]==='object'?JSON.stringify(data[key]):data[key]||'')}" type="${key==='joinedYear'?'number':key==='validUntil'?'date':key==='email'?'email':'text'}" ${key==='joinedYear'?'min="1900" max="'+(new Date().getFullYear()+1)+'" step="1"':''} ${key==='name'?'required':''} ${key==='registration'?'readonly':''}></label>`).join('')}${type==='press'?`<label class="field full proof-input">Justificatif : carte de presse ou attestation${data.proofId?`<a class="workflow-proof" href="api/admin/proof?id=${encodeURIComponent(data.proofId)}" target="_blank" rel="noopener">Consulter : ${esc(data.proofName||'justificatif')}</a>`:'<small>Aucun justificatif joint.</small>'}<input name="proofFile" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" ${id?'':'required'}><small>PDF ou image, 5 Mo maximum. Un nouvel envoi remplace le justificatif dans ce dossier.</small></label>`:''}<div class="field full button-row"><button class="primary">Enregistrer</button><button type="button" class="ghost" onclick="this.closest('section').classList.add('hidden')">Annuler</button></div><p role="status" id="dossierStatus"></p></form>`;
  box.scrollIntoView({behavior:'smooth',block:'start'});
  $('#dossierForm').onsubmit = async event => {
    event.preventDefault(); const button = event.target.querySelector('button'); button.disabled = true;
    try {
      const form = new FormData(event.target), updated = {...data};
      for (const [key] of fields) {
        if(type==='member'&&key==='role'){updated.roleChoice=form.get('roleChoice');updated.roleOther=form.get('roleOther');updated.role=updated.roleChoice==='Autre'?updated.roleOther:updated.roleChoice;continue;}
        const value = form.get(key);
        if (value instanceof File) {
          if (form.has('remove_'+key)) updated[key] = '';
          if (value.size) {const upload = new FormData(); upload.append('file',value); updated[key] = (await api('admin/upload',{method:'POST',body:upload})).url;}
        } else updated[key] = typeof data[key]==='object' ? JSON.parse(value) : typeof data[key]==='number' ? Number(value) : typeof data[key]==='boolean' ? value==='true' : value;
      }
      const proof=form.get('proofFile');if(proof instanceof File && proof.size){const upload=new FormData();upload.append('file',proof);const saved=await api('public/proof',{method:'POST',body:upload});updated.proofId=saved.id;updated.proofName=saved.name;}
      await api(id?'admin/submissions':'submissions',{method:id?'PATCH':'POST',body:JSON.stringify(id?{id,data:updated}:{type,data:updated})});
      await loadAll(); toast('Dossier enregistré');
    } catch(error) {$('#dossierStatus').textContent = error.message; button.disabled = false;}
  };
};
window.editWorkflow = id => {const item=badgeRecord(id);if(item)showWorkflowForm(item.type,id)};
let badgeLayout = 4, previewVersion = 0;
async function badgeImages(item, options={}) {
  const result = await api('admin/credential-token',{method:'POST',body:JSON.stringify({id:item.id})});
  const document=result.document||item;const front = await Credential.png(document,state.BADGE_SETTINGS,result.verifyUrl,options);
  return {front};
}
window.updateBadgePreview = async () => {
  const version=++previewVersion, box=$('#badgePreview'); if(!box)return;
  const items=selectedBadgeIds().map(id=>badgeRecord(id)).filter(Boolean);
  $('#badgeCount').textContent=`${items.length} badge(s)`; box.textContent=items.length?'Préparation de l’aperçu…':'Sélectionnez au moins un document.';
  try {const images=await Promise.all(items.map(item=>badgeImages(item)));if(version!==previewVersion||!box.isConnected)return;
    box.innerHTML=images.map(({front})=>`<div><img class="badge-png" src="${front}" alt="Badge, face unique"></div>`).join('');
  }catch(error){if(version===previewVersion)box.textContent=error.message}
};
window.setBadgeLayout = (button,count) => {badgeLayout=count;button.parentElement.querySelectorAll('button').forEach(b=>b.classList.toggle('active',b===button));$('#badgePreview').classList.toggle('a4-grid',count===4)};
window.uploadBadgeBackground = async (input,side='frontBackground') => {
  if(!input.files[0])return;
  try {const form=new FormData();form.append('file',input.files[0]);const result=await api('admin/upload',{method:'POST',body:form});
    const settings={...state.BADGE_SETTINGS,frontBackground:result.url,templateVersion:5};await api('admin/badge-settings',{method:'PUT',body:JSON.stringify(settings)});state.BADGE_SETTINGS=settings;updateBadgePreview();toast('Arrière-plan enregistré');
  }catch(error){toast(error.message)}
};
window.removeBadgeBackground = async side => {if(!confirm('Retirer cet arrière-plan ?'))return;try{const settings={...state.BADGE_SETTINGS,frontBackground:'',templateVersion:5};await api('admin/badge-settings',{method:'PUT',body:JSON.stringify(settings)});state.BADGE_SETTINGS=settings;updateBadgePreview();toast('Arrière-plan retiré')}catch(error){toast(error.message)}};
async function printBadges(ids,layout=1) {
  if(!ids.length)return toast('Sélectionnez au moins un document');
  const popup=window.open('','_blank');if(!popup)return toast('Autorisez l’ouverture de la fenêtre d’impression.');
  popup.document.body.textContent='Préparation des badges…';
  try {
    const images=[];for(const id of ids){const item=badgeRecord(id);if(item)images.push(await badgeImages(item));}
    const type=badgeRecord(ids[0])?.type;const {width,height}=Credential.dimensions(state.BADGE_SETTINGS,type);if(type==='pass')layout=1;
    const sheets=[];for(let i=0;i<images.length;i+=layout){const group=images.slice(i,i+layout);sheets.push(`<section>${group.map(x=>`<img src="${x.front}">`).join('')}</section>`)}
    popup.document.write(`<!doctype html><html lang="fr"><title>Badges et laissez-passer Abissa 2026</title><style>@page{size:${type==='pass'?'A4 landscape':layout===4?'A4':width+'mm '+height+'mm'};margin:0}body{margin:0}section{display:grid;grid-template-columns:repeat(${layout===4?2:1},${width}mm);gap:${layout===4?'8mm':'0'};padding:${layout===4?'9mm 6mm':'0'};break-after:page}section:last-child{break-after:auto}img{width:${width}mm;height:${height}mm;display:block}</style><body>${sheets.join('')}</body></html>`);popup.document.close();
    await Promise.all([...popup.document.images].map(img=>img.decode()));popup.focus();popup.print();
  }catch(error){popup.document.body.textContent=error.message;toast(error.message)}
}
window.printCredential=id=>printBadges([id]);
window.printSelectedBadges=()=>printBadges(selectedBadgeIds(),badgeLayout);

window.downloadBadgeLayers=async()=>{
 const items=selectedBadgeIds().map(id=>badgeRecord(id)).filter(Boolean);
 if(!items.length)return toast('Sélectionnez au moins un dossier');
 try{for(const item of items){const {front}=await badgeImages(item,{transparent:true,crop:item.type!=='pass'});const link=document.createElement('a');link.href=front;link.download='Abissa-'+(item.data.name||'badge').replace(/[^a-z0-9_-]/gi,'-')+'-transparent.png';link.click();}toast('PNG préparé'+(items.length>1?'s. Autorisez les téléchargements multiples si nécessaire.':'.'));}catch(error){toast(error.message)}
};
