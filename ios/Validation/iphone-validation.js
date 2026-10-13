/* Runs only inside the opt-in Debug iPhone simulator build. No accounts or writes to the API. */
(() => {
  const report = {stage:'starting', pages:[], network:[], errors:[], engine:'WKWebView'};
  const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
  const send = () => window.webkit.messageHandlers.abissa.postMessage({action:'iphoneValidation',result:report});
  const require = (ok, message) => { if (!ok) throw Error(message); };
  const active = () => document.querySelector('.page.active');
  const visible = el => !!el && el.getBoundingClientRect().width > 0 && el.getBoundingClientRect().height > 0 && getComputedStyle(el).visibility !== 'hidden';
  async function capture(page) {
    await delay(500);
    const el = active();
    require(el?.id === 'page-'+page && visible(el) && el.innerText.trim().length > 20, 'Page not rendered: '+page);
    require(document.documentElement.scrollWidth <= innerWidth+2, 'Horizontal overflow: '+page);
    report.pages.push(page); report.stage = 'page'; report.page = page; send();
    await delay(3500);
  }
  async function api(path, validate) {
    const control = new AbortController(), timer = setTimeout(() => control.abort(), 22000);
    try {
      const response = await fetch('https://abissamobile.com/appli/api/'+path, {signal:control.signal,cache:'no-store'});
      require(response.ok, 'API '+path+': HTTP '+response.status);
      const value = await response.json();
      require(validate(value), 'API '+path+': unexpected JSON');
      report.network.push({path,status:response.status,jsonValid:true});
    } finally { clearTimeout(timer); }
  }
  (async () => {
    require(document.querySelectorAll('#menuGrid .tile').length >= 12, 'Main menu missing');
    document.querySelector('.r-skip')?.click();
    await delay(800);
    goTo('accueil');
    await capture('accueil');
    for (const page of ['programme','histoire','sites','langue']) {
      goTo('accueil');
      const tile = [...document.querySelectorAll('#menuGrid .tile')].find(el => {
        try { return JSON.parse(el.dataset.rArgs || '[]')[0] === page; } catch { return false; }
      });
      require(!!tile, 'Menu control missing: '+page);
      tile.scrollIntoView({block:'center'}); tile.click();
      await capture(page);
    }
    goTo('profil'); await capture('profil');
    require(document.querySelectorAll('#native212ProfileTools button').length === 6, 'Native services missing');
    // Both the production URLSession public-data bridge and a direct WebKit fetch are exercised.
    await api('data', value => !!value && typeof value === 'object' && !Array.isArray(value));
    await api('nzema/audio', value => !!value?.items && typeof value.items === 'object');
    goTo('accueil'); await delay(500);
    require((window.__abissaValidationErrors || []).length === 0, 'JavaScript runtime errors');
    report.stage = 'complete'; report.passed = true; send();
    window.addEventListener('abissa-deeplink', async () => {
      await delay(700);
      report.deepLinkVerified = active()?.id === 'page-programme';
      report.stage = 'deeplink'; report.passed = report.deepLinkVerified; send();
    }, {once:true});
  })().catch(error => {
    report.stage = 'failed'; report.passed = false; report.errors.push(error.message);
    report.runtimeErrors = window.__abissaValidationErrors || []; send();
  });
})();
