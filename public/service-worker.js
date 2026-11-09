self.addEventListener('push', function (event) {
    if (!event.data) {
        return;
    }

    const data = event.data.json();
    event.waitUntil(self.registration.showNotification(data.title, {
        body: data.body,
        data: { url: data.url },
        icon: '/favicon.ico',
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const target = new URL(event.notification.data.url, self.location.origin);
    if (target.origin !== self.location.origin) {
        return;
    }

    event.waitUntil(clients.openWindow(target.href));
});
