(function () {
	const list = document.querySelector('[data-items]');
	if (!list) return;

	const addBtn = document.querySelector('[data-add-item]');
	const totals = {
		neto: document.querySelector('[data-neto]'),
		iva: document.querySelector('[data-iva]'),
		total: document.querySelector('[data-total]'),
	};

	function rows() {
		return list.querySelectorAll('[data-item-row]');
	}

	function formatMoney(n) {
		return '$' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}

	function parseMoney(value) {
		return Number(String(value).replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
	}

	function recalc() {
		let neto = 0;
		rows().forEach(function (row) {
			const qtyInput = row.querySelector('[name="item_quantity[]"]');
			const priceInput = row.querySelector('[name="item_price[]"]');
			const lineEl = row.querySelector('[data-line]');
			if (!qtyInput || !priceInput || !lineEl) return;
			const qty = Number(String(qtyInput.value).replace(',', '.')) || 0;
			const price = parseMoney(priceInput.value);
			const line = qty * price;
			neto += line;
			lineEl.textContent = formatMoney(line);
		});
		const iva = Math.round(neto * 0.19);
		if (totals.neto) totals.neto.textContent = formatMoney(neto);
		if (totals.iva) totals.iva.textContent = formatMoney(iva);
		if (totals.total) totals.total.textContent = formatMoney(neto + iva);
	}

	function bindRow(row) {
		row.querySelectorAll('input').forEach(function (input) {
			input.addEventListener('input', recalc);
		});
		const remove = row.querySelector('[data-remove]');
		if (remove) {
			remove.addEventListener('click', function () {
				if (rows().length === 1) return;
				row.remove();
				recalc();
			});
		}
	}

	rows().forEach(bindRow);
	recalc();

	if (addBtn) {
		addBtn.addEventListener('click', function () {
			const first = list.querySelector('[data-item-row]');
			if (!first) return;
			const row = first.cloneNode(true);
			row.querySelectorAll('input').forEach(function (input) {
				const name = input.getAttribute('name') || '';
				if (name === 'item_quantity[]') input.value = '1';
				else if (name === 'item_unit[]') input.value = 'un';
				else input.value = '';
			});
			const line = row.querySelector('[data-line]');
			if (line) line.textContent = '$0';
			list.appendChild(row);
			bindRow(row);
			const focus = row.querySelector('[name="item_description[]"]');
			if (focus) focus.focus();
		});
	}
})();

(function () {
	const root = document.getElementById('live-alert');
	if (!root) return;
	const url = root.getAttribute('data-live-url');
	const link = root.querySelector('.live-alert');
	const titleEl = root.querySelector('strong');
	const textEl = root.querySelector('.live-alert-copy span');
	const badge = document.getElementById('mail-badge');
	if (!url || !link || !titleEl || !textEl) return;

	const seenKey = 'mizo_seen_alerts';
	const unreadKey = 'mizo_unread_known';
	let primed = false;
	let lastUnread = badge && !badge.hidden ? Number(badge.textContent || 0) : 0;
	let hideTimer = 0;

	function seenIds() {
		try {
			const raw = localStorage.getItem(seenKey);
			return raw ? JSON.parse(raw) : [];
		} catch (e) {
			return [];
		}
	}

	function remember(id) {
		const ids = seenIds();
		if (ids.indexOf(id) !== -1) return;
		ids.push(id);
		localStorage.setItem(seenKey, JSON.stringify(ids.slice(-80)));
	}

	function updateBadge(count) {
		if (!badge) return;
		if (count > 0) {
			badge.hidden = false;
			badge.textContent = String(count);
		} else {
			badge.hidden = true;
		}
	}

	function updateQuotes(alerts) {
		alerts.forEach(function (alert) {
			if (!alert.quote_id || !alert.label) return;
			const cell = document.querySelector('[data-quote-status="' + alert.quote_id + '"]');
			if (cell) cell.textContent = alert.label;
		});
	}

	function showAlert(alert) {
		link.className = 'live-alert is-' + alert.kind;
		link.href = alert.href || '#';
		titleEl.textContent = alert.title || '';
		textEl.textContent = alert.text || '';
		root.hidden = false;
		clearTimeout(hideTimer);
		hideTimer = setTimeout(function () {
			root.hidden = true;
		}, 14000);
	}

	function onInbox() {
		const path = window.location.pathname.replace(/\/+$/, '');
		return /\/correo$/.test(path);
	}

	function apply(data) {
		if (!data || !data.ok) return;
		const unread = Number(data.unread || 0);
		const alerts = Array.isArray(data.alerts) ? data.alerts : [];
		updateBadge(unread);
		updateQuotes(alerts);

		const known = seenIds();
		const fresh = alerts.filter(function (alert) {
			return known.indexOf(alert.id) === -1;
		});

		if (!primed) {
			const saved = Number(localStorage.getItem(unreadKey) || -1);
			const quotes = fresh.filter(function (alert) { return alert.kind !== 'mail'; });
			const mails = fresh.filter(function (alert) { return alert.kind === 'mail'; });
			if (quotes[0]) {
				showAlert(quotes[0]);
				quotes.forEach(function (alert) { remember(alert.id); });
			} else if (saved >= 0 && unread > saved && mails[0]) {
				showAlert(mails[0]);
			}
			mails.forEach(function (alert) { remember(alert.id); });
			localStorage.setItem(unreadKey, String(unread));
			primed = true;
			lastUnread = unread;
			return;
		}

		if (fresh[0]) {
			showAlert(fresh[0]);
			remember(fresh[0].id);
		}
		localStorage.setItem(unreadKey, String(unread));
		if (onInbox() && unread > lastUnread) {
			window.location.reload();
			return;
		}
		lastUnread = unread;
	}

	function poll() {
		if (document.hidden) return;
		fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (res) { return res.ok ? res.json() : null; })
			.then(apply)
			.catch(function () {});
	}

	poll();
	setInterval(poll, 18000);
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) poll();
	});
	root.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
	root.addEventListener('mouseleave', function () {
		hideTimer = setTimeout(function () { root.hidden = true; }, 5000);
	});
})();

