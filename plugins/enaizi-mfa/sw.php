<?php
// sw.php – service worker script
header('Content-Type: application/javascript');
header('Service-Worker-Allowed: /');

// Ensure external global constants or fallback defaults pass verification
$plugin_url = defined('ENAIZI_PWA_URL') ? ENAIZI_PWA_URL : '/wp-content/plugins/enaizi-mfa/'; 
$offline_url = $plugin_url . 'offline.html';
?>

// Enaizi PWA Service Worker
const CACHE_VERSION = 'v4.59'; // Version bumped to force device updates (single-h1 fix on surah pages, css renamed to v7)
const CACHE_NAME = `enaizi-pwa-${CACHE_VERSION}`;
const OFFLINE_URL = '<?php echo esc_url($offline_url); ?>';

const PRECACHE_URLS = [
  OFFLINE_URL,
  '<?php echo esc_url($plugin_url); ?>assets/icon-192.png',
  '<?php echo esc_url($plugin_url); ?>assets/icon-512.png'
]; 

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE_URLS))
  );
  // CRITICAL: Force the new SW to take over immediately and kill the old one
  self.skipWaiting(); 
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
    ))
  );
  // CRITICAL: Claim all open tabs instantly
  self.clients.claim(); 
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Bypass admin and REST API routes
  if (
    url.pathname.includes('/wp-admin') ||
    url.pathname.includes('/wp-login.php') ||
    url.pathname.includes('/wp-json')
  ) {
    return;
  }

  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  // 1. HTML PAGES: Strict Network-First Strategy
  if (event.request.mode === 'navigate') {
    event.respondWith(
      (async () => {
        try {
          // Force the phone to bypass disk cache entirely for HTML pages
          return await fetch(event.request, { cache: 'no-cache' });
        } catch (error) {
          const offlinePage = await caches.match(OFFLINE_URL);
          if (offlinePage) return offlinePage;
          return new Response('Offline Mode Active. Page unavailable.', { 
            status: 503,
            headers: { 'Content-Type': 'text/html' }
          });
        }
      })()
    );
    return;
  }

  // 2. STATIC ASSETS: Cache-First Strategy
  // ONLY cache safe, static file extensions. Ignore dynamic frontend queries!
  const isStaticAsset = url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot|ico)$/i);

  if (isStaticAsset) {
    event.respondWith(
      (async () => {
        const cached = await caches.match(event.request);
        if (cached) return cached;
        
        try {
          const networkResponse = await fetch(event.request);
          if (networkResponse && networkResponse.status === 200) {
            const isSameOrigin = event.request.url.startsWith(self.location.origin);
            const isAllowedCdn = event.request.url.includes('cdn.staging.masjid4all.com') || event.request.url.includes('jsdelivr.net');
            
            if (isSameOrigin || isAllowedCdn) {
              const cache = await caches.open(CACHE_NAME);
              cache.put(event.request, networkResponse.clone());
            }
          }
          return networkResponse;
        } catch (error) {
          return new Response('Resource unavailable', { status: 404 });
        }
      })()
    );
}
