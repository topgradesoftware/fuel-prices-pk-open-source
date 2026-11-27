const CACHE = 'fuel-pk-v29';
const ASSETS = [
  '/',
  '/fuel-calculator',
  '/price-history',
  '/manifest.webmanifest',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];
const API_CACHE_PATHS = ['/api/v1/prices', '/api/v1/stations', '/api/v1/notifications', '/api/v1/cities'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))),
    ).then(() => self.clients.claim()),
  );
});

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'SKIP_WAITING') {
    self.skipWaiting();
    return;
  }
  if (data.type === 'CLEAR_OLD_CACHES' || data.type === 'CLEAR_ALL_CACHES') {
    event.waitUntil(
      caches.keys().then((keys) =>
        Promise.all(
          keys
            .filter((k) => (data.type === 'CLEAR_ALL_CACHES' ? true : k !== CACHE))
            .map((k) => caches.delete(k)),
        ),
      ),
    );
  }
});

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = { body: event.data ? event.data.text() : '' };
  }
  const title = data.title || 'Fuel Prices PK';
  event.waitUntil(
    self.registration.showNotification(title, {
      body: data.body || 'Fuel prices were updated.',
      icon: '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
      tag: data.tag || 'fuel-pk',
      data: { url: data.url || '/' },
    }),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      for (const client of clients) {
        if ('focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      return self.clients.openWindow(url);
    }),
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  const isApi = url.pathname.startsWith('/api/');
  const isNav = event.request.mode === 'navigate';
  const isBuild = url.pathname.startsWith('/build/');
  const preferCacheApi = API_CACHE_PATHS.some((p) => url.pathname.startsWith(p));

  // Built assets carry a content hash — cache-first is safe.
  if (isBuild) {
    event.respondWith(
      caches.match(event.request).then((hit) => {
        if (hit) return hit;
        return fetch(event.request).then((response) => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE).then((cache) => cache.put(event.request, copy));
          }
          return response;
        });
      }),
    );
    return;
  }

  // HTML navigations: always prefer network so PWA stays in parity with the live web app.
  // Only keep `/` as an offline shell fallback.
  if (isNav) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (response.ok && (url.pathname === '/' || url.pathname === '')) {
            const copy = response.clone();
            caches.open(CACHE).then((cache) => cache.put('/', copy));
          }
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(event.request);
          if (cached) return cached;
          return caches.match('/');
        }),
    );
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response.ok && isApi) {
          const copy = response.clone();
          caches.open(CACHE).then((cache) => cache.put(event.request, copy));
        }
        return response;
      })
      .catch(async () => {
        const cached = await caches.match(event.request);
        if (cached) return cached;
        if (preferCacheApi) {
          const keys = await caches.open(CACHE).then((c) => c.keys());
          const hit = keys.find((k) => new URL(k.url).pathname === url.pathname);
          if (hit) return caches.match(hit);
        }
        return Response.error();
      }),
  );
});
