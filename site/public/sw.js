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
    if (typeof self.clients === 'object' && typeof self.clients.claim === 'function') {
        event.waitUntil(self.clients.claim());
    }
    else {
        // Fake clients for test environments (Cypress)
        self.clients = {
            claim: () => Promise.resolve(),
            matchAll: () => Promise.resolve([]),
        };
    }
});

self.addEventListener('fetch', (fetchEvent) => {
    fetchEvent.respondWith(
        caches.match(fetchEvent.request).then((res) => {
            return res || fetch(fetchEvent.request);
        }),
    );
});
