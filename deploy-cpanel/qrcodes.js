/* QR library and editor. Saved URL codes keep their identity when the target changes. */
let qrItems=[], qrEditorVersion=0;
window.renderQRCodes=async()=>{
  const version=++qrEditorVersion;
  $('#content').innerHTML='<section class="panel"><p role="status">Chargement des QR Codes…</p></section>';
  try {
    const items=await api('admin/qrcodes'); if(version!==qrEditorVersion||current!=='QRCODES')return; qrItems=items;
    $('#content').innerHTML=`<div class="page-head"><div><div class="eyebrow">Gestion du site web</div><h2>QR Codes</h2><p>Créez, personnalisez et retrouvez tous vos QR Codes.</p></div><button class="gold-button" onclick="editQRCode()"><i class="ri-add-line"></i> Nouveau QR Code</button></div><section id="qrEditor" class="panel hidden"></section><section class="panel"><label class="field">Rechercher un QR Code<input id="qrSearch" placeholder="Titre ou destination"></label><div id="qrLibrary" class="qr-library"></div></section>`;
    $('#qrSearch').oninput=renderQRLibrary; renderQRLibrary();
  }catch(error){if(current==='QRCODES')$('#content').innerHTML=`<section class="panel" role="alert">${esc(error.message)}</section>`;}
};
function qrPayload(item){return item.kind==='url'?new URL('qr.php?id='+encodeURIComponent(item.id),location.href).href:item.content;}
function qrMatrix(value){const box=document.createElement('div');const code=new QRCode(box,{text:value,width:256,height:256,correctLevel:QRCode.CorrectLevel.H});return code._oQRCode;}
function qrCanvas(item){
  const matrix=qrMatrix(qrPayload(item)),count=matrix.getModuleCount(),quiet=4,canvas=document.createElement('canvas');
  canvas.width=item.size;canvas.height=item.size+(item.caption?Math.round(item.size*.11):0);
  const ctx=canvas.getContext('2d'),cell=Math.floor(item.size/(count+quiet*2)),offset=Math.floor((item.size-count*cell)/2);
  ctx.fillStyle=item.light;ctx.fillRect(0,0,canvas.width,canvas.height);ctx.fillStyle=item.dark;
  for(let row=0;row<count;row++)for(let col=0;col<count;col++)if(matrix.isDark(row,col))ctx.fillRect(offset+col*cell,offset+row*cell,cell,cell);
  if(item.caption){let size=item.size*.037;ctx.textAlign='center';ctx.textBaseline='middle';ctx.font=`700 ${size}px Arial`;while(ctx.measureText(item.caption).width>item.size*.9){size--;ctx.font=`700 ${size}px Arial`;}ctx.fillText(item.caption,item.size/2,item.size*1.04);}
  return canvas;
}
function renderQRLibrary(){
  const query=($('#qrSearch')?.value||'').toLocaleLowerCase('fr');const items=qrItems.filter(i=>(i.title+' '+i.content).toLocaleLowerCase('fr').includes(query));
  $('#qrLibrary').innerHTML=items.map(item=>`<article class="qr-card"><img data-qr-id="${item.id}" alt="QR Code ${esc(item.title)}"><h3>${esc(item.title)}</h3><span class="pill">${item.kind==='url'?'Lien modifiable':'Texte'} · ${item.active?'Actif':'Désactivé'}</span><p>${esc(item.content)}</p><div class="button-row"><button class="ghost" onclick="editQRCode('${item.id}')">Modifier</button><button class="ghost" onclick="exportQRCode('${item.id}','png')">PNG</button><button class="ghost" onclick="exportQRCode('${item.id}','svg')">SVG</button><button class="remove" aria-label="Supprimer ${esc(item.title)}" onclick="deleteQRCode('${item.id}')"><i class="ri-delete-bin-line"></i></button></div></article>`).join('')||'<p class="empty">Aucun QR Code. Créez votre premier code.</p>';
  for(const item of items)try{document.querySelector(`[data-qr-id="${item.id}"]`).src=qrCanvas(item).toDataURL('image/png');}catch(error){toast(error.message);}
}
window.editQRCode=id=>{
  const item=qrItems.find(x=>x.id===id)||{title:'',kind:'url',content:'',dark:'#073e59',light:'#ffffff',caption:'',size:1024,active:true};
  const box=$('#qrEditor');box.classList.remove('hidden');
  box.innerHTML=`<div class="panel-head"><h3>${id?'Modifier':'Créer'} un QR Code</h3><button class="ghost" id="qrCancel">Fermer</button></div><div class="qr-editor-layout"><form id="qrForm" class="form-grid"><label class="field full">Titre<input name="title" value="${esc(item.title)}" required maxlength="100"></label><label class="field full">Type<select name="kind" ${id?'disabled':''}><option value="url" ${item.kind==='url'?'selected':''}>Lien web modifiable</option><option value="text" ${item.kind==='text'?'selected':''}>Texte</option></select></label><label class="field full">Destination ou contenu<textarea name="content" required maxlength="1200" rows="3">${esc(item.content)}</textarea></label><p class="muted full" id="qrTypeHelp"></p><label class="field">Couleur du code<input type="color" name="dark" value="${item.dark}"></label><label class="field">Couleur du fond<input type="color" name="light" value="${item.light}"></label><label class="field full">Légende (facultatif)<input name="caption" maxlength="100" value="${esc(item.caption)}"></label><label class="field">Résolution<select name="size">${[512,1024,2048].map(s=>`<option ${s===item.size?'selected':''}>${s}</option>`).join('')}</select></label><label class="field">État<select name="active"><option value="true" ${item.active?'selected':''}>Actif</option><option value="false" ${!item.active?'selected':''}>Désactivé</option></select></label><div class="field full"><button class="primary" type="submit">Enregistrer le QR Code</button><p id="qrStatus" role="status"></p></div></form><div class="qr-live"><img id="qrLiveImage" alt="Aperçu du QR Code"><p id="qrPreviewNote" class="muted"></p></div></div>`;
  const form=$('#qrForm'),read=()=>{const data=Object.fromEntries(new FormData(form));return {...data,id:id||'apercu-non-enregistre',kind:id?item.kind:data.kind,size:Number(data.size),active:data.active==='true'};};
  const preview=()=>{const draft=read();$('#qrTypeHelp').textContent=draft.kind==='url'?'Le lien de destination peut être modifié après impression. Vous pouvez aussi désactiver ce code.':'Modifier le texte nécessite de télécharger et réimprimer le QR Code. Un texte imprimé ne peut pas être désactivé.';form.elements.active.disabled=draft.kind==='text';$('#qrPreviewNote').textContent=id?'Aperçu des modifications. Enregistrez avant de télécharger.':'Aperçu. Enregistrez pour obtenir le QR Code définitif.';try{$('#qrLiveImage').src=qrCanvas({...draft,content:draft.content||'Votre texte'}).toDataURL('image/png');$('#qrStatus').textContent='';}catch(error){$('#qrLiveImage').removeAttribute('src');$('#qrStatus').textContent='Contenu trop long pour un QR Code.';}};
  form.oninput=preview;$('#qrCancel').onclick=()=>box.classList.add('hidden');preview();
  form.onsubmit=async event=>{event.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;try{const draft=read();if(draft.kind==='text')draft.active=true;await api('admin/qrcodes',{method:id?'PUT':'POST',body:JSON.stringify(draft)});await renderQRCodes();toast('QR Code enregistré');}catch(error){$('#qrStatus').textContent=error.message;button.disabled=false;}};
  box.scrollIntoView({behavior:'smooth',block:'start'});
};
window.deleteQRCode=async id=>{if(!confirm('Supprimer ce QR Code ? Les liens déjà imprimés deviendront indisponibles.'))return;try{await api('admin/qrcodes?id='+encodeURIComponent(id),{method:'DELETE'});await renderQRCodes();toast('QR Code supprimé');}catch(error){toast(error.message);}};
window.exportQRCode=(id,format)=>{
  const item=qrItems.find(x=>x.id===id);if(!item)return;
  let blob;
  if(format==='svg'){
    const matrix=qrMatrix(qrPayload(item)),count=matrix.getModuleCount(),side=count+8,captionHeight=item.caption?5:0;let modules='';
    for(let row=0;row<count;row++)for(let col=0;col<count;col++)if(matrix.isDark(row,col))modules+=`M${col+4} ${row+4}h1v1h-1z`;
    const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="${item.size}" height="${Math.round(item.size*(side+captionHeight)/side)}" viewBox="0 0 ${side} ${side+captionHeight}"><rect width="100%" height="100%" fill="${item.light}"/><path d="${modules}" fill="${item.dark}" shape-rendering="crispEdges"/>${item.caption?`<text x="${side/2}" y="${side+2}" font-size="${Math.min(2,side*1.4/item.caption.length)}" text-anchor="middle" fill="${item.dark}" font-family="Arial,sans-serif">${esc(item.caption)}</text>`:''}</svg>`;
    blob=new Blob([svg],{type:'image/svg+xml'});save(blob);
  }else qrCanvas(item).toBlob(save,'image/png');
  function save(file){if(!file)return toast('Export indisponible');const url=URL.createObjectURL(file),a=document.createElement('a');a.href=url;a.download=(item.title.replace(/[^a-z0-9_-]/gi,'-')||'qrcode')+'.'+format;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
};
