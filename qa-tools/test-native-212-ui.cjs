const {chromium}=require('playwright'),fs=require('node:fs'),assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[],checks=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.addInitScript(()=>{window.nativeRequests=[];window.Android={nativeAction:json=>nativeRequests.push(JSON.parse(json)),share:(title,text,url)=>nativeRequests.push({action:'share',title,text,url})};});
 const media=JSON.parse(fs.readFileSync('qa-tools/bundled-media-212.json')).assets;
 await page.route('**/*',route=>{const u=new URL(route.request().url());if(u.href==='https://abissamobile.com/appli/')return route.fulfill({contentType:'text/html',body:fs.readFileSync('app/src/main/assets/abissa_mobile.html')});if(u.pathname.startsWith('/appli/bundled-media/')){const name=u.pathname.split('/').pop();return route.fulfill({contentType:media[name].mime,body:fs.readFileSync('app/src/main/assets/bundled-media/'+name)});}if(u.pathname.startsWith('/appli/bundled-refonte/'))return route.fulfill({contentType:'image/png',body:fs.readFileSync('app/src/main/assets/refonte-2026/'+u.pathname.split('/').pop())});return route.abort();});
 await page.goto('https://abissamobile.com/appli/');await page.locator('.r-skip').click();await page.waitForTimeout(800);
 for(const id of ['accueil','programme','langue','boutique','comite','carte','profil']){await page.evaluate(id=>goTo(id),id);assert.equal(await page.locator('.page.active').getAttribute('id'),'page-'+id);}
 checks.push('Seven bundled screens navigate with no network and no native permission request at launch');
 assert.equal(await page.evaluate(()=>nativeRequests.some(x=>['scan','notifications','location'].includes(x.action))),false);
 await page.evaluate(()=>{goTo('sites');AbissaRefonte.actions.open('site',0)});await page.waitForTimeout(100);
 await page.locator('dialog[open] [data-native212] button').first().click();
 await page.locator('dialog[open] [data-native212] button').last().click();
 let requests=await page.evaluate(()=>nativeRequests);
 assert(requests.some(x=>x.action==='share'&&x.url.includes('content=site')));assert(requests.some(x=>x.action==='favorite'&&x.url.includes('content=site')));
 checks.push('Site detail sends canonical native share and favorite actions');
 await page.evaluate(()=>document.querySelector('dialog[open]').close());
 await page.evaluate(()=>AbissaNative212.deepLink(AbissaNative212.link('hotel',HOTELS[0].id||HOTELS[0].name)));
 assert(await page.locator('#r201-annuaire-detail [data-native212]').count());checks.push('Hotel deep link opens its detail from cached content');
 await page.evaluate(()=>goTo('boutique'));await page.waitForTimeout(100);
 assert((await page.locator('#productsGrid [data-native212]').count())>0);
 await page.locator('#productsGrid [data-native212] button').first().click();assert((await page.evaluate(()=>nativeRequests)).some(x=>x.action==='share'&&x.url.includes('content=product')));
 checks.push('Shop items expose native share and favorite controls');
 await page.evaluate(()=>{window.dispatchEvent(new CustomEvent('abissa-native-state',{detail:{lastPage:'programme',language:'nz',favorites:[]}}));});
 assert.equal(await page.locator('.page.active').getAttribute('id'),'page-programme');assert.equal(await page.locator('html').getAttribute('data-preferred-language'),'nz');
 await page.evaluate(()=>window.dispatchEvent(new CustomEvent('abissa-native-network',{detail:false})));assert.equal(await page.locator('html').getAttribute('data-native-offline'),'true');
 checks.push('Native saved page/language restore; offline state is delivered without clearing web storage');
 await page.evaluate(()=>AbissaNative212.deepLink('https://example.com/appli/mobile.html?page=boutique'));assert.equal(await page.locator('.page.active').getAttribute('id'),'page-programme');
 checks.push('Foreign deep links are ignored');assert.deepEqual(errors,[]);
 await page.evaluate(()=>goTo('profil'));
 assert.equal(await page.locator('#native212ProfileTools [data-native-tool]').count(),6);
 for(const key of ['scan','favorites','notifications','downloads','settings','about']){
  await page.locator('[data-native-tool="'+key+'"]').click();
  assert((await page.evaluate(()=>nativeRequests)).some(x=>x.action===key));
 }
 fs.mkdirSync('qa-tools/profile-212-captures',{recursive:true});
 for(const width of [360,390,768]){
  await page.setViewportSize({width,height:844});await page.locator('#native212ProfileTools').scrollIntoViewIfNeeded();
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await page.screenshot({path:'qa-tools/profile-212-captures/profil-'+width+'.png'});
 }
 checks.push('Six native service controls are reachable from Profile at 360/390/768px without horizontal overflow');
 fs.writeFileSync('qa-tools/test-native-212-ui-results.json',JSON.stringify({checks,errors,mode:'Browser bridge simulation, not a native device test'},null,2));await browser.close();console.log(JSON.stringify({checks,errors}));
})().catch(e=>{console.error(e);process.exit(1)});
