const {chromium}=require(process.env.ABISSA_PLAYWRIGHT||'@playwright/test');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),web=path.join(root,'ios/AbissaMobile/Web'),out=path.join(root,'ios/review-evidence');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||(process.platform==='win32'?'C:/Program Files/Google/Chrome/Application/chrome.exe':undefined),headless:true});
 const checks=[],errors=[];let mode='success',requests=0;
 const user={id:'fixture-review',name:'Apple Review',username:'apple.review',status:'active'},token='a'.repeat(64);
 try{
  for(const viewport of [{width:393,height:852},{width:834,height:1194},{width:1194,height:834}]){
   const context=await browser.newContext({viewport});
   await context.addInitScript(()=>{window.__nativeCalls=[];window.webkit={messageHandlers:{abissa:{postMessage(p){window.__nativeCalls.push({action:p.action,url:p.url});if(p.action==='publicData')queueMicrotask(()=>window.__abissaPublicDataResult?.(p.id,'{}',null));}}}};});
   await context.route('**/*',async route=>{
    const url=new URL(route.request().url());
    if(url.pathname.startsWith('/appli/api/')){
     if(url.pathname.endsWith('/users/login')){
      requests++;if(mode==='network')return route.abort();
      if(mode==='slow')await new Promise(resolve=>setTimeout(resolve,250));
      if(mode==='invalid')return route.fulfill({status:401,json:{error:'Identifiants incorrects'}});
      if(mode==='limited')return route.fulfill({status:429,json:{error:'Limite'}});
      if(mode==='server')return route.fulfill({status:503,json:{error:'Service indisponible'}});
      if(mode==='html')return route.fulfill({status:200,contentType:'text/html',body:'<h1>Hosting unavailable</h1>'});
      return route.fulfill({json:{user,token:mode==='bad-token'?'':token}});
     }
     if(url.pathname.endsWith('/users/me'))return route.fulfill({status:mode==='expired'?401:200,json:mode==='expired'?{error:'Session expirée'}:{user}});
     return route.fulfill({json:url.pathname.endsWith('/data')?{}:[]});
    }
    if(url.hostname!=='abissamobile.com')return route.abort();
    const name=decodeURIComponent(url.pathname.replace(/^\/appli\//,'')),file=path.resolve(web,name||'abissa_mobile.html');
    if(!file.startsWith(web+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile())return route.abort();
    return route.fulfill({contentType:({'.html':'text/html','.js':'text/javascript','.css':'text/css','.json':'application/json','.svg':'image/svg+xml','.png':'image/png','.woff2':'font/woff2','.wav':'audio/wav'})[path.extname(file)]||'application/octet-stream',body:fs.readFileSync(file)});
   });
   const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
   await page.goto('https://abissamobile.com/appli/abissa_mobile.html');await page.locator('#menuGrid .tile').first().waitFor();
   await page.locator('.r-skip').click();await page.evaluate(()=>{loginMode='user';showLoginMode();goTo('login');});
   assert.equal(await page.locator('#loginId').getAttribute('autocapitalize'),'none');
   for(const [state,message] of [['network','Connexion au serveur impossible'],['invalid','Identifiant ou mot de passe incorrect'],['limited','Trop de tentatives'],['server','temporairement indisponible'],['html','réponse illisible'],['bad-token','non confirmée'],['expired','Session expirée']]){
    mode=state;await page.fill('#loginId','apple.review');await page.fill('#loginPwd','Fixture-only!');
    await page.locator('#loginUserForm button[onclick="attemptUserLogin()"]').click();
    await page.waitForFunction(message=>document.getElementById('iosLoginStatus')?.textContent.includes(message),message);
    assert.equal(await page.evaluate(()=>localStorage.getItem('abissa_user_session')),null);
   }
   mode='slow';const before=requests;await page.evaluate(()=>{attemptUserLogin();attemptUserLogin();});
   await page.waitForFunction(()=>currentUser?.id==='fixture-review');assert.equal(requests-before,1);
   assert.equal(await page.locator('#loginPwd').inputValue(),'');assert.equal(await page.evaluate(()=>currentAdminRole),null);
   await page.evaluate(()=>goTo('profil'));assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   await page.screenshot({path:path.join(out,'profile-'+viewport.width+'.png')});
   await page.evaluate(()=>{SETTINGS.liveSources=[{name:'Diffusion de test',type:'facebook',url:'https://www.facebook.com/example/videos/123',active:true}];goTo('direct');});
   await page.locator('[data-live-index]').first().click();assert.equal(await page.locator('#r209LivePlayer iframe').count(),0);
   assert((await page.evaluate(()=>window.__nativeCalls)).some(p=>p.action==='openWebsite'&&p.url==='https://www.facebook.com/example/videos/123'));
   await page.evaluate(()=>{logoutUser();goTo('login');});assert.equal(await page.evaluate(()=>localStorage.getItem('abissa_user_session')),null);
   checks.push(`${viewport.width}x${viewport.height}: erreurs distinctes, session vérifiée, double clic protégé, profil sans débordement, média externe sans iframe`);
   await context.close();mode='success';
  }
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,'ui-tests.json'),JSON.stringify({engine:'Chromium (responsive web UI, not WKWebView)',checks,errors,physicalIOS:false},null,2));console.log(JSON.stringify({passed:checks.length,errors}));
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
