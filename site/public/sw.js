const staticDevPort = 'Submittty';
const assets = [
];

self.addEventListener('install', (installEvent) => {
    installEvent.waitUntil(
        caches.open(staticDevPort).then((cache) => {
            cache.addAll(assets);
        }).then(self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (fetchEvent) => {
    fetchEvent.respondWith(
        caches.match(fetchEvent.request).then((res) => {
            return res || fetch(fetchEvent.request);
        }),
    );
});
