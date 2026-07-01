/* Mizo Admin PWA - notificaciones en segundo plano */
const POLL_MS = 45000;
const TOKEN_KEY = 'mizo-admin-device-token';

let deviceToken = null;
let pollTimer = null;

async function readStoredToken() {
	try {
		const cache = await caches.open('mizo-admin-meta');
		const response = await cache.match(TOKEN_KEY);
		if (!response) return null;
		return (await response.text()).trim() || null;
	} catch (error) {
		return null;
	}
}

async function storeToken(token) {
	deviceToken = token;
	try {
		const cache = await caches.open('mizo-admin-meta');
		await cache.put(TOKEN_KEY, new Response(String(token || '')));
	} catch (error) {
		/* ignore */
	}
}

async function pollNotifications() {
	if (!deviceToken) {
		deviceToken = await readStoredToken();
	}
	if (!deviceToken) return;

	try {
		const response = await fetch(
			`/api/admin-push.php?action=check&token=${encodeURIComponent(deviceToken)}`,
			{ cache: 'no-store', headers: { Accept: 'application/json' } },
		);
		const payload = await response.json().catch(() => null);
		if (!response.ok || !payload?.notify) return;

		await self.registration.showNotification(payload.title || 'Nuevo formulario Mizo', {
			body: payload.body || 'Hay una nueva solicitud en el panel.',
			icon: '/favicon.png',
			badge: '/favicon.png',
			data: { url: payload.url || '/admin/#leads' },
			tag: 'mizo-admin-form',
			renotify: true,
		});
	} catch (error) {
		/* ignore transient network errors */
	}
}

function startPolling() {
	if (pollTimer) return;
	pollNotifications();
	pollTimer = setInterval(pollNotifications, POLL_MS);
}

self.addEventListener('install', (event) => {
	event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
	event.waitUntil(
		(async () => {
			await self.clients.claim();
			deviceToken = await readStoredToken();
			if (deviceToken) startPolling();
		})(),
	);
});

self.addEventListener('message', (event) => {
	const data = event.data || {};
	if (data.type === 'mizo-set-token' && data.token) {
		storeToken(data.token).then(startPolling);
	}
	if (data.type === 'mizo-clear-token') {
		deviceToken = null;
		if (pollTimer) {
			clearInterval(pollTimer);
			pollTimer = null;
		}
	}
});

self.addEventListener('push', (event) => {
	let payload = {};
	try {
		payload = event.data ? event.data.json() : {};
	} catch (error) {
		payload = {};
	}

	event.waitUntil(
		self.registration.showNotification(payload.title || 'Nuevo formulario Mizo', {
			body: payload.body || 'Hay una nueva solicitud en el panel.',
			icon: '/favicon.png',
			badge: '/favicon.png',
			data: { url: payload.url || '/admin/#leads' },
			tag: 'mizo-admin-form',
			renotify: true,
		}),
	);
});

self.addEventListener('notificationclick', (event) => {
	event.notification.close();
	const target = event.notification.data?.url || '/admin/#leads';

	event.waitUntil(
		(async () => {
			const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
			for (const client of clientsList) {
				if (client.url.includes('/admin')) {
					if ('navigate' in client) await client.navigate(target);
					return client.focus();
				}
			}
			if (self.clients.openWindow) return self.clients.openWindow(target);
		})(),
	);
});
