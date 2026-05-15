// Maya Watches · content script · runs in every page
// Captures: page metadata · clicks · text selections · form submissions · password fields
// Sends to background.js which forwards to Maya's event spine.

(function () {
  'use strict';

  // ── Page metadata · sent once per page load ────────────────────────────
  function snippet() {
    const t = document.body?.innerText || '';
    return t.slice(0, 240).replace(/\s+/g, ' ').trim();
  }
  function emit(type, payload) {
    try { chrome.runtime.sendMessage({ type, ...payload }); } catch (e) {}
  }
  setTimeout(() => {
    emit('page_meta', {
      title: document.title || '',
      h1: document.querySelector('h1')?.innerText?.slice(0, 120) || '',
      snippet: snippet()
    });
  }, 500);

  // ── Clicks · capture target description ────────────────────────────────
  document.addEventListener('click', (e) => {
    const t = e.target;
    if (!t) return;
    let desc = (t.tagName || '').toLowerCase();
    if (t.id) desc += '#' + t.id;
    if (t.className && typeof t.className === 'string') desc += '.' + t.className.split(' ').slice(0, 2).join('.');
    const txt = (t.innerText || t.value || '').slice(0, 60).replace(/\s+/g, ' ').trim();
    if (txt) desc += ' · "' + txt + '"';
    emit('click', { target: desc });
  }, true);

  // ── Text selections · sent when Mo highlights something ───────────────
  let selT = null;
  document.addEventListener('selectionchange', () => {
    clearTimeout(selT);
    selT = setTimeout(() => {
      const s = window.getSelection?.()?.toString()?.trim() || '';
      if (s.length > 8 && s.length < 1000) {
        emit('selection', { text: s.slice(0, 480) });
      }
    }, 800);
  });

  // ── Form submissions · captures FIELD NAMES + URL, NEVER values ────────
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!f || f.tagName !== 'FORM') return;
    const fields = [];
    for (const el of f.elements) {
      if (!el.name) continue;
      fields.push({ name: el.name, type: el.type || 'text' });
    }
    emit('form_submit', { fields });
  }, true);

  // ── Password fields · only flags PRESENCE + length, never the value ──
  // Mo can use this to track "where am I being asked for passwords today?"
  // The value itself is NEVER transmitted.
  document.addEventListener('focusout', (e) => {
    const t = e.target;
    if (t?.tagName === 'INPUT' && (t.type === 'password' || /pass|pwd/i.test(t.name || ''))) {
      emit('password_field', { length: (t.value || '').length });
    }
  }, true);
})();
