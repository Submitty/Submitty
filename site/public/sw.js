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
    if (self.clients && typeof self.clients.claim === 'function') {
        // Wait until all clients are controlled by this service worker
        event.waitUntil(self.clients.claim());
    }
    else {
        // Resolve immediately if clients.claim is not available, such as in Cypress environments
        event.waitUntil(Promise.resolve());
    }
});

self.addEventListener('fetch', (fetchEvent) => {
    fetchEvent.respondWith(
        caches.match(fetchEvent.request).then((res) => {
            return res || fetch(fetchEvent.request);
        }),
    );
});
