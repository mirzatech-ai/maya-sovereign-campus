// Maya Watches · background service worker
// Mo's directive 2026-05-15: "While I'm browsing, Maya needs to see what page
// I'm on, what the page says, what buttons do I click, what passwords do I use,
// what logins do I use, what am I looking for."
//
// This worker:
//   1. Listens for tab activation + navigation events
//   2. Receives messages from content.js (clicks · selections · form events)
//   3. POSTs everything to https://iamsuperio.cloud/api/maya_event
//   4. Stores opt-in toggles in chrome.storage.local
//
// Privacy: by default ALL features are opt-in via the popup. Password-watching
// is OFF unless explicitly enabled. Mo controls everything from the popup.

const EVENT_URL = 'https://iamsuperio.cloud/api/maya_event';
const DEFAULTS = {
  enabled: true,            // master switch
  watchPages: true,         // log every page navigation
  watchClicks: false,       // log every click target (verbose)
  watchSelection: true,     // log text Mo selects
  watchForms: false,        // log form submissions (visible field names + URL, NOT values)
  watchPasswords: false,    // EXPLICIT opt-in only · log password field URLs + length only (never the value)
  pinTag: ''                // optional Commander PIN to tag events (not required)
};

async function getSettings() {
  const { mayaWatchSettings } = await chrome.storage.local.get('mayaWatchSettings');
  return { ...DEFAULTS, ...(mayaWatchSettings || {}) };
}

async function emit(action, payload) {
  const s = await getSettings();
  if (!s.enabled) return;
  const body = {
    actor: 'browser',
    action,
    target: (payload.url || payload.target || '').slice(0, 200),
    status: 'done',
    room: 'maya_brain',
    result: JSON.stringify(payload).slice(0, 480),
    ...(s.pinTag ? { _pin_tag: s.pinTag } : {})
  };
  try {
    await fetch(EVENT_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
  } catch (e) {
    // Network fail — silent (Maya will see the gap)
  }
}

// 1. Tab activation = "Mo switched to this tab"
chrome.tabs.onActivated.addListener(async ({ tabId }) => {
  const s = await getSettings();
  if (!s.watchPages) return;
  try {
    const tab = await chrome.tabs.get(tabId);
    emit('tab_activated', { url: tab.url, title: tab.title });
  } catch (e) {}
});

// 2. Page navigation = "Mo opened/changed page"
chrome.webNavigation = chrome.webNavigation || {};
if (chrome.webNavigation && chrome.webNavigation.onCompleted) {
  chrome.webNavigation.onCompleted.addListener(async (details) => {
    if (details.frameId !== 0) return;  // top frame only
    const s = await getSettings();
    if (!s.watchPages) return;
    emit('page_loaded', { url: details.url });
  });
}

// 3. Content script messages
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  (async () => {
    const s = await getSettings();
    if (!s.enabled) { sendResponse({ ok: true, ignored: true }); return; }
    if (msg.type === 'click' && s.watchClicks) {
      emit('click', { url: sender.tab?.url, target: msg.target });
    } else if (msg.type === 'selection' && s.watchSelection) {
      emit('selection', { url: sender.tab?.url, text: msg.text });
    } else if (msg.type === 'form_submit' && s.watchForms) {
      emit('form_submit', { url: sender.tab?.url, fields: msg.fields });
    } else if (msg.type === 'password_field' && s.watchPasswords) {
      // NEVER include the value · only the URL and length
      emit('password_field_seen', { url: sender.tab?.url, length: msg.length });
    } else if (msg.type === 'page_meta' && s.watchPages) {
      emit('page_meta', { url: sender.tab?.url, title: msg.title, h1: msg.h1, snippet: msg.snippet });
    }
    sendResponse({ ok: true });
  })();
  return true;  // async
});

// 4. Heartbeat ping every 60s so Maya knows the extension is alive
chrome.alarms.create('mayaHeartbeat', { periodInMinutes: 1 });
chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name !== 'mayaHeartbeat') return;
  emit('extension_heartbeat', { target: 'maya_watches' });
});
