/* Maya OS · service worker · v1.1 · NETWORK-FIRST during dev
 * Mo asked: "If I refresh that page, I should see the updates you do to it. right???"
 * Answer (for now): YES. Network-first for everything. Cache only as fallback when offline.
 * When v1 locks, flip back to cache-first.
 */
const VERSION = 'maya-os-v1.10.3-2026-05-15-rail-cleanup-campus-primary-cmdk-canvas';

self.addEventListener('install', e => { self.skipWaiting(); });
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))))
  );
  self.clients.claim();
});
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  // NETWORK-FIRST · refresh = fresh
  e.respondWith(
    fetch(e.request).then(resp => {
      // squirrel a copy for offline fallback only
      if (resp && resp.ok) {
        const clone = resp.clone();
        caches.open(VERSION).then(c => c.put(e.request, clone)).catch(() => {});
      }
      return resp;
    }).catch(() => caches.match(e.request).then(c => c || new Response('Offline', { status: 503 })))
  );
});

// Allow page to force a clear from console: `navigator.serviceWorker.controller.postMessage({type:'clearCache'})`
self.addEventListener('message', e => {
  if (e.data && e.data.type === 'clearCache') {
    caches.keys().then(keys => keys.forEach(k => caches.delete(k)));
  }
});
