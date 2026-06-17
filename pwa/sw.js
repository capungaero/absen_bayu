var CACHE = 'tiffany-emp-v7';
var ASSETS = ['./','index.html','style.css?v=7','app.js?v=7','manifest.json','icons/icon-192.png','icons/icon-512.png'];

self.addEventListener('install', function(e){
  e.waitUntil(caches.open(CACHE).then(function(c){ return c.addAll(ASSETS); }).then(function(){ return self.skipWaiting(); }));
});
self.addEventListener('activate', function(e){
  e.waitUntil(caches.keys().then(function(ks){
    return Promise.all(ks.map(function(k){ if(k !== CACHE) return caches.delete(k); }));
  }).then(function(){ return self.clients.claim(); }));
});
self.addEventListener('fetch', function(e){
  if(e.request.method !== 'GET') return;                 // POST (login) selalu jaringan
  if(e.request.url.indexOf('/absen/api') > -1) return;   // API selalu jaringan, tak di-cache
  e.respondWith(caches.match(e.request).then(function(r){ return r || fetch(e.request); }));
});
