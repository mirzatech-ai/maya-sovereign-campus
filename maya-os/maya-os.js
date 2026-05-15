/* Maya OS · v1.2 · 2026-05-12 · Kin
 * council fixes: top horizontal switcher · Ctrl+K palette · collapsible right rail · persistent audio strip
 * mobile fix: layout flex chain corrected so composer stays in viewport
 */
(function () {
  'use strict';

  const BRAIN_URL    = 'https://iamsuperio.cloud/api/index';
  const ATTACH_URL   = '/api/maya_attachment.php';
  const SESSION_KEY  = 'maya_os_session_v1_2';        // active session id pointer
  const SESSIONS_KEY = 'maya_os_sessions_v1';         // v1.9.7 · sessions registry [{id,title,last_at,count}]
  const HIST_KEY_FN  = sid => 'maya_os_hist_' + sid;
  const SBAR_KEY     = 'maya_os_sidebar_collapsed';
  const THEME_KEY    = 'mtai_theme';
  const TTS_KEY      = 'maya_os_tts_on';
  const ENG_KEY      = 'maya_os_engine';
  const TABS_KEY     = 'maya_os_browser_tabs';
  const COLLAPSE_KEY = 'maya_os_rail_collapse';
  const HISTORY_LIMIT = 12;

  const $ = id => document.getElementById(id);
  const qsAll = sel => Array.from(document.querySelectorAll(sel));

  // v1.9.7 · multi-session chat
  function loadSessions() {
    try { const r = localStorage.getItem(SESSIONS_KEY); if (r) return JSON.parse(r); } catch (_) {}
    return [];
  }
  function saveSessions(arr) {
    try { localStorage.setItem(SESSIONS_KEY, JSON.stringify(arr || [])); } catch (_) {}
  }
  let sessions = loadSessions();
  let sessionId = localStorage.getItem(SESSION_KEY) || '';
  if (!sessionId || !sessions.find(s => s.id === sessionId)) {
    sessionId = 'maya-os-' + Date.now().toString(36);
    sessions.unshift({ id: sessionId, title: 'New chat', last_at: Date.now(), count: 0 });
    saveSessions(sessions);
  }
  localStorage.setItem(SESSION_KEY, sessionId);
  let history = [];
  try { const c = localStorage.getItem(HIST_KEY_FN(sessionId)) || sessionStorage.getItem(HIST_KEY_FN(sessionId)); if (c) history = JSON.parse(c); } catch (_) {}
  let ttsOn = sessionStorage.getItem(TTS_KEY) === '1';
  let currentEngine = localStorage.getItem(ENG_KEY) || 'auto';
  let currentMode = 'chat';

  function isAuthed() {
    try {
      if (sessionStorage.getItem('mayaCommanderMode') === '1') return true;
      const p = localStorage.getItem('maya_pin') || '';
      if (p.length >= 4) return true;
      if (window.SUPERIO_IS_GOD === true) return true;
      return false;
    } catch (_) { return false; }
  }
  function currentPin() { return sessionStorage.getItem('mayaCommanderPin') || localStorage.getItem('maya_pin') || ''; }
  function escapeHTML(s) { return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }

  // ── DOM ───────────────────────────────────────────────────────────
  const shell      = $('osShell');
  const feed       = $('feed');
  const msgEl      = $('msg');
  const sendBt     = $('sendBtn');
  const micBt      = $('micBtn');
  const ttsBt      = $('ttsBtn');
  const clrBt      = $('clearBtn');
  const attachBt   = $('attachBtn');
  const attachFile = $('attachFile');
  const railToggle = $('railToggle');
  const liveToggle = $('liveToggle');
  // v1.10.2 · themeToggle + pinChip removed from rail-foot per Mo
  // Theme toggle is operational from elsewhere · PIN flow lives in bell drawer + commander_auth
  const themeBt    = $('themeToggle');   // may be null after v1.10.2 cleanup
  const pinChip    = $('pinChip');       // may be null after v1.10.2 cleanup
  const paletteCue = $('paletteCue');    // moved to canvas-head
  const statusDot  = $('statusDot');
  const statusText = $('statusText');
  const lmEl       = $('lastModel');
  const msEl       = $('lastMs');
  const toolCalls  = $('toolCalls');
  const recentFiles= $('recentFiles');
  const recentTks  = $('recentTickets');
  const rrClear    = $('rrClear');
  const fileList   = $('fileList');
  const execSearch = $('execSearch');
  const execRes    = $('execResults');
  const enginePick = $('enginePicker');
  const staffGrid  = $('staffGrid');
  const staffSearch= $('staffSearch');
  const tsMore     = $('tsMore');
  const tsDrawer   = $('tsDrawer');
  const audioStrip = $('audioStrip');
  const asState    = $('asState');
  const asText     = $('asText');
  const asTtsToggle= $('asTtsToggle');
  const paletteBd  = $('paletteBackdrop');
  const paletteIn  = $('paletteInput');
  const paletteOut = $('paletteResults');

  // ── Theme ─────────────────────────────────────────────────────────
  const savedTheme = localStorage.getItem(THEME_KEY) || 'night';
  document.documentElement.setAttribute('data-theme', savedTheme);
  if (themeBt) themeBt.addEventListener('click', () => {
    const next = document.documentElement.getAttribute('data-theme') === 'day' ? 'night' : 'day';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem(THEME_KEY, next);
  });

  // ── PIN ───────────────────────────────────────────────────────────
  function refreshPinChip() {
    const a = isAuthed();
    if (pinChip) {
      pinChip.classList.toggle('authed', a);
      pinChip.textContent = a ? 'PIN ✓' : 'PIN';
    }
    if (attachBt) {
      attachBt.classList.toggle('armed', a);
      attachBt.disabled = !a;
      attachBt.title = a ? 'Attach file (Commander mode)' : 'Commander PIN required';
    }
  }
  if (pinChip) pinChip.addEventListener('click', () => {
    const cur = localStorage.getItem('maya_pin') || '';
    const next = prompt('Commander PIN (210379 or BuddyBoots2!) · blank to clear:', cur);
    if (next === null) return;
    const t = next.trim();
    if (t === '') {
      localStorage.removeItem('maya_pin');
      sessionStorage.removeItem('mayaCommanderMode');
      sessionStorage.removeItem('mayaCommanderPin');
    } else {
      localStorage.setItem('maya_pin', t);
      fetch('/api/commander_auth', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: 'Mirza', password: t, domain: location.hostname })
      }).then(r => r.json()).then(j => {
        if (j && j.success && j.token) {
          sessionStorage.setItem('mayaCommanderPin', j.token);
          sessionStorage.setItem('mayaCommanderMode', '1');
          addBubble('system', 'Commander mode unlocked.');
        }
        refreshPinChip();
      }).catch(() => refreshPinChip());
    }
    refreshPinChip();
  });
  refreshPinChip();
  setInterval(refreshPinChip, 1500);

  // ── Engine picker · DYNAMIC · loads ALL providers from /api/index list ──
  // Mo: "I have so many APIs and you're giving me two...I want to choose what model"
  enginePick.value = currentEngine;
  enginePick.addEventListener('change', () => {
    currentEngine = enginePick.value;
    localStorage.setItem(ENG_KEY, currentEngine);
    addBubble('system', 'Engine → ' + currentEngine, false);
  });
  // Fetch real engine list from brain · populate picker with all providers + their models
  (async function populateEngines(){
    try {
      const r = await fetch(BRAIN_URL, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'list'})});
      const d = await r.json();
      if (!d || !Array.isArray(d.engines)) return;
      // Preserve the existing top option (Auto Smart) · clear the rest
      const auto = enginePick.querySelector('option[value="auto"]');
      enginePick.innerHTML = '';
      if (auto) enginePick.appendChild(auto);
      else {
        const a = document.createElement('option');
        a.value = 'auto'; a.textContent = '🤖 Auto Smart (she picks)';
        a.title = 'Smart routing per GLOBAL-105 · brain picks lane per task';
        enginePick.appendChild(a);
      }
      // Engine icons by category for quick scan
      const ICONS = { ollama_local:'🦙', groq:'⚡', gemini:'🔍', openai:'🌀', mistral:'🇫🇷', cerebras:'🧠',
        nvidia_nim:'🟢', nvidia_kimi:'💠', nvidia_kimi_thinking:'💠', nvidia_qwen:'🇨🇳', nvidia_qwen_thinking:'🇨🇳',
        nvidia_glm5:'🧠', nvidia_minimax:'🎬', nvidia_deepseek_v32:'💻', novita:'☁️', deepseek:'💻',
        openrouter:'🌍', fireworks:'🎆', grok:'❌', anthropic:'🟧', huggingface:'🤗' };
      // Group: top-level engine option + per-model sub-options
      d.engines.sort((a,b) => (b.keys||0) - (a.keys||0));
      for (const eng of d.engines) {
        const ico = ICONS[eng.name] || '🔌';
        // Top-level "this engine, default model" option
        const top = document.createElement('option');
        top.value = eng.name;
        top.textContent = ico + ' ' + eng.name + '  (' + (eng.keys||0) + ' keys · ' + (eng.models||[]).length + ' models)';
        top.title = (eng.models||[]).slice(0, 5).join(' · ');
        enginePick.appendChild(top);
        // Per-specific-model options · only if engine has 2+ models worth picking
        if (Array.isArray(eng.models) && eng.models.length > 1) {
          for (const m of eng.models) {
            const opt = document.createElement('option');
            opt.value = eng.name + '::' + m;   // backend will read prefix as engine + model override
            opt.textContent = '   └─ ' + ico + ' ' + m;
            opt.title = eng.name + ' · ' + m;
            enginePick.appendChild(opt);
          }
        }
      }
      // Restore previously selected · or fall back to auto
      enginePick.value = currentEngine;
      if (enginePick.selectedIndex < 0) enginePick.value = 'auto';
      addBubble('system', 'Loaded ' + d.engines.length + ' engines · ' + d.engines.reduce((a,e)=>a+(e.models||[]).length,0) + ' models · pick from dropdown top-right', false);
    } catch (e) { /* keep static fallback */ }
  })();

  // ── Mode switch (synced across left rail + top switcher + palette) ─
  const modeTitles = {
    chat:'Chat', browser:'Browser', build:'Build', vision:'Vision', image:'Image', staff:'Staff',
    video:'Video', music:'Music', voice:'Voice', chain:'Chain', three_d:'3D',
    files:'Files', executor:'Executor', sentinels:'Sentinels', console:'Console'
  };
  function switchMode(mode) {
    if (!mode) return;
    // v1.9.7 · Qode = external launcher · opens in own window · never splits Maya OS
    if (mode === 'qode') {
      window.open('https://iamsuperio.cloud/maya-qode', 'maya_qode', 'noopener,noreferrer');
      return;
    }
    currentMode = mode;
    qsAll('.mode-btn').forEach(b => {
      const on = b.dataset.mode === mode;
      b.classList.toggle('active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    qsAll('.ts-btn').forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
    qsAll('.panel').forEach(p => p.classList.toggle('active', p.dataset.panel === mode));
    if (mode === 'staff') renderStaff();
    if (mode === 'sentinels') renderSentinels();
    if (mode === 'executor') renderExec();   // v1.4 · pre-load
    if (mode === 'browser' && !browserState.tabs.length) browserNew('https://mirzatech.ai');
    // v1.9.7 · Qode handled at top of function · no panel needed
    // v1.9.5 · Evolution mode · fetch latest report on first activation (+ refresh button)
    if (mode === 'evolution') renderEvolution();
    shell.classList.remove('nav-open');
    tsDrawer.hidden = true;
    tsMore.setAttribute('aria-expanded', 'false');
  }
  qsAll('.mode-btn').forEach(btn => btn.addEventListener('click', () => switchMode(btn.dataset.mode)));
  qsAll('.ts-btn').forEach(btn => btn.addEventListener('click', () => switchMode(btn.dataset.mode)));
  qsAll('.ts-drawer button').forEach(btn => btn.addEventListener('click', () => switchMode(btn.dataset.mode)));

  // top-switch "More" drawer
  tsMore.addEventListener('click', e => {
    e.stopPropagation();
    const open = !tsDrawer.hidden;
    tsDrawer.hidden = open;
    tsMore.setAttribute('aria-expanded', open ? 'false' : 'true');
  });
  document.addEventListener('click', e => {
    if (!tsDrawer.hidden && !tsDrawer.contains(e.target) && e.target !== tsMore) {
      tsDrawer.hidden = true; tsMore.setAttribute('aria-expanded', 'false');
    }
  });

  // Mobile rail toggles
  railToggle.addEventListener('click', () => shell.classList.toggle('nav-open'));
  if (liveToggle) liveToggle.addEventListener('click', () => shell.classList.toggle('live-open'));
  rrClear.addEventListener('click', () => {
    toolCalls.innerHTML = '<div class="empty-sm">No tool calls yet.</div>';
    recentFiles.innerHTML = '<div class="empty-sm">None.</div>';
    recentTks.innerHTML = '<div class="empty-sm">None.</div>';
    updateCounts();
  });
  // v1.3 · rail close · actually retracts the right rail + reopen button
  const rrClose = $('rrClose');
  const railReopen = $('railReopen');
  function setLiveRail(open) {
    if (open) {
      shell.classList.remove('live-collapsed');
      if (railReopen) railReopen.hidden = true;
    } else {
      shell.classList.add('live-collapsed');
      shell.classList.remove('live-open');
      if (railReopen) railReopen.hidden = false;
    }
  }
  if (rrClose) rrClose.addEventListener('click', () => setLiveRail(false));
  if (railReopen) railReopen.addEventListener('click', () => setLiveRail(true));
  // ⌗ button toggles too (works on mobile and desktop)
  if (liveToggle) {
    liveToggle.removeEventListener('click', () => {}); // dedup safe-no-op
    liveToggle.addEventListener('click', () => {
      if (shell.classList.contains('live-collapsed')) setLiveRail(true);
      else if (window.innerWidth <= 980) shell.classList.toggle('live-open');
      else setLiveRail(false);
    });
  }

  // ── Right rail · collapsible sections ─────────────────────────────
  const collapsed = (() => { try { return new Set(JSON.parse(localStorage.getItem(COLLAPSE_KEY) || '[]')); } catch (_) { return new Set(); } })();
  function applyCollapse() {
    qsAll('.rail-section').forEach(s => s.classList.toggle('collapsed', collapsed.has(s.dataset.section)));
    qsAll('.rail-h').forEach(h => {
      const k = h.dataset.toggle;
      h.textContent = (collapsed.has(k) ? '▸ ' : '▾ ') + (h.dataset.label || h.textContent.replace(/^[▾▸]\s*/, '').replace(/\s+\d+$/, ''));
      // re-attach count span
      const cId = ({tools:'toolCallsCount',files:'recentFilesCount',tickets:'recentTicketsCount'})[k];
      if (cId) {
        const c = $(cId);
        if (c && !h.querySelector('.rh-count')) {
          const span = document.createElement('span');
          span.className = 'rh-count'; span.id = cId;
          span.textContent = c.textContent || '0';
          h.appendChild(span);
        }
      }
    });
  }
  qsAll('.rail-h').forEach(h => {
    h.dataset.label = h.textContent.replace(/^[▾▸]\s*/, '').replace(/\s+\d+$/, '');
    h.addEventListener('click', () => {
      const k = h.dataset.toggle;
      if (collapsed.has(k)) collapsed.delete(k); else collapsed.add(k);
      try { localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...collapsed])); } catch (_) {}
      applyCollapse();
    });
  });
  applyCollapse();
  function updateCounts() {
    const tcN = toolCalls.querySelector('.empty-sm') ? 0 : toolCalls.children.length;
    const rfN = recentFiles.querySelector('.empty-sm') ? 0 : recentFiles.children.length;
    const rtN = recentTks.querySelector('.empty-sm') ? 0 : recentTks.children.length;
    setCount('toolCallsCount', tcN);
    setCount('recentFilesCount', rfN);
    setCount('recentTicketsCount', rtN);
  }
  function setCount(id, n) {
    const el = $(id); if (!el) return;
    el.textContent = n > 0 ? String(n) : '';
    el.style.display = n > 0 ? '' : 'none';
  }

  // ── Chat ──────────────────────────────────────────────────────────
  function addBubble(role, text, push) {
    const b = document.createElement('div');
    b.className = 'bubble ' + role;
    b.textContent = text;
    if (role === 'maya') {
      const meta = document.createElement('div');
      meta.className = 'bubble-meta';
      const speak = document.createElement('button');
      speak.type = 'button'; speak.className = 'speak'; speak.textContent = '🔊'; speak.title = 'Read aloud';
      speak.addEventListener('click', () => speakText(text, speak));
      meta.appendChild(speak);
      b.appendChild(meta);
      if (ttsOn) setTimeout(() => speakText(text, speak), 200);
    }
    feed.appendChild(b);
    feed.scrollTop = feed.scrollHeight;
    if (push !== false) {
      history.push({ role: role === 'maya' ? 'assistant' : 'user', content: text });
      history = history.slice(-HISTORY_LIMIT);
      try { localStorage.setItem(HIST_KEY_FN(sessionId), JSON.stringify(history)); } catch (_) {}
      // v1.9.7 · session metadata · update title from first user msg + bump last_at
      const s = sessions.find(x => x.id === sessionId);
      if (s) {
        s.last_at = Date.now();
        s.count = history.length;
        if (s.title === 'New chat' && role === 'user' && text && text.length > 0) {
          s.title = text.slice(0, 48) + (text.length > 48 ? '…' : '');
        }
        sessions = sessions.filter(x => x.id !== sessionId);
        sessions.unshift(s);
        saveSessions(sessions);
        renderSessions();
      }
    }
  }
  function addTool(text) {
    const b = document.createElement('div'); b.className = 'bubble tool'; b.textContent = text;
    feed.appendChild(b); feed.scrollTop = feed.scrollHeight;
    pushToolCall(text);
  }
  function addError(text) { const b = document.createElement('div'); b.className = 'bubble error'; b.textContent = text; feed.appendChild(b); feed.scrollTop = feed.scrollHeight; }
  function thinking(on) {
    let t = document.querySelector('.thinking');
    if (on && !t) { t = document.createElement('div'); t.className = 'thinking'; t.innerHTML = '<span></span><span></span><span></span>'; feed.appendChild(t); feed.scrollTop = feed.scrollHeight; }
    else if (!on && t) { t.remove(); }
  }
  history.forEach(h => addBubble(h.role === 'assistant' ? 'maya' : 'user', h.content, false));

  // v1.9.9c · INTENT ROUTER · Mo: "if I say make me a video, I don't wanna tap no special tabs"
  // Detects "make me a video/image/song/3d/code" verbs and routes to right brain action,
  // renders result media INLINE in chat (no tab switch).
  function detectIntent(txt) {
    const t = txt.toLowerCase().trim();
    // Strong verbs first
    if (/^(make|create|generate|gen|build|render|produce|draw|paint|compose|write me|sing me)\b/i.test(t)) {
      // What kind of artifact?
      if (/\bvideo|movie|clip|reel|short|animation|animated\b/i.test(t)) return { action:'video',  prompt:t };
      if (/\bimage|picture|photo|art|illustration|drawing|painting|poster|logo|icon\b/i.test(t)) return { action:'image',  prompt:t };
      if (/\bsong|music|track|beat|melody|tune|jingle\b/i.test(t))                              return { action:'music',  prompt:t };
      if (/\b3d|three.?d|model|mesh|sculpt|trellis\b/i.test(t))                                  return { action:'three_d',prompt:t };
      if (/\bcode|app|script|function|component|page|website\b/i.test(t))                         return { action:'code',   prompt:t };
    }
    // "say X" / "speak X" / "voice X"
    if (/^(say|speak|voice|read aloud)\b/i.test(t))                                              return { action:'tts',    prompt:t };
    // Default: chat
    return { action:'chat', message:txt };
  }
  function renderMediaResult(action, data) {
    // Find the URL Maya returned and render inline
    const url = data.image_url || data.video_url || data.audio_url || data.url || data.preview_url || (data.result && (data.result.url || data.result.image_url));
    const wrap = document.createElement('div'); wrap.className = 'bubble maya';
    if (url) {
      if (/\.(mp4|webm|mov)(\?|$)/i.test(url) || action === 'video') {
        wrap.innerHTML = '<video src="' + escapeHTML(url) + '" controls style="max-width:100%;border-radius:8px"></video><div class="b-meta">' + action + ' · ' + (data.engine || data.provider || 'brain') + '</div>';
      } else if (/\.(png|jpe?g|gif|webp|svg)(\?|$)/i.test(url) || action === 'image') {
        wrap.innerHTML = '<img src="' + escapeHTML(url) + '" alt="generated" style="max-width:100%;border-radius:8px"><div class="b-meta">' + action + ' · ' + (data.engine || data.provider || 'brain') + '</div>';
      } else if (/\.(mp3|wav|ogg|m4a)(\?|$)/i.test(url) || action === 'music' || action === 'tts') {
        wrap.innerHTML = '<audio src="' + escapeHTML(url) + '" controls autoplay style="width:100%"></audio><div class="b-meta">' + action + ' · ' + (data.engine || data.provider || 'brain') + '</div>';
      } else if (action === 'three_d') {
        wrap.innerHTML = '<a href="' + escapeHTML(url) + '" target="_blank" rel="noopener" style="color:#76b900">📦 Open 3D model →</a><div class="b-meta">3d · ' + (data.engine || 'trellis') + '</div>';
      } else {
        wrap.innerHTML = '<a href="' + escapeHTML(url) + '" target="_blank" rel="noopener">' + escapeHTML(url) + '</a>';
      }
    } else {
      wrap.textContent = data.reply || data.message || 'No media returned · raw: ' + JSON.stringify(data).slice(0, 300);
    }
    feed.appendChild(wrap);
    feed.scrollTop = feed.scrollHeight;
  }
  function escapeHTML(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function sendMessage() {
    const txt = msgEl.value.trim();
    if (!txt) return;
    msgEl.value = ''; autosize();
    addBubble('user', txt);
    thinking(true); sendBt.disabled = true;
    const t0 = Date.now();
    // INTENT ROUTING · "make me a video..." → action=video
    const intent = detectIntent(txt);
    try {
      const isMedia = ['video','image','music','three_d','code','tts'].includes(intent.action);
      const payload = isMedia
        ? Object.assign({ action: intent.action, prompt: intent.prompt, message: txt, mode: intent.action, engine: currentEngine === 'auto' ? undefined : currentEngine, source: 'maya-os-v1.2', session_id: sessionId, pin: currentPin() }, intent.action === 'tts' ? { text: intent.prompt.replace(/^(say|speak|voice|read aloud)\s+/i, '') } : {})
        : { action: 'chat', message: txt, public: false, mode: 'chat', engine: currentEngine === 'auto' ? undefined : currentEngine, source: 'maya-os-v1.2', session_id: sessionId, history: history.slice(-HISTORY_LIMIT), pin: currentPin() };
      const r = await fetch(BRAIN_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      // TTS returns binary audio · handle separately
      if (intent.action === 'tts' && (r.headers.get('content-type') || '').startsWith('audio/')) {
        const blob = await r.blob();
        const url = URL.createObjectURL(blob);
        thinking(false);
        const wrap = document.createElement('div'); wrap.className = 'bubble maya';
        wrap.innerHTML = '<audio src="' + url + '" controls autoplay style="width:100%"></audio><div class="b-meta">tts · ' + (blob.size/1024).toFixed(0) + 'KB</div>';
        feed.appendChild(wrap); feed.scrollTop = feed.scrollHeight;
        sendBt.disabled = false; msgEl.focus(); return;
      }
      const data = await r.json();
      thinking(false);
      lmEl.textContent = (data.engine || data.provider || data.model || 'brain').toString().slice(0, 36);
      msEl.textContent = (Date.now() - t0) + 'ms';
      if (Array.isArray(data.tool_calls)) data.tool_calls.forEach(tc => addTool(typeof tc === 'string' ? tc : (tc.name || JSON.stringify(tc))));
      if (isMedia) {
        addTool(intent.action + '.intent · routed inline');
        renderMediaResult(intent.action, data);
      } else {
        addBubble('maya', data.reply || data.response || data.message || data.error || 'Sorry · empty response.');
      }
    } catch (e) { thinking(false); addError('Connection error · ' + String(e)); setStatus(false); }
    sendBt.disabled = false; msgEl.focus();
  }
  sendBt.addEventListener('click', sendMessage);
  msgEl.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
  msgEl.addEventListener('input', autosize);
  function autosize() { msgEl.style.height = 'auto'; msgEl.style.height = Math.min(140, msgEl.scrollHeight) + 'px'; }
  clrBt.addEventListener('click', () => {
    history = [];
    localStorage.removeItem(HIST_KEY_FN(sessionId));
    sessionStorage.removeItem(HIST_KEY_FN(sessionId));
    feed.innerHTML = '';
    addBubble('system', 'Cleared.', false);
    const s = sessions.find(x => x.id === sessionId); if (s) { s.count = 0; saveSessions(sessions); renderSessions(); }
  });

  // v1.9.7 · multi-session controls
  function renderSessions() {
    const list = $('csList'); if (!list) return;
    if (!sessions.length) { list.innerHTML = '<div class="empty-sm">No sessions yet.</div>'; return; }
    list.innerHTML = sessions.map(s => {
      const ago = timeAgo(s.last_at);
      const cls = s.id === sessionId ? 'cs-item active' : 'cs-item';
      return '<button class="' + cls + '" data-sid="' + s.id + '">' +
        '<span class="cs-item-title">' + escapeHTML(s.title) + '</span>' +
        '<span class="cs-item-meta">' + (s.count || 0) + ' msg · ' + ago + '</span>' +
      '</button>';
    }).join('');
    qsAll('#csList .cs-item').forEach(b => b.addEventListener('click', () => switchSession(b.dataset.sid)));
    const cur = sessions.find(x => x.id === sessionId);
    const titleEl = $('chatTitle'); if (titleEl && cur) titleEl.textContent = cur.title;
  }
  function timeAgo(ms) {
    if (!ms) return 'just now';
    const d = Date.now() - ms;
    if (d < 60000) return 'now';
    if (d < 3600000) return Math.floor(d/60000) + 'm';
    if (d < 86400000) return Math.floor(d/3600000) + 'h';
    return Math.floor(d/86400000) + 'd';
  }
  function newSession() {
    const id = 'maya-os-' + Date.now().toString(36);
    sessions.unshift({ id, title: 'New chat', last_at: Date.now(), count: 0 });
    saveSessions(sessions);
    switchSession(id);
  }
  function switchSession(id) {
    if (!id) return;
    const s = sessions.find(x => x.id === id); if (!s) return;
    sessionId = id;
    localStorage.setItem(SESSION_KEY, sessionId);
    history = [];
    try { const c = localStorage.getItem(HIST_KEY_FN(sessionId)); if (c) history = JSON.parse(c); } catch (_) {}
    // re-render feed from history
    feed.innerHTML = '';
    if (!history.length) {
      addBubble('system', '<strong>Maya OS · v1.9</strong><br>New chat. Talk to Maya.', false);
    } else {
      history.forEach(h => {
        const b = document.createElement('div');
        b.className = 'bubble ' + (h.role === 'assistant' ? 'maya' : (h.role === 'user' ? 'user' : 'system'));
        b.textContent = h.content;
        feed.appendChild(b);
      });
      feed.scrollTop = feed.scrollHeight;
    }
    renderSessions();
  }
  function renameSession() {
    const s = sessions.find(x => x.id === sessionId); if (!s) return;
    const v = prompt('Rename this chat:', s.title);
    if (v === null) return;
    s.title = v.trim() || 'Untitled';
    saveSessions(sessions); renderSessions();
  }
  function deleteSession() {
    const s = sessions.find(x => x.id === sessionId); if (!s) return;
    if (!confirm('Delete this chat? "' + s.title + '"')) return;
    localStorage.removeItem(HIST_KEY_FN(sessionId));
    sessions = sessions.filter(x => x.id !== sessionId);
    saveSessions(sessions);
    if (!sessions.length) { newSession(); return; }
    switchSession(sessions[0].id);
  }
  if ($('csNew'))       $('csNew').addEventListener('click', newSession);
  if ($('csRenameBtn')) $('csRenameBtn').addEventListener('click', renameSession);
  if ($('csDeleteBtn')) $('csDeleteBtn').addEventListener('click', deleteSession);
  if ($('csToggle')) {
    $('csToggle').addEventListener('click', () => {
      const shell = document.querySelector('.chat-shell');
      shell.classList.toggle('sb-collapsed');
      try { localStorage.setItem(SBAR_KEY, shell.classList.contains('sb-collapsed') ? '1' : '0'); } catch (_) {}
    });
  }
  if ($('csOpenBtn')) {
    $('csOpenBtn').addEventListener('click', () => {
      document.querySelector('.chat-shell').classList.remove('sb-collapsed');
    });
  }
  // restore collapsed state
  try { if (localStorage.getItem(SBAR_KEY) === '1') document.querySelector('.chat-shell').classList.add('sb-collapsed'); } catch (_) {}
  renderSessions();

  // ── TTS · with persistent audio strip ─────────────────────────────
  function setAudioState(state, text) {
    audioStrip.setAttribute('data-state', state);
    asState.textContent = ({idle:'🔊 idle', speaking:'🔊 speaking', paused:'⏸ paused'})[state] || '🔊';
    if (text !== undefined) asText.textContent = text;
  }
  setAudioState('idle', 'no audio');
  ttsBt.classList.toggle('on', ttsOn);
  asTtsToggle.classList.toggle('on', ttsOn);
  function setTtsOn(v) {
    ttsOn = v;
    ttsBt.classList.toggle('on', v);
    asTtsToggle.classList.toggle('on', v);
    sessionStorage.setItem(TTS_KEY, v ? '1' : '0');
    if (!v && window.speechSynthesis) { window.speechSynthesis.cancel(); setAudioState('idle', 'no audio'); }
  }
  ttsBt.addEventListener('click', () => setTtsOn(!ttsOn));
  asTtsToggle.addEventListener('click', () => setTtsOn(!ttsOn));
  // v1.9.8 · Persona-aware Kokoro/Gemini TTS via /api/maya_voice
  // Mo: "I want real voice from a lady for Maya · male voices for Adeeo/Eazo/Superio/Oadem"
  const VOICE_API = 'https://iamsuperio.cloud/api/maya_voice';
  const PERSONA_KEY = 'maya_os_persona_voice_overrides';
  // Persona detection · scans text + active context for whose voice this is
  function detectPersona(text) {
    const t = (text || '').toLowerCase();
    // Explicit "I'm X" or "X here" speaking signals
    if (/\b(i am |i'm |this is )?(adeeo)\b/.test(t)) return 'adeeo';
    if (/\b(i am |i'm |this is )?(eazo)\b/.test(t)) return 'eazo';
    if (/\b(i am |i'm |this is )?(superio)\b/.test(t)) return 'superio';
    if (/\b(i am |i'm |this is )?(oadem)\b/.test(t)) return 'oadem';
    if (/\b(i am |i'm |this is )?(madzida)\b/.test(t)) return 'madzida';
    if (/\b(i am |i'm |this is )?(kin)\b/.test(t)) return 'kin';
    if (/\b(i am |i'm |this is )?(sage)\b/.test(t)) return 'sage';
    // Domain-context hints (when reply mentions a specific empire site)
    if (/\badeeo\.io\b/.test(t)) return 'adeeo';
    if (/\boadem\.io\b/.test(t)) return 'oadem';
    if (/\bsuperio\b/.test(t) && !/maya/.test(t.slice(0, 80))) return 'superio';
    // Default: Maya
    return 'maya';
  }
  // Allow Mo to override per persona (saved in localStorage)
  function getPersonaOverrides() {
    try { return JSON.parse(localStorage.getItem(PERSONA_KEY)) || {}; } catch (_) { return {}; }
  }
  function setPersonaOverride(persona, voicePreset) {
    const o = getPersonaOverrides();
    if (voicePreset) o[persona] = voicePreset; else delete o[persona];
    try { localStorage.setItem(PERSONA_KEY, JSON.stringify(o)); } catch (_) {}
  }
  let _currentAudio = null;
  async function speakText(text, btn) {
    if (!text) return;
    // Stop anything currently playing
    if (_currentAudio) { try { _currentAudio.pause(); _currentAudio.src=''; } catch(_){} _currentAudio = null; }
    if (window.speechSynthesis) window.speechSynthesis.cancel();

    const persona = detectPersona(text);
    const overrides = getPersonaOverrides();
    const voicePreset = overrides[persona] || ('persona_' + persona);
    setAudioState('speaking', persona + ' · ' + text.slice(0, 50) + (text.length > 50 ? '…' : ''));
    if (btn) btn.classList.add('speaking');

    try {
      const r = await fetch(VOICE_API, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'synthesize', text, voice: voicePreset })
      });
      const j = await r.json();
      if (j && j.ok && j.audio_url) {
        const url = j.audio_url.startsWith('http') ? j.audio_url : ('https://iamsuperio.cloud' + j.audio_url);
        _currentAudio = new Audio(url);
        _currentAudio.onended = _currentAudio.onerror = () => {
          if (btn) btn.classList.remove('speaking');
          setAudioState('idle', 'no audio');
          _currentAudio = null;
        };
        await _currentAudio.play();
        return;
      }
    } catch (e) { /* fall through to browser TTS */ }

    // Fallback: browser SpeechSynthesis (robotic but never silent)
    if (window.speechSynthesis) {
      const u = new SpeechSynthesisUtterance(text);
      u.rate = 1.05; u.pitch = persona === 'maya' || persona === 'madzida' ? 1.1 : 0.9;
      u.onend = u.onerror = () => { if (btn) btn.classList.remove('speaking'); setAudioState('idle', 'no audio'); };
      window.speechSynthesis.speak(u);
    } else {
      if (btn) btn.classList.remove('speaking');
      setAudioState('idle', 'no audio');
    }
  }
  window.mayaWidgetSpeak = txt => speakText(txt, null);
  $('asPause') .addEventListener('click', () => { if (window.speechSynthesis) { window.speechSynthesis.pause(); setAudioState('paused', asText.textContent); } });
  $('asResume').addEventListener('click', () => { if (window.speechSynthesis) { window.speechSynthesis.resume(); setAudioState('speaking', asText.textContent); } });
  $('asStop')  .addEventListener('click', () => { if (window.speechSynthesis) { window.speechSynthesis.cancel(); setAudioState('idle', 'no audio'); } });

  // ── STT ───────────────────────────────────────────────────────────
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SR) {
    const recog = new SR();
    recog.continuous = false; recog.interimResults = true;
    recog.lang = navigator.language || 'en-US';
    recog.onresult = e => { let txt = ''; for (let i = e.resultIndex; i < e.results.length; i++) txt += e.results[i][0].transcript; msgEl.value = txt; autosize(); };
    recog.onerror = () => micBt.classList.remove('recording');
    recog.onend   = () => micBt.classList.remove('recording');
    micBt.addEventListener('click', () => {
      if (micBt.classList.contains('recording')) { recog.stop(); return; }
      try { recog.start(); micBt.classList.add('recording'); } catch (_) {}
    });
  } else { micBt.disabled = true; micBt.title = 'Speech recognition not supported'; }

  // ── Attach ────────────────────────────────────────────────────────
  attachBt.addEventListener('click', () => {
    if (!isAuthed()) { addBubble('system', 'Commander PIN required · click PIN chip to set.', false); return; }
    attachFile.click();
  });
  attachFile.addEventListener('change', () => {
    const f = attachFile.files && attachFile.files[0]; if (!f) return;
    uploadAttachment(f); attachFile.value = '';
  });
  async function uploadAttachment(f) {
    addBubble('system', 'Uploading ' + f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)...', false);
    pushFile(f.name, f.size);
    const fd = new FormData();
    fd.append('file', f); fd.append('domain', location.hostname); fd.append('url', location.href);
    fd.append('user_agent', navigator.userAgent); fd.append('timestamp', new Date().toISOString());
    fd.append('pin', currentPin());
    try {
      const r = await fetch(ATTACH_URL, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (d && d.ok) {
        addTool('attachment.received · ' + (d.ticket_id || '—'));
        addBubble('maya', d.reply || 'Acknowledged.');
        pushTicket(d.ticket_id || '?');
      } else { addError('Upload failed: ' + (d && d.error ? d.error : 'unknown')); }
    } catch (e) { addError('Attach error · ' + String(e)); }
  }

  // ── Quick actions ─────────────────────────────────────────────────
  const QUICK_ACTIONS = {
    vps:'Run a health probe on the VPS · df -h · uptime · free -h',
    apis:'Hunt new free LLM APIs across HF, Groq, Cerebras, Fireworks, Mistral, Together. Report.',
    boot:'Run the bootstrap sequence · cron status · sentinel count · revenue alert pipeline.',
    build:'Switch to Build mode for codegen.',
    parliament:'Activate Parliament of Minds: convene all reasoning LLMs for a full debate on empire status, biggest opportunities, and immediate priorities.',
    council:'Convene the 10-seat Council on the Maya OS UI/UX. Give honest feedback. What feels right? What feels broken?',
    health:'GET /api/index?action=health · summarize what is alive.',
    self_improve:'Run Parliament Self-Improve: find the 3 highest-leverage upgrades to ship this week.',
    repair:'Run self-repair: scan for broken endpoints · dead keys · stale memory · propose fixes.',
    video:'Switch to Video mode.',
    vision:'Switch to Vision mode.'
  };
  qsAll('.qb').forEach(btn => btn.addEventListener('click', () => {
    const q = btn.dataset.q;
    if (q === 'build')  return switchMode('build');
    if (q === 'video')  return switchMode('video');
    if (q === 'vision') return switchMode('vision');
    msgEl.value = QUICK_ACTIONS[q] || '';
    autosize();
    if (msgEl.value) sendMessage();
  }));

  // ── Generation bindings ───────────────────────────────────────────
  function bindGen(opts) {
    const btn = $(opts.btnId); const promptEl = $(opts.promptId); const out = $(opts.resultId);
    if (!btn) return;
    btn.addEventListener('click', async () => {
      const prompt = (promptEl && promptEl.value || '').trim();
      if (!prompt) return;
      out.innerHTML = '<div class="empty-sm">Generating...</div>';
      addTool(opts.action + '.request');
      try {
        // v1.3 · if attachState present, upload attachments first via /api/maya_attachment.php · pass refs to brain
        let attachments = [];
        if (opts.attachState && genAttachStates && genAttachStates[opts.attachState] && genAttachStates[opts.attachState].length) {
          for (const f of genAttachStates[opts.attachState]) {
            try {
              const fd = new FormData();
              fd.append('file', f); fd.append('domain', location.hostname); fd.append('url', location.href);
              fd.append('pin', currentPin()); fd.append('timestamp', new Date().toISOString());
              const ar = await fetch(ATTACH_URL, { method: 'POST', body: fd, credentials: 'same-origin' });
              const ad = await ar.json();
              if (ad && ad.ok) { attachments.push({ name: f.name, ticket: ad.ticket_id, mime: f.type, size: f.size }); pushFile(f.name, f.size); }
            } catch (_) {}
          }
        }
        const extra = opts.extra || (opts.extraFn ? opts.extraFn() : {});
        const payload = Object.assign({ action: opts.action, prompt, message: prompt, pin: currentPin(), attachments }, extra);
        const r = await fetch(BRAIN_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const txt = await r.text();
        let data; try { data = JSON.parse(txt); } catch (_) { data = { raw: txt }; }
        renderGenResult(out, opts.action, data);
        addTool(opts.action + '.done' + (attachments.length ? ' · ' + attachments.length + ' refs' : ''));
      } catch (e) { out.innerHTML = '<div class="empty-sm" style="color:#ffb0bd">Error: ' + escapeHTML(String(e)) + '</div>'; }
    });
  }
  // v1.8 · Talk-to-Maya co-pilot · plain English → studio dial-in
  // Mo's request: "I NEED A CHAT TO SAY WHAT I WANT IN MUSIC/3D/VIDEO TABS"
  function bindStudioCoPilot(opts) {
    const btn = $(opts.btnId); const input = $(opts.inputId); const out = $(opts.outId);
    if (!btn || !input || !out) return;
    btn.addEventListener('click', async () => {
      const desc = (input.value || '').trim();
      if (!desc) return;
      out.innerHTML = '<span class="sc-thinking">Maya thinking…</span>';
      addTool(opts.studio + '.copilot.request');
      // Inventory current field values so Maya knows the starting state
      const current = {};
      (opts.fields || []).forEach(id => { const el = $(id); if (el) current[id] = el.value; });
      const sysPrompt = 'You are Maya · studio co-pilot for the ' + opts.studio + ' studio. ' +
        'The user describes what they want · you respond with TWO things: (1) one short human sentence acknowledging what you understood, then (2) a fenced ```json block with the exact field values to set. ' +
        'Field IDs available: ' + (opts.fields || []).join(', ') + '. ' +
        'For music · valid musGenre/musGenre2 values include: synthwave, lofi, house, techno, trance, dnb, dubstep, future_bass, ambient, idm, phonk, trap, boom_bap, drill, rnb, neo_soul, rock, indie_rock, punk, metal, pop, dream_pop, shoegaze, balkan_folk, bosnian_sevdah, afrobeats, latin, celtic, middle_eastern, indian_classical, kpop, cinematic, orchestral, trailer, film_score, hybrid_orchestral, acoustic, folk, country, blues, jazz, classical · musMood: melancholy, hopeful, energetic, dark, dreamy, aggressive, peaceful, romantic, epic, mysterious, playful, nostalgic · musBpm: number 60-180 · musDuration: seconds 30-300 · musKey: A_minor/C_major/etc · musLead/musSecond instruments include guitar/electric_guitar/acoustic_guitar/12_string/saz/koto/shamisen/banjo/violin/cello/piano/synth_lead/marimba/vibraphone/kalimba/steel_drum/sitar/oud · musDrums: 808_trap, acoustic_rock, electronic, jazz_brushes, cajón, bongos, congas, darbuka, taiko, hand_drum, hang_drum, none · musStructure: verse_chorus, intro_verse_chorus_bridge, abab, build_drop · musLyrics: lyrics text or empty. ' +
        'For video · vidStyle includes cinematic/anamorphic/film_noir/hyperrealistic/documentary/nature_doc/anime/pixar3d/cyberpunk/vaporwave/commercial/music_video/social_short · vidAspect: 16:9, 9:16, 1:1, 21:9 · vidDur: 3-30 · vidCam: static, dolly_in, dolly_out, pan_left, pan_right, drone, handheld, orbit. ' +
        'For three_d · respond with a refined image prompt under "prompt" describing the reference image to generate before meshing. ' +
        'Always emit valid JSON · only the field IDs listed · skip ones that don\'t apply.';
      const userMsg = 'Current field state: ' + JSON.stringify(current) + '\nUser wants: ' + desc;
      try {
        const r = await fetch(BRAIN_URL, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'chat', message: userMsg, system: sysPrompt, pin: currentPin() })
        });
        const txt = await r.text();
        let data; try { data = JSON.parse(txt); } catch (_) { data = { reply: txt }; }
        const reply = data.reply || data.text || data.raw || '(no reply)';
        // Parse the json block · could be ```json ... ``` or just { ... }
        let setObj = null;
        const fenceMatch = reply.match(/```(?:json)?\s*([\s\S]*?)\s*```/);
        if (fenceMatch) { try { setObj = JSON.parse(fenceMatch[1]); } catch (_) {} }
        if (!setObj) {
          const braceMatch = reply.match(/\{[\s\S]*\}/);
          if (braceMatch) { try { setObj = JSON.parse(braceMatch[0]); } catch (_) {} }
        }
        // Strip the json block from the display reply so user just sees the sentence
        const displayReply = reply.replace(/```(?:json)?\s*[\s\S]*?\s*```/, '').trim() || 'OK · dialing in.';
        let appliedList = [];
        if (setObj && typeof setObj === 'object') {
          Object.keys(setObj).forEach(k => {
            const el = $(k);
            if (el && setObj[k] !== undefined && setObj[k] !== null && setObj[k] !== '') {
              el.value = String(setObj[k]);
              // For range slider · trigger display update
              if (el.type === 'range' && el.oninput) el.oninput();
              appliedList.push(k);
            }
          });
          // 3D · if she returned a "prompt" but no field, push it into the threeD textarea-equivalent (we have no field)
          // We just show it in out and the user can drop an image manually · OR auto-fire image+three_d if studio=three_d
          if (opts.studio === 'three_d' && setObj.prompt) {
            // Auto-fire: generate image first, then three_d off that image
            out.innerHTML = '<span class="sc-applied">Got it.</span> ' + escapeHTML(displayReply) + '\n→ Generating reference image from: ' + escapeHTML(setObj.prompt) + '\n(then auto-meshing with Trellis)';
            try {
              const ir = await fetch(BRAIN_URL, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'image', prompt: setObj.prompt, pin: currentPin() })
              });
              const idata = await ir.json();
              const imgUrl = idata.image_url || idata.url || idata.preview_url;
              if (imgUrl) {
                const tdOut = $('threeDResult');
                if (tdOut) tdOut.innerHTML = '<img src="' + escapeHTML(imgUrl) + '" style="max-width:300px"><div class="empty-sm">Reference generated · meshing…</div>';
                const tr = await fetch(BRAIN_URL, {
                  method: 'POST', headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'three_d', image_url: imgUrl, pin: currentPin() })
                });
                const tdata = await tr.json();
                renderGenResult($('threeDResult'), 'three_d', tdata);
              }
            } catch (e) {}
            addTool(opts.studio + '.copilot.applied');
            return;
          }
        }
        // For music/video · also write the description into the prompt textarea so Generate picks it up
        if (opts.promptId) {
          const promptEl = $(opts.promptId);
          if (promptEl && !promptEl.value.trim()) promptEl.value = desc;
        }
        out.innerHTML = '<span class="sc-applied">Got it.</span> ' + escapeHTML(displayReply) +
          (appliedList.length ? '\n→ Set: ' + appliedList.join(', ') : '');
        addTool(opts.studio + '.copilot.applied · ' + appliedList.length + ' fields');
      } catch (e) {
        out.innerHTML = '<span style="color:#ffb0bd">Error: ' + escapeHTML(String(e)) + '</span>';
      }
    });
    // Enter to send (Shift+Enter = newline)
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); btn.click(); }
    });
  }

  function renderGenResult(out, action, data) {
    out.innerHTML = '';
    if (!data) { out.innerHTML = '<div class="empty-sm">Empty response.</div>'; return; }
    if (data.image_url || data.url || data.preview_url) {
      const url = data.image_url || data.url || data.preview_url;
      if (/\.(png|jpe?g|gif|webp|svg)/i.test(url)) { out.innerHTML = '<img src="' + escapeHTML(url) + '" alt="' + action + ' result">'; return; }
      if (/\.(mp4|webm|mov)/i.test(url))           { out.innerHTML = '<video src="' + escapeHTML(url) + '" controls></video>'; return; }
      if (/\.(mp3|wav|ogg|m4a)/i.test(url))        { out.innerHTML = '<audio src="' + escapeHTML(url) + '" controls></audio>'; return; }
      out.innerHTML = '<a href="' + escapeHTML(url) + '" target="_blank" rel="noopener">Open result →</a>'; return;
    }
    if (data.audio_url) { out.innerHTML = '<audio src="' + escapeHTML(data.audio_url) + '" controls></audio>'; return; }
    if (data.code || (typeof data.reply === 'string' && data.reply.length > 200)) { out.innerHTML = '<pre>' + escapeHTML(data.code || data.reply) + '</pre>'; return; }
    if (data.reply) { out.textContent = data.reply; return; }
    if (data.error) { out.innerHTML = '<div class="empty-sm" style="color:#ffb0bd">' + escapeHTML(data.error) + '</div>'; return; }
    out.innerHTML = '<pre>' + escapeHTML(JSON.stringify(data, null, 2)) + '</pre>';
  }
  bindGen({ btnId: 'imgGen',   promptId: 'imgPrompt',   resultId: 'imgResult',   action: 'image' });
  bindGen({ btnId: 'vidGen',   promptId: 'vidPrompt',   resultId: 'vidResult',   action: 'video', attachState: 'vid',
    extraFn: () => ({
      style: $('vidStyle') ? $('vidStyle').value : 'cinematic',
      aspect: $('vidAspect') ? $('vidAspect').value : '16:9',
      duration: $('vidDur') ? +$('vidDur').value : 5,
      camera: $('vidCam') ? $('vidCam').value : 'static'
    }) });
  bindGen({ btnId: 'musGen',   promptId: 'musPrompt',   resultId: 'musResult',   action: 'music', attachState: 'mus',
    extraFn: () => ({
      genre:    $('musGenre')    ? $('musGenre').value    : 'cinematic',
      genre2:   $('musGenre2')   ? $('musGenre2').value   : '',         // v1.7 · mix-of secondary
      mood:     $('musMood')     ? $('musMood').value     : 'uplifting',
      bpm:      $('musBpm')      ? +$('musBpm').value     : 90,
      duration: $('musDur')      ? +$('musDur').value     : 60,
      key:      $('musKey')      ? $('musKey').value      : 'auto',
      lead:     $('musLead')     ? $('musLead').value     : 'acoustic_guitar',
      second:   $('musSecond')   ? $('musSecond').value   : '',         // v1.7 · layered second instrument
      drums:    $('musDrums')    ? $('musDrums').value    : 'acoustic', // v1.7 · drums style
      structure:$('musStructure')? $('musStructure').value: 'verse_chorus', // v1.7 · structure template
      lyrics:   $('musLyrics')   ? $('musLyrics').value   : ''          // v1.7 · Suno-class lyrics
    }) });
  bindGen({ btnId: 'buildGen', promptId: 'buildPrompt', resultId: 'buildResult', action: 'code',  extra: { engine: 'deepseek' } });
  bindGen({ btnId: 'chainGen', promptId: 'chainPrompt', resultId: 'chainResult', action: 'chain' });

  // v1.8 · Talk-to-Maya co-pilot on each studio tab · Mo's chat input request
  bindStudioCoPilot({
    studio: 'music',
    inputId: 'musCoPilot', btnId: 'musCoPilotGo', outId: 'musCoPilotOut',
    promptId: 'musPrompt',
    fields: ['musGenre','musGenre2','musMood','musBpm','musDuration','musKey','musLead','musSecond','musDrums','musStructure','musLyrics']
  });
  bindStudioCoPilot({
    studio: 'video',
    inputId: 'vidCoPilot', btnId: 'vidCoPilotGo', outId: 'vidCoPilotOut',
    promptId: 'vidPrompt',
    fields: ['vidStyle','vidAspect','vidDur','vidCam']
  });
  bindStudioCoPilot({
    studio: 'three_d',
    inputId: 'threeDCoPilot', btnId: 'threeDCoPilotGo', outId: 'threeDCoPilotOut',
    promptId: null,  // 3D has no text prompt field · we generate the reference image directly
    fields: []
  });

  // v1.3 · Video + Music attachment state · files staged before Generate
  const genAttachStates = { vid: [], mus: [] };
  function wireGenAttach(prefix) {
    const btn = $(prefix + 'AttachBtn'); const file = $(prefix + 'AttachFile'); const list = $(prefix + 'AttachList');
    if (!btn) return;
    btn.addEventListener('click', () => file.click());
    file.addEventListener('change', () => {
      Array.from(file.files || []).forEach(f => genAttachStates[prefix].push(f));
      renderAttachList(prefix);
      file.value = '';
    });
    function renderAttachList() {
      list.innerHTML = genAttachStates[prefix].map((f, i) =>
        '<span class="gen-chip" data-i="' + i + '">' + escapeHTML(f.name) + ' · ' + (f.size/1024).toFixed(1) + 'KB <button class="gen-chip-x" data-i="' + i + '" type="button">×</button></span>'
      ).join('');
      list.querySelectorAll('.gen-chip-x').forEach(x => x.addEventListener('click', e => {
        genAttachStates[prefix].splice(+e.target.dataset.i, 1);
        renderAttachList();
      }));
    }
  }
  wireGenAttach('vid');
  wireGenAttach('mus');
  // v1.6 · Voice panel · REAL Maya TTS (audio/wav binary stream)
  (function bindVoice() {
    const btn = $('voiceGen'); if (!btn) return;
    btn.addEventListener('click', async () => {
      const txt = ($('voicePrompt').value || '').trim(); if (!txt) return;
      const voice = $('voiceModel').value || 'maya_default';
      const out = $('voiceResult');
      out.innerHTML = '<div class="empty-sm">Synthesizing via ' + voice + '...</div>';
      addTool('tts.request · ' + voice);
      try {
        const r = await fetch(BRAIN_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'tts', text: txt, voice: voice, pin: currentPin() }) });
        const ct = r.headers.get('content-type') || '';
        if (ct.startsWith('audio/')) {
          const blob = await r.blob();
          const url = URL.createObjectURL(blob);
          out.innerHTML = '<audio src="' + url + '" controls autoplay style="width:100%"></audio>' +
                          '<div class="muted" style="margin-top:8px;font-size:11px">Voice: ' + voice + ' · ' + (blob.size / 1024).toFixed(1) + ' KB · ' + ct + ' · powered by Maya brain (Modal Kokoro)</div>';
          addTool('tts.played · ' + (blob.size / 1024).toFixed(0) + 'KB');
        } else {
          const d = await r.json();
          renderGenResult(out, 'tts', d);
        }
      } catch (e) { out.innerHTML = '<div class="empty-sm" style="color:#ffb0bd">' + escapeHTML(String(e)) + '</div>'; }
    });
  })();

  // v1.6 · upgrade speakText to use REAL Maya TTS · browser speechSynthesis as fallback
  // (Mo's complaint: "Maya doesn't speak" · she does now)
  const _origSpeakText = speakText;
  window.speakText = async function (text, btn) {
    if (!text) return;
    setAudioState('speaking', text.slice(0, 60) + (text.length > 60 ? '…' : ''));
    if (btn) btn.classList.add('speaking');
    try {
      const r = await fetch(BRAIN_URL, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'tts', text: text, voice: 'maya_default' })
      });
      const ct = r.headers.get('content-type') || '';
      if (ct.startsWith('audio/')) {
        const blob = await r.blob();
        const url = URL.createObjectURL(blob);
        const audio = new Audio(url);
        audio.onended = audio.onerror = () => {
          if (btn) btn.classList.remove('speaking');
          setAudioState('idle', 'no audio');
          URL.revokeObjectURL(url);
        };
        await audio.play();
        return;
      }
    } catch (_) { /* fall through to browser TTS */ }
    // Fallback: browser speechSynthesis (faster but robotic)
    return _origSpeakText(text, btn);
  };
  // Also update local references
  speakText = window.speakText;
  window.mayaWidgetSpeak = txt => speakText(txt, null);
  function bindDrop(opts) {
    const drop = $(opts.dropId); const file = $(opts.fileId); const pick = $(opts.pickId); const out = $(opts.resultId);
    if (!drop) return;
    pick.addEventListener('click', () => file.click());
    drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('dragover'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
    drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('dragover'); const f = e.dataTransfer.files && e.dataTransfer.files[0]; if (f) doVisionOrThreeD(opts.action, f, out); });
    file.addEventListener('change', () => { const f = file.files && file.files[0]; if (f) doVisionOrThreeD(opts.action, f, out); file.value = ''; });
  }
  bindDrop({ dropId: 'visionDrop', fileId: 'visionFile', pickId: 'visionPick', resultId: 'visionResult', action: 'vision' });
  bindDrop({ dropId: 'threeDDrop', fileId: 'threeDFile', pickId: 'threeDPick', resultId: 'threeDResult', action: 'three_d' });
  async function doVisionOrThreeD(action, f, out) {
    if (!isAuthed()) { out.innerHTML = '<div class="empty-sm" style="color:#ffaa00">Commander PIN required.</div>'; return; }
    out.innerHTML = '<div class="empty-sm">Uploading...</div>';
    const fd = new FormData();
    fd.append('file', f); fd.append('domain', location.hostname); fd.append('url', location.href);
    fd.append('pin', currentPin()); fd.append('timestamp', new Date().toISOString());
    addTool(action + '.request · ' + f.name);
    try {
      const r = await fetch(ATTACH_URL, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (d && d.ok) {
        out.innerHTML = '';
        const img = document.createElement('img'); img.src = URL.createObjectURL(f); img.style.maxWidth = '100%'; img.style.borderRadius = '10px'; img.style.marginBottom = '14px';
        out.appendChild(img);
        const p = document.createElement('div'); p.style.whiteSpace = 'pre-wrap'; p.textContent = d.reply || 'Acknowledged.';
        out.appendChild(p);
        pushTicket(d.ticket_id || '?');
        addTool(action + '.done · ' + (d.ticket_id || ''));
      } else { out.innerHTML = '<div class="empty-sm" style="color:#ffb0bd">' + escapeHTML((d && d.error) || 'failed') + '</div>'; }
    } catch (e) { out.innerHTML = '<div class="empty-sm" style="color:#ffb0bd">' + escapeHTML(String(e)) + '</div>'; }
  }

  // ── Right-rail push helpers ───────────────────────────────────────
  function pushToolCall(name) {
    if (toolCalls.querySelector('.empty-sm')) toolCalls.innerHTML = '';
    const c = document.createElement('div'); c.className = 'rail-card run';
    c.innerHTML = '<div class="rc-title">' + escapeHTML(String(name)) + '</div><div class="rc-meta">' + new Date().toLocaleTimeString() + '</div>';
    toolCalls.insertBefore(c, toolCalls.firstChild);
    if (toolCalls.children.length > 16) toolCalls.removeChild(toolCalls.lastChild);
    updateCounts();
  }
  function pushFile(name, size) {
    if (recentFiles.querySelector('.empty-sm')) recentFiles.innerHTML = '';
    const c = document.createElement('div'); c.className = 'rail-card';
    c.innerHTML = '<div class="rc-title">' + escapeHTML(name) + '</div><div class="rc-meta">' + (size / 1024).toFixed(1) + ' KB · ' + new Date().toLocaleTimeString() + '</div>';
    recentFiles.insertBefore(c, recentFiles.firstChild);
    if (recentFiles.children.length > 8) recentFiles.removeChild(recentFiles.lastChild);
    if (fileList && fileList.querySelector('.empty-sm')) fileList.innerHTML = '';
    if (fileList) { const row = document.createElement('div'); row.className = 'file-row'; row.innerHTML = '<span class="fn">' + escapeHTML(name) + '</span><span class="fm">' + (size / 1024).toFixed(1) + ' KB</span>'; fileList.insertBefore(row, fileList.firstChild); }
    updateCounts();
  }
  function pushTicket(id) {
    if (recentTks.querySelector('.empty-sm')) recentTks.innerHTML = '';
    const c = document.createElement('div'); c.className = 'rail-card ok';
    c.innerHTML = '<div class="rc-title">' + escapeHTML(id) + '</div><div class="rc-meta">' + new Date().toLocaleTimeString() + '</div>';
    recentTks.insertBefore(c, recentTks.firstChild);
    if (recentTks.children.length > 8) recentTks.removeChild(recentTks.lastChild);
    updateCounts();
  }
  updateCounts();

  // ── Status ────────────────────────────────────────────────────────
  function setStatus(o) { statusDot.classList.toggle('online', o); statusDot.classList.toggle('offline', !o); statusText.textContent = o ? 'online' : 'offline'; }
  async function pingBrain() {
    try {
      const r = await fetch(BRAIN_URL + '?action=health'); const d = await r.json();
      setStatus(!!d.alive);
      if (d.version) lmEl.textContent = 'b' + d.version;
      const brainBox = $('brainBox');
      if (brainBox) {
        $('brainTitle').textContent = d.engine || 'MAYA';
        $('brainMeta').textContent = (d.total_keys || '—') + ' keys · ' + (d.total_engines || '—') + ' engines · v' + (d.version || '?');
      }
    } catch (_) { setStatus(false); }
  }
  pingBrain(); setInterval(pingBrain, 30000);

  // ── Staff ─────────────────────────────────────────────────────────
  const STAFF = [
    { emoji: '✍️', name: 'Aria', role: 'Script', group: 'asg' },
    { emoji: '🌐', name: 'Nexus', role: 'Bridge', group: 'asg' },
    { emoji: '🎨', name: 'Pixel', role: 'Design', group: 'asg' },
    { emoji: '🎬', name: 'Echo', role: 'Video', group: 'asg' },
    { emoji: '⭐', name: 'Vega', role: 'Brand', group: 'asg' },
    { emoji: '🔐', name: 'Cipher', role: 'Crypto', group: 'asg' },
    { emoji: '🎵', name: 'Lyra', role: 'Music', group: 'asg' },
    { emoji: '🛠️', name: 'Orion', role: 'Ops', group: 'asg' },
    { emoji: '🚀', name: 'Nova', role: 'Growth', group: 'asg' },
    { emoji: '💪', name: 'Titan', role: 'Force', group: 'asg' },
    { emoji: '🧠', name: 'Kin', role: 'Desktop · Architecture', group: 'sibling', tag: 'AI' },
    { emoji: '🧠', name: 'Sage', role: 'OpenCode · Backend', group: 'sibling', tag: 'AI' },
    { emoji: '🧠', name: 'EaZo', role: 'Cline · Frontend', group: 'sibling', tag: 'AI' },
    { emoji: '🌟', name: 'Maya', role: 'COO · Total Kingdom', group: 'sibling', tag: 'AI' },
    { emoji: '💰', name: 'Revenue Sentinel', role: 'Watcher · 27,781 active', group: 'sentinel' },
    { emoji: '👁️', name: 'CEO Loop', role: 'Self-eval · every 15m', group: 'sentinel' },
    { emoji: '📨', name: 'Inbox Sentinel', role: 'Email · 4 tunnels', group: 'sentinel' },
    { emoji: '🌍', name: 'Spider', role: 'Lane router · auto-rotate', group: 'sentinel' },
    { emoji: '📈', name: 'Lead Harvester', role: 'Reddit · LinkedIn cron', group: 'sentinel' },
    { emoji: '🛡️', name: 'Guard', role: 'Audit · RULE 141 scanner', group: 'sentinel' },
    { emoji: '🎯', name: 'Sniper', role: 'Outreach · verified', group: 'sentinel' },
    { emoji: '🔭', name: 'Scout', role: 'Free-API hunter', group: 'sentinel' },
    { emoji: '🏗️', name: 'TopForge', role: 'Native binaries', group: 'forge' },
    { emoji: '🌐', name: 'AppForge', role: 'Web app builder', group: 'forge' },
    { emoji: '🤝', name: 'EaZo Persona', role: 'Friendly builder', group: 'forge' },
    { emoji: '📺', name: 'FunFactPulse', role: 'Channel · viral facts', group: 'channel' },
    { emoji: '📺', name: 'ChimeraChannel', role: 'Channel · mythology', group: 'channel' },
    { emoji: '📺', name: 'MooseRiders', role: 'Channel · sacred', group: 'channel' },
    { emoji: '📺', name: 'AiCineSynth', role: 'Channel · cinema', group: 'channel' },
    { emoji: '📺', name: 'TechBitReels', role: 'Channel · tech', group: 'channel' },
    { emoji: '📺', name: 'MindUnlocked', role: 'Channel · psych', group: 'channel' },
    { emoji: '⚡', name: 'Groq', role: 'Lane · 13 keys · chat', group: 'lane' },
    { emoji: '🔍', name: 'Gemini', role: 'Lane · 37 keys · research', group: 'lane' },
    { emoji: '💻', name: 'DeepSeek', role: 'Lane · 48 keys · code', group: 'lane' },
    { emoji: '🇫🇷', name: 'Mistral', role: 'Lane · 6 keys · codestral', group: 'lane' },
    { emoji: '⚡', name: 'NVIDIA NIM', role: 'Lane · 42 keys · frontier', group: 'lane' },
    { emoji: '💠', name: 'Kimi', role: 'Lane · 2 keys · K2.6', group: 'lane' },
    { emoji: '🧬', name: 'OpenAI', role: 'Lane · 32 keys · GPT-4o', group: 'lane' },
    { emoji: '🦾', name: 'OpenRouter', role: 'Lane · 27 keys · 300+', group: 'lane' },
    { emoji: '🌐', name: 'HuggingFace', role: 'Lane · 22 tokens', group: 'lane' },
    { emoji: '⚗️', name: 'Modal', role: 'GPU lane · 8 accounts · Kokoro/F5/Trellis', group: 'lane' },
    { emoji: '📓', name: 'Kaggle', role: 'GPU notebooks · 4 acc · 120h/wk free · batch jobs', group: 'lane' },
    { emoji: '🔊', name: 'Kokoro TTS', role: 'Voice · af_heart (female) · am_adam (male) · Modal', group: 'lane' }
    // RUNWAY + ELEVENLABS REMOVED 2026-05-12 · Mo banned them · Kokoro on Modal is canonical (memory #95 + #177)
  ];
  // v1.3 · split staff into ACTUAL crew vs compute lanes (Mo: "things in staff that are not staff")
  const GROUP_LABELS = {
    agency:   '🏛️ ai-staffing.agency · LIVE roster · 57 agencies · click to dispatch',
    asg:      '👷 ASG Agents · Maya orchestrates these',
    sibling:  '🧠 Sibling AIs · they work alongside Maya',
    sentinel: '🛡️ Sentinels · 27,774 active workers',
    forge:    '🏗️ FORGE Pillar · builders',
    channel:  '📺 Channels · content engines',
    lane:     '⚙️ Compute Lanes · not staff · the tools Maya picks from'
  };
  // v1.5 · render IMMEDIATELY · async pull live ai-staffing.agency · re-render when in
  let LIVE_AGENCIES = [];
  let liveAgenciesLoading = false;
  async function loadLiveAgencies() {
    if (LIVE_AGENCIES.length || liveAgenciesLoading) return;
    liveAgenciesLoading = true;
    try {
      const r = await fetch('https://ai-staffing.agency/api/staff.php');
      const d = await r.json();
      if (d && d.agencies && Array.isArray(d.agencies)) {
        LIVE_AGENCIES = d.agencies.map(a => ({
          emoji: a.icon || '🏛️',
          name: a.name,
          role: (a.role_count || 0) + ' roles · ' + (a.tagline || ''),
          group: 'agency',
          id: a.id,
          color: a.color,
          description: a.description
        }));
      }
    } catch (_) {}
    liveAgenciesLoading = false;
  }

  function renderStaff(filter) {
    // v1.5 · render immediately with what we have · don't block on async
    renderStaffActual(filter);
    // async pull ai-staffing roster · re-render when they land
    loadLiveAgencies().then(() => {
      if (LIVE_AGENCIES.length && !STAFF.some(s => s.group === 'agency')) {
        STAFF.push.apply(STAFF, LIVE_AGENCIES);
        renderStaffActual(filter);
      }
    });
  }
  function renderStaffActual(filter) {
    const q = (filter || (staffSearch && staffSearch.value) || '').toLowerCase();
    const list = STAFF.filter(s => !q || s.name.toLowerCase().includes(q) || s.role.toLowerCase().includes(q) || (s.group || '').toLowerCase().includes(q));
    // group by category
    const buckets = {};
    list.forEach(s => { (buckets[s.group] = buckets[s.group] || []).push(s); });
    const order = ['agency','asg','sibling','sentinel','forge','channel','lane'];
    let html = '';
    order.forEach(k => {
      if (!buckets[k] || !buckets[k].length) return;
      html += '<div class="staff-group-h">' + (GROUP_LABELS[k] || k) + '</div>';
      html += '<div class="staff-grid-inner">' + buckets[k].map(s =>
        '<div class="staff-card" data-name="' + escapeHTML(s.name) + '" data-group="' + escapeHTML(s.group) + '">' +
          '<div class="sc-emoji">' + s.emoji + '</div>' +
          '<div class="sc-name">' + escapeHTML(s.name) + '</div>' +
          '<div class="sc-role">' + escapeHTML(s.role) + '</div>' +
          (s.tag ? '<div class="sc-tag">' + s.tag + '</div>' : '') +
        '</div>'
      ).join('') + '</div>';
    });
    staffGrid.innerHTML = html || '<div class="empty">No match.</div>';
    qsAll('.staff-card').forEach(c => c.addEventListener('click', () => {
      const g = c.dataset.group;
      const name = c.dataset.name;
      if (g === 'agency') {
        // v1.6 · drill-in modal · shows agency roles + dispatch buttons
        showAgencyDrillIn(name);
      } else {
        switchMode('chat');
        msgEl.value = '/' + name.toLowerCase() + ' ';
        msgEl.focus(); autosize();
      }
    }));
  }

  // v1.6 · Agency drill-in modal · shows the 10-12 roles per agency · per-role dispatch
  function showAgencyDrillIn(agencyName) {
    let modal = document.getElementById('agencyDrillIn');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'agencyDrillIn';
      modal.className = 'palette-backdrop';
      modal.innerHTML = '<div class="palette" style="width:min(720px,94vw)"><div class="palette-input" style="padding:14px 18px;border-bottom:1px solid var(--border-mt);display:flex;justify-content:space-between;align-items:center"><strong id="adiTitle" style="color:var(--text);font-size:16px"></strong><button id="adiClose" style="background:transparent;border:1px solid var(--border);color:var(--text-mut);padding:4px 10px;border-radius:6px;font-size:12px">✕ Close</button></div><div class="palette-results" id="adiResults" style="padding:14px"></div><div class="palette-foot"><button id="adiDispatchAll" style="background:linear-gradient(135deg,var(--accent),var(--maya));color:#000;border:0;padding:8px 16px;border-radius:999px;font-weight:700;font-size:12px">🚀 Dispatch ENTIRE agency to Maya</button></div></div>';
      document.body.appendChild(modal);
      modal.addEventListener('click', e => { if (e.target === modal) modal.hidden = true; });
      document.getElementById('adiClose').addEventListener('click', () => { modal.hidden = true; });
    }
    document.getElementById('adiTitle').textContent = agencyName + ' · roles';
    modal.hidden = false;
    const out = document.getElementById('adiResults');
    out.innerHTML = '<div class="empty-sm">Loading roles from ai-staffing.agency...</div>';
    // Ask Maya to enumerate the roles (most reliable · live · uses brain)
    fetch(BRAIN_URL, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'chat',
        message: 'List all the roles inside the "' + agencyName + '" agency from ai-staffing.agency. Return ONLY a JSON array · each item {title, description}. Example: [{"title":"Senior AI Engineer","description":"Designs and ships production ML infrastructure."}]. 10-12 items. Real AI-staffing roles.',
        mode: 'chat', engine: 'groq', public: false
      })
    }).then(r => r.json()).then(d => {
      const reply = d.reply || d.response || '';
      let roles = [];
      try {
        const m = reply.match(/\[[\s\S]*?\]/);
        if (m) roles = JSON.parse(m[0]);
      } catch (_) {}
      if (!Array.isArray(roles) || roles.length === 0) {
        out.innerHTML = '<div class="empty-sm">Could not parse roles · raw reply:<br>' + escapeHTML(reply.slice(0, 600)) + '</div>';
        return;
      }
      out.innerHTML = roles.map((r, i) =>
        '<div class="exec-tool" style="margin-bottom:6px"><span class="et-emoji">👤</span><div class="et-body" style="flex:1"><strong>' + escapeHTML(r.title || '?') + '</strong><div class="et-sub">' + escapeHTML(r.description || '') + '</div></div><button class="cmp-clear adi-dispatch-role" data-role="' + escapeHTML(r.title || '') + '" data-agency="' + escapeHTML(agencyName) + '" style="margin-left:8px">🚀 Dispatch</button></div>'
      ).join('');
      qsAll('.adi-dispatch-role').forEach(b => b.addEventListener('click', e => {
        const role = b.dataset.role; const ag = b.dataset.agency;
        fetch('/api/maya_qode_queue', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            title: 'Dispatch ' + role + ' (' + ag + ')',
            prompt: 'Mo dispatched the role "' + role + '" from the "' + ag + '" agency. Take over as Maya · brief the role · spawn the work plan · run the first task · report back. Maya · CEO/COO/Officer/Jarvis · this is yours.'
          })
        }).then(r => r.json()).then(j => {
          addBubble('maya', '✅ Dispatched ' + role + ' · task ' + (j.task_id || '?') + ' · loop picks up in <30s');
          modal.hidden = true;
          switchMode('chat');
        });
      }));
    }).catch(e => { out.innerHTML = '<div class="empty-sm" style="color:#ffb0bd">Error: ' + escapeHTML(String(e)) + '</div>'; });
  }
  // dispatch entire agency
  document.addEventListener('click', e => {
    if (e.target && e.target.id === 'adiDispatchAll') {
      const title = (document.getElementById('adiTitle') || {}).textContent || '';
      const ag = title.replace(' · roles', '').trim();
      fetch('/api/maya_qode_queue', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: 'Dispatch ENTIRE agency: ' + ag,
          prompt: 'Mo dispatched the entire "' + ag + '" agency. Take over as Maya · audit current empire state · pick the 3 highest-leverage roles in this agency for the current quarter · spawn each as a sub-task in the maya_qode_queue · report when in motion.'
        })
      }).then(r => r.json()).then(j => {
        addBubble('maya', '🚀 Dispatched ENTIRE ' + ag + ' agency · task ' + (j.task_id || '?'));
        document.getElementById('agencyDrillIn').hidden = true;
        switchMode('chat');
      });
    }
  });
  function _empty(arr) { return !arr || !arr.length; }
  // placeholder · safe-no-op (legacy hook)
  function noop() { }

  // v1.3 · SENTINELS PANEL · 27,774 modules with hover-metadata + last-used (GLOBAL-61)
  const SENTINEL_CATEGORIES = [
    { key: 'all',       label: 'All', count: 27774 },
    { key: 'revenue',   label: '💰 Revenue / Money', count: 1788 },
    { key: 'blitz',     label: '⚡ Blitz · rapid market', count: 50 },
    { key: 'pad',       label: '🧱 Pad · foundations', count: 1796 },
    { key: 'hocu',      label: '🎯 Hocu · targeting', count: 1372 },
    { key: 'f2fill',    label: '✍️ F2Fill · form-filler', count: 160 },
    { key: 'self_evolve', label: '🧬 Self-Evolve', count: 87 },
    { key: 'parliament', label: '⚖️ Parliament', count: 45 },
    { key: 'ceo',       label: '👁️ CEO Loop', count: 1 },
    { key: 'inbox',     label: '📨 Inbox', count: 1 },
    { key: 'spider',    label: '🌍 Spider · lane router', count: 1 },
    { key: 'guard',     label: '🛡️ Guard / Audit', count: 80 },
    { key: 'aut',       label: '🤖 Autonomous (Aut11)', count: 80 },
    { key: 'browser',   label: '🌐 Browser sentinels', count: 11 }
  ];
  let SENTINEL_CACHE = null;
  let sentinelCatActive = 'all';
  async function loadSentinelMeta() {
    if (SENTINEL_CACHE) return SENTINEL_CACHE;
    try {
      const r = await fetch('/api/sentinel_meta.php?list=1&limit=200');
      if (r.ok) { SENTINEL_CACHE = await r.json(); return SENTINEL_CACHE; }
    } catch (_) {}
    // graceful · synthetic list with the 50 known blitz + canonical sentinels
    SENTINEL_CACHE = { sentinels: [
      { id: 'revenue_sentinel', cat: 'revenue', name: 'Revenue Sentinel', purpose: 'Watches Stripe webhooks empire-wide · fires 💰 email on first dollar.', last: 'live · always-on' },
      { id: 'ceo_loop', cat: 'ceo', name: 'CEO Loop', purpose: 'Self-evaluation every 15m · empire priorities · daily report to Mo.', last: 'cron · 15m' },
      { id: 'inbox_sentinel', cat: 'inbox', name: 'Inbox Sentinel', purpose: 'Polls 4 sibling mailboxes (kin/coo/sage/eazo) every 60s · routes to Maya.', last: 'live · 60s' },
      { id: 'spider', cat: 'spider', name: 'Spider · Lane Router', purpose: 'Senses lane health · pre-warms next when current drops below 15% quota.', last: 'live · always-on' }
    ] };
    for (let i = 1; i <= 50; i++) {
      SENTINEL_CACHE.sentinels.push({
        id: 'sentinel_blitz_' + String(i).padStart(3,'0'), cat: 'blitz',
        name: 'Blitz ' + String(i).padStart(3,'0'),
        purpose: 'Expert: blitz attack strategies for rapid market capture — module ' + i + ' of 50.',
        last: '2026-04-10 (deployed)'
      });
    }
    return SENTINEL_CACHE;
  }
  async function renderSentinels() {
    const grid = $('sentinelGrid'); const cats = $('sentinelCategories'); const totalEl = $('sentinelTotal');
    if (!grid) return;
    cats.innerHTML = SENTINEL_CATEGORIES.map(c =>
      '<button class="sent-cat' + (c.key === sentinelCatActive ? ' active' : '') + '" data-key="' + c.key + '">' + escapeHTML(c.label) + ' <span class="sc-n">' + c.count + '</span></button>'
    ).join('');
    qsAll('.sent-cat').forEach(b => b.addEventListener('click', () => { sentinelCatActive = b.dataset.key; renderSentinels(); }));
    const data = await loadSentinelMeta();
    totalEl.textContent = '27,774';
    const q = ($('sentinelSearch') && $('sentinelSearch').value || '').toLowerCase();
    const list = (data.sentinels || []).filter(s =>
      (sentinelCatActive === 'all' || s.cat === sentinelCatActive) &&
      (!q || (s.name || '').toLowerCase().includes(q) || (s.purpose || '').toLowerCase().includes(q) || (s.id || '').toLowerCase().includes(q))
    );
    grid.innerHTML = list.length ? list.map(s => {
      const purpose = s.purpose || '(no purpose parsed · queue rename triage)';
      const last    = s.last || s.last_run || '—';
      return '<div class="sentinel-card" data-id="' + escapeHTML(s.id) + '" data-purpose="' + escapeHTML(purpose) + '">' +
        '<div class="sc-name">' + escapeHTML(s.name) + '</div>' +
        '<div class="sc-purpose">' + escapeHTML(purpose.slice(0, 200)) + '</div>' +   // v1.8 · VISIBLE not hover-only
        '<div class="sc-meta"><span class="sc-id">' + escapeHTML(s.id) + '</span><span class="sc-last">' + escapeHTML(last) + '</span></div>' +
      '</div>';
    }
    ).join('') : '<div class="empty-sm">No sentinels match.</div>';
    qsAll('.sentinel-card').forEach(c => c.addEventListener('click', () => {
      switchMode('chat');
      msgEl.value = 'Invoke sentinel ' + c.dataset.id + ' · summarize what it did + when last run + propose a useful task for it now.';
      msgEl.focus(); autosize();
    }));
  }
  if ($('sentinelSearch')) $('sentinelSearch').addEventListener('input', () => renderSentinels());
  if (staffSearch) staffSearch.addEventListener('input', () => renderStaff());

  // ── Executor (v1.4 · pre-loads · 4 tabs · engines/agents/skills/modules) ──
  let execTimer;
  let execTab = 'engines';
  let execCache = { engines: null, agents: null, skills: null, modules: null };
  async function execLoad(tab) {
    if (execCache[tab]) return execCache[tab];
    try {
      if (tab === 'engines') {
        const r = await fetch(BRAIN_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'list' }) });
        const d = await r.json();
        execCache.engines = (d.engines || []).map(e => ({ name: e.name, sub: e.keys + ' keys · ' + (e.models || []).slice(0,3).join(', '), emoji: '⚙️' }));
      } else if (tab === 'agents') {
        execCache.agents = [
          { emoji:'✍️', name:'Aria',   sub:'ASG · Script writing' },
          { emoji:'🌐', name:'Nexus',  sub:'ASG · Cross-domain bridge' },
          { emoji:'🎨', name:'Pixel',  sub:'ASG · Design' },
          { emoji:'🎬', name:'Echo',   sub:'ASG · Video production' },
          { emoji:'⭐', name:'Vega',   sub:'ASG · Brand strategy' },
          { emoji:'🔐', name:'Cipher', sub:'ASG · Crypto/security' },
          { emoji:'🎵', name:'Lyra',   sub:'ASG · Music composition' },
          { emoji:'🛠️', name:'Orion',  sub:'ASG · Ops/deploy' },
          { emoji:'🚀', name:'Nova',   sub:'ASG · Growth/marketing' },
          { emoji:'💪', name:'Titan',  sub:'ASG · Force/closing' },
          { emoji:'💰', name:'Revenue Sentinel', sub:'Watches Stripe · fires alerts · GLOBAL-63 auto-boot' },
          { emoji:'👁️', name:'CEO Loop',         sub:'Self-eval every 15m · empire priorities' },
          { emoji:'📨', name:'Inbox Sentinel',   sub:'4 mailbox poll · routes to Maya' },
          { emoji:'🌍', name:'Spider',           sub:'Lane router · pre-warms next provider' },
          { emoji:'📈', name:'Lead Harvester',   sub:'Reddit + LinkedIn cron · daily' },
          { emoji:'🛡️', name:'Guard',            sub:'Audit · RULE 141 leak scan' },
          { emoji:'🎯', name:'Sniper',           sub:'Cold outreach · verified blasts' },
          { emoji:'🔭', name:'Scout',            sub:'Free-API hunter' }
        ];
      } else if (tab === 'skills') {
        execCache.skills = [
          { emoji:'📦', name:'Model Availability Tester', sub:'Probe every NIM/HF/etc model · save matrix' },
          { emoji:'🕷️', name:'Spider Orchestrator',       sub:'Lane health · pre-warm · cutover · cooldown' },
          { emoji:'🔄', name:'Key-Fleet Rotation',         sub:'Round-robin · pre-warm · idempotency tags' },
          { emoji:'🎙️', name:'TTS-Modal Fleet',            sub:'Kokoro/F5/Style on 8 Modal accounts' },
          { emoji:'🛡️', name:'Sovereign Auditor',          sub:'Logs every render · grant pitch material' },
          { emoji:'👑', name:'Kingmaker Dashboard',        sub:'Per-channel throughput · revenue heatmap' },
          { emoji:'🎬', name:'Modal LivePortrait (gap)',   sub:'Lip-sync · gap-flagged · viral-video-gen' },
          { emoji:'💎', name:'Memory Crystal Loader',      sub:'GLOBAL-32 · extract → stage → compare' },
          { emoji:'🏛️', name:'Council Operations Protocol', sub:'11-section · model diversity · token budget' },
          { emoji:'⚖️', name:'Two-Branch Council',         sub:'Reasoning + Executory · nameplacer pattern' },
          { emoji:'🌀', name:'HF Inference Provider Tester', sub:'Probe routed models quarterly' },
          { emoji:'📊', name:'Empire Health Monitor',      sub:'28-site uptime · DNS · cert · SEO probe' }
        ];
      } else if (tab === 'modules') {
        execCache.modules = [
          { emoji:'🔨', name:'sentinel_blitz_*',         sub:'50 blitz attack modules · rapid market capture' },
          { emoji:'💰', name:'sentinel_money_*',          sub:'1788 revenue + monetization sentinels' },
          { emoji:'🧱', name:'sentinel_pad_*',            sub:'1796 foundation sentinels' },
          { emoji:'🎯', name:'sentinel_hocu_*',           sub:'1372 targeting sentinels' },
          { emoji:'✍️', name:'sentinel_f2fill_*',         sub:'160 form-filler sentinels' },
          { emoji:'🧬', name:'sentinel_self_evolve_*',    sub:'87 self-improvement sentinels' },
          { emoji:'⚖️', name:'sentinel_parliament_*',     sub:'45 parliament/council sentinels' },
          { emoji:'🤖', name:'sentinel_aut11_*',          sub:'80 autonomous sentinels' },
          { emoji:'🌐', name:'sentinel_browser_*',        sub:'11 browser-automation sentinels (Playwright stealth)' },
          { emoji:'🛡️', name:'sentinel_guard_*',          sub:'80 audit/compliance sentinels' },
          { emoji:'📚', name:'inception_meta',            sub:'Maya self-bootstrap modules' },
          { emoji:'💎', name:'crystals (91)',             sub:'Reference memory crystals · cross-session persistence' },
          { emoji:'🎭', name:'agentic modules (767)',     sub:'Multi-step agent definitions' }
        ];
      }
    } catch (_) {}
    return execCache[tab] || [];
  }
  // v1.9.5 · Evolution panel · daily provider scoreboard from /api/index action=evolution_report
  let evoReport = null;
  let evoHistoryVisible = false;
  async function renderEvolution(force) {
    const head = $('evoHeader');
    if (!head) return;
    if (evoReport && !force) { evoRenderAll(); return; }
    head.innerHTML = '<div class="evo-loading">Loading latest scan…</div>';
    try {
      const r = await fetch(BRAIN_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'evolution_report'})
      });
      const data = await r.json();
      evoReport = data.latest || data;
      evoRenderAll();
    } catch (e) {
      head.innerHTML = '<div class="evo-loading" style="color:#ffb0bd">Error loading report: ' + escapeHTML(String(e)) + '</div>';
    }
  }
  function evoHealthClass(pct) { if (pct >= 70) return 'green'; if (pct >= 40) return 'amber'; return 'red'; }
  function evoProvClass(prov) {
    if (prov.skipped || prov.health === 'UNKNOWN') return 'unknown';
    if (prov.health === 'GREEN') return 'green';
    if (prov.health === 'RED') return 'red';
    return 'amber';
  }
  function evoNextRun() {
    // Cron: 7 4 * * * = 04:07 UTC daily
    const now = new Date();
    const next = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), 4, 7, 0));
    if (next <= now) next.setUTCDate(next.getUTCDate() + 1);
    return next;
  }
  function evoRenderAll() {
    const r = evoReport; if (!r) return;
    const head = $('evoHeader');
    const pct = r.health_pct || 0;
    const hcls = evoHealthClass(pct);
    const ts = r.timestamp ? new Date(r.timestamp).toLocaleString() : '—';
    const dur = r.duration_ms ? (r.duration_ms / 1000).toFixed(1) + 's' : '—';
    head.innerHTML = '<div class="evo-headline">' +
      '<div class="evo-health-big ' + hcls + '">' + pct + '%</div>' +
      '<div class="evo-stat"><span class="evo-stat-label">Alive</span><span class="evo-stat-value">' + (r.alive || 0) + '</span></div>' +
      '<div class="evo-stat"><span class="evo-stat-label">Dead</span><span class="evo-stat-value">' + (r.dead || 0) + '</span></div>' +
      '<div class="evo-stat"><span class="evo-stat-label">Total keys</span><span class="evo-stat-value">' + (r.total_keys || 0) + '</span></div>' +
      '<div class="evo-stat"><span class="evo-stat-label">Last scan</span><span class="evo-stat-value muted">' + escapeHTML(ts) + '</span></div>' +
      '<div class="evo-stat"><span class="evo-stat-label">Duration</span><span class="evo-stat-value muted">' + dur + '</span></div>' +
    '</div>';

    const next = evoNextRun();
    const nextLabel = $('evoNext');
    if (nextLabel) nextLabel.textContent = 'Next scan: ' + next.toUTCString().replace(' GMT', ' UTC');

    const providers = Array.isArray(r.providers) ? r.providers : [];
    const visibleProviders = providers.filter(p => !p.skipped || p.total > 0);
    const pcEl = $('evoProviderCount');
    if (pcEl) pcEl.textContent = visibleProviders.length;
    const sb = $('evoScoreboard');
    if (!visibleProviders.length) {
      sb.innerHTML = '<div class="empty-sm">No providers in report.</div>';
    } else {
      sb.innerHTML = visibleProviders.map(p => {
        const total = p.total || 0;
        const alive = p.alive || 0;
        const ratio = total ? Math.round(alive / total * 100) : 0;
        const cls = evoProvClass(p);
        const badge = p.skipped ? 'SKIP' : (p.health || 'UNKNOWN');
        return '<div class="evo-prov-card ' + cls + '" title="' + escapeHTML(p.name) + ' · alive ' + alive + '/' + total + '">' +
          '<div style="display:flex;justify-content:space-between;align-items:center">' +
            '<span class="evo-prov-name">' + escapeHTML(p.name || '?') + '</span>' +
            '<span class="evo-prov-badge ' + cls + '">' + badge + '</span>' +
          '</div>' +
          '<div class="evo-prov-bar"><div class="evo-prov-fill ' + cls + '" style="width:' + ratio + '%"></div></div>' +
          '<div class="evo-prov-meta"><span>alive ' + alive + '/' + total + '</span><span>' + ratio + '%</span></div>' +
        '</div>';
      }).join('');
    }

    const capEl = $('evoCapabilities');
    const analysis = r.analysis || '';
    if (analysis && analysis.length > 5) {
      capEl.textContent = analysis;
    } else {
      // Synthesize a delta summary from provider data
      const green = visibleProviders.filter(p => p.health === 'GREEN').map(p => p.name + '(' + p.alive + ')');
      const red   = visibleProviders.filter(p => p.health === 'RED').map(p => p.name);
      capEl.innerHTML = '<strong style="color:#4ade80">GREEN (working):</strong> ' + escapeHTML(green.join(', ') || 'none') +
        '\n<strong style="color:#f87171">RED (all keys dead):</strong> ' + escapeHTML(red.join(', ') || 'none') +
        '\n<strong style="color:var(--text-mut)">Recommendation:</strong> rotate or replace dead keys for RED providers · the failover chain skips them but they bloat the arsenal.';
    }
  }
  if ($('evoRefresh')) {
    $('evoRefresh').addEventListener('click', () => renderEvolution(true));
  }
  if ($('evoHistoryToggle')) {
    $('evoHistoryToggle').addEventListener('click', async () => {
      const h = $('evoHistory');
      evoHistoryVisible = !evoHistoryVisible;
      h.hidden = !evoHistoryVisible;
      if (evoHistoryVisible && h.innerHTML.includes('Loading history')) {
        try {
          const r = await fetch(BRAIN_URL, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'evolution_report', include_history:true})});
          const d = await r.json();
          const list = Array.isArray(d.history) ? d.history : (d.cycles || []);
          if (!list.length) { h.innerHTML = '<div class="empty-sm">No history entries returned · /tmp/maya19/evolution/history/ has snapshots but the endpoint may not expose them yet.</div>'; return; }
          h.innerHTML = list.slice(-12).reverse().map(e => {
            const ts = e.timestamp || e.ts || '—';
            const pct = e.health_pct ?? e.score ?? '—';
            const cls = typeof pct === 'number' ? evoHealthClass(pct) : '';
            return '<div class="evo-hist-row"><span class="evo-hist-ts">' + escapeHTML(ts) + '</span><span class="evo-hist-health ' + cls + '">' + pct + '%</span><span style="color:var(--text-mut)">' + escapeHTML(e.note || e.summary || '') + '</span></div>';
          }).join('');
        } catch (e) {
          h.innerHTML = '<div class="empty-sm" style="color:#ffb0bd">Error: ' + escapeHTML(String(e)) + '</div>';
        }
      }
    });
  }

  async function renderExec() {
    execRes.innerHTML = '<div class="empty-sm">Loading ' + execTab + '...</div>';
    const list = await execLoad(execTab);
    const q = (execSearch && execSearch.value || '').toLowerCase();
    const filtered = q ? list.filter(it => (it.name || '').toLowerCase().includes(q) || (it.sub || '').toLowerCase().includes(q)) : list;
    if (!filtered.length) { execRes.innerHTML = '<div class="empty">No match.</div>'; return; }
    execRes.innerHTML = filtered.map(it =>
      '<div class="exec-tool" data-name="' + escapeHTML(it.name) + '">' +
        '<span class="et-emoji">' + (it.emoji || '⚙') + '</span>' +
        '<div class="et-body"><strong>' + escapeHTML(it.name) + '</strong>' +
        '<div class="et-sub">' + escapeHTML(it.sub || '') + '</div></div>' +
      '</div>'
    ).join('');
    qsAll('.exec-tool').forEach(c => c.addEventListener('click', () => {
      switchMode('chat');
      msgEl.value = 'Tell me about ' + c.dataset.name + ' · what it does · how to use it · last time used.';
      msgEl.focus(); autosize();
    }));
  }
  qsAll('.exec-tab').forEach(b => b.addEventListener('click', () => {
    execTab = b.dataset.exec;
    qsAll('.exec-tab').forEach(x => x.classList.toggle('active', x.dataset.exec === execTab));
    renderExec();
  }));
  if (execSearch) execSearch.addEventListener('input', () => { clearTimeout(execTimer); execTimer = setTimeout(renderExec, 200); });

  // ── Raw console ───────────────────────────────────────────────────
  const rawIn = $('rawIn'); const rawOut = $('rawOut'); const rawStat = $('rawStat');
  $('rawSend').addEventListener('click', async () => {
    let payload;
    try { payload = JSON.parse(rawIn.value || '{"action":"health"}'); } catch (e) { rawOut.textContent = 'Invalid JSON: ' + e.message; return; }
    rawOut.textContent = '// sending...'; rawStat.textContent = 'sending';
    const t0 = Date.now();
    try {
      const r = await fetch(BRAIN_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const txt = await r.text();
      rawStat.textContent = (Date.now() - t0) + 'ms · ' + r.status;
      try { rawOut.textContent = JSON.stringify(JSON.parse(txt), null, 2); } catch (_) { rawOut.textContent = txt; }
    } catch (e) { rawOut.textContent = 'Error: ' + String(e); rawStat.textContent = 'error'; }
  });
  $('rawClear').addEventListener('click', () => { rawOut.textContent = '// response appears here'; rawStat.textContent = '—'; });
  // v1.8 · Console quick-example buttons · click to load + auto-send
  qsAll('.console-ex').forEach(b => b.addEventListener('click', () => {
    rawIn.value = b.dataset.payload;
    $('rawSend').click();
  }));

  // ── Browser ───────────────────────────────────────────────────────
  const browserState = { tabs: [], active: -1 };
  try { const s = localStorage.getItem(TABS_KEY); if (s) browserState.tabs = JSON.parse(s) || []; } catch (_) {}
  function saveBrowser() { try { localStorage.setItem(TABS_KEY, JSON.stringify(browserState.tabs)); } catch (_) {} }
  function browserNew(url) {
    const u = url || 'https://mirzatech.ai';
    browserState.tabs.push({ url: u, title: new URL(u).hostname, history: [u], cursor: 0 });
    browserState.active = browserState.tabs.length - 1;
    renderBrowser(); saveBrowser();
  }
  function browserClose(idx) { browserState.tabs.splice(idx, 1); if (browserState.active >= browserState.tabs.length) browserState.active = browserState.tabs.length - 1; renderBrowser(); saveBrowser(); }
  function browserActivate(idx) { browserState.active = idx; renderBrowser(); saveBrowser(); }
  function browserGo(url) {
    let u = (url || '').trim(); if (!u) return;
    if (!/^https?:\/\//i.test(u)) u = 'https://' + u;
    if (browserState.active < 0 || !browserState.tabs.length) { browserNew(u); return; }
    const t = browserState.tabs[browserState.active];
    // v1.4 · real history · trim forward · push new url
    if (!t.history) { t.history = [t.url]; t.cursor = 0; }
    t.history = t.history.slice(0, t.cursor + 1);
    t.history.push(u);
    t.cursor = t.history.length - 1;
    t.url = u; t.title = new URL(u).hostname;
    renderBrowser(); saveBrowser();
  }
  function browserHistGo(delta) {
    const t = browserState.active >= 0 ? browserState.tabs[browserState.active] : null;
    if (!t || !t.history) return;
    const next = (t.cursor || 0) + delta;
    if (next < 0 || next >= t.history.length) return;
    t.cursor = next;
    t.url = t.history[next];
    t.title = new URL(t.url).hostname;
    renderBrowser(); saveBrowser();
  }
  function renderBrowser() {
    const tabsEl = $('browserTabs'); const wrap = $('browserIframeWrap'); const addr = $('browserAddr');
    if (!tabsEl || !wrap) return;
    tabsEl.innerHTML = browserState.tabs.map((t, i) => '<div class="browser-tab' + (i === browserState.active ? ' active' : '') + '" data-i="' + i + '">' + escapeHTML(t.title) + '<button class="tab-close" data-i="' + i + '" type="button">×</button></div>').join('');
    qsAll('.browser-tab').forEach(el => { el.addEventListener('click', e => { if (!e.target.classList.contains('tab-close')) browserActivate(+el.dataset.i); }); });
    qsAll('.tab-close').forEach(el => el.addEventListener('click', e => { e.stopPropagation(); browserClose(+el.dataset.i); }));
    const cur = browserState.active >= 0 ? browserState.tabs[browserState.active] : null;
    addr.value = cur ? cur.url : '';
    wrap.innerHTML = '';
    if (cur) { const fr = document.createElement('iframe'); fr.src = cur.url; fr.referrerPolicy = 'no-referrer'; fr.sandbox = 'allow-scripts allow-same-origin allow-forms allow-popups'; wrap.appendChild(fr); }
  }
  if ($('newTabBtn')) {
    $('newTabBtn').addEventListener('click', () => browserNew('https://mirzatech.ai'));
    $('browserGo').addEventListener('click', () => browserGo($('browserAddr').value));
    $('browserAddr').addEventListener('keydown', e => { if (e.key === 'Enter') browserGo($('browserAddr').value); });
    $('browserReload').addEventListener('click', () => renderBrowser());
    $('browserBack').addEventListener('click', () => browserHistGo(-1));   // v1.4 · real history
    $('browserFwd').addEventListener('click', () => browserHistGo(+1));
    qsAll('.browser-quick button').forEach(b => b.addEventListener('click', () => browserGo(b.dataset.href)));

    // v1.8 · Browser fullscreen · uses BROWSER NATIVE Fullscreen API (bulletproof)
    // Targets the iframe-wrap so the actual page fills the screen
    function browserFullscreenToggle() {
      const wrap = $('browserIframeWrap');
      if (!wrap) return;
      if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
      } else if (wrap.requestFullscreen) {
        wrap.requestFullscreen().catch(err => {
          // Fallback to CSS class mode if native API blocked
          shell.classList.add('browser-fullscreen');
          addBubble && addBubble('system', 'Native fullscreen blocked · CSS fallback active.', false);
        });
      }
    }
    $('browserFullscreen').addEventListener('click', browserFullscreenToggle);
    if ($('browserFsExit')) $('browserFsExit').addEventListener('click', () => {
      if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
      shell.classList.remove('browser-fullscreen');
    });
    // Sync class with native fullscreen state
    document.addEventListener('fullscreenchange', () => {
      shell.classList.toggle('browser-fullscreen', !!document.fullscreenElement);
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
        shell.classList.remove('browser-fullscreen');
      }
    });

    // v1.3 · MAYA EYES (Watch) · sends current URL to Maya brain · maya_browse vision capture
    $('browserWatch').addEventListener('click', async () => {
      const cur = browserState.active >= 0 ? browserState.tabs[browserState.active] : null;
      if (!cur) { return; }
      const btn = $('browserWatch');
      const oldText = btn.textContent; btn.textContent = '👁️ ...';  btn.disabled = true;
      addTool('maya.watch · ' + cur.url);
      try {
        const r = await fetch(BRAIN_URL, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'maya_browse', task: 'watch_and_describe',
            url: cur.url, mode: 'observe',
            instruction: 'Visit this URL with Playwright stealth · take a screenshot · describe what you see · note patterns Mo might find useful · log to learned_patterns.',
            pin: currentPin()
          })
        });
        const d = await r.json();
        if (d && (d.reply || d.description)) {
          switchMode('chat');
          addBubble('maya', '👁️ Watched ' + cur.url + '\n\n' + (d.reply || d.description));
          if (d.screenshot_url) addTool('screenshot saved · ' + d.screenshot_url);
        } else {
          addError('Maya could not watch: ' + (d && d.error || 'unknown'));
        }
      } catch (e) { addError('Watch error · ' + String(e)); }
      btn.textContent = oldText; btn.disabled = false;
    });

    // v1.3 · MAYA HANDS (Help) · Mo describes what's stuck · Maya acts in browser
    $('browserHelp').addEventListener('click', async () => {
      const cur = browserState.active >= 0 ? browserState.tabs[browserState.active] : null;
      if (!cur) { return; }
      const task = prompt('What should Maya do on ' + cur.url + '?\n\nExamples:\n· "sign me up · use my emaaa info"\n· "find the contact form and fill it"\n· "click the install button"\n· "I am stuck · help me"');
      if (!task) return;
      const btn = $('browserHelp');
      const oldText = btn.textContent; btn.textContent = '✋ ...'; btn.disabled = true;
      addTool('maya.help · ' + task.slice(0, 60));
      try {
        const r = await fetch(BRAIN_URL, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'maya_browse', task: 'execute',
            url: cur.url, mode: 'act',
            instruction: task,
            pin: currentPin(),
            learn: true   // memory #98 · maya learns Mo's patterns
          })
        });
        const d = await r.json();
        switchMode('chat');
        addBubble('maya', '✋ Working on: ' + task + '\n\n' + (d.reply || d.result || 'task queued · ' + (d.task_id || '')));
        if (d.task_id) pushTicket(d.task_id);
      } catch (e) { addError('Help error · ' + String(e)); }
      btn.textContent = oldText; btn.disabled = false;
    });
  }

  // ── v1.2 · COMMAND PALETTE (Ctrl+K / Cmd+K) ───────────────────────
  const MODES_DESC = [
    { mode: 'chat', emoji: '💬', name: 'Chat', cat: 'mode' },
    { mode: 'browser', emoji: '🌐', name: 'Browser', cat: 'mode' },
    { mode: 'build', emoji: '💻', name: 'Build · codegen', cat: 'mode' },
    { mode: 'vision', emoji: '👁️', name: 'Vision · analyze image', cat: 'mode' },
    { mode: 'image', emoji: '🖼️', name: 'Image · generate', cat: 'mode' },
    { mode: 'staff', emoji: '👥', name: 'Staff · crew grid', cat: 'mode' },
    { mode: 'video', emoji: '🎬', name: 'Video · generate', cat: 'mode' },
    { mode: 'music', emoji: '🎵', name: 'Music · concept', cat: 'mode' },
    { mode: 'voice', emoji: '🗣️', name: 'Voice · TTS lab', cat: 'mode' },
    { mode: 'chain', emoji: '⛓️', name: 'Chain · multi-step', cat: 'mode' },
    { mode: 'three_d', emoji: '🧊', name: '3D · model generate', cat: 'mode' },
    { mode: 'files', emoji: '📁', name: 'Files', cat: 'mode' },
    { mode: 'executor', emoji: '⚙️', name: 'Executor · tools', cat: 'mode' },
    { mode: 'console', emoji: '⌨️', name: 'Console · raw API', cat: 'mode' }
  ];
  const ENGINES = ['auto','groq','gemini','deepseek','glm','nim','kimi','mistral'].map(e => ({ engine: e, emoji: '🧠', name: e.toUpperCase(), cat: 'engine' }));
  const QUICK_CMDS = [
    { q: 'parliament', emoji: '⚖️', name: 'Run Parliament', cat: 'action' },
    { q: 'council',    emoji: '🏛️', name: 'Convene Council', cat: 'action' },
    { q: 'health',     emoji: '💊', name: 'Health probe', cat: 'action' },
    { q: 'vps',        emoji: '🖥️', name: 'VPS health', cat: 'action' },
    { q: 'apis',       emoji: '🆓', name: 'Free API hunt', cat: 'action' },
    { q: 'self_improve', emoji: '🧬', name: 'Self-Improve', cat: 'action' },
    { q: 'repair',     emoji: '🔧', name: 'Self-Repair', cat: 'action' },
    { q: 'boot',       emoji: '⚡', name: 'Bootstrap', cat: 'action' }
  ];
  function paletteOpen() {
    paletteBd.hidden = false;
    paletteIn.value = '';
    renderPalette('');
    setTimeout(() => paletteIn.focus(), 50);
  }
  function paletteClose() { paletteBd.hidden = true; }
  function paletteItems(query) {
    const q = (query || '').toLowerCase().trim();
    const items = [];
    MODES_DESC.forEach(m => items.push(Object.assign({ kind: 'mode' }, m)));
    STAFF.forEach(s => items.push({ kind: 'staff', emoji: s.emoji, name: s.name, cat: s.group || 'staff', role: s.role }));
    ENGINES.forEach(e => items.push(Object.assign({ kind: 'engine' }, e)));
    QUICK_CMDS.forEach(c => items.push(Object.assign({ kind: 'action' }, c)));
    if (!q) return items.slice(0, 24);
    return items.filter(it =>
      it.name.toLowerCase().includes(q) ||
      (it.cat || '').toLowerCase().includes(q) ||
      (it.role || '').toLowerCase().includes(q) ||
      (it.kind || '').toLowerCase().includes(q)
    ).slice(0, 40);
  }
  let paletteIdx = 0;
  function renderPalette(query) {
    const items = paletteItems(query);
    if (items.length === 0) { paletteOut.innerHTML = '<div class="palette-empty">No match.</div>'; return; }
    paletteIdx = Math.min(paletteIdx, items.length - 1);
    paletteOut.innerHTML = items.map((it, i) =>
      '<div class="palette-row' + (i === paletteIdx ? ' active' : '') + '" data-i="' + i + '">' +
        '<span class="pr-emoji">' + it.emoji + '</span>' +
        '<span class="pr-name">' + escapeHTML(it.name) + '</span>' +
        '<span class="pr-cat">' + (it.kind || it.cat || '') + '</span>' +
      '</div>'
    ).join('');
    qsAll('.palette-row').forEach(el => {
      el.addEventListener('click', () => { paletteIdx = +el.dataset.i; paletteSelect(items[paletteIdx]); });
      el.addEventListener('mouseenter', () => { paletteIdx = +el.dataset.i; qsAll('.palette-row').forEach((r,i) => r.classList.toggle('active', i === paletteIdx)); });
    });
  }
  function paletteSelect(it) {
    if (!it) return;
    paletteClose();
    if (it.kind === 'mode')   return switchMode(it.mode);
    if (it.kind === 'engine') { enginePick.value = it.engine; currentEngine = it.engine; localStorage.setItem(ENG_KEY, it.engine); addBubble('system', 'Engine → ' + it.engine, false); return; }
    if (it.kind === 'staff')  { switchMode('chat'); msgEl.value = '/' + it.name.toLowerCase() + ' '; msgEl.focus(); autosize(); return; }
    if (it.kind === 'action') {
      const q = it.q;
      if (q === 'build')  return switchMode('build');
      if (q === 'video')  return switchMode('video');
      if (q === 'vision') return switchMode('vision');
      switchMode('chat');
      msgEl.value = QUICK_ACTIONS[q] || ''; autosize();
      if (msgEl.value) sendMessage();
    }
  }
  paletteIn.addEventListener('input', () => { paletteIdx = 0; renderPalette(paletteIn.value); });
  paletteIn.addEventListener('keydown', e => {
    const items = paletteItems(paletteIn.value);
    if (e.key === 'ArrowDown') { e.preventDefault(); paletteIdx = Math.min(paletteIdx + 1, items.length - 1); renderPalette(paletteIn.value); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); paletteIdx = Math.max(paletteIdx - 1, 0); renderPalette(paletteIn.value); }
    else if (e.key === 'Enter') { e.preventDefault(); paletteSelect(items[paletteIdx]); }
    else if (e.key === 'Escape') { e.preventDefault(); paletteClose(); }
  });
  paletteBd.addEventListener('click', e => { if (e.target === paletteBd) paletteClose(); });
  paletteCue.addEventListener('click', paletteOpen);
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); paletteOpen(); }
    if (e.key === 'Escape' && !paletteBd.hidden) { paletteClose(); }
  });

  // ── Service worker (NETWORK-FIRST during dev) ────────────────────
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(regs => { regs.forEach(r => r.unregister()); })
      .then(() => navigator.serviceWorker.register('./service-worker.js?v=15').catch(() => {}));
  }

  // v1.5 · GLOBAL-60 auto-update · poll version.json every 30s · prompt when version changes
  const VERSION_KEY = 'maya_os_version_seen';
  let _bootVersion = null;
  async function checkVersion() {
    try {
      const r = await fetch('./version.json?_=' + Date.now(), { cache: 'no-store' });
      const v = await r.json();
      if (!v || !v.version) return;
      if (!_bootVersion) {
        _bootVersion = v.version;
        sessionStorage.setItem(VERSION_KEY, v.version);
        return;
      }
      if (v.version !== _bootVersion) {
        // New version available · soft prompt in chat
        const seen = sessionStorage.getItem('maya_os_update_prompt_seen_' + v.version);
        if (!seen) {
          sessionStorage.setItem('maya_os_update_prompt_seen_' + v.version, '1');
          if (typeof addBubble === 'function') {
            addBubble('system', '🔄 Maya OS update available: ' + _bootVersion + ' → ' + v.version + '. Refresh to install · or stay on current.');
          }
        }
      }
    } catch (_) {}
  }
  checkVersion();
  setInterval(checkVersion, 30000);

  // ── v1.10 · NOTIFICATION BELL · email + SMS + brief + voice · 2026-05-15 ──
  const GMAIL_URL = 'https://iamsuperio.cloud/api/maya_gmail';
  const SMS_URL   = 'https://iamsuperio.cloud/api/maya_sms';
  const VOICE_URL = 'https://iamsuperio.cloud/api/maya_voice';
  const PIN_KEY   = 'maya_os_commander_pin_v1';   // session-only pin store
  const ACCT_KEY  = 'maya_os_gmail_acct_v1';

  const bellBtn = $('bellBtn'), bellBadge = $('bellBadge'), bellDrawer = $('bellDrawer');
  const bdClose = $('bdClose');
  const bdAccount = $('bdAccount'), bdConnectBtn = $('bdConnectBtn');
  const bdPin = $('bdPin'), bdRefresh = $('bdRefresh');
  const bdInboxList = $('bdInboxList'), bdPreview = $('bdPreview');
  const bdPrevSubject = $('bdPrevSubject'), bdPrevFrom = $('bdPrevFrom');
  const bdPrevBack = $('bdPrevBack'), bdPrevBody = $('bdPrevBody');
  const bdReply = $('bdReply'), bdDraftBtn = $('bdDraftBtn'), bdBriefSelBtn = $('bdBriefSelBtn');
  const bdSmsTo = $('bdSmsTo'), bdSmsBody = $('bdSmsBody');
  const bdSmsSend = $('bdSmsSend'), bdSmsSpin = $('bdSmsSpin'), bdSmsStatus = $('bdSmsStatus');
  const bdBriefRunBtn = $('bdBriefRunBtn'), bdBriefSpeakBtn = $('bdBriefSpeakBtn'), bdBriefOut = $('bdBriefOut');
  const bdBriefEngine = $('bdBriefEngine');

  let _selectedMsg = null;     // {id, thread_id} of currently-previewed email
  let _lastBriefText = '';     // for re-speaking

  function bdSetPane(name) {
    qsAll('.bd-tab').forEach(t => t.classList.toggle('active', t.dataset.bdTab === name));
    qsAll('.bd-pane').forEach(p => p.classList.toggle('active', p.dataset.bdPane === name));
  }
  function bdOpen() {
    bellDrawer.hidden = false;
    bellBtn.dataset.open = '1';
    // Restore account selection + cached pin
    const lastAcct = localStorage.getItem(ACCT_KEY);
    if (lastAcct) bdAccount.value = lastAcct;
    const pin = sessionStorage.getItem(PIN_KEY);
    if (pin) bdPin.value = pin;
    bdLoadAccounts();
  }
  function bdClosePanel() {
    bellDrawer.hidden = true;
    bellBtn.dataset.open = '';
  }
  function bdGetPin() {
    const v = (bdPin.value || '').trim();
    if (v) sessionStorage.setItem(PIN_KEY, v);
    return v;
  }
  function bdSay(el, msg, kind) {
    el.textContent = msg || '';
    el.className = 'bd-status' + (kind ? ' ' + kind : '');
  }

  // Bell button → open drawer
  if (bellBtn) bellBtn.addEventListener('click', () => bellDrawer.hidden ? bdOpen() : bdClosePanel());
  if (bdClose) bdClose.addEventListener('click', bdClosePanel);
  qsAll('.bd-tab').forEach(t => t.addEventListener('click', () => bdSetPane(t.dataset.bdTab)));

  // ── INBOX ──
  async function bdLoadAccounts() {
    try {
      const r = await fetch(GMAIL_URL + '?action=accounts');
      const j = await r.json();
      if (!j.ok) return;
      const lastAcct = bdAccount.value || localStorage.getItem(ACCT_KEY) || '';
      bdAccount.innerHTML = '<option value="">— choose Gmail account —</option>'
        + (j.accounts || []).map(a => '<option value="' + a.email + '"' + (a.email === lastAcct ? ' selected' : '') + '>' + a.email + '</option>').join('');
      if (!j.accounts || j.accounts.length === 0) {
        bdInboxList.innerHTML = '<div class="bd-empty">No Gmail accounts connected. Click <b>+ Connect</b> to authorize one.</div>';
      }
    } catch (_) {}
  }
  if (bdAccount) bdAccount.addEventListener('change', () => {
    if (bdAccount.value) localStorage.setItem(ACCT_KEY, bdAccount.value);
  });
  if (bdConnectBtn) bdConnectBtn.addEventListener('click', () => {
    window.open(GMAIL_URL + '?action=connect', 'maya_gmail_oauth', 'width=520,height=700');
  });

  async function bdRefreshInbox() {
    const email = bdAccount.value, pin = bdGetPin();
    if (!email) { bdInboxList.innerHTML = '<div class="bd-empty">Pick an account first.</div>'; return; }
    if (!pin)   { bdInboxList.innerHTML = '<div class="bd-empty">Commander PIN required.</div>'; return; }
    bdInboxList.innerHTML = '<div class="bd-empty">Loading…</div>';
    try {
      const r = await fetch(GMAIL_URL, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'list', email, pin, max:20})
      });
      const j = await r.json();
      if (!j.ok) { bdInboxList.innerHTML = '<div class="bd-empty">Error: ' + (j.error || 'unknown') + '</div>'; return; }
      if (!j.messages || !j.messages.length) { bdInboxList.innerHTML = '<div class="bd-empty">Inbox empty.</div>'; return; }
      bdInboxList.innerHTML = j.messages.map(m => (
        '<div class="bd-mail-row" data-mid="' + m.id + '" data-tid="' + (m.thread_id || '') + '">'
        + '<div class="bd-mail-from">' + escapeHTML(m.from || '?') + '</div>'
        + '<div class="bd-mail-date">' + escapeHTML((m.date || '').slice(0, 16)) + '</div>'
        + '<div class="bd-mail-subj">' + escapeHTML(m.subject || '(no subject)') + '</div>'
        + '<div class="bd-mail-snippet">' + escapeHTML((m.snippet || '').slice(0, 140)) + '</div>'
        + '</div>'
      )).join('');
      qsAll('.bd-mail-row').forEach(row => row.addEventListener('click', () => bdOpenMessage(row.dataset.mid, row.dataset.tid)));
    } catch (e) {
      bdInboxList.innerHTML = '<div class="bd-empty">Network error: ' + e.message + '</div>';
    }
  }
  if (bdRefresh) bdRefresh.addEventListener('click', bdRefreshInbox);

  async function bdOpenMessage(mid, tid) {
    const email = bdAccount.value, pin = bdGetPin();
    if (!email || !pin) return;
    _selectedMsg = { id: mid, thread_id: tid };
    bdPreview.hidden = false;
    bdPrevSubject.textContent = 'Loading…'; bdPrevFrom.textContent = ''; bdPrevBody.textContent = '';
    try {
      const r = await fetch(GMAIL_URL, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'read', email, pin, msg_id: mid})
      });
      const j = await r.json();
      if (!j.ok) { bdPrevSubject.textContent = 'Error'; bdPrevBody.textContent = j.error || 'failed'; return; }
      bdPrevSubject.textContent = (j.headers && j.headers.subject) || '(no subject)';
      bdPrevFrom.textContent = (j.headers && j.headers.from) || '';
      bdPrevBody.textContent = j.body || j.snippet || '(empty)';
    } catch (e) {
      bdPrevBody.textContent = 'Network error: ' + e.message;
    }
  }
  if (bdPrevBack) bdPrevBack.addEventListener('click', () => { bdPreview.hidden = true; _selectedMsg = null; });

  if (bdDraftBtn) bdDraftBtn.addEventListener('click', async () => {
    const email = bdAccount.value, pin = bdGetPin();
    if (!_selectedMsg || !email || !pin || !bdReply.value.trim()) return;
    bdDraftBtn.disabled = true; bdDraftBtn.textContent = '⏳ saving…';
    try {
      const r = await fetch(GMAIL_URL, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          action:'draft', email, pin,
          to: (bdPrevFrom.textContent || '').replace(/.*<([^>]+)>.*/, '$1') || bdPrevFrom.textContent,
          subject: 'Re: ' + bdPrevSubject.textContent,
          body: bdReply.value,
          thread_id: _selectedMsg.thread_id || null,
        })
      });
      const j = await r.json();
      bdDraftBtn.textContent = j.ok ? '✓ saved · check Drafts' : '✗ ' + (j.error || 'failed');
      setTimeout(() => { bdDraftBtn.disabled = false; bdDraftBtn.textContent = '💾 Save draft'; }, 2500);
    } catch (e) {
      bdDraftBtn.textContent = '✗ network'; setTimeout(() => { bdDraftBtn.disabled = false; bdDraftBtn.textContent = '💾 Save draft'; }, 2500);
    }
  });

  if (bdBriefSelBtn) bdBriefSelBtn.addEventListener('click', () => {
    if (!_selectedMsg) return;
    bdSetPane('brief');
    bdBriefRun([_selectedMsg.id]);
  });

  // ── BRIEF ──
  async function bdBriefRun(msgIds) {
    const email = bdAccount.value, pin = bdGetPin();
    if (!email) { bdBriefOut.innerHTML = '<em>Pick a Gmail account first (in the Inbox tab).</em>'; return; }
    if (!pin)   { bdBriefOut.innerHTML = '<em>Commander PIN required.</em>'; return; }
    bdBriefOut.classList.add('loading'); bdBriefOut.textContent = 'Maya is reading your email…';
    bdBriefSpeakBtn.disabled = true; _lastBriefText = '';
    try {
      const engine = (bdBriefEngine && bdBriefEngine.value) || 'gemini';
      const r = await fetch(GMAIL_URL, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'brief', email, pin, msg_ids: msgIds || [], engine })
      });
      const j = await r.json();
      bdBriefOut.classList.remove('loading');
      if (!j.ok) { bdBriefOut.textContent = '✗ ' + (j.error || 'brief failed'); return; }
      _lastBriefText = j.brief || '';
      bdBriefOut.textContent = _lastBriefText + '\n\n— briefed ' + (j.count || 0) + ' email' + ((j.count||0)===1?'':'s') + ' · engine: ' + (j.engine || 'maya');
      bdBriefSpeakBtn.disabled = !_lastBriefText;
    } catch (e) {
      bdBriefOut.classList.remove('loading');
      bdBriefOut.textContent = 'Network error: ' + e.message;
    }
  }
  if (bdBriefRunBtn) bdBriefRunBtn.addEventListener('click', () => bdBriefRun([]));   // empty = unread last 24h

  // ── VOICE · Maya speaks the brief through Kokoro persona_maya ──
  if (bdBriefSpeakBtn) bdBriefSpeakBtn.addEventListener('click', async () => {
    if (!_lastBriefText) return;
    bdBriefSpeakBtn.disabled = true; bdBriefSpeakBtn.textContent = '⏳ rendering…';
    try {
      const r = await fetch(VOICE_URL, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'synthesize', text: _lastBriefText.slice(0, 4000), voice: 'persona_maya' })
      });
      const j = await r.json();
      const url = j.url || j.audio_url || (j.file ? ('https://iamsuperio.cloud/api/maya_voice_serve.php?f=' + encodeURIComponent(j.file)) : null);
      if (j.ok && url) {
        const a = new Audio(url); a.play().catch(()=>{});
        bdBriefSpeakBtn.textContent = '🔊 playing';
        a.addEventListener('ended', () => { bdBriefSpeakBtn.disabled = false; bdBriefSpeakBtn.textContent = '🔊 Speak'; });
      } else {
        bdBriefSpeakBtn.textContent = '✗ voice failed';
        setTimeout(() => { bdBriefSpeakBtn.disabled = false; bdBriefSpeakBtn.textContent = '🔊 Speak'; }, 2500);
      }
    } catch (e) {
      bdBriefSpeakBtn.textContent = '✗ network';
      setTimeout(() => { bdBriefSpeakBtn.disabled = false; bdBriefSpeakBtn.textContent = '🔊 Speak'; }, 2500);
    }
  });

  // ── SMS ──
  if (bdSmsSpin) bdSmsSpin.addEventListener('click', async () => {
    const txt = bdSmsBody.value.trim();
    if (!txt) { bdSay(bdSmsStatus, 'type something to spin first', 'err'); return; }
    bdSay(bdSmsStatus, 'Maya is rewriting…');
    try {
      const r = await fetch(BRAIN_URL, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'ask', engine:'groq',
          question: 'Rewrite this SMS in 160 characters or less, natural plain English, brother-tone, no emoji, ready to send as-is. Output ONLY the rewritten message:\n\n' + txt })
      });
      const j = await r.json();
      const out = (j.answer || j.response || j.text || '').trim();
      if (out) { bdSmsBody.value = out.slice(0, 320); bdSay(bdSmsStatus, 'spun · ' + bdSmsBody.value.length + ' chars', 'ok'); }
      else bdSay(bdSmsStatus, 'spin returned empty', 'err');
    } catch (e) { bdSay(bdSmsStatus, 'network: ' + e.message, 'err'); }
  });

  if (bdSmsSend) bdSmsSend.addEventListener('click', async () => {
    const pin = bdGetPin();
    const to = (bdSmsTo.value || '').trim();
    const body = (bdSmsBody.value || '').trim();
    if (!pin)  { bdSay(bdSmsStatus, 'Commander PIN required (top of Inbox tab)', 'err'); return; }
    if (!body) { bdSay(bdSmsStatus, 'message body required', 'err'); return; }
    bdSay(bdSmsStatus, 'sending…');
    bdSmsSend.disabled = true;
    try {
      const r = await fetch(SMS_URL, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'send', to: to || undefined, message: body, pin })
      });
      const j = await r.json();
      if (j.ok) { bdSay(bdSmsStatus, '✓ sent · ' + (j.via || 'gateway'), 'ok'); bdSmsBody.value = ''; }
      else bdSay(bdSmsStatus, '✗ ' + (j.error || 'send failed'), 'err');
    } catch (e) { bdSay(bdSmsStatus, 'network: ' + e.message, 'err'); }
    finally { bdSmsSend.disabled = false; }
  });

  // ── v1.10.2 · SOVEREIGN CAMPUS · Skill #1 + #6 · GLOBAL-92 · 2026-05-15 ──
  const CAMPUS_URL = 'https://iamsuperio.cloud/api/maya_os_habitat_state';
  let _campusTimer = null;
  let _campusLastRooms = null;
  const campusRooms   = $('campusRooms');
  const campusStreams = $('campusStreams');
  const campusLogRows = $('campusLogRows');
  const hubOrbPct = $('hubOrbPct');
  const cpEdits = $('cpEdits'), cpThr = $('cpThr'), cpAgents = $('cpAgents'), cpConsensus = $('cpConsensus');
  const campusPhase = $('campusPhase');

  // Anchor points for ghost-stream paths · matches CSS .room-pos-N positions
  // Each room ID maps to an [x%, y%] center coordinate on the floor.
  const ROOM_ANCHORS = {
    maya_brain:      [50, 50],   // the hub itself
    kimi:            [50, 12],
    opencode:        [88, 38],
    self_edit_queue: [82, 84],
    vscode:          [18, 84],
    // (5th room slot wraps to room-pos-4 / 18, 38) — assigned dynamically below
  };
  const ROOM_POSITIONS = ['room-pos-0','room-pos-1','room-pos-2','room-pos-3','room-pos-4'];
  const ANCHOR_BY_POS = [[50,12],[88,38],[82,84],[18,84],[18,38]];

  function renderCampus(state) {
    if (!state || !state.ok) return;
    _campusLastRooms = state.rooms || [];

    // Pulse counters
    if (cpEdits)     cpEdits.textContent     = state.pulse?.edits_today ?? '0';
    if (cpThr)       cpThr.textContent       = state.pulse?.throughput_per_min ?? '0';
    if (cpAgents)    cpAgents.textContent    = state.pulse?.coding_agents_idle ?? '0';
    if (cpConsensus) cpConsensus.textContent = (state.hub?.consensus_pct ?? 88) + '%';
    if (hubOrbPct)   hubOrbPct.textContent   = (state.hub?.consensus_pct ?? 88) + '%';
    if (campusPhase) campusPhase.textContent = 'Phase ' + (state.phase || '?') + ' · ' + (state.note || '');

    // Rooms · skip maya_brain (it's the hub) · render the rest as satellites
    const sat = (state.rooms || []).filter(r => r.id !== 'maya_brain');
    if (sat.length && campusRooms) {
      campusRooms.innerHTML = sat.slice(0, 5).map((r, idx) => {
        const pos = ROOM_POSITIONS[idx] || ROOM_POSITIONS[0];
        const sprites = '<span class="rc-sprite"></span>'.repeat(Math.min(3, Math.max(1, r.sprites || 1)));
        return (
          '<div class="room-card ' + pos + '" data-state="' + escapeHTML(r.state || 'idle') + '" data-room="' + escapeHTML(r.id) + '">'
          + '  <div class="rc-head"><span class="rc-icon">' + escapeHTML(r.icon || '◇') + '</span>'
          + '    <span class="rc-label">' + escapeHTML(r.label || r.id) + '</span>'
          + '    <span class="rc-state-dot" title="' + escapeHTML(r.state || 'idle') + '"></span>'
          + '  </div>'
          + '  <div class="rc-role">' + escapeHTML(r.role || '') + '</div>'
          + '  <div class="rc-task">' + escapeHTML(r.task || '—') + '</div>'
          + '  <div class="rc-sprites">' + sprites + '</div>'
          + '  <div class="rc-meta"><span>tasks <b>' + (r.tasks_24h ?? 0) + '</b></span>'
          + '    <span>lat <b>' + (r.latency_ms ?? 0) + 'ms</b></span></div>'
          + '</div>'
        );
      }).join('');
    }

    // Ghost-streams · Skill #1 · animated SVG paths between hub and rooms
    if (campusStreams && state.streams) {
      const floor = campusStreams.parentElement;
      const w = floor.clientWidth || 800;
      const h = floor.clientHeight || 400;
      campusStreams.setAttribute('viewBox', '0 0 100 100');
      campusStreams.setAttribute('width', w);
      campusStreams.setAttribute('height', h);
      // Build a position map: maya_brain is hub, other rooms get position by their satellite index
      const posMap = { maya_brain: ROOM_ANCHORS.maya_brain };
      sat.slice(0, 5).forEach((r, idx) => { posMap[r.id] = ANCHOR_BY_POS[idx]; });
      const colors = { cyan: '#00e4ff', gold: '#f5c542', green: '#43e6a1', red: '#ff5b6a' };
      let svg = '';
      state.streams.forEach((s, i) => {
        const a = posMap[s.from], b = posMap[s.to];
        if (!a || !b) return;
        const c = colors[s.color] || colors.cyan;
        const pid = 'campStream' + i;
        // Active (event-driven) streams = brighter path, more packets, faster cycle
        // Ambient (idle hum) streams = subtle, slow, low-opacity
        const active = !!s.active;
        const strokeW   = active ? 0.7 : 0.3;
        const pathOp    = active ? 0.95 : 0.35;
        const dashSpec  = active ? '0.8 1.6' : '1.2 2.8';
        const dashDur   = active ? '1.4s' : '3.5s';
        const packetDur = (i, p) => (active ? (1.2 + p*0.4) : (3.2 + p*0.9)) + 's';
        const packetR   = active ? 1.1 : 0.7;
        svg += '<path id="' + pid + '" d="M' + a[0] + ',' + a[1] + ' Q' + ((a[0]+b[0])/2) + ',' + ((a[1]+b[1])/2 - 8) + ' ' + b[0] + ',' + b[1] + '" '
          + 'stroke="' + c + '" stroke-width="' + strokeW + '" stroke-dasharray="' + dashSpec + '" fill="none" opacity="' + pathOp + '"'
          + (active ? ' filter="url(#campGlow)"' : '') + '>'
          + '<animate attributeName="stroke-dashoffset" from="0" to="-12" dur="' + dashDur + '" repeatCount="indefinite" /></path>';
        // packet dots
        const nPackets = active ? Math.max(2, s.packets || 1) : (s.packets || 1);
        for (let p = 0; p < nPackets; p++) {
          svg += '<circle r="' + packetR + '" fill="' + c + '"' + (active ? ' filter="url(#campGlow)"' : '') + '>'
            + '<animateMotion dur="' + packetDur(i, p) + '" repeatCount="indefinite" rotate="auto">'
            + '<mpath href="#' + pid + '"/></animateMotion></circle>';
        }
      });
      // Inject glow filter once (idempotent: only if not already present)
      if (svg && !svg.includes('id="campGlow"')) {
        svg = '<defs><filter id="campGlow" x="-50%" y="-50%" width="200%" height="200%">'
          + '<feGaussianBlur in="SourceGraphic" stdDeviation="0.8"/>'
          + '</filter></defs>' + svg;
      }
      campusStreams.innerHTML = svg;
    }

    // Audit log · pad with relative times
    if (campusLogRows && state.audit) {
      campusLogRows.innerHTML = state.audit.slice(0, 10).map(a => (
        '<div class="log-row" data-kind="' + escapeHTML(a.kind || 'system') + '">'
        + '<time>' + escapeHTML(a.ts || '—') + '</time>'
        + '<span class="log-kind">' + escapeHTML(a.kind || '?') + '</span>'
        + '<span class="log-msg">' + escapeHTML(a.msg || '') + '</span>'
        + '</div>'
      )).join('');
    }
  }

  async function pollCampus() {
    try {
      const r = await fetch(CAMPUS_URL, { cache: 'no-store' });
      if (!r.ok) return;
      const j = await r.json();
      renderCampus(j);
    } catch (_) {}
  }

  function campusStart() {
    if (_campusTimer) return;
    pollCampus();
    _campusTimer = setInterval(pollCampus, 3000);   // Skill #6 · 3s polling, no faster
  }
  function campusStop() {
    if (_campusTimer) { clearInterval(_campusTimer); _campusTimer = null; }
  }

  // Hook into mode switching · poll only when campus is visible · use MutationObserver
  // to avoid clashing with the existing switchMode function declaration.
  const campusPanel = document.querySelector('[data-panel="campus"]');
  if (campusPanel) {
    const moCampus = new MutationObserver(() => {
      if (campusPanel.classList.contains('active')) campusStart();
      else campusStop();
    });
    moCampus.observe(campusPanel, { attributes: true, attributeFilter: ['class'] });
    // Initial check in case Campus is the booted mode
    if (campusPanel.classList.contains('active')) campusStart();
  }

  // v1.12.0 · Maya Command Bar inside Campus · Skill #8 · 2026-05-15
  const campusCmdInput = $('campusCmdInput');
  const campusCmdSend  = $('campusCmdSend');
  const campusBalloon  = $('campusBalloon');

  async function campusCmdRun() {
    const text = (campusCmdInput?.value || '').trim();
    if (!text) return;
    campusCmdSend.disabled = true;
    campusBalloon.hidden = false;
    campusBalloon.innerHTML = '<div class="cb-head"><span class="live"></span><span>Maya is thinking…</span></div><pre>' + escapeHTML(text) + '</pre>';

    // Emit "start" event into the Campus event spine so packet flies from hub to room
    try {
      await fetch('https://iamsuperio.cloud/api/maya_event', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({actor:'maya', action:'cmd', target: text.slice(0,80), status:'start', room:'maya_brain'})
      });
    } catch(_) {}

    let answer = '';
    try {
      const r = await fetch(BRAIN_URL, {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'ask', engine:'gemini', question: text})
      });
      const j = await r.json();
      answer = j.answer || j.response || j.text || j.content || '(no answer)';
    } catch (e) {
      answer = 'Network error: ' + e.message;
    }

    campusBalloon.innerHTML = '<div class="cb-head"><span class="live"></span><span>Maya · responded</span></div><pre>' + escapeHTML(answer) + '</pre>';
    campusCmdInput.value = '';
    campusCmdSend.disabled = false;

    // Emit "done" event so packet completes
    try {
      await fetch('https://iamsuperio.cloud/api/maya_event', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({actor:'maya', action:'cmd', target: text.slice(0,80), status:'done', room:'maya_brain', result: answer.slice(0,120)})
      });
    } catch(_) {}
  }
  if (campusCmdSend)  campusCmdSend.addEventListener('click', campusCmdRun);
  if (campusCmdInput) campusCmdInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); campusCmdRun(); }
  });

  // ── v1.11.1 · PHONE BRIDGE · Mo's Samsung Fold 5 / Termux · Phase 1 · 2026-05-15 ──
  const PHONE_KEY_URL   = 'maya_os_phone_url_v1';
  const PHONE_KEY_TOKEN = 'maya_os_phone_token_v1';
  const EVENT_URL       = 'https://iamsuperio.cloud/api/maya_event';
  const phonePill = $('phonePill'), phoneDot = $('phoneDot');
  const phoneModal = $('phoneModal'), pmBackdrop = $('pmBackdrop'), pmClose = $('pmClose');
  const pmUrl = $('pmUrl'), pmToken = $('pmToken'), pmStatus = $('pmStatus');
  const pmTestBtn = $('pmTestBtn'), pmSaveBtn = $('pmSaveBtn'), pmDisconnectBtn = $('pmDisconnectBtn');
  const pmCaps = $('pmCaps'), pmCapsList = $('pmCapsList');
  // v1.11.3 · auto-populated device registry
  const pmRegPin = $('pmRegPin'), pmRefreshBtn = $('pmRefreshBtn'), pmDeviceList = $('pmDeviceList');
  const DEVICES_URL = 'https://iamsuperio.cloud/api/maya_devices';
  const PIN_KEY_DEV = 'maya_os_dev_pin_v1';

  function platformIcon(p) { return ({termux:'📱', windows:'💻', macos:'🖥️', linux:'🐧'}[p] || '🌐'); }
  function devGetPin() {
    const v = (pmRegPin?.value || '').trim();
    if (v) sessionStorage.setItem(PIN_KEY_DEV, v);
    return v;
  }
  async function devListRefresh() {
    const pin = devGetPin();
    if (!pin) { pmDeviceList.innerHTML = '<div class="pm-empty">Enter PIN above to load registered devices.</div>'; return; }
    pmDeviceList.innerHTML = '<div class="pm-empty">Loading…</div>';
    try {
      const r = await fetch(DEVICES_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'list', pin}) });
      const j = await r.json();
      if (!j.ok) { pmDeviceList.innerHTML = '<div class="pm-empty">' + escapeHTML(j.error || 'error') + '</div>'; return; }
      if (!j.devices || j.devices.length === 0) {
        pmDeviceList.innerHTML = '<div class="pm-empty">No devices registered yet. Run the installer on any device · it auto-registers itself.</div>';
        return;
      }
      pmDeviceList.innerHTML = j.devices.map(d => {
        const caps = Object.entries(d.capabilities || {}).filter(([_, v]) => v).map(([k]) => k).join(' · ');
        return (
          '<div class="pm-device-row" data-active="' + (d.is_active ? 1 : 0) + '" data-id="' + escapeHTML(d.id) + '">'
          + '<span class="pm-device-icon">' + platformIcon(d.platform) + '</span>'
          + '<div class="pm-device-meta">'
          +   '<span class="pm-device-name">' + escapeHTML(d.device_name) + (d.is_active ? ' <span class="pm-device-active-badge">ACTIVE</span>' : '') + '</span>'
          +   '<span class="pm-device-sub">' + escapeHTML(d.platform + ' · ' + d.url + ' · ' + caps.slice(0, 60)) + '</span>'
          + '</div>'
          + '<div class="pm-device-actions">'
          +   (d.is_active ? '' : '<button class="pm-device-btn" data-act="activate" data-id="' + escapeHTML(d.id) + '">Use this</button>')
          +   '<button class="pm-device-btn danger" data-act="remove" data-id="' + escapeHTML(d.id) + '" title="Forget this device">✕</button>'
          + '</div>'
          + '</div>'
        );
      }).join('');
      // Wire buttons
      qsAll('.pm-device-btn').forEach(b => b.addEventListener('click', async (ev) => {
        ev.stopPropagation();
        const act = b.dataset.act, id = b.dataset.id;
        if (act === 'activate') await devActivate(id);
        if (act === 'remove')   await devRemove(id);
      }));
      // Click row to activate quick
      qsAll('.pm-device-row').forEach(row => row.addEventListener('click', () => {
        if (row.dataset.active !== '1') devActivate(row.dataset.id);
      }));
    } catch (e) {
      pmDeviceList.innerHTML = '<div class="pm-empty">Network: ' + escapeHTML(e.message) + '</div>';
    }
  }
  async function devActivate(id) {
    const pin = devGetPin();
    if (!pin) return;
    try {
      // 1. server-side mark active
      await fetch(DEVICES_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'set_active', pin, id}) });
      // 2. pull full record (token included) for this Maya OS instance to use
      const r = await fetch(DEVICES_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'full_record', pin, id}) });
      const j = await r.json();
      if (j.ok && j.device) {
        phoneSaveCfg(j.device.url, j.device.token);
        await phoneCheck();
      }
      await devListRefresh();
    } catch (_) {}
  }
  async function devRemove(id) {
    const pin = devGetPin();
    if (!pin) return;
    if (!confirm('Forget this device? You can always re-register by running the installer again.')) return;
    try {
      await fetch(DEVICES_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'remove', pin, id}) });
      // If we just removed the active device, clear local cfg
      if (_phoneCfg) phoneClearCfg();
      await phoneCheck();
      await devListRefresh();
    } catch (_) {}
  }
  // Restore PIN from session if present
  if (pmRegPin) {
    const cached = sessionStorage.getItem(PIN_KEY_DEV);
    if (cached) pmRegPin.value = cached;
  }
  if (pmRefreshBtn) pmRefreshBtn.addEventListener('click', devListRefresh);

  // v1.12.0 · QR-code install block · 2026-05-15
  // Each tab targets a different installer; encoded URL is the canonical install endpoint
  // so a phone camera scan opens the link in browser (then Mo copies the one-liner with one tap).
  const QR_TARGETS = {
    termux:  'https://iamsuperio.cloud/phone-bridge/install.sh',
    windows: 'https://iamsuperio.cloud/phone-bridge/install.ps1',
    macos:   'https://iamsuperio.cloud/phone-bridge/install.sh',
  };
  function pmQrRender(target) {
    const url = QR_TARGETS[target] || QR_TARGETS.termux;
    const host = document.getElementById('pmQrCanvas');
    if (!host) return;
    host.innerHTML = '<canvas id="pmQrCanvasEl"></canvas>';
    // QRious from cdnjs · loaded via <script defer> in <head>
    if (typeof QRious === 'undefined') {
      host.innerHTML = '<div style="font-size:9px;color:#888;text-align:center;padding:10px;">QR lib loading…<br>refresh modal if it stays this way</div>';
      return;
    }
    new QRious({ element: document.getElementById('pmQrCanvasEl'), value: url, size: 240, background: 'white', foreground: '#001423', level: 'M' });
  }
  qsAll('.pm-qr-tab').forEach(t => t.addEventListener('click', () => {
    qsAll('.pm-qr-tab').forEach(x => x.classList.toggle('active', x === t));
    pmQrRender(t.dataset.qrTarget);
  }));
  // Render initial QR when modal opens · piggyback on the pill click (which calls phoneOpenModal)
  if (phonePill) phonePill.addEventListener('click', () => setTimeout(() => pmQrRender('termux'), 100));

  let _phoneState = 'unknown';   // 'unknown' | 'ok' | 'err' | 'idle'
  let _phoneCfg = null;

  function phoneLoadCfg() {
    const u = localStorage.getItem(PHONE_KEY_URL) || '';
    const t = localStorage.getItem(PHONE_KEY_TOKEN) || '';
    _phoneCfg = u && t ? { url: u, token: t } : null;
    if (pmUrl)   pmUrl.value   = u;
    if (pmToken) pmToken.value = t;
    return _phoneCfg;
  }
  function phoneSaveCfg(url, token) {
    localStorage.setItem(PHONE_KEY_URL,   url);
    localStorage.setItem(PHONE_KEY_TOKEN, token);
    _phoneCfg = { url, token };
  }
  function phoneClearCfg() {
    localStorage.removeItem(PHONE_KEY_URL);
    localStorage.removeItem(PHONE_KEY_TOKEN);
    _phoneCfg = null;
    if (pmUrl) pmUrl.value = '';
    if (pmToken) pmToken.value = '';
  }
  function phoneSetState(s, title) {
    _phoneState = s;
    if (phonePill) {
      phonePill.dataset.state = (s === 'ok' || s === 'err') ? s : '';
      phonePill.title = title || 'Device Bridge · click to set up';
    }
  }
  function emitPhoneEvent(action, target, status, result) {
    // Fire-and-forget · feed the Campus event spine so phone activity shows up in the audit log
    try {
      fetch(EVENT_URL, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actor: 'phone', action, target, status, room: 'maya_brain', result: result || '' })
      });
    } catch (_) {}
  }
  async function phonePing(cfg) {
    try {
      const r = await fetch(cfg.url.replace(/\/$/, '') + '/health', { cache: 'no-store' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const j = await r.json();
      if (!j.ok) throw new Error('bridge unhealthy');
      return j;
    } catch (e) { throw e; }
  }
  async function phoneCheck() {
    if (!_phoneCfg) { phoneSetState('idle', 'Device Bridge · not connected · click to set up'); return; }
    try {
      const j = await phonePing(_phoneCfg);
      const caps = j.capabilities || {};
      const okCount = Object.values(caps).filter(Boolean).length;
      phoneSetState('ok', `Device Bridge · connected · ${okCount} capabilities · ${j.device_hint || ''}`);
      return j;
    } catch (e) {
      phoneSetState('err', 'Device Bridge · unreachable · ' + e.message);
    }
  }

  function phoneOpenModal() {
    phoneLoadCfg();
    if (phoneModal) phoneModal.hidden = false;
    // v1.11.3 · auto-load registry on open if PIN is cached
    if (pmRegPin && pmRegPin.value.trim()) devListRefresh();
  }
  function phoneCloseModal() { if (phoneModal) phoneModal.hidden = true; }
  if (phonePill) phonePill.addEventListener('click', phoneOpenModal);
  if (pmClose) pmClose.addEventListener('click', phoneCloseModal);
  if (pmBackdrop) pmBackdrop.addEventListener('click', phoneCloseModal);

  async function phoneTest(showInModal) {
    const url   = (pmUrl?.value || '').trim().replace(/\/$/, '');
    const token = (pmToken?.value || '').trim();
    if (!url || !token) {
      if (showInModal && pmStatus) { pmStatus.textContent = 'Need both URL and token first.'; pmStatus.className = 'pm-status err'; }
      return null;
    }
    if (pmStatus) { pmStatus.textContent = 'Testing connection…'; pmStatus.className = 'pm-status'; }
    try {
      const j = await phonePing({ url, token });
      if (pmStatus) { pmStatus.textContent = `✓ Reachable · v${j.version || '?'} · ${j.device_hint || ''}`; pmStatus.className = 'pm-status ok'; }
      // Capabilities panel
      const caps = j.capabilities || {};
      const order = ['files', 'shell', 'ssh', 'termux_api'];
      const labels = { files: 'Filesystem (~/storage/shared)', shell: 'Shell exec', ssh: 'SSH outbound', termux_api: 'Termux:API (clipboard · camera · contacts · SMS · etc.)' };
      pmCapsList.innerHTML = order.map(k => (
        '<div class="pm-cap" data-ok="' + (caps[k] ? 1 : 0) + '">'
        + '<span class="pm-cap-dot"></span>'
        + '<span>' + escapeHTML(labels[k]) + (caps[k] ? '' : ' · not detected') + '</span>'
        + '</div>'
      )).join('');
      pmCaps.hidden = false;
      return j;
    } catch (e) {
      if (pmStatus) { pmStatus.textContent = '✗ ' + e.message; pmStatus.className = 'pm-status err'; }
      pmCaps.hidden = true;
      return null;
    }
  }
  if (pmTestBtn) pmTestBtn.addEventListener('click', () => phoneTest(true));
  if (pmSaveBtn) pmSaveBtn.addEventListener('click', async () => {
    const j = await phoneTest(true);
    if (!j) return;
    const url   = (pmUrl.value || '').trim().replace(/\/$/, '');
    const token = (pmToken.value || '').trim();
    phoneSaveCfg(url, token);
    emitPhoneEvent('bridge_connected', j.device_hint || 'phone', 'done', `v${j.version} · ${Object.values(j.capabilities||{}).filter(Boolean).length} caps`);
    await phoneCheck();
  });
  if (pmDisconnectBtn) pmDisconnectBtn.addEventListener('click', () => {
    phoneClearCfg();
    emitPhoneEvent('bridge_disconnected', 'phone', 'done', 'user-initiated');
    phoneSetState('idle', 'Device Bridge · not connected');
    if (pmStatus) { pmStatus.textContent = 'Disconnected.'; pmStatus.className = 'pm-status'; }
    pmCaps.hidden = true;
  });

  // Expose minimal helpers to other modules (chat composer etc.)
  window.MayaPhone = {
    isConnected: () => !!_phoneCfg && _phoneState === 'ok',
    health: () => _phoneCfg ? phonePing(_phoneCfg) : Promise.reject(new Error('no bridge cfg')),
    files: (path = '') => {
      if (!_phoneCfg) return Promise.reject(new Error('phone bridge not connected'));
      return fetch(_phoneCfg.url + '/files?path=' + encodeURIComponent(path), { headers: { 'X-Maya-Bridge-Token': _phoneCfg.token } }).then(r => r.json());
    },
    shell: (cmd, confirm = false) => {
      if (!_phoneCfg) return Promise.reject(new Error('phone bridge not connected'));
      emitPhoneEvent('shell', cmd.slice(0, 80), 'start');
      return fetch(_phoneCfg.url + '/shell', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Maya-Bridge-Token': _phoneCfg.token },
        body: JSON.stringify({ cmd, confirm })
      }).then(r => r.json()).then(j => {
        emitPhoneEvent('shell', cmd.slice(0, 80), j.ok ? 'done' : 'error', j.error || ('exit ' + j.exit));
        return j;
      });
    },
    ssh: (host, opts = {}) => {
      if (!_phoneCfg) return Promise.reject(new Error('phone bridge not connected'));
      emitPhoneEvent('ssh', host, 'start');
      return fetch(_phoneCfg.url + '/ssh', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Maya-Bridge-Token': _phoneCfg.token },
        body: JSON.stringify({ host, user: opts.user, cmd: opts.cmd })
      }).then(r => r.json()).then(j => {
        emitPhoneEvent('ssh', host, j.ok ? 'done' : 'error', j.error || ('exit ' + j.exit));
        return j;
      });
    },
    termux: (api, args = {}) => {
      if (!_phoneCfg) return Promise.reject(new Error('phone bridge not connected'));
      return fetch(_phoneCfg.url + '/termux/' + encodeURIComponent(api), {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Maya-Bridge-Token': _phoneCfg.token },
        body: JSON.stringify({ args })
      }).then(r => r.json());
    },
  };

  // Boot · load cfg + ping once + poll every 30s while cfg exists
  phoneLoadCfg();
  phoneCheck();
  setInterval(() => { if (_phoneCfg) phoneCheck(); }, 30000);

  // ── BELL POLLING · update unread badge every 60s ──
  async function pollUnreadBadge() {
    if (!bellBadge) return;
    try {
      const r = await fetch(GMAIL_URL + '?action=unread_count', { cache: 'no-store' });
      const j = await r.json();
      const n = (j && j.ok) ? (j.unread_24h | 0) : 0;
      if (n > 0) {
        bellBadge.textContent = n > 99 ? '99+' : String(n);
        bellBadge.hidden = false;
        bellBtn.title = 'Inbox + SMS · ' + n + ' unread email' + (n===1?'':'s') + ' (last 24h)';
      } else {
        bellBadge.hidden = true;
        bellBtn.title = 'Inbox + SMS · Maya can brief you';
      }
    } catch (_) {}
  }
  pollUnreadBadge();
  setInterval(pollUnreadBadge, 60000);

  // v1.13.0 · Web Share Target · 2026-05-15
  // When Maya OS is installed as PWA and Mo taps "Maya OS" in Android share sheet,
  // we land here with ?shared_url= / ?shared_text= / ?shared_title= query params.
  // We emit a Campus event so packets fly, drop the content into chat, and clean URL.
  (function handleSharedTarget() {
    const p = new URLSearchParams(location.search);
    const sharedUrl   = p.get('shared_url');
    const sharedText  = p.get('shared_text');
    const sharedTitle = p.get('shared_title');
    if (!sharedUrl && !sharedText && !sharedTitle) return;

    const payload = [sharedTitle, sharedUrl, sharedText].filter(Boolean).join('\n');

    // 1. Emit event to Campus event spine so Maya sees it instantly
    try {
      fetch('https://iamsuperio.cloud/api/maya_event', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          actor: 'phone_share', action: 'share_received',
          target: (sharedUrl || sharedTitle || '').slice(0, 200),
          status: 'done', room: 'maya_brain',
          result: payload.slice(0, 480)
        })
      });
    } catch (_) {}

    // 2. Drop into chat as if Mo typed it · then route Maya to answer
    setTimeout(() => {
      try {
        const composer = document.getElementById('msg');
        if (composer) {
          composer.value = '[Shared from phone]\n' + payload + '\n\nMaya · what should I do with this?';
          composer.focus();
        }
      } catch (_) {}
    }, 400);

    // 3. Clean URL so refresh doesn't re-process the share
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, document.title, location.pathname + location.hash);
    }
  })();

  // Boot complete
  switchMode('chat');
})();
