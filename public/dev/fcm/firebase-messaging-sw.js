/* global importScripts, firebase */
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-messaging-compat.js');

// The page passes the Firebase web config in the registration URL because service workers cannot read localStorage.
const config = JSON.parse(new URL(self.location.href).searchParams.get('config') || '{}');

if (config.apiKey) {
    firebase.initializeApp(config);
    const messaging = firebase.messaging();

    messaging.onBackgroundMessage(async (payload) => {
        const notification = payload.notification || {};
        const data = payload.data || {};

        if (notification.title) {
            await self.registration.showNotification(notification.title, {
                body: notification.body || '',
                icon: '/images/cg-net-logo.png',
                data,
            });
        }

        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        clients.forEach((client) => client.postMessage({ source: 'fcm-sw', type: 'message', payload }));
    });
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const data = event.notification.data || {};

    event.waitUntil((async () => {
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const testPage = clients.find((client) => client.url.includes('/dev/fcm/'));

        if (testPage) {
            await testPage.focus();
            testPage.postMessage({ source: 'fcm-sw', type: 'notification-click', data });
            return;
        }

        const url = new URL('/dev/fcm/index.html', self.location.origin);
        Object.entries(data).forEach(([key, value]) => url.searchParams.set(key, String(value)));
        await self.clients.openWindow(url.toString());
    })());
});
