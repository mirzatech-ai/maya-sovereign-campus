// Maya Watches · popup script
const DEFAULTS = {
  enabled: true, watchPages: true, watchClicks: false,
  watchSelection: true, watchForms: false, watchPasswords: false
};

async function getSettings() {
  const { mayaWatchSettings } = await chrome.storage.local.get('mayaWatchSettings');
  return { ...DEFAULTS, ...(mayaWatchSettings || {}) };
}
async function setSettings(s) {
  await chrome.storage.local.set({ mayaWatchSettings: s });
}

function renderSwitches(s) {
  document.querySelectorAll('.switch').forEach(sw => {
    const key = sw.dataset.key;
    sw.classList.toggle('on', !!s[key]);
    sw.onclick = async () => {
      const cur = await getSettings();
      cur[key] = !cur[key];
      await setSettings(cur);
      renderSwitches(cur);
    };
  });
}

// Pull event count from Maya's tail endpoint as a sanity check
async function loadStats() {
  try {
    const r = await fetch('https://iamsuperio.cloud/api/maya_event?action=tail&n=200');
    const j = await r.json();
    const today = new Date().toISOString().slice(0, 10);
    const count = (j.events || []).filter(e => e.actor === 'browser' && (e.ts || '').startsWith(today)).length;
    document.getElementById('count').textContent = count;
  } catch (e) {
    document.getElementById('count').textContent = '— offline';
  }
}

(async () => {
  const s = await getSettings();
  renderSwitches(s);
  loadStats();
})();
