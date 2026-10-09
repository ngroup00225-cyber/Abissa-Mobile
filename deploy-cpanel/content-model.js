const CONTENT_TEMPLATES={
 EVENTS:{id:0,tag:'Événement',title:'',day:'',time:'',place:'',color:['#008cc4','#07567e'],desc:'',glyph:'drum',img:''},
 PROGRAMME:{n:1,title:'',time:'',text:''},
 SITES:{name:'',meta:'',icon:'landmark',color:'#07567e',img:'',desc:'',address:''},
 HOTELS:{type:'hotel',name:'',meta:'',rating:4,color:'#07567e',img:'',photos:[],desc:'',address:'',phone:'',priceList:[]},
 NEWS:{id:'',title:'',date:'',text:'',img:'',likes:0,comments:[]},
 GALLERY:{label:'Abissa 2026',photos:[]},
 PRODUCTS:{category:'',name:'',desc:'',priceFcfa:0,img:''},
 ADVERTISEMENTS:{name:'',category:'Restaurant',stand:'',img:'',logo:'',photos:[],desc:'',phone:'',phone2:'',whatsapp:'',email:'',website:'',address:'',active:true},
 PARTNERS:{name:'',category:'',color:'#008cc4',img:'',photos:[],desc:'',phone:'',website:''},
 HISTOIRE:{title:'',text:''},NZEMA_DICT:{ntzima:'',french:'',phonetic:'',category:''},
 NZIMA_FAMILIES:{name:'',desc:'',img:''},COMMENTS:{author:'',text:'',status:'pending'},
 COMMISSIONS:{name:'',description:'',color:'#d6a024',active:true},
 MENU_ITEMS:{id:'',line1:'',line2:'',icon:'book',color:'#07567e',page:'',gated:false}
};
function ensureContentDefaults(){
 for(const key of Object.keys(CONTENT_TEMPLATES))if(!Array.isArray(state[key]))state[key]=[];
 if(!state.MENU_ITEMS.length)state.MENU_ITEMS=[['programme','Programme','scroll'],['histoire','Histoire','book'],['sites','Sites culturels','palm'],['hotels','Hôtels & restaurants','bed'],['carte','Carte','map'],['reservations','Réservations','ticket'],['galerie','Galerie','image'],['actualites','Actualités','megaphone'],['partenaires','Partenaires','handshake'],['presse','Accréditation','press'],['comite','Comité Abissa','committee'],['langue','Dico N’zima','language']].map(([id,line1,icon])=>({id,line1,line2:'',icon,color:'#07567e',page:id,gated:['presse','reservations','laissez-passer'].includes(id)}));
 state.COMMITTEE=Object.assign({president:{name:'',role:'Président',img:''},vicePresident:{name:'',role:'Vice-président',img:''},commissions:[]},state.COMMITTEE||{});
 state.SETTINGS=Object.assign({fcfaPerUsd:600,fcfaPerEur:655.96,eventStartDate:'2026-10-25T00:00',eventEndDate:'2026-11-08T23:59',facebook:'',instagram:'',twitter:'',tiktok:''},state.SETTINGS||{});
 state.BADGE_SETTINGS={...state.BADGE_SETTINGS,widthMm:95,heightMm:135};
}
function newContent(key){const value=structuredClone(CONTENT_TEMPLATES[key]||{name:''});if(key==='EVENTS')value.id=Date.now();if(key==='NEWS')value.id='news-'+Date.now();if(key==='PROGRAMME')value.n=(state[key]?.length||0)+1;return value;}
window.addNested=encoded=>{const path=JSON.parse(decodeURIComponent(encoded)),[parent,key]=getParent(path);let value={name:'',role:'',img:''};if(key==='commissions')value={name:'',members:[]};if(key==='priceList')value={label:'',priceFcfa:0};if(key==='comments')value={author:'',text:''};parent[key].push(value);dirty=true;render()};
window.removeNested=(encoded,index)=>{const path=JSON.parse(decodeURIComponent(encoded)),[parent,key]=getParent(path);parent[key].splice(index,1);dirty=true;render()};
window.removeImage=encoded=>{setPath(JSON.parse(decodeURIComponent(encoded)),'');render()};
