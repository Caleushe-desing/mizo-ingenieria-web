(function () {
	'use strict';
	if (/[?&]editar=1(?:&|$)/.test(window.location.search)) return;
	var path = window.location.pathname || '/';
	if (path.indexOf('/crm') === 0) return;

	function id(store, key) {
		try {
			var current = store.getItem(key);
			if (current && /^[a-zA-Z0-9-]{8,64}$/.test(current)) return current;
			var next = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10));
			next = next.replace(/[^a-zA-Z0-9-]/g, '').slice(0, 64);
			store.setItem(key, next);
			return next;
		} catch (error) {
			return '';
		}
	}

	var visitor = id(localStorage, 'mizo_vid');
	var session = id(sessionStorage, 'mizo_sid');
	if (!visitor || !session) return;
	var params = new URLSearchParams(window.location.search);
	var body = JSON.stringify({
		path: path,
		title: document.title || '',
		referrer: document.referrer || '',
		lang: navigator.language || '',
		screen: (screen.width || 0) + 'x' + (screen.height || 0),
		visitor: visitor,
		session: session,
		utm_source: params.get('utm_source') || '',
		utm_medium: params.get('utm_medium') || '',
		utm_campaign: params.get('utm_campaign') || '',
		utm_term: params.get('utm_term') || '',
		utm_content: params.get('utm_content') || '',
		gclid: params.get('gclid') ? 1 : 0,
		fbclid: params.get('fbclid') ? 1 : 0
	});
	var url = '/crm/api/visita.php';
	if (navigator.sendBeacon) {
		navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
	} else {
		fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'same-origin' }).catch(function () {});
	}

	function beat() {
		var pulse = JSON.stringify({
			action: 'presencia',
			visitor: visitor,
			session: session,
			path: window.location.pathname || '/',
			title: document.title || '',
			referrer: document.referrer || ''
		});
		fetch('/crm/api/sitio-vivo.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: pulse,
			keepalive: true,
			credentials: 'same-origin'
		}).catch(function () {});
	}
	beat();
	setInterval(beat, 15000);
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) beat();
	});
})();
