// Service worker Temuan: cache shell saja, API selalu network.
// v2 (2 Okt 2026): index.html tanpa Cache-Control di nginx -> fetch() SW
// bisa kena heuristic cache browser & balikin HTML lama meski "network-
// first" (bug: admin lihat UI lama walau server sudah update). Fix: request
// navigasi (HTML) dipaksa cache:'no-store' supaya SELALU hit network asli.
var CACHE = 'temuan-v2';

self.addEventListener('install', function (e) {
  self.skipWaiting();
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  var url = new URL(e.request.url);
  // API & upload: jangan cache
  if (e.request.method !== 'GET' || url.pathname.indexOf('/absen/temuan') === 0 || url.pathname.indexOf('/absen/api') === 0 || url.pathname.indexOf('/absen/assets') === 0) {
    return;
  }
  var fetchOpts = e.request.mode === 'navigate' ? { cache: 'no-store' } : undefined;
  e.respondWith(
    fetch(e.request, fetchOpts).then(function (res) {
      var copy = res.clone();
      caches.open(CACHE).then(function (c) { c.put(e.request, copy); });
      return res;
    }).catch(function () {
      return caches.match(e.request);
    })
  );
});
