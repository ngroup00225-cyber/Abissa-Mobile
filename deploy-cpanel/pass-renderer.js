/* Shared pass artwork: canvas composition, live drafts and secure server QR. */
(()=>{
 const original=Credential.png,dimensions=Credential.dimensions;
 Credential.dimensions=(s={},type='member',item=null)=>type==='pass'&&item?.access?.orientation==='portrait'?{width:210,height:297}:dimensions(s,type);
 const images=new Map();
 function image(src){const url=new URL(src,location.href).href;if(!images.has(url)){const im=new Image();im.crossOrigin='anonymous';im.src=url;images.set(url,im.decode().then(()=>im).catch(e=>{images.delete(url);throw Error('Image indisponible. Vérifiez son import.');}));}return images.get(url);}
 let source;const colored=new Map();
 async function artwork(color){
  source??=image('assets/pass-official-2026.png');const im=await source;
  if(colored.has(color))return colored.get(color);
  const cv=document.createElement('canvas');cv.width=im.width;cv.height=im.height;const c=cv.getContext('2d');c.drawImage(im,0,0);const pixels=c.getImageData(0,0,cv.width,cv.height),rgb=color.slice(1).match(/../g).map(x=>parseInt(x,16));
  const waveY=new Int32Array(cv.width).fill(-1);for(let x=0;x<cv.width;x++)for(let y=Math.floor(cv.height*.78);y<cv.height*.97;y++){const i=(y*cv.width+x)*4,r=pixels.data[i],g=pixels.data[i+1],b=pixels.data[i+2];if(r>175&&g>110&&g<245&&b<130&&r>b*1.6&&g>b*1.4)waveY[x]=y;}
  // Recolor only graphic trim, preserving the official seal and cultural photography.
  for(let y=0;y<cv.height;y++)for(let x=0;x<cv.width;x++){
   const nx=x/cv.width,ny=y/cv.height,trim=(nx>.041&&nx<.064&&ny<.40)||(waveY[x]>=0&&Math.abs(y-waveY[x])<cv.height*.015)||(nx>.725&&ny>.23&&ny<.30)||(ny>.325&&ny<.46);
   if(!trim)continue;const i=(y*cv.width+x)*4,r=pixels.data[i],g=pixels.data[i+1],b=pixels.data[i+2];
   if(r>175&&g>110&&g<245&&b<130&&r>b*1.6&&g>b*1.4){const light=Math.max(.65,Math.min(1.18,(r+g)/430));for(let k=0;k<3;k++)pixels.data[i+k]=Math.min(255,Math.round(rgb[k]*light));}
  }
  c.putImageData(pixels,0,0);colored.set(color,cv);if(colored.size>12)colored.delete(colored.keys().next().value);return cv;
 }
 Credential.png=async(item,settings={},verifyUrl='',options={})=>{
  if(item.type!=='pass')return original(item,settings,verifyUrl,options);
  const p=item.access,a={...AbissaPassEditor.defaults(),...p?.appearance},color=a.color;
  if(!p?.profile||p.status==='unconfigured'||(!options.draft&&!verifyUrl.includes('access-control.html')))throw Error('Configurez les accès avant l’impression.');
  const portrait=p.orientation==='portrait',mm=portrait?{width:210,height:297}:{width:297,height:210},canvas=document.createElement('canvas'),density=options.preview?5:12;
  canvas.width=Math.round(mm.width*density);canvas.height=Math.round(mm.height*density);const c=canvas.getContext('2d'),w=canvas.width,h=canvas.height,bg=await artwork(color);
  c.fillStyle='#f8fcff';c.fillRect(0,0,w,h);
  if(portrait){c.drawImage(bg,0,0,bg.width,bg.height*.32,0,0,w,w*bg.height*.32/bg.width);c.drawImage(bg,0,bg.height*.86,bg.width,bg.height*.14,0,h*.88,w,h*.12);}else c.drawImage(bg,0,0,w,h);
  const text=(value,x,y,size,max=.86,ink='#052768',align='left')=>{let px=w*size;c.font=`700 ${px}px Arial`;while(c.measureText(String(value)).width>w*max&&px>9){px--;c.font=`700 ${px}px Arial`;}c.fillStyle=ink;c.textAlign=align;c.fillText(String(value||''),w*x,h*y,w*max);};
  const cover=(im,x,y,bw,bh)=>{const scale=Math.max(w*bw/im.width,h*bh/im.height);c.save();c.beginPath();c.rect(w*x,h*y,w*bw,h*bh);c.clip();c.drawImage(im,w*(x+bw/2)-im.width*scale/2,h*(y+bh/2)-im.height*scale/2,im.width*scale,im.height*scale);c.restore();};
  if(a.topImage!==null){const x=.73,y=0,bw=.27,bh=portrait?.16:.25;if(!a.topImage){const clear=c.createLinearGradient(w*x,0,w,0);clear.addColorStop(0,'#f8fcff00');clear.addColorStop(.3,'#f8fcff');clear.addColorStop(1,'#f8fcff');c.fillStyle=clear;c.fillRect(w*x,h*y,w*bw,h*bh);}
   if(a.topImage){const im=await image(a.topImage),layer=document.createElement('canvas');layer.width=Math.round(w*bw);layer.height=Math.round(h*bh);const lc=layer.getContext('2d'),scale=Math.max(layer.width/im.width,layer.height/im.height);lc.drawImage(im,(layer.width-im.width*scale)/2,(layer.height-im.height*scale)/2,im.width*scale,im.height*scale);lc.globalCompositeOperation='destination-in';const mask=lc.createLinearGradient(0,0,layer.width,0);mask.addColorStop(0,'#0000');mask.addColorStop(.28,'#000');mask.addColorStop(1,'#000');lc.fillStyle=mask;lc.fillRect(0,0,layer.width,layer.height);const bottom=lc.createLinearGradient(0,0,0,layer.height);bottom.addColorStop(0,'#000');bottom.addColorStop(.7,'#000');bottom.addColorStop(1,'#0000');lc.fillStyle=bottom;lc.fillRect(0,0,layer.width,layer.height);c.drawImage(layer,w*x,h*y);}
  }
  c.strokeStyle=color;c.lineWidth=w*.009;c.beginPath();c.moveTo(w*.995,0);c.lineTo(w*.995,h*.86);c.stroke();
  c.beginPath();c.moveTo(w*.051,0);c.bezierCurveTo(w*.063,h*.1,w*.052,h*.28,w*.06,h*(portrait?.86:.465));c.stroke();
  const by=portrait?.21:.326,bh=portrait?.075:.135;
  c.fillStyle=color;c.beginPath();c.roundRect(w*.103,h*by,w*.795,h*bh,w*.015);c.fill();
  const rgb=color.slice(1).match(/../g).map(x=>parseInt(x,16)),light=rgb[0]*.299+rgb[1]*.587+rgb[2]*.114>165;
  text(a.title,.5,by+bh*.75,.067,.74,light?'#052768':'#ffffff','center');
  const left=portrait?.09:.276,top=portrait?.345:.52,width=portrait?.82:.438;
  if(!portrait){const grad=c.createLinearGradient(w*.268,0,w*.988,0);grad.addColorStop(0,'#eef8ff');grad.addColorStop(1,'#f8fcff');c.fillStyle=grad;c.fillRect(w*.268,h*.466,w*.721,h*.393);}
  text(a.heading,left,top,.037,width);c.fillStyle=color;c.fillRect(w*left,h*(top+.013),w*Math.min(.23,width),h*.006);
  const start=top+(portrait?.046:.057),end=portrait?.69:.836,rowHeight=(end-start)/Math.max(4,a.fields.length),radius=w*(portrait?.025:.023);
  function icon(kind,x,y){c.save();c.translate(w*x,h*y);c.fillStyle=color;c.beginPath();c.arc(0,0,radius,0,Math.PI*2);c.fill();c.strokeStyle='#fff';c.fillStyle='#fff';c.lineWidth=Math.max(1.5,w*.0018);const r=radius*.6;
   if(kind==='people'){for(const dx of [-.48,.48]){c.beginPath();c.arc(r*dx,-r*.35,r*.25,0,Math.PI*2);c.fill();c.fillRect(r*(dx-.35),r*.05,r*.7,r*.7);}}
   else if(kind==='document'){c.strokeRect(-r*.55,-r*.8,r*1.1,r*1.6);for(let i=0;i<3;i++){c.beginPath();c.moveTo(-r*.35,-r*.35+i*r*.4);c.lineTo(r*.35,-r*.35+i*r*.4);c.stroke();}}
   else if(kind==='qr'){for(const [x,y] of [[-.8,-.8],[.15,-.8],[-.8,.15]]){c.strokeRect(r*x,r*y,r*.65,r*.65);}c.fillRect(r*.2,r*.2,r*.4,r*.4);}
   else if(kind==='shield'){c.beginPath();c.moveTo(0,-r);c.lineTo(r*.75,-r*.6);c.lineTo(r*.65,r*.4);c.lineTo(0,r);c.lineTo(-r*.65,r*.4);c.lineTo(-r*.75,-r*.6);c.closePath();c.stroke();c.beginPath();c.moveTo(-r*.4,0);c.lineTo(-r*.05,r*.35);c.lineTo(r*.45,-r*.3);c.stroke();}
   else if(kind==='pin'){c.beginPath();c.arc(0,-r*.2,r*.55,Math.PI*.15,Math.PI*.85,true);c.lineTo(0,r);c.closePath();c.stroke();}
   else if(kind==='calendar'){c.strokeRect(-r*.7,-r*.65,r*1.4,r*1.4);c.beginPath();c.moveTo(-r*.7,-r*.25);c.lineTo(r*.7,-r*.25);c.stroke();}
   else{c.beginPath();for(let i=0;i<10;i++){const t=-Math.PI/2+i*Math.PI/5,rr=i%2?r*.4:r;c.lineTo(Math.cos(t)*rr,Math.sin(t)*rr);}c.closePath();c.stroke();}c.restore();
  }
  function wrap(value,x,y,max,fs,available){let size=fs;const linesFor=s=>{c.font=`700 ${w*s}px Arial`;const lines=[];let line='';for(const word of String(value).split(/\s+/)){const candidate=line?line+' '+word:word;if(c.measureText(candidate).width>w*max&&line){lines.push(line);line=word;}else line=candidate;}if(line)lines.push(line);return lines;};let lines=linesFor(size);while(lines.length*w*size*1.2>h*available&&size>.006){size-=.001;lines=linesFor(size);}lines.forEach((l,i)=>text(l,x,y+i*w*size*1.2/h,size,max));}
  a.fields.forEach((f,i)=>{const y=start+i*rowHeight+rowHeight*.35,ix=left+(portrait?.025:.032);icon(f.icon,ix,y);const tx=left+(portrait?.068:.074),tw=width-(tx-left);wrap([f.label,f.value].filter(Boolean).join(' : '),tx,y+.008,tw,portrait?.021:.019,rowHeight*.85);});
  c.strokeStyle=color;c.lineWidth=h*.012;c.beginPath();c.moveTo(0,h*.84);c.bezierCurveTo(w*.30,h*1.05,w*.62,h*.82,w,h*.825);c.stroke();
  // Fine guilloche stays outside the mandatory four-module white QR quiet zone.
  const qx=portrait?.63:.772,qy=portrait?.715:.516,side=w*(portrait?.25:.178),qrboxX=w*qx-w*.012,qrboxY=h*qy-h*.018,qrboxW=side+w*.024,qrboxH=side+h*(portrait?.061:.071);
  c.fillStyle='#fff';c.fillRect(qrboxX,qrboxY,qrboxW,qrboxH);c.save();c.beginPath();c.rect(qrboxX,qrboxY,qrboxW,qrboxH);c.clip();c.strokeStyle=color;c.globalAlpha=.22;c.lineWidth=w*.00035;
  for(let i=0;i<42;i++){c.beginPath();for(let x=0;x<qrboxW;x+=4){const y=qrboxY+i*qrboxH/36+Math.sin(x/12+i)*h*.004;if(x===0)c.moveTo(qrboxX+x,y);else c.lineTo(qrboxX+x,y);}c.stroke();}c.restore();c.strokeStyle=color;c.lineWidth=w*.0015;c.strokeRect(qrboxX,qrboxY,qrboxW,qrboxH);
  c.save();c.globalAlpha=.055;c.translate(w*.48,h*.76);c.rotate(-.2);text('ABISSA · ORIGINAL',0,0,.04,.8,color,'center');c.restore();
  const sx=Math.round(w*qx),sy=Math.round(h*qy),sz=Math.round(side);c.fillStyle='#fff';c.fillRect(sx,sy,sz,sz);
  if(options.draft){text('QR APRÈS',qx+.089,qy+.06,.013,.15,'#052768','center');text('ENREGISTREMENT',qx+.089,qy+.095,.011,.15,'#052768','center');}
  else{const box=document.createElement('div'),qr=new QRCode(box,{text:verifyUrl,width:512,height:512,correctLevel:QRCode.CorrectLevel.M}),matrix=qr._oQRCode,n=matrix.getModuleCount(),cell=Math.floor(sz/(n+8)),actual=cell*(n+8),inset=Math.floor((sz-actual)/2);c.fillStyle='#061c38';for(let r=0;r<n;r++)for(let col=0;col<n;col++)if(matrix.isDark(r,col))c.fillRect(sx+inset+(col+4)*cell,sy+inset+(r+4)*cell,cell,cell);}
  const labelY=(sy+sz)/h+(portrait?.014:.022);text('SCANNER POUR VÉRIFICATION',qx+side/w/2,labelY,.0105,qrboxW/w-.012,'#052768','center');text(p.number||'BROUILLON',qx+side/w/2,labelY+(portrait?.017:.025),.010,qrboxW/w-.012,color,'center');
  c.fillStyle='#052768';c.fillRect(0,h*.937,w,h*.063);
  if(a.bottomImage){const im=await image(a.bottomImage),scale=Math.min(w*.18/im.width,h*.057/im.height);c.drawImage(im,w*.025+(w*.18-im.width*scale)/2,h*.94+(h*.057-im.height*scale)/2,im.width*scale,im.height*scale);}
  if(a.bottomText)text(a.bottomText,a.bottomImage?.59:.5,.98,.017,a.bottomImage?.73:.87,'#fff','center');
  if(options.draft)text('APERÇU · NON VALIDE',.5,.928,.013,.5,'#052768','center');
  return canvas.toDataURL('image/png');
 };
})();
