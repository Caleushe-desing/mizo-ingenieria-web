/* Mizo Admin - service worker raíz (scope /) para push en segundo plano */
const SW_VERSION = 'mizo-admin-sw-v3';
const POLL_MS = 10000;
const DB_NAME = 'mizo-admin-sw';
const STORE = 'meta';

let deviceToken = null;
let pollTimer = null;
let lastShownSequence = 0;

function openDb() {
	return new Promise((resolve, reject) => {
		const request = indexedDB.open(DB_NAME, 1);
		request.onupgradeneeded = () => {
			const db = request.result;
			if (!db.objectStoreNames.contains(STORE)) {
				db.createObjectStore(STORE);
			}
		};
		request.onsuccess = () => resolve(request.result);
		request.onerror = () => reject(request.error);
	});
}

async function idbGet(key) {
	const db = await openDb();
	return new Promise((resolve, reject) => {
		const tx = db.transaction(STORE, 'readonly');
		const request = tx.objectStore(STORE).get(key);
		request.onsuccess = () => resolve(request.result ?? null);
		request.onerror = () => reject(request.error);
	});
}

async function idbSet(key, value) {
	const db = await openDb();
	return new Promise((resolve, reject) => {
		const tx = db.transaction(STORE, 'readwrite');
		tx.objectStore(STORE).put(value, key);
		tx.oncomplete = () => resolve();
		tx.onerror = () => reject(tx.error);
	});
}

async function loadMeta() {
	try {
		deviceToken = (await idbGet('deviceToken')) || null;
		lastShownSequence = Number((await idbGet('lastShownSequence')) || 0);
	} catch (error) {
		/* ignore */
	}
}

async function storeToken(token) {
	deviceToken = token || null;
	await idbSet('deviceToken', deviceToken || '');
	if (deviceToken) startPolling();
}

async function storeLastShownSequence(sequence) {
	lastShownSequence = Number(sequence || 0);
	await idbSet('lastShownSequence', lastShownSequence);
}

async function ackSequence(sequence) {
	if (!deviceToken || !sequence) return;
	try {
		await fetch(
			`/api/admin-push.php?action=ack&token=${encodeURIComponent(deviceToken)}&sequence=${encodeURIComponent(sequence)}`,
			{ cache: 'no-store', headers: { Accept: 'application/json' } },
		);
	} catch (error) {
		/* ignore */
	}
}

async function showLeadNotification(payload) {
	const sequence = Number(payload.sequence || 0);
	if (sequence > 0 && sequence <= lastShownSequence) return;

	await self.registration.showNotification(payload.title || 'Nuevo formulario Mizo', {
		body: payload.body || 'Hay una nueva solicitud en el panel.',
		icon: '/favicon.png',
		badge: '/favicon.png',
		data: {
			url: payload.url || '/admin/#leads',
			sequence: sequence || null,
		},
		tag: sequence > 0 ? `mizo-admin-form-${sequence}` : 'mizo-admin-form',
		renotify: true,
		vibrate: [200, 100, 200, 100, 200],
		requireInteraction: true,
	});

	if (sequence > 0) {
		await storeLastShownSequence(sequence);
		await ackSequence(sequence);
	}
}

async function peekNotifications() {
	if (!deviceToken) return null;
	try {
		const response = await fetch(
			`/api/admin-push.php?action=peek&token=${encodeURIComponent(deviceToken)}`,
			{ cache: 'no-store', headers: { Accept: 'application/json' } },
		);
		return await response.json().catch(() => null);
	} catch (error) {
		return null;
	}
}

async function pollNotifications() {
	if (!deviceToken) {
		await loadMeta();
	}
	if (!deviceToken) return;

	const payload = await peekNotifications();
	if (!payload?.ok || !payload?.notify) return;
	await showLeadNotification(payload);
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
			await loadMeta();
			if (deviceToken) startPolling();
		})(),
	);
});

self.addEventListener('message', (event) => {
	const data = event.data || {};
	if (data.type === 'mizo-set-token' && data.token) {
		event.waitUntil(storeToken(data.token));
	}
	if (data.type === 'mizo-clear-token') {
		deviceToken = null;
		if (pollTimer) {
			clearInterval(pollTimer);
			pollTimer = null;
		}
		event.waitUntil(idbSet('deviceToken', ''));
	}
	if (data.type === 'mizo-poll-now') {
		event.waitUntil(pollNotifications());
	}
});

self.addEventListener('sync', (event) => {
	if (event.tag === 'mizo-check-leads') {
		event.waitUntil(pollNotifications());
	}
});

self.addEventListener('periodicsync', (event) => {
	if (event.tag === 'mizo-check-leads') {
		event.waitUntil(pollNotifications());
	}
});

self.addEventListener('push', (event) => {
	let payload = {};
	try {
		payload = event.data ? event.data.json() : {};
	} catch (error) {
		payload = {};
	}
	event.waitUntil(showLeadNotification(payload));
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
