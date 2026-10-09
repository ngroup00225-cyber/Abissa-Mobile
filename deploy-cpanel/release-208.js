(() => {
  'use strict';
  function accountLinks() {
    const footer=document.querySelector('#rProfile .r-profile-footer');
    if(!footer||footer.querySelector('[data-account-links]'))return;
    const box=document.createElement('div');box.dataset.accountLinks='';box.style.cssText='display:flex;flex-wrap:wrap;justify-content:center;gap:12px;padding:16px 0';
    box.innerHTML='<a href="https://abissamobile.com/confidentialite.php" target="_blank" rel="noopener">Confidentialité</a><a href="https://abissamobile.com/suppression-compte.php" target="_blank" rel="noopener">Suppression du compte</a>';
    if(currentUser){const button=document.createElement('button');button.type='button';button.className='r-button outline';button.textContent='Supprimer mon compte';button.onclick=deleteDialog;box.append(button);}
    footer.append(box);
  }
  function deleteDialog() {
    if(!currentUser||!userToken){showToast('Connectez-vous pour supprimer votre compte.');return;}
    const dialog=document.createElement('dialog');dialog.style.cssText='max-width:420px;width:calc(100% - 32px);border:0;border-radius:16px;padding:24px';
    dialog.innerHTML='<h2>Supprimer mon compte</h2><p>Cette action supprime votre compte, vos photos liées, demandes, badges, commentaires et progression N’zima. Elle est définitive.</p><form><label>Mot de passe<input name="password" type="password" autocomplete="current-password" required style="display:block;width:100%;margin:8px 0 16px"></label><label>Écrivez SUPPRIMER<input name="confirmation" required autocomplete="off" style="display:block;width:100%;margin:8px 0 16px"></label><p role="status"></p><button type="submit" class="r-button blue">Supprimer définitivement</button><button type="button" class="r-button outline" data-cancel>Annuler</button></form>';
    document.body.append(dialog);dialog.querySelector('[data-cancel]').onclick=()=>dialog.close();dialog.onclose=()=>dialog.remove();dialog.showModal();
    dialog.querySelector('form').onsubmit=async event=>{
      event.preventDefault();const form=event.target,button=form.querySelector('[type=submit]'),status=form.querySelector('[role=status]');button.disabled=true;
      try{const fields=new FormData(form);const response=await fetch(API_BASE.replace(/\/$/,'')+'/api/users/me',{method:'DELETE',headers:{Authorization:'Bearer '+userToken,'Content-Type':'application/json'},body:JSON.stringify({password:fields.get('password'),confirmation:fields.get('confirmation')})});const result=await response.json();if(!response.ok)throw new Error(result.error||'Suppression impossible');
        for(const key of Object.keys(localStorage))if(key.startsWith('abissa')||key.startsWith('abissa_'))localStorage.removeItem(key);
        currentUser=null;userToken='';reservations=[];dialog.close();location.reload();
      }catch(error){status.textContent=error.message;button.disabled=false;}
    };
  }
  new MutationObserver(accountLinks).observe(document.body,{childList:true,subtree:true});accountLinks();
})();
