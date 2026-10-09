/* These exact forms are shared by the website and the Android app. */
window.AbissaRequests=(()=>{
 const schemas={
  member:[['name','Nom et prénoms','text',true],['phone','Téléphone','tel',true],['email','E-mail','email'],['joinedYear','Année d’adhésion','number',true],['commission','Commission','select',true,[]],['roleChoice','Fonction','select',true,['Membre','Président','Bénévole','Autre']],['roleOther','Précisez votre fonction','text'],['photoFile','Photo pour le badge','image',true]],
  press:[['name','Nom et prénoms','text',true],['organization','Organisation ou média','text',true],['accreditationType','Type de média','select',true,['Presse écrite','Télévision','Radio','Média en ligne','Photographe / Vidéaste indépendant']],['role','Fonction','text'],['phone','Téléphone','tel',true],['email','E-mail','email'],['days','Jours souhaités','text'],['message','Motif de la demande','textarea'],['photoFile','Photo pour le badge (facultatif)','image'],['proofFile','Justificatif : carte de presse ou attestation','proof',true]],
  pass:[['name','Nom et prénoms','text',true],['phone','Téléphone','tel',true],['email','E-mail','email'],['organization','Organisation (facultatif)','text'],['vehicle','Véhicule (marque et modèle)','text',true],['plate','Immatriculation du véhicule','text',true],['validUntil','Validité demandée jusqu’au','date',true],['message','Motif de la demande','textarea']]
 };
 const roles=values=>[...new Set([...(Array.isArray(values)?values:['Membre','Président','Bénévole']).filter(v=>typeof v==='string'&&v.trim()).map(v=>v.trim()),'Autre'])];
 const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 function mount(container,type,options={}){
  if(!container||!schemas[type])return;
  const control=()=>(typeof options.controls==='function'?options.controls():options.controls)||{};
  const popup=message=>{const dialog=document.createElement('dialog');dialog.className='learning-dialog';const p=document.createElement('p');p.textContent=message;const close=document.createElement('button');close.textContent='Fermer';close.onclick=()=>dialog.close();dialog.append(p,close);document.body.append(dialog);dialog.onclose=()=>dialog.remove();dialog.showModal();};
  if(control().open===false){const message=control().message||'Les demandes sont temporairement interrompues. Veuillez revenir ultérieurement.';container.innerHTML=`<section class="request-gate"><p>${escape(message)}</p><button type="button" data-request-closed>Informations sur les demandes</button></section>`;container.querySelector('button').onclick=()=>popup(message);return;}
  if(!options.token?.()){container.innerHTML=`<section class="request-gate"><p>Veuillez vous connecter ou créer un compte avant d’effectuer cette demande.</p>${options.loginUrl?`<a class="button navy" href="${escape(options.loginUrl)}">Se connecter</a>`:'<button type="button" data-request-login>Se connecter</button>'}${options.registerUrl?`<a class="button navy" href="${escape(options.registerUrl)}">Créer un compte</a>`:'<button type="button" data-request-register>Créer un compte</button>'}</section>`;container.querySelector('[data-request-login]')?.addEventListener('click',()=>options.onLogin?.(type));container.querySelector('[data-request-register]')?.addEventListener('click',()=>options.onRegister?options.onRegister(type):options.onLogin?.(type));return;}
  const schema=schemas[type]?.map(field=>field[0]==='commission'?[...field.slice(0,4),['',...(typeof options.commissions==='function'?options.commissions():options.commissions||[])]]:field[0]==='roleChoice'?[...field.slice(0,4),roles(typeof options.roles==='function'?options.roles():options.roles)]:field);if(!container||!schema)return;
  container.innerHTML=`<form class="abissa-request-form public-form" data-request="${type}">${schema.map(([name,label,kind,required,values])=>`<label class="form-group ${['textarea','proof'].includes(kind)?'full':''}"><span>${label}${required?' *':''}</span>${kind==='select'?`<select name="${name}" ${required?'required':''}>${values.map(v=>`<option value="${escape(v)}">${escape(v)||"Choisir une commission"}</option>`).join('')}</select>`:kind==='textarea'?`<textarea name="${name}" rows="3" maxlength="2000"></textarea>`:`<input name="${name}" type="${['image','proof'].includes(kind)?'file':kind}" ${required?'required':''} ${kind==='image'?'accept="image/jpeg,image/png,image/webp"':kind==='proof'?'accept="application/pdf,image/jpeg,image/png,image/webp"':kind==='date'?'min="'+new Date().toISOString().slice(0,10)+'"':'maxlength="180"'}>`}${kind==='proof'?'<small>PDF, JPEG, PNG ou WebP · 5 Mo maximum. Consultation réservée à l’équipe qui traite votre demande.</small>':kind==='image'?'<small>JPEG, PNG ou WebP · 3 Mo maximum.</small>':''}</label>`).join('')}<p class="full request-auth-note"></p><button class="button navy btn-primary" type="submit">Envoyer la demande</button><p class="form-status full" role="status" aria-live="polite"></p></form>`;
  const form=container.querySelector('form'),status=form.querySelector('.form-status');
  const other=form.querySelector('[name=roleOther]'),role=form.querySelector('[name=roleChoice]');
  const updateRole=()=>{if(other){const show=role.value==='Autre';other.closest('label').hidden=!show;other.disabled=!show;other.required=show;}};
  if(role){role.onchange=updateRole;updateRole();form.addEventListener('focusin',()=>{const selected=role.value,values=roles(typeof options.roles==='function'?options.roles():options.roles);if(selected&&!values.includes(selected))values.unshift(selected);role.innerHTML=values.map(v=>`<option value="${escape(v)}" ${v===selected?'selected':''}>${escape(v)}</option>`).join('');});}
  if(type==='member')form.querySelector('.request-auth-note').textContent='Votre nom, votre fonction et votre photo seront affichés sur le site et dans l’application après validation du dossier. Vos coordonnées restent privées.';
  const year=form.querySelector('[name=joinedYear]');if(year){year.min='1900';year.max=String(new Date().getFullYear()+1);year.step='1';year.placeholder='Ex. 2026';year.removeAttribute('maxlength');}
  form.onsubmit=async event=>{
   event.preventDefault();if(!form.reportValidity())return;
   const token=options.token?.()||'';
   if(!token){status.textContent='Veuillez vous connecter ou créer un compte avant d’effectuer cette demande.';options.onLogin?.(type);return;}
   if(control().open===false){const message=control().message||'Les demandes sont temporairement interrompues. Veuillez revenir ultérieurement.';status.textContent=message;popup(message);return;}
   const button=form.querySelector('[type=submit]');button.disabled=true;status.textContent='Envoi en cours…';
   const base=String(typeof options.base==='function'?options.base():options.base||'').replace(/\/$/,''),headers=token?{Authorization:'Bearer '+token}:{};
   async function send(path,body){const response=await fetch(base+'/api/'+path,{method:'POST',headers:body instanceof FormData?headers:{...headers,'Content-Type':'application/json'},body:body instanceof FormData?body:JSON.stringify(body)});const result=await response.json();if(!response.ok)throw new Error(result.error||'Envoi impossible. Réessayez.');return result;}
   try{
    const data={},values=new FormData(form);
    for(const [name] of schema){const value=values.get(name);if(!(value instanceof File))data[name]=String(value||'').trim();}
    const photo=values.get('photoFile'),proof=values.get('proofFile');
    const imageType=file=>['image/jpeg','image/png','image/webp'].includes(file.type)||(!file.type&&/\.(jpe?g|png|webp)$/i.test(file.name));
    const proofType=file=>file.type==='application/pdf'||imageType(file)||(!file.type&&/\.pdf$/i.test(file.name));
    if(photo?.size && (photo.size>3*1024*1024||!imageType(photo)))throw new Error('Photo : JPEG, PNG ou WebP, 3 Mo maximum.');
    if(type==='press'&&(!proof?.size||proof.size>5*1024*1024||!proofType(proof)))throw new Error('Ajoutez un justificatif PDF ou image de 5 Mo maximum.');
    if(proof?.size){status.textContent='Envoi du justificatif…';const upload=new FormData();upload.append('file',proof);const saved=await send('public/proof',upload);data.proofId=saved.id;data.proofName=saved.name;}
    if(photo?.size){status.textContent='Envoi de la photo…';const upload=new FormData();upload.append('file',photo);data.photo=(await send('public/upload',upload)).url;}
    status.textContent='Transmission du dossier…';await send('submissions',{type,data});
    form.reset();updateRole();status.textContent='Votre demande a été envoyée. L’équipe Abissa Mobile examinera votre dossier.';options.onSuccess?.(type);
   }catch(error){status.textContent=error.message||'Envoi impossible. Vérifiez votre connexion.';}finally{button.disabled=false;}
  };
  if(options.loginUrl){const note=form.querySelector('.request-auth-note');note.innerHTML=`Utilisez votre compte Abissa Mobile. <a href="${escape(options.loginUrl)}">Se connecter ou créer un compte</a>`;}
 }
 return {mount,schemas};
})();
