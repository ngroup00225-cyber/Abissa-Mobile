/* Final home corrections: preserve editorial data and menu actions. */
(()=>{
 const icons=new Set(['programme','histoire','sites','hotels','galerie','actualites','partenaires','accreditation','comite','langue','lexique','direct','guides','palais','site-web','boutique','publicite','carte','blog','feedback']);
 const asset=name=>window.ABISSA_211_ASSETS?.[name]||new URL('assets/'+name,API_BASE+'/').href;
 const menu=renderMenuGrid;renderMenuGrid=function(){menu();document.querySelectorAll('#menuGrid .tile').forEach(b=>{let page=b.dataset.communityPage;try{page??=JSON.parse(b.dataset.rArgs||'[]')[0];}catch{}if(!icons.has(page))return;const span=b.querySelector('.icon');if(span){const im=new Image();im.src=asset('fluent-color/'+page+'.svg');im.alt='';im.width=46;im.height=46;span.replaceChildren(im);}b.querySelector('.a211-tile-arrow')?.remove();});};
 const carousel=renderCarousel;renderCarousel=function(){carousel();const host=document.getElementById('carousel');for(const [cls,label,icon] of [['c210-carousel-prev','Diapositive précédente','ri-arrow-left-s-line'],['r-carousel-next','Diapositive suivante','ri-arrow-right-s-line']]){const b=document.querySelector('.'+cls);if(b){host.append(b);b.setAttribute('aria-label',label);b.innerHTML='<i class="'+icon+'" aria-hidden="true"></i>';}}};
 document.documentElement.style.setProperty('--a211-menu-image','url("'+asset('refonte-2026/coast.png')+'")');
 renderMenuGrid();renderCarousel();
})();
