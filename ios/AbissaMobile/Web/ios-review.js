/* iOS only. Normal server authentication; no review-specific credentials or permissions. */
(()=>{
 'use strict';
 const official='https://abissamobile.com/appli';
 const loginButton=()=>document.querySelector('#loginUserForm button[onclick="attemptUserLogin()"]');
 let signingIn=false;
 function status(message,error=false){
  let node=document.getElementById('iosLoginStatus');
  if(!node){node=document.createElement('p');node.id='iosLoginStatus';node.className='form-status';node.setAttribute('aria-live','polite');document.getElementById('loginUserForm').append(node);}
  node.setAttribute('role',error?'alert':'status');node.textContent=message;
 }
 async function json(route,options={}){
  if(API_BASE!==official)throw Error('Adresse du serveur indisponible. Rouvrez l’application pour réessayer.');
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),15000);
  try{
   let response;
   try{response=await fetch(official+'/api/'+route,{...options,signal:controller.signal,credentials:'omit',cache:'no-store',redirect:'error'});}
   catch(error){throw Error(error.name==='AbortError'?'Le serveur met trop de temps à répondre. Réessayez.':'Connexion au serveur impossible. Vérifiez votre connexion puis réessayez.');}
   let data;try{data=await response.json();}catch{throw Error('Le serveur a renvoyé une réponse illisible. Réessayez plus tard.');}
   if(!response.ok){const e=Error(response.status===401?(route==='users/login'?'Identifiant ou mot de passe incorrect.':'Session expirée. Reconnectez-vous.'):response.status===429?'Trop de tentatives. Réessayez dans 15 minutes.':response.status>=500?'Le serveur est temporairement indisponible. Réessayez plus tard.':data.error||'Connexion refusée.');e.status=response.status;throw e;}
   return data;
  }finally{clearTimeout(timer);}
 }
 window.attemptUserLogin=async function(){
  if(signingIn)return;
  const id=document.getElementById('loginId').value.trim(),password=document.getElementById('loginPwd').value;
  if(!id||!password){status('Saisissez votre identifiant et votre mot de passe.',true);return;}
  const button=loginButton(),label=button?.textContent;
  signingIn=true;if(button){button.disabled=true;button.textContent='Connexion…';}status('Vérification de votre compte…');
  try{
   const result=await json('users/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,password})});
   if(!/^[a-f0-9]{64}$/.test(result.token||'')||!result.user?.id)throw Error('Connexion non confirmée par le serveur. Réessayez.');
   // Verify that this token can actually access protected routes before persisting it.
   const verified=await json('users/me',{headers:{Authorization:'Bearer '+result.token}});
   if(verified.user?.id!==result.user.id||verified.user.status!=='active'||typeof verified.user.name!=='string'||!verified.user.name.trim())throw Error('Ce compte est indisponible. Contactez l’organisation.');
   currentUser=verified.user;userToken=result.token;saveUserSession();
   if(!USERS.some(u=>u.id===currentUser.id))USERS.push(currentUser);
   isAdminLoggedIn=false;currentAdminRole=null;currentAdminPermissions=[];
   document.getElementById('loginPwd').value='';status('Connexion réussie.');renderProfileHeader();
   const target=redirectAfterAuth||'profil';redirectAfterAuth=null;goTo(target);showToast('Bienvenue '+currentUser.name.split(' ')[0]);
  }catch(error){status(error.message,true);}
  finally{signingIn=false;if(button){button.disabled=false;button.textContent=label;}}
 };
 const id=document.getElementById('loginId'),password=document.getElementById('loginPwd');
 id?.setAttribute('autocomplete','username');id?.setAttribute('autocapitalize','none');id?.setAttribute('autocorrect','off');id?.setAttribute('spellcheck','false');
 password?.setAttribute('autocomplete','current-password');password?.setAttribute('enterkeyhint','go');
 for(const input of [id,password])input?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();window.attemptUserLogin();}});
 // A saved profile is not proof of a still-valid server session. Network errors keep the offline cache.
 if(userToken){const token=userToken;json('users/me',{headers:{Authorization:'Bearer '+token}}).then(data=>{if(userToken===token&&data.user?.status==='active'){currentUser=data.user;saveUserSession();renderProfileHeader();}}).catch(error=>{if(error.status===401&&userToken===token){userToken='';currentUser=null;localStorage.removeItem('abissa_user_session');renderProfileHeader();status('Session expirée. Reconnectez-vous.',true);}});}
 // External live providers can include advertising cookies/scripts. Open their page in Safari.
 document.addEventListener('click',event=>{
  const button=event.target.closest('[data-live-index]');if(!button)return;
  const source=(SETTINGS.liveSources||[]).filter(s=>s.active!==false)[Number(button.dataset.liveIndex)];
  let url;try{url=new URL(source?.url);}catch{return;}
  if(url.protocol!=='https:'||url.username||url.password)return;
  if(url.hostname==='abissamobile.com')return;
  event.preventDefault();event.stopImmediatePropagation();
  document.querySelectorAll('[data-live-index]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));
  window.webkit?.messageHandlers?.abissa?.postMessage({action:'openWebsite',url:url.href});
 },true);
})();
