const staticDevPort = 'Submittty';
const assets = [
];

self.addEventListener('install', (installEvent) => {
    installEvent.waitUntil(
        caches.open(staticDevPort).then((cache) => {
            cache.addAll(assets);
        }),
    );
});

self.addEventListener('fetch', (fetchEvent) => {
    fetchEvent.respondWith(
        caches.match(fetchEvent.request).then((res) => {
            return res || fetch(fetchEvent.request);
        }),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});
