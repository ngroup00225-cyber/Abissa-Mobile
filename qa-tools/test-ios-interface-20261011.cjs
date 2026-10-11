// Browser check of the embedded HTML only; this is not an iOS/WebKit execution.
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const web = path.join(root, 'ios', 'AbissaMobile', 'Web');
const out = path.join(__dirname, 'ios-interface-20261011');
fs.mkdirSync(out, { recursive: true });
const mime = { '.html':'text/html; charset=utf-8', '.css':'text/css', '.js':'application/javascript',
  '.png':'image/png', '.svg':'image/svg+xml', '.webp':'image/webp', '.woff2':'font/woff2', '.wav':'audio/wav' };
let browser;
const server = http.createServer((req, res) => {
  try {
    const file = path.resolve(web, '.' + decodeURIComponent(new URL(req.url, 'http://localhost').pathname));
    if (!file.startsWith(web + path.sep) || !fs.statSync(file).isFile()) throw Error('Missing asset');
    let data = fs.readFileSync(file);
    if (file.endsWith('.html')) data = Buffer.from(data.toString('utf8').replaceAll('abissa-resource://app/', `http://127.0.0.1:${server.address().port}/`));
    res.writeHead(200, { 'Content-Type':mime[path.extname(file)] || 'application/octet-stream' }); res.end(data);
  } catch { res.writeHead(404); res.end(); }
});
(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const origin = `http://127.0.0.1:${server.address().port}`;
  browser = await chromium.launch({ channel: 'chrome', headless: true });
  const context = await browser.newContext({ viewport: { width:390, height:844 }, deviceScaleFactor:1 });
  await context.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
  const controller = fs.readFileSync(path.join(root, 'ios/AbissaMobile/AbissaViewController.swift'), 'utf8');
  const bridge = controller.match(/let bridge = """([\s\S]*?)"""/)[1];
  await context.addInitScript(() => {
    window.__nativeCalls = [];
    window.webkit = { messageHandlers: { abissa: { postMessage(body) {
      window.__nativeCalls.push(body);
      if (body.action === 'publicData') setTimeout(() => window.__abissaPublicDataResult?.(body.id, null, 'Hors connexion'), 0);
    } } } };
  });
  await context.addInitScript(bridge);
  const page = await context.newPage(), errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(origin + '/abissa_mobile.html', { waitUntil:'load' });
  const ready = "document.readyState === 'complete' && document.querySelectorAll('#menuGrid .tile').length > 0 && typeof goTo === 'function' && !!window.AbissaNative212";
  await page.waitForFunction(ready, { timeout:15000 });
  await page.waitForTimeout(7000);
  assert.equal(await page.evaluate(ready), true);
  assert.equal(await page.locator('#menuGrid .tile').count(), 20);
  await page.screenshot({ path:path.join(out, 'accueil.png') });
  await page.evaluate(() => {
    window.dispatchEvent(new CustomEvent('abissa-native-state', { detail:{ platform:'ios', version:'2.1.2', favorites:[], lastPage:'accueil', language:'fr' } }));
    goTo('profil');
  });
  assert.equal(await page.locator('#native212ProfileTools button').count(), 6);
  await page.screenshot({ path:path.join(out, 'profil.png') });
  assert.deepEqual(errors, []);
  const result = { checks:['Embedded UI boots with remote requests blocked', '20 home menu entries rendered',
    'Actual Swift readiness expression succeeds', 'Six native profile services retained', 'No uncaught JavaScript errors'],
    errors, engine:'Chrome', embeddedSchemeMappedToLocalHttp:true, actualIosWebKitExecuted:false, physicalIphoneVerified:false };
  fs.writeFileSync(path.join(out,'results.json'), JSON.stringify(result,null,2));
  console.log(JSON.stringify(result));
})().catch(error => { console.error(error); process.exitCode = 1; })
  .finally(async () => { if (browser) await browser.close(); server.close(); });
