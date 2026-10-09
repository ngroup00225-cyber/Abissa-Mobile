(function () {
  'use strict';
  const body = document.getElementById('appBody');
  const indicator = document.getElementById('pullRefresh');
  if (!body || !indicator) return;
  indicator.setAttribute('role', 'status');
  indicator.setAttribute('aria-live', 'polite');
  indicator.setAttribute('aria-atomic', 'true');
  indicator.querySelector('svg')?.setAttribute('aria-hidden', 'true');
  const label = document.createElement('span');
  label.className = 'pull-refresh-label';
  indicator.appendChild(label);
  let gesture = null, distance = 0, loading = false, closeTimer;
  const threshold = 60;

  function paint(height, message) {
    indicator.style.height = height + 'px';
    label.textContent = message;
    const icon = indicator.querySelector('svg');
    if (icon) icon.style.transform = loading ? '' : 'rotate(' + height * 4 + 'deg)';
  }
  function cancel() {
    gesture = null;
    distance = 0;
    if (!loading) {
      indicator.classList.remove('armed');
      paint(0, '');
    }
  }
  function eligible(target) {
    if (!(target instanceof Element)) return false;
    if (target.closest('input,textarea,select,[contenteditable="true"],[data-no-pull-refresh],.leaflet-container')) return false;
    for (let node = target; node && node !== body; node = node.parentElement) {
      const css = getComputedStyle(node);
      if (/(auto|scroll)/.test(css.overflowY) && node.scrollHeight > node.clientHeight + 2) return false;
      if (/(auto|scroll)/.test(css.overflowX) && node.scrollWidth > node.clientWidth + 2) return false;
    }
    return body.scrollTop <= 1;
  }
  function start(x, y, target) {
    if (loading || !eligible(target)) return;
    clearTimeout(closeTimer);
    indicator.classList.remove('success', 'error');
    gesture = { x, y };
    distance = 0;
  }
  function move(x, y, event) {
    if (!gesture || loading) return;
    const dx = x - gesture.x, dy = y - gesture.y;
    if (body.scrollTop > 1 || dy < -5 || Math.abs(dx) > Math.max(12, Math.abs(dy) * 1.15)) return cancel();
    if (dy <= 8) return;
    if (event.cancelable) event.preventDefault();
    distance = Math.min(86, dy * 0.55);
    indicator.classList.toggle('armed', distance >= threshold);
    paint(distance, distance >= threshold ? 'Relâchez pour actualiser' : 'Tirez pour actualiser');
  }
  async function refresh() {
    if (loading) return false;
    loading = true;
    gesture = null;
    indicator.classList.remove('armed', 'success', 'error');
    indicator.classList.add('spinning');
    paint(58, 'Actualisation en cours…');
    let ok = false;
    try {
      ok = typeof syncPull === 'function' && (await syncPull(true)) === true;
      // The synchronization applies new data without navigating away from the open module.
    } catch (_) { ok = false; }
    finally {
      loading = false;
      distance = 0;
      indicator.classList.remove('spinning');
      indicator.classList.add(ok ? 'success' : 'error');
      const message = ok ? 'Contenu actualisé' : 'Connexion indisponible. Réessayez.';
      paint(58, message);
      if (typeof showToast === 'function') showToast(message);
      closeTimer = setTimeout(() => {
        indicator.classList.remove('success', 'error');
        paint(0, '');
      }, ok ? 850 : 1800);
    }
    return ok;
  }
  function release() {
    if (!gesture) return;
    const ready = distance >= threshold;
    gesture = null;
    if (ready) refresh(); else cancel();
  }
  body.addEventListener('touchstart', e => {
    if (e.touches.length !== 1) return cancel();
    start(e.touches[0].clientX, e.touches[0].clientY, e.target);
  }, { passive: true });
  body.addEventListener('touchmove', e => {
    if (e.touches.length !== 1) return cancel();
    move(e.touches[0].clientX, e.touches[0].clientY, e);
  }, { passive: false });
  body.addEventListener('touchend', release, { passive: true });
  body.addEventListener('touchcancel', cancel, { passive: true });
  body.addEventListener('pointerdown', e => {
    if (e.pointerType === 'mouse' && e.isPrimary && e.button === 0) start(e.clientX, e.clientY, e.target);
  });
  body.addEventListener('pointermove', e => {
    if (e.pointerType === 'mouse') move(e.clientX, e.clientY, e);
  });
  window.addEventListener('pointerup', e => { if (e.pointerType === 'mouse') release(); });
  window.addEventListener('pointercancel', e => { if (e.pointerType === 'mouse') cancel(); });
  window.addEventListener('blur', cancel);
  window.AbissaRefresh = { refresh, cancel };
})();
