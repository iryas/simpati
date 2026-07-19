/* ============================================================
   PORTAL PELANGGAN — Service Worker
   Strategi:
   - Navigasi (halaman)  : network-first, fallback cache
   - Aset statis         : stale-while-revalidate
   - Jangan cache act.php / POST / non-GET
   ============================================================ */
const CACHE = 'portal-kahfinet-v1';
const ASSETS = [
  'assets/portal.css',
  'assets/icon-192.png',
  'assets/icon-512.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(ASSETS)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const req = e.request;

  // Hanya tangani GET same-origin
  if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;

  // Jangan cache endpoint aksi
  if (req.url.includes('act.php') || req.url.includes('manifest.php')) return;

  // Navigasi halaman → network-first
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
          return res;
        })
        .catch(() => caches.match(req))
    );
    return;
  }

  // Aset lain → stale-while-revalidate
  e.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
          return res;
        })
        .catch(() => cached);
      return cached || network;
    })
  );
});
