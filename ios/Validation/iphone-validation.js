/* Opt-in Debug simulator checks. Optional demo credentials arrive only at runtime. */
(() => {
  const report = {stage:'starting', pages:[], network:[], errors:[], engine:'WKWebView'};
  const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
  const send = () => window.webkit.messageHandlers.abissa.postMessage({action:'iphoneValidation',result:report});
  const require = (ok, message) => { if (!ok) throw Error(message); };
  const active = () => document.querySelector('.page.active');
  const visible = el => !!el && el.getBoundingClientRect().width > 0 && el.getBoundingClientRect().height > 0 && getComputedStyle(el).visibility !== 'hidden';
  async function capture(page, label = page) {
    await delay(500);
    const el = active();
    require(el?.id === 'page-'+page && visible(el) && el.innerText.trim().length > 20, 'Page not rendered: '+page);
    require(document.documentElement.scrollWidth <= innerWidth+2, 'Horizontal overflow: '+page);
    report.pages.push(page); report.stage = 'page'; report.page = label; send();
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
  async function reviewLogin() {
    report.authentication = {attempted:false, verified:false};
    if (!reviewCredentials.username || !reviewCredentials.password) return;
    report.authentication.attempted = true;
    loginMode = 'user'; showLoginMode(); goTo('login');
    const id = document.getElementById('loginId'), password = document.getElementById('loginPwd');
    id.value = reviewCredentials.username; password.value = reviewCredentials.password;
    const button = document.querySelector('#loginUserForm button[onclick="attemptUserLogin()"]');
    require(visible(button) && !button.disabled, 'Login control unavailable');
    button.click();
    const deadline = Date.now()+75000;
    while (button.disabled && Date.now()<deadline) await delay(200);
    // Clear the password before any screenshot, including failure screenshots.
    password.value = ''; reviewCredentials.password = '';
    require(!button.disabled && currentUser?.username === reviewCredentials.username &&
            currentUser?.status === 'active' && /^[a-f0-9]{64}$/.test(userToken || ''),
            'Demo login did not establish a verified active session');
    require(!isAdminLoggedIn && currentAdminRole === null, 'Unexpected administrative session');
    require(!!localStorage.getItem('abissa_user_session'), 'Verified session not persisted');
    goTo('profil'); await capture('profil','profil-connecte');
    const response = await fetch('https://abissamobile.com/appli/api/users/logout', {
      method:'POST', credentials:'omit', headers:{'Content-Type':'application/json',Authorization:'Bearer '+userToken}, body:'{}'
    });
    require(response.ok, 'Demo session revocation failed');
    logoutUser();
    require(!currentUser && !userToken && !localStorage.getItem('abissa_user_session'), 'Local logout failed');
    report.authentication.verified = true;
    reviewCredentials.username = '';
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
    await reviewLogin();
    goTo('accueil'); await delay(500);
    require((window.__abissaValidationErrors || []).length === 0, 'JavaScript runtime errors');
    report.stage = 'complete'; report.passed = true; send();
  })().catch(error => {
    report.stage = 'failed'; report.passed = false; report.errors.push(error.message);
    report.runtimeErrors = window.__abissaValidationErrors || []; send();
  });
})();
