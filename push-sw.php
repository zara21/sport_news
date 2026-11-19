<?php
// Service worker push-შეტყობინებებისთვის. მისამართი: /sport_blog/firebase-messaging-sw.js (rewrite .htaccess-ში)
require_once __DIR__ . '/includes/push.php';

header('Content-Type: application/javascript; charset=UTF-8');
header('Service-Worker-Allowed: /sport_blog/');
header('Cache-Control: no-cache');

$config = push_client_enabled() ? push_client_config() : null;
?>
/* Sport Blog push service worker */
<?php if ($config): ?>
importScripts('https://www.gstatic.com/firebasejs/10.12.5/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.5/firebase-messaging-compat.js');

firebase.initializeApp(<?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>);
const messaging = firebase.messaging();

// სერვერი აგზავნის data-only შეტყობინებას — აქ ვაჩვენებთ
messaging.onBackgroundMessage((payload) => {
  const data = payload.data || {};
  self.registration.showNotification(data.title || 'Sport Blog', {
    body: data.body || '',
    icon: data.icon || '/sport_blog/assets/brand/app-icon-192.png',
    badge: '/sport_blog/assets/brand/app-icon-192.png',
    data: { link: data.link || '/sport_blog/' },
  });
});
<?php endif; ?>

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.link) || '/sport_blog/';
  event.waitUntil((async () => {
    const url = new URL(target, self.location.origin);
    // მხოლოდ საკუთარი საიტის ბმულები
    const safe = url.origin === self.location.origin ? url.href : new URL('/sport_blog/', self.location.origin).href;
    const windows = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of windows) {
      if (client.url === safe && 'focus' in client) return client.focus();
    }
    return clients.openWindow(safe);
  })());
});

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
