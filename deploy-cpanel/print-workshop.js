/* One integrated selection, physical page layout and PDF path for every badge copy. */
(()=>{
 let quantities={},revision=0,settingsLoaded=false;
 const cache=new Map(),call=(r,m='GET',b)=>api(r,{method:m,...(b!==undefined?{body:JSON.stringify(b)}:{})});
 const quantityKey=()=>'abissa_badge_quantities_'+(admin?.id||admin?.username||'admin');
 const params=()=>({widthMm:95,heightMm:135,orientation:'portrait',marginMm:6,gapMm:8,layout:'a4'});
 const cm=v=>(v/10).toLocaleString('fr',{maximumFractionDigits:2});
 function geometry(p){const pageWidth=p.layout==='badge'?p.widthMm:p.orientation==='landscape'?297:210,pageHeight=p.layout==='badge'?p.heightMm:p.orientation==='landscape'?210:297,margin=p.layout==='badge'?0:p.marginMm;
  if(!Number.isFinite(p.widthMm)||!Number.isFinite(p.heightMm)||p.widthMm<20||p.widthMm>297||p.heightMm<20||p.heightMm>420||margin<0||margin>30)throw Error('Dimensions ou marges hors limites.');
  const gap=p.layout==='badge'?0:Number(p.gapMm??8),columns=p.layout==='badge'?1:Math.floor((pageWidth-2*margin+gap)/(p.widthMm+gap)),rows=p.layout==='badge'?1:Math.floor((pageHeight-2*margin+gap)/(p.heightMm+gap));if(columns<1||rows<1)throw Error('Le badge ne tient pas sur la feuille.');return {...p,pageWidth,pageHeight,margin,gap,columns,rows,perPage:columns*rows};
 }
 function fitPages(target){const style=getComputedStyle(target),available=target.clientWidth-parseFloat(style.paddingLeft)-parseFloat(style.paddingRight);target.querySelectorAll('.physical-page').forEach(page=>{page.style.zoom=Math.min(1,available/(Number(page.dataset.pageWidth)*96/25.4));});}
 const previewResize=new ResizeObserver(entries=>entries.forEach(({target})=>fitPages(target)));
 async function imageFor(id){const item=badgeRecord(id),key=JSON.stringify([id,item?.printStatus,item?.data,state.BADGE_SETTINGS?.frontBackground,state.BADGE_SETTINGS?.widthMm,state.BADGE_SETTINGS?.heightMm]);if(!cache.has(key)){cache.set(key,badgeImages(item).then(im=>im.front).catch(e=>{cache.delete(key);throw e;}));}return cache.get(key);}
 const selection=()=>selectedBadgeIds().map(id=>({id,quantity:quantities[id]||1}));
 function syncQuantities(){document.querySelectorAll('[data-quantity-row]').forEach(row=>{const id=row.dataset.quantityRow,selected=badgeSelectedIds.has(id);row.querySelector('.badge-quantity').hidden=!selected;});}
 function controls(){return '<div><strong>Disposition</strong><p>A4 · 4 badges par page (2 × 2)<br>Espace de découpe : 8 mm entre les badges</p></div>';}
 setBadgeLayout=()=>{badgeLayout=4;updateBadgePreview();};
 const draw=drawBadgeWorkshop;drawBadgeWorkshop=function(){draw();$('#content').classList.add('print-workshop');document.querySelector('[data-badge-type="pass"]')?.remove();
  const h2=$('#content .page-head h2');h2.textContent='Impression';h2.nextElementSibling.id='badgeFormatCaption';h2.nextElementSibling.textContent=`Badges ${cm(params().widthMm)} × ${cm(params().heightMm)} cm`;
  const controlsHost=$('#content .badge-controls');controlsHost.innerHTML=controls()+ (canAccess('BADGES_MANAGE')?'<div><strong>Votre arrière-plan (facultatif)</strong><input type="file" accept="image/jpeg,image/png,image/webp" onchange="uploadBadgeBackground(this)"><button onclick="removeBadgeBackground()">Retirer le fond</button></div>':'');
  document.querySelectorAll('.badge-check').forEach(check=>{const id=check.value,label=check.closest('label'),row=document.createElement('div');row.className='badge-pick-row';row.dataset.quantityRow=id;label.before(row);row.append(label);row.insertAdjacentHTML('beforeend',`<div class="badge-quantity" ${check.checked?'':'hidden'}><input type="number" inputmode="numeric" min="1" max="100" step="1" value="${quantities[id]||1}" aria-label="Quantité pour ${esc(badgeRecord(id).data.name)}"></div>`);const input=row.querySelector('.badge-quantity input'),set=value=>{quantities[id]=Math.max(1,Math.min(100,Math.floor(Number(value)||1)));input.value=quantities[id];localStorage.setItem(quantityKey(),JSON.stringify(quantities));updateBadgePreview();};input.oninput=()=>{if(input.value!=='')set(input.value);};input.onchange=()=>set(input.value);check.onchange=()=>{if(check.checked&&!quantities[id])quantities[id]=1;updateBadgePreview();};});
  $('#badgePreview').className='print-pages';
  previewResize.disconnect();previewResize.observe($('#badgePreview'));
  updateBadgePreview();
 };
 const oldRender=renderBadges;renderBadges=async()=>{if(!settingsLoaded){try{Object.assign(state.BADGE_SETTINGS,await call('admin/badge-print-settings'));}catch(e){toast(e.message);}try{quantities=Object.fromEntries(Object.entries(JSON.parse(localStorage.getItem(quantityKey())||'{}')).filter(([id,n])=>Number.isInteger(n)&&n>=1&&n<=100));}catch{quantities={};}settingsLoaded=true;}cache.clear();await oldRender();};
 updateBadgePreview=async()=>{
  const v=++revision,target=$('#badgePreview');if(!target)return;const chosen=selection(),total=chosen.reduce((n,x)=>n+x.quantity,0);syncQuantities();$('#badgeCount').textContent=`${chosen.length} personne(s) sélectionnée(s) · ${total} badges à imprimer`;
  if(!total){target.textContent='Sélectionnez au moins un document.';return;}if(total>500){target.textContent='500 exemplaires maximum par travail.';return;}
  try{const g=geometry(params()),sources=await Promise.all(chosen.map(x=>imageFor(x.id)));if(v!==revision||!target.isConnected)return;const images=sources.flatMap((src,i)=>Array.from({length:chosen[i].quantity},()=>src));target.innerHTML='';for(let i=0;i<images.length;i+=g.perPage){const page=document.createElement('div');page.className='physical-page';page.dataset.pageWidth=g.pageWidth;page.style.cssText=`width:${g.pageWidth}mm;height:${g.pageHeight}mm;padding:${g.margin}mm;grid-template-columns:repeat(${g.columns},${g.widthMm}mm);grid-auto-rows:${g.heightMm}mm;gap:${g.gap}mm`;for(const src of images.slice(i,i+g.perPage)){const im=new Image();im.src=src;im.alt=`Badge ${cm(g.widthMm)} × ${cm(g.heightMm)} cm`;im.style.width=g.widthMm+'mm';im.style.height=g.heightMm+'mm';page.append(im);}target.append(page);}fitPages(target);$('#badgeCount').textContent+=` · ${Math.ceil(total/g.perPage)} page(s) · ${g.columns} × ${g.rows} par feuille`;
  }catch(e){if(v===revision&&target.isConnected)target.textContent=e.message;}
 };
 async function exportPDF(print=false){const chosen=selection();if(!chosen.length)return toast('Sélectionnez des badges.');const p=params();geometry(p);const popup=print?window.open('','_blank'):null;if(print&&!popup)throw Error('Autorisez la fenêtre PDF pour imprimer.');if(popup)popup.document.body.textContent='Préparation du PDF d’impression…';try{await call('admin/badge-print-settings','PUT',p);const preview=await call('admin/badges/print-preview','POST',{...p,items:chosen});const sources=await Promise.all(chosen.map(x=>imageFor(x.id))),images=sources.flatMap((src,i)=>Array.from({length:chosen[i].quantity},()=>src)),blob=await AbissaBadgeDesigner.pdf(images,preview);await call('admin/badge-print-jobs','POST',{...p,items:chosen});const url=URL.createObjectURL(blob);
  if(print){popup.addEventListener('load',()=>{try{popup.focus();popup.print();}catch{}},{once:true});popup.location.href=url;}
  else{const link=document.createElement('a');link.href=url;link.download='ABISSA-badges.pdf';link.click();}setTimeout(()=>URL.revokeObjectURL(url),60000);return {blob,preview};}catch(e){popup?.close();throw e;}
 }
 printSelectedBadges=()=>exportPDF(false).catch(e=>toast(e.message));
 // The two explicit actions share the identical physical PDF, including page breaks.
 document.addEventListener('click',e=>{const b=e.target.closest('.print-workshop [onclick="printSelectedBadges()"]');if(b?.classList.contains('gold-button')){e.preventDefault();e.stopImmediatePropagation();exportPDF(true).catch(e=>toast(e.message));}},true);
 markBadgesPrinted=async printed=>{const chosen=selection();if(!chosen.length)return toast('Sélectionnez des badges.');try{await call('admin/credential-prints','PATCH',{ids:chosen.map(x=>x.id),printed,quantities:Object.fromEntries(chosen.map(x=>[x.id,x.quantity]))});await renderBadges();toast('Statut d’impression enregistré.');}catch(e){toast(e.message);}};
 window.AbissaPrintWorkshop={geometry,exportPDF,params,selection};
})();
