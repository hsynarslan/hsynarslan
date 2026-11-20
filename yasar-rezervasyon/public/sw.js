/* Basit PWA service worker: yalnızca statik dosyaları önbelleğe alır.
 * Kişisel veri içeren sayfalar önbelleğe ALINMAZ (ISO 27001 A.8.12). */
const CACHE = "yr-static-v1";
const ASSETS = ["/css/app.css", "/js/app.js", "/icons/icon.svg", "/manifest.webmanifest"];
self.addEventListener("install", (e) => e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS))));
self.addEventListener("activate", (e) => e.waitUntil(caches.keys().then((keys) =>
  Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))));
self.addEventListener("fetch", (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== "GET" || url.origin !== location.origin || !ASSETS.includes(url.pathname)) return;
  e.respondWith(caches.match(e.request).then((r) => r || fetch(e.request)));
});
