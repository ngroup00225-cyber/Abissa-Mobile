/* The same single-face image is used by the workshop, print and verification. */
window.Credential=(()=>{
 const defaultLogo='assets/logo-badge-default-2026.png';
 function photoSource(src){if(!src)return defaultLogo;try{const url=new URL(src,location.href),name=url.pathname.split('/').pop();if(url.pathname.includes('/assets/')&&/^(?:logo(?:[-_.].*)?|abissa-2026|badge-default|default-(?:avatar|photo)|avatar-default|icon(?:[-_.].*)?|favicon.*)\.(?:png|jpe?g|webp|gif)$/i.test(name))return defaultLogo;}catch{}return src;}
 const dimensions=(settings={},type='member')=>type==='pass'?{width:297,height:210}:{width:95,height:135};
 const images=new Map();
 function loadImage(src){const url=new URL(src,location.href).href;if(!images.has(url)){const image=new Image();image.crossOrigin='anonymous';image.src=url;images.set(url,image.decode().then(()=>image).catch(error=>{images.delete(url);throw error}));}return images.get(url);}
 async function png(item,settings={},verifyUrl='',options={}){
  const pass=item.type==='pass',press=item.type==='press',{width,height}=dimensions(settings,item.type),canvas=document.createElement('canvas');
  canvas.width=Math.round(width*12);canvas.height=Math.round(height*12);
  const content=item.design?.standard?item.design.overrides||{}:{};
  const c=canvas.getContext('2d'),w=canvas.width,h=canvas.height,d={...item.data,...content};
  const rect=(x,y,rw,rh,color)=>{c.fillStyle=color;c.fillRect(w*x,h*y,w*rw,h*rh)};
  function text(value,x,y,size,color='#082e7c',align='center',max=.84,font='Arial',weight=700,stretch=1){c.textAlign=align;c.textBaseline='alphabetic';c.fillStyle=color;let fs=w*size;const valueText=String(value||'');c.font=`${weight} ${fs}px ${font}`;while(c.measureText(valueText).width*stretch>w*max&&fs>10){fs--;c.font=`${weight} ${fs}px ${font}`;}c.save();c.translate(w*x,h*y);c.scale(stretch,1);c.fillText(valueText,0,0,w*max/stretch);c.restore();}
  function cover(img,x,y,rw,rh){const scale=Math.max(w*rw/img.width,h*rh/img.height);c.save();c.beginPath();c.rect(w*x,h*y,w*rw,h*rh);c.clip();c.drawImage(img,w*(x+rw/2)-img.width*scale/2,h*(y+rh/2)-img.height*scale/2,img.width*scale,img.height*scale);c.restore();}
  function contain(img,x,y,rw,rh){const scale=Math.min(w*rw/img.width,h*rh/img.height);c.drawImage(img,w*(x+rw/2)-img.width*scale/2,h*(y+rh/2)-img.height*scale/2,img.width*scale,img.height*scale);}
  function qr(x,y,rw){if(!verifyUrl)return;const box=document.createElement('div'),code=new QRCode(box,{text:verifyUrl,width:256,height:256,correctLevel:QRCode.CorrectLevel.M}),matrix=code._oQRCode,count=matrix.getModuleCount(),side=Math.round(w*rw),cell=Math.floor(side/(count+8)),grid=(count+8)*cell,left=Math.round(w*x),top=Math.round(h*y),inset=Math.floor((side-grid)/2);c.fillStyle='#fff';c.fillRect(left,top,side,side);if(!pass){c.strokeStyle='#725f94';c.lineWidth=2;c.strokeRect(left-2,top-2,side+4,side+4);}c.fillStyle='#071b2b';for(let row=0;row<count;row++)for(let col=0;col<count;col++)if(matrix.isDark(row,col))c.fillRect(left+inset+(col+4)*cell,top+inset+(row+4)*cell,cell,cell);}
  if(pass){
   const logo=await loadImage(defaultLogo);
   rect(0,0,1,1,'#fff');
   rect(.035,.045,.93,.91,'#fff');rect(.035,.045,.014,.91,'#00a6d8');rect(.049,.045,.916,.014,'#fdda63');
   contain(logo,.07,.085,.13,.25);text('ABISSA 2026',.57,.15,.048);text('ROYAUTÉ N’ZIMA KOTOKO · GRAND-BASSAM',.57,.20,.014,'#59727c');
   rect(.235,.25,.68,.125,'#073e59');text('LAISSEZ-PASSER',.575,.333,.051,'#fff');text('VÉHICULE',.10,.425,.013,'#67808a','left');
   text(d.vehicle||'Véhicule autorisé',.10,.487,.028,'#073e59','left',.58);rect(.09,.525,.55,.125,'#eaf5f8');text(d.plate||'IMMATRICULATION',.365,.615,.052,'#073e59','center',.50);
   text('TITULAIRE',.10,.715,.013,'#67808a','left');text(d.name||d.fullName||'Titulaire',.10,.765,.025,'#073e59','left',.55);
   text(d.validUntil?'VALABLE JUSQU’AU '+d.validUntil.split('-').reverse().join('/'):'VALIDITÉ SELON AUTORISATION',.10,.85,.016,'#9b762c','left',.55);
   qr(.72,.465,.20);text('CONTRÔLE D’ACCÈS',.82,.81,.013);rect(.07,.90,.86,.003,'#fdda63');text('ABISSA 2026 · GRAND-BASSAM',.5,.933,.011,'#59727c');
   return canvas.toDataURL('image/png');
  }
  // Only the identity layer is drawn; the organizer supplies any background.
  const direction=item.source==='leader'||String(item.id||'').startsWith('leader-')||/bureau ex[ée]cutif|direction du comité/i.test(d.commission||'');
  const label=String(direction?'BUREAU EXÉCUTIF':d.commission||d.organization||'COMITÉ ABISSA').trim().toLocaleUpperCase('fr');
  const accent=/^#[0-9a-f]{6}$/i.test(d.commissionColor||'')?d.commissionColor:direction?'#725f94':'#07567e';
  if(!options.transparent&&settings.frontBackground)cover(await loadImage(settings.frontBackground),0,0,1,1);
  const logo=await loadImage(defaultLogo);let portrait=logo;
  const photo=photoSource(d.photo);
  if(photo!==defaultLogo){try{portrait=await loadImage(photo);}catch{}}
  const cx=w*.5,cy=h*.352,r=w*.178;
  c.fillStyle=accent;c.beginPath();c.arc(cx,cy,r+w*.007,0,Math.PI*2);c.fill();
  c.fillStyle='#fff';c.beginPath();c.arc(cx,cy,r+w*.0015,0,Math.PI*2);c.fill();
  c.save();c.beginPath();c.arc(cx,cy,r,0,Math.PI*2);c.clip();
  if(portrait===logo)contain(logo,.5-r/w,.352-r/h,2*r/w,2*r/h);else cover(portrait,.5-r/w,.352-r/h,2*r/w,2*r/h);c.restore();
  function balancedLines(value,size,max){
   const words=String(value).trim().split(/\s+/);c.font=`700 ${w*size}px Arial`;
   if(c.measureText(value).width<=w*max||words.length<2)return [String(value)];
   let split=1,best=Infinity;for(let i=1;i<words.length;i++){const a=c.measureText(words.slice(0,i).join(' ')).width,b=c.measureText(words.slice(i).join(' ')).width,score=Math.max(a,b);if(score<best){best=score;split=i;}}
   return [words.slice(0,split).join(' '),words.slice(split).join(' ')];
  }
  function drawLines(lines,baseline,step,size,color,max=.88){
   let fs=size;c.font=`700 ${w*fs}px Arial`;
   while(lines.some(line=>c.measureText(line).width>w*max)&&fs>.018){fs-=.001;c.font=`700 ${w*fs}px Arial`;}
   lines.forEach((line,i)=>text(line,.5,baseline+i*step,fs,color,'center',max));
  }
  const name=String(d.name||d.fullName||[d.lastName,d.firstName].filter(Boolean).join(' ')||'Titulaire').trim().toLocaleUpperCase('fr'),words=name.split(/\s+/);
  const split=Math.min(3,words.length-1);
  const nameLines=words.length>=3?[words.slice(0,split).join(' '),words.slice(split).join(' ')]:balancedLines(name,.055,.82);
  drawLines(nameLines,nameLines.length===1?.550:.528,.043,.055,'#003b93',.82);
  const roleLines=balancedLines(String(d.role||(press?'Presse':'Membre')),.048,.78);
  drawLines(roleLines,.620,.038,.048,'#d22032',.78);
  const commission=/^COMMISSION\s+(.+)$/i.exec(label);
  const labelLines=commission?['COMMISSION',commission[1]]:balancedLines(label,.075,.88);
  drawLines(labelLines,roleLines.length>1?.705:.680,.058,.075,accent);
  // Reserve the bottom 6% for the organizer's "ABISSA 2026" footer.
  qr(.38,.770,.24);
  if(options.crop){
   const layer=document.createElement('canvas');layer.width=Math.round(w*.90);layer.height=Math.round(h*.97);
   layer.getContext('2d').drawImage(canvas,Math.round(w*.05),Math.round(h*.015),layer.width,layer.height,0,0,layer.width,layer.height);
   return layer.toDataURL('image/png');
  }
  return canvas.toDataURL('image/png');
 }
 return {png,dimensions,photoSource,defaultLogo};
})();
