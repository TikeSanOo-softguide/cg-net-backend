/* global importScripts, firebase */
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-messaging-compat.js');

// The page passes the Firebase web config in the registration URL because service workers cannot read localStorage.
const config = JSON.parse(new URL(self.location.href).searchParams.get('config') || '{}');

if (config.apiKey) {
    firebase.initializeApp(config);
    const messaging = firebase.messaging();

    messaging.onBackgroundMessage((payload) => {
        const clients = self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        clients.then((list) => list.forEach((client) => client.postMessage({ source: 'fcm-sw', payload })));
    });
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(self.clients.openWindow('/dev/fcm/index.html'));
});
