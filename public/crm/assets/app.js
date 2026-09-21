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
	const chatBadge = document.getElementById('chat-badge');
	if (!url || !link || !titleEl || !textEl) return;

	const seenKey = 'mizo_seen_alerts';
	const unreadKey = 'mizo_unread_known';
	const chatKey = 'mizo_chat_known';
	let primed = false;
	let lastUnread = badge && !badge.hidden ? Number(badge.textContent || 0) : 0;
	let lastChat = chatBadge && !chatBadge.hidden ? Number(chatBadge.textContent || 0) : 0;
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
		localStorage.setItem(seenKey, JSON.stringify(ids.slice(-120)));
	}

	function updateBadge(el, count) {
		if (!el) return;
		if (count > 0) {
			el.hidden = false;
			el.textContent = String(count);
		} else {
			el.hidden = true;
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
		link.className = 'live-alert is-' + (alert.kind || 'change');
		link.href = alert.href || '#';
		titleEl.textContent = alert.title || '';
		textEl.textContent = alert.text || '';
		root.hidden = false;
		clearTimeout(hideTimer);
		hideTimer = setTimeout(function () {
			root.hidden = true;
		}, 14000);
	}

	function pathName() {
		return window.location.pathname.replace(/\/+$/, '');
	}

	function onInbox() {
		return /\/correo$/.test(pathName());
	}

	function onChatThread() {
		return /\/chat\/\d+$/.test(pathName());
	}

	function shouldReload(alert) {
		if (!alert || !alert.reload) return false;
		const path = pathName();
		if (alert.kind === 'mail' && onInbox()) return true;
		if (alert.quote_id && path.indexOf('/cotizaciones/' + alert.quote_id) !== -1) return true;
		if (alert.href && path.indexOf('/clientes/') !== -1 && String(alert.href).indexOf(path) !== -1) return true;
		if ((alert.kind === 'quote_edit' || alert.kind === 'change' || alert.kind === 'quote_ok' || alert.kind === 'quote_no')
			&& (/\/crm\/?$/.test(path) || /\/clientes$/.test(path) || path.endsWith('/crm'))) {
			return true;
		}
		return false;
	}

	function apply(data) {
		if (!data || !data.ok) return;
		const unread = Number(data.unread || 0);
		const chatUnread = Number(data.chat_unread || 0);
		const alerts = Array.isArray(data.alerts) ? data.alerts : [];
		updateBadge(badge, unread);
		updateBadge(chatBadge, chatUnread);
		updateQuotes(alerts);

		const known = seenIds();
		const fresh = alerts.filter(function (alert) {
			return known.indexOf(alert.id) === -1;
		});

		if (!primed) {
			const saved = Number(localStorage.getItem(unreadKey) || -1);
			const savedChat = Number(localStorage.getItem(chatKey) || -1);
			const priority = fresh.filter(function (alert) {
				return alert.kind !== 'mail' && alert.kind !== 'chat';
			});
			const mails = fresh.filter(function (alert) { return alert.kind === 'mail'; });
			const chats = fresh.filter(function (alert) { return alert.kind === 'chat'; });
			if (priority[0]) {
				showAlert(priority[0]);
				priority.forEach(function (alert) { remember(alert.id); });
			} else if (saved >= 0 && unread > saved && mails[0]) {
				showAlert(mails[0]);
			} else if (savedChat >= 0 && chatUnread > savedChat && chats[0]) {
				showAlert(chats[0]);
			}
			mails.forEach(function (alert) { remember(alert.id); });
			chats.forEach(function (alert) { remember(alert.id); });
			localStorage.setItem(unreadKey, String(unread));
			localStorage.setItem(chatKey, String(chatUnread));
			primed = true;
			lastUnread = unread;
			lastChat = chatUnread;
			return;
		}

		if (fresh[0]) {
			showAlert(fresh[0]);
			remember(fresh[0].id);
			if (shouldReload(fresh[0])) {
				window.location.reload();
				return;
			}
		}
		localStorage.setItem(unreadKey, String(unread));
		localStorage.setItem(chatKey, String(chatUnread));
		if (onInbox() && unread > lastUnread) {
			window.location.reload();
			return;
		}
		if (onChatThread() && chatUnread > lastChat) {
			lastChat = chatUnread;
			return;
		}
		lastUnread = unread;
		lastChat = chatUnread;
	}

	function poll() {
		if (document.hidden) return;
		fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (res) { return res.ok ? res.json() : null; })
			.then(apply)
			.catch(function () {});
	}

	poll();
	setInterval(poll, 10000);
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) poll();
	});
	root.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
	root.addEventListener('mouseleave', function () {
		hideTimer = setTimeout(function () { root.hidden = true; }, 5000);
	});
})();

(function () {
	const box = document.getElementById('chat-messages');
	if (!box) return;
	const pollUrl = box.getAttribute('data-poll');
	if (!pollUrl) return;
	let last = Number(box.getAttribute('data-last') || 0);

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function appendMessage(msg) {
		if (box.querySelector('[data-id="' + msg.id + '"]')) return;
		const start = box.querySelector('.chat-start');
		if (start) start.remove();
		const el = document.createElement('div');
		el.className = 'chat-bubble ' + (msg.from_me ? 'is-mine' : 'is-theirs');
		el.setAttribute('data-id', String(msg.id));
		el.innerHTML = '<div class="chat-bubble-body">' + escapeHtml(msg.body).replace(/\n/g, '<br>')
			+ '</div><time>' + escapeHtml(msg.created_at || '') + '</time>';
		box.appendChild(el);
		box.scrollTop = box.scrollHeight;
		last = Math.max(last, Number(msg.id) || 0);
	}

	function tick() {
		if (document.hidden) return;
		fetch(pollUrl + '?despues=' + last, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (res) { return res.ok ? res.json() : null; })
			.then(function (data) {
				if (!data || !data.ok || !Array.isArray(data.messages)) return;
				data.messages.forEach(appendMessage);
				const chatBadge = document.getElementById('chat-badge');
				if (chatBadge && typeof data.chat_unread === 'number') {
					if (data.chat_unread > 0) {
						chatBadge.hidden = false;
						chatBadge.textContent = String(data.chat_unread);
					} else {
						chatBadge.hidden = true;
					}
				}
			})
			.catch(function () {});
	}

	box.scrollTop = box.scrollHeight;
	setInterval(tick, 4000);
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) tick();
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
