self.addEventListener('install', function (event) {
	self.skipWaiting();
});

self.addEventListener('activate', function (event) {
	event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function () {});

self.addEventListener('push', function (event) {
	var data = {};
	try {
		data = event.data ? event.data.json() : {};
	} catch (error) {
		data = {};
	}
	var title = data.title || 'Mizo CRM';
	var options = {
		body: data.body || '',
		icon: '/crm/icon-192.png',
		badge: '/crm/icon-192.png',
		tag: data.tag || 'mizo',
		renotify: true,
		data: { url: data.url || '/crm/' }
	};
	event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
	event.notification.close();
	var target = (event.notification.data && event.notification.data.url) || '/crm/';
	event.waitUntil(
		self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
			for (var i = 0; i < list.length; i++) {
				if (list[i].url.indexOf('/crm') !== -1 && 'focus' in list[i]) {
					if ('navigate' in list[i]) {
						list[i].navigate(target);
					}
					return list[i].focus();
				}
			}
			if (self.clients.openWindow) {
				return self.clients.openWindow(target);
			}
		})
	);
});