(function () {
	function insertImage(area, url) {
		area.focus();
		document.execCommand('insertHTML', false, '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" style="max-width:220px;height:auto">');
	}

	function uploadImage(root, file, done) {
		const url = root.getAttribute('data-upload');
		const csrf = root.getAttribute('data-csrf') || '';
		if (!url || !file) return;
		const data = new FormData();
		data.append('_csrf', csrf);
		data.append('image', file, file.name || 'firma.png');
		fetch(url, { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (json && json.ok && json.url) done(json.url);
				else fallbackDataUrl(file, done);
			})
			.catch(function () { fallbackDataUrl(file, done); });
	}

	function fallbackDataUrl(file, done) {
		const reader = new FileReader();
		reader.onload = function () { done(String(reader.result || '')); };
		reader.readAsDataURL(file);
	}

	document.querySelectorAll('[data-rich-editor]').forEach(function (root) {
		const area = root.querySelector('.rich-area');
		const input = root.querySelector('[data-rich-input]');
		const form = root.closest('form');
		const fileInput = root.querySelector('[data-file]');
		if (!area || !input) return;

		root.querySelectorAll('[data-cmd]').forEach(function (btn) {
			btn.addEventListener('mousedown', function (event) { event.preventDefault(); });
			btn.addEventListener('click', function () {
				area.focus();
				document.execCommand(btn.getAttribute('data-cmd'), false, null);
			});
		});

		const size = root.querySelector('[data-fontsize]');
		if (size) {
			size.addEventListener('change', function () {
				if (!size.value) return;
				area.focus();
				document.execCommand('fontSize', false, size.value);
				size.value = '';
			});
		}

		const color = root.querySelector('[data-color]');
		if (color) {
			color.addEventListener('input', function () {
				area.focus();
				document.execCommand('foreColor', false, color.value);
			});
		}

		const pick = root.querySelector('[data-pick-image]');
		if (pick && fileInput) {
			pick.addEventListener('click', function () { fileInput.click(); });
			fileInput.addEventListener('change', function () {
				const file = fileInput.files && fileInput.files[0];
				if (file) uploadImage(root, file, function (url) { insertImage(area, url); });
				fileInput.value = '';
			});
		}

		area.addEventListener('paste', function (event) {
			const items = event.clipboardData ? event.clipboardData.items : null;
			if (!items) return;
			for (let i = 0; i < items.length; i += 1) {
				if (items[i].type.indexOf('image/') === 0) {
					event.preventDefault();
					const file = items[i].getAsFile();
					if (file) uploadImage(root, file, function (url) { insertImage(area, url); });
					return;
				}
			}
		});

		if (form) {
			form.addEventListener('submit', function () {
				input.value = area.innerHTML;
			});
		}
	});
})();
