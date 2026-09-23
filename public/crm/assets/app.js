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

	function onChatList() {
		return /\/chat$/.test(pathName());
	}

	function shouldReload(alert) {
		if (!alert || !alert.reload) return false;
		const path = pathName();
		if (alert.kind === 'mail' && onInbox()) return true;
		if (alert.quote_id && path.indexOf('/cotizaciones/' + alert.quote_id) !== -1) return true;
		if (alert.href && path.indexOf('/clientes/') !== -1 && String(alert.href).indexOf(path) !== -1) return true;
		if ((alert.kind === 'quote_edit' || alert.kind === 'change' || alert.kind === 'quote_ok' || alert.kind === 'quote_no')
			&& (/\/crm\/?$/.test(path) || /\/clientes$/.test(path) || path.endsWith('/crm') || path.indexOf('/clientes/') !== -1 || path.indexOf('/cotizaciones/') !== -1)) {
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
		if (onChatList() && chatUnread > lastChat) {
			window.location.reload();
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
	setInterval(poll, 4000);
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
	const form = document.querySelector('[data-chat-form]');
	const input = document.querySelector('[data-chat-input]');
	if (!box) return;
	const pollUrl = box.getAttribute('data-poll');
	if (!pollUrl) return;
	let last = Number(box.getAttribute('data-last') || 0);
	let sending = false;

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

	function updateChatBadge(count) {
		const chatBadge = document.getElementById('chat-badge');
		if (!chatBadge || typeof count !== 'number') return;
		if (count > 0) {
			chatBadge.hidden = false;
			chatBadge.textContent = String(count);
		} else {
			chatBadge.hidden = true;
		}
	}

	function tick() {
		if (document.hidden) return;
		fetch(pollUrl + '?despues=' + last, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (res) { return res.ok ? res.json() : null; })
			.then(function (data) {
				if (!data || !data.ok || !Array.isArray(data.messages)) return;
				data.messages.forEach(appendMessage);
				updateChatBadge(data.chat_unread);
			})
			.catch(function () {});
	}

	function sendNow() {
		if (!form || !input || sending) return;
		const body = String(input.value || '').trim();
		if (!body) return;
		sending = true;
		const data = new FormData(form);
		data.set('body', body);
		fetch(form.getAttribute('action'), {
			method: 'POST',
			body: data,
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'X-Requested-With': 'fetch',
			},
		})
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (json && json.ok && json.message) {
					appendMessage(json.message);
					input.value = '';
					input.focus();
					updateChatBadge(json.chat_unread);
				}
			})
			.catch(function () {
				form.submit();
			})
			.finally(function () {
				sending = false;
			});
	}

	if (input) {
		input.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter' || event.shiftKey) return;
			event.preventDefault();
			sendNow();
		});
		input.focus();
	}

	if (form) {
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			sendNow();
		});
	}

	const emojiToggle = document.getElementById('chat-emoji-toggle');
	const emojiPanel = document.getElementById('chat-emoji-panel');
	function insertEmoji(emoji) {
		if (!input) return;
		const start = input.selectionStart || 0;
		const end = input.selectionEnd || 0;
		const value = String(input.value || '');
		input.value = value.slice(0, start) + emoji + value.slice(end);
		const pos = start + emoji.length;
		input.focus();
		input.setSelectionRange(pos, pos);
	}
	if (emojiToggle && emojiPanel) {
		emojiToggle.addEventListener('click', function () {
			const open = emojiPanel.hasAttribute('hidden');
			if (open) {
				emojiPanel.removeAttribute('hidden');
				emojiToggle.classList.add('is-on');
			} else {
				emojiPanel.setAttribute('hidden', '');
				emojiToggle.classList.remove('is-on');
			}
			input && input.focus();
		});
		emojiPanel.querySelectorAll('[data-emoji]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				insertEmoji(btn.getAttribute('data-emoji') || '');
			});
		});
		document.addEventListener('click', function (event) {
			if (!emojiPanel || emojiPanel.hasAttribute('hidden')) return;
			const t = event.target;
			if (emojiPanel.contains(t) || emojiToggle.contains(t)) return;
			emojiPanel.setAttribute('hidden', '');
			emojiToggle.classList.remove('is-on');
		});
	}

	box.scrollTop = box.scrollHeight;
	setInterval(tick, 2000);
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) tick();
	});
})();

(function () {
	function insertImage(area, url) {
		area.focus();
		document.execCommand('insertHTML', false, '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" style="max-width:420px;width:100%;height:auto;display:block;">');
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

(function () {
	document.querySelectorAll("[data-check-all]").forEach(function (master) {
		master.addEventListener("change", function () {
			document.querySelectorAll("input[form=\"bulk-form\"][name=\"ids[]\"]").forEach(function (box) {
				box.checked = master.checked;
			});
		});
	});
})();

(function () {
	const forms = document.querySelectorAll("[data-contacts-form]");
	if (!forms.length) return;
	forms.forEach(function (form) {
		const rows = form.querySelector("[data-contact-rows]");
		const addBtn = form.querySelector("[data-add-contact]");
		if (!rows || !addBtn) return;
		function bindRemove(row) {
			const btn = row.querySelector("[data-remove-contact]");
			if (!btn) return;
			btn.hidden = rows.querySelectorAll("[data-contact-row]").length <= 1;
			btn.onclick = function () {
				if (rows.querySelectorAll("[data-contact-row]").length <= 1) return;
				row.remove();
				rows.querySelectorAll("[data-contact-row]").forEach(function (r) {
					const b = r.querySelector("[data-remove-contact]");
					if (b) b.hidden = rows.querySelectorAll("[data-contact-row]").length <= 1;
				});
				form.dispatchEvent(new Event("input", { bubbles: true }));
			};
		}
		rows.querySelectorAll("[data-contact-row]").forEach(bindRemove);
		addBtn.addEventListener("click", function () {
			const first = rows.querySelector("[data-contact-row]");
			if (!first) return;
			const clone = first.cloneNode(true);
			clone.querySelectorAll("input").forEach(function (input) {
				if (input.name === "contact_id[]") input.value = "";
				else input.value = "";
			});
			rows.appendChild(clone);
			rows.querySelectorAll("[data-contact-row]").forEach(bindRemove);
			form.dispatchEvent(new Event("input", { bubbles: true }));
		});
	});
})();

(function () {
	const form = document.querySelector("[data-client-inline]");
	if (!form) return;
	const lockBtn = form.querySelector("[data-lock]");
	const saveBtn = form.querySelector("[data-save]");
	function fields() {
		return form.querySelectorAll("input, select, textarea");
	}
	function applyLock() {
		const locked = form.classList.contains("is-locked");
		fields().forEach(function (el) {
			if (el.type === "hidden") return;
			if (el.tagName === "SELECT") el.disabled = locked;
			else el.readOnly = locked;
		});
		if (lockBtn) lockBtn.textContent = locked ? "Desbloquear datos" : "Bloquear datos";
	}
	function snapshot() {
		fields().forEach(function (el) { if (el.tagName === "SELECT") el.disabled = false; });
		const value = new URLSearchParams(new FormData(form)).toString();
		applyLock();
		return value;
	}
	const initial = snapshot();
	function paint() {
		if (!saveBtn) return;
		saveBtn.hidden = snapshot() === initial;
	}
	applyLock();
	if (lockBtn) {
		lockBtn.addEventListener("click", function () {
			form.classList.toggle("is-locked");
			applyLock();
			paint();
		});
	}
	form.addEventListener("input", paint);
	form.addEventListener("change", paint);
	form.addEventListener("submit", function () {
		fields().forEach(function (el) { el.disabled = false; el.readOnly = false; });
	});
})();

(function () {
	const root = document.querySelector("[data-compose]");
	if (!root) return;
	const clientSelect = root.querySelector("[data-pick-client]");
	const contactsBox = root.querySelector("[data-pick-contacts]");
	const toField = root.querySelector("[data-to-field]");
	let clientInput = root.querySelector("input[name=\"client_id\"]");
	if (!clientInput) {
		clientInput = document.createElement("input");
		clientInput.type = "hidden";
		clientInput.name = "client_id";
		root.appendChild(clientInput);
	}
	function syncTo() {
		if (!toField || !contactsBox) return;
		const emails = [];
		contactsBox.querySelectorAll("[data-contact-email]:checked").forEach(function (box) {
			emails.push(box.value);
		});
		toField.value = emails.join(", ");
	}
	function renderContacts(list) {
		if (!contactsBox) return;
		contactsBox.innerHTML = "";
		if (!list || !list.length) {
			contactsBox.innerHTML = "<p class=\"muted\">Este cliente no tiene contactos con correo.</p>";
			return;
		}
		list.forEach(function (c) {
			if (!c.email) return;
			const label = document.createElement("label");
			label.className = "check-pill";
			label.innerHTML = "<span><input type=\"checkbox\" data-contact-email value=\"" + String(c.email).replace(/"/g, "&quot;") + "\" checked> " +
				(c.name || c.email).replace(/</g, "&lt;") + "</span><small>" + String(c.email).replace(/</g, "&lt;") + "</small>";
			contactsBox.appendChild(label);
		});
		contactsBox.querySelectorAll("[data-contact-email]").forEach(function (box) {
			box.addEventListener("change", syncTo);
		});
		syncTo();
	}
	if (clientSelect) {
		clientSelect.addEventListener("change", function () {
			const opt = clientSelect.options[clientSelect.selectedIndex];
			clientInput.value = clientSelect.value || "";
			let list = [];
			try { list = JSON.parse(opt.getAttribute("data-contacts") || "[]"); } catch (e) { list = []; }
			renderContacts(list);
		});
	}
	if (contactsBox) {
		contactsBox.querySelectorAll("[data-contact-email]").forEach(function (box) {
			box.addEventListener("change", syncTo);
		});
	}
})();

(function () {
	const PLACE_KEY = "mizo-crm-places";
	const base = (document.body && document.body.getAttribute("data-crm-base")) || "/crm";

	function loadPlaces() {
		try {
			return JSON.parse(localStorage.getItem(PLACE_KEY) || "{}") || {};
		} catch (e) {
			return {};
		}
	}

	function savePlaces(places) {
		try {
			localStorage.setItem(PLACE_KEY, JSON.stringify(places));
		} catch (e) {}
	}

	function internalPath(pathname) {
		pathname = pathname || "/";
		if (pathname === base || pathname === base + "/") return "/";
		if (pathname.indexOf(base + "/") === 0) {
			return pathname.slice(base.length) || "/";
		}
		return pathname;
	}

	function sectionOf(pathname) {
		const path = internalPath(pathname);
		if (path.indexOf("/correo") === 0) return "correo";
		if (path.indexOf("/chat") === 0) return "chat";
		if (path.indexOf("/equipo") === 0) return "equipo";
		if (path === "/" || path.indexOf("/tablero") === 0) return "tablero";
		if (path.indexOf("/clientes") === 0 || path.indexOf("/cotizaciones") === 0) return "clientes";
		return null;
	}

	function shouldRemember(pathname) {
		const path = internalPath(pathname);
		if (!sectionOf(pathname)) return false;
		if (path === "/login" || path === "/setup" || path === "/logout" || path === "/avisos") return false;
		if (path.indexOf("/correo/adjunto/") === 0) return false;
		if (/^\/chat\/\d+\/mensajes$/.test(path)) return false;
		return true;
	}

	function isTransient(pathname) {
		const path = internalPath(pathname);
		return (
			path === "/correo/nuevo" ||
			path === "/clientes/nuevo" ||
			/\/cotizacion$/.test(path) ||
			path === "/correo/cuenta"
		);
	}

	function fullPlace() {
		return location.pathname + location.search;
	}

	function rememberHere() {
		if (!shouldRemember(location.pathname)) return;
		if (isTransient(location.pathname)) return;
		const section = sectionOf(location.pathname);
		if (!section) return;
		const places = loadPlaces();
		places[section] = fullPlace();
		savePlaces(places);
	}

	function scrollStoreKey(id) {
		return "mizo-crm-scroll:" + fullPlace() + "#" + id;
	}

	function saveScrolls() {
		if (!shouldRemember(location.pathname)) return;
		try {
			sessionStorage.setItem(scrollStoreKey("window"), String(window.scrollY || 0));
			document.querySelectorAll("[data-crm-scroll]").forEach(function (el) {
				const id = el.getAttribute("data-crm-scroll") || "pane";
				sessionStorage.setItem(scrollStoreKey(id), String(el.scrollTop || 0));
			});
		} catch (e) {}
	}

	function restoreScrolls() {
		try {
			const y = sessionStorage.getItem(scrollStoreKey("window"));
			if (y) window.scrollTo(0, parseInt(y, 10) || 0);
			document.querySelectorAll("[data-crm-scroll]").forEach(function (el) {
				const id = el.getAttribute("data-crm-scroll") || "pane";
				const top = sessionStorage.getItem(scrollStoreKey(id));
				if (top) el.scrollTop = parseInt(top, 10) || 0;
			});
		} catch (e) {}
	}

	function refreshNav() {
		const places = loadPlaces();
		const current = sectionOf(location.pathname);
		document.querySelectorAll("[data-crm-section]").forEach(function (a) {
			const section = a.getAttribute("data-crm-section");
			const home = a.getAttribute("data-crm-home") || a.getAttribute("href");
			if (!section || !home) return;
			if (a.hasAttribute("data-crm-fixed")) {
				a.setAttribute("href", home);
				a.removeAttribute("title");
				return;
			}
			if (current === section) {
				a.setAttribute("href", home);
				a.title = "Ir al inicio de esta secciÃÂÃÂÃÂÃÂÃÂÃÂÃÂÃÂÃÂÃÂÃÂÃÂÃÂÃÂÃÂÃÂ³n";
			} else if (places[section]) {
				a.setAttribute("href", places[section]);
				a.title = "Volver a donde lo dejaste";
			} else {
				a.setAttribute("href", home);
				a.removeAttribute("title");
			}
		});
	}

	rememberHere();
	refreshNav();
	requestAnimationFrame(function () {
		requestAnimationFrame(restoreScrolls);
	});

	window.addEventListener("pagehide", saveScrolls);
	document.addEventListener("visibilitychange", function () {
		if (document.visibilityState === "hidden") saveScrolls();
	});
	let scrollTimer = null;
	window.addEventListener("scroll", function () {
		clearTimeout(scrollTimer);
		scrollTimer = setTimeout(saveScrolls, 200);
	}, { passive: true });
	document.querySelectorAll("[data-crm-scroll]").forEach(function (el) {
		el.addEventListener("scroll", function () {
			clearTimeout(scrollTimer);
			scrollTimer = setTimeout(saveScrolls, 200);
		}, { passive: true });
	});

	const HIST_KEY = "mizo-crm-hist";
	const HIST_NAV = "mizo-crm-hist-nav";
	function loadHist() {
		try { return JSON.parse(sessionStorage.getItem(HIST_KEY) || "{}") || {}; } catch (e) { return {}; }
	}
	function saveHist(data) {
		try { sessionStorage.setItem(HIST_KEY, JSON.stringify(data)); } catch (e) {}
	}
	function histBucket() {
		const section = sectionOf(location.pathname);
		if (!section) return null;
		const all = loadHist();
		if (!all[section]) all[section] = { stack: [], index: -1 };
		return { all: all, section: section, bucket: all[section] };
	}
	function paintHist() {
		const back = document.querySelector("[data-hist='back']");
		const forward = document.querySelector("[data-hist='forward']");
		if (!back || !forward) return;
		const found = histBucket();
		const index = found ? found.bucket.index : -1;
		const size = found ? found.bucket.stack.length : 0;
		back.disabled = index <= 0;
		forward.disabled = index < 0 || index >= size - 1;
	}
	function recordHist() {
		const found = histBucket();
		if (!found) return;
		const url = location.pathname + location.search;
		let marked = "";
		try { marked = sessionStorage.getItem(HIST_NAV) || ""; } catch (e) {}
		if (marked === url) {
			try { sessionStorage.removeItem(HIST_NAV); } catch (e) {}
			paintHist();
			return;
		}
		const bucket = found.bucket;
		if (bucket.stack[bucket.index] === url) {
			paintHist();
			return;
		}
		bucket.stack = bucket.stack.slice(0, bucket.index + 1);
		bucket.stack.push(url);
		if (bucket.stack.length > 40) {
			bucket.stack.shift();
		}
		bucket.index = bucket.stack.length - 1;
		found.all[found.section] = bucket;
		saveHist(found.all);
		paintHist();
	}
	function stepHist(delta) {
		const found = histBucket();
		if (!found) return;
		const next = found.bucket.index + delta;
		if (next < 0 || next >= found.bucket.stack.length) return;
		found.bucket.index = next;
		found.all[found.section] = found.bucket;
		saveHist(found.all);
		const url = found.bucket.stack[next];
		try { sessionStorage.setItem(HIST_NAV, url); } catch (e) {}
		location.href = url;
	}
	recordHist();
	document.querySelectorAll("[data-hist]").forEach(function (btn) {
		btn.addEventListener("click", function () {
			stepHist(btn.getAttribute("data-hist") === "forward" ? 1 : -1);
		});
	});
})();

(function () {
	const KEY = "mizo-crm-rail";
	const WIDTH_KEY = "mizo-crm-rail-w";
	function loadState() {
		try { return JSON.parse(localStorage.getItem(KEY) || "{}") || {}; } catch (e) { return {}; }
	}
	function saveState(state) {
		try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
	}
	const state = loadState();

	document.querySelectorAll("[data-rail-panel]").forEach(function (panel) {
		const id = panel.getAttribute("data-rail-panel");
		if (state[id] === false) panel.open = false;
		if (state[id] === true) panel.open = true;
		panel.addEventListener("toggle", function () {
			const next = loadState();
			next[id] = panel.open;
			saveState(next);
			syncDock();
		});
	});

	function syncDock() {
		let any = false;
		document.querySelectorAll("[data-panel-toggle]").forEach(function (btn) {
			const id = btn.getAttribute("data-panel-toggle");
			const panel = document.querySelector('[data-rail-panel="' + id + '"]');
			const open = !!(panel && panel.open);
			btn.classList.toggle("is-on", open);
			btn.setAttribute("aria-pressed", open ? "true" : "false");
			if (open) any = true;
		});
		document.body.classList.toggle("is-rail-empty", !any && !!document.querySelector("[data-crm-rail]"));
	}

	document.querySelectorAll("[data-panel-toggle]").forEach(function (btn) {
		btn.addEventListener("click", function () {
			const id = btn.getAttribute("data-panel-toggle");
			const panel = document.querySelector('[data-rail-panel="' + id + '"]');
			if (!panel) return;
			panel.open = !panel.open;
		});
	});
	syncDock();

	const q = document.querySelector("[data-rail-client-q]");
	const empty = document.querySelector("[data-rail-client-empty]");
	if (q) {
		q.addEventListener("input", function () {
			const term = (q.value || "").trim().toLowerCase();
			let shown = 0;
			document.querySelectorAll("[data-rail-client]").forEach(function (row) {
				const hay = (row.getAttribute("data-hay") || "").toLowerCase();
				const ok = term === "" || hay.indexOf(term) !== -1;
				row.hidden = !ok;
				if (ok) shown++;
			});
			if (empty) empty.hidden = shown > 0 || term === "";
		});
	}

	const handle = document.querySelector("[data-crm-split]");
	function applyWidth(px) {
		const min = 240;
		const max = Math.min(560, Math.round(window.innerWidth * 0.5));
		const next = Math.max(min, Math.min(max, px));
		document.documentElement.style.setProperty("--rail-w", next + "px");
		return next;
	}
	try {
		const saved = parseInt(localStorage.getItem(WIDTH_KEY) || "", 10);
		if (saved) applyWidth(saved);
	} catch (e) {}
	if (handle) {
		handle.addEventListener("pointerdown", function (event) {
			event.preventDefault();
			handle.setPointerCapture(event.pointerId);
			document.body.classList.add("is-rail-dragging");
			function move(ev) {
				const width = window.innerWidth - ev.clientX;
				applyWidth(width);
			}
			function up(ev) {
				handle.releasePointerCapture(ev.pointerId);
				document.body.classList.remove("is-rail-dragging");
				handle.removeEventListener("pointermove", move);
				handle.removeEventListener("pointerup", up);
				const current = getComputedStyle(document.documentElement).getPropertyValue("--rail-w");
				const n = parseInt(current, 10);
				if (n) {
					try { localStorage.setItem(WIDTH_KEY, String(n)); } catch (e) {}
				}
			}
			handle.addEventListener("pointermove", move);
			handle.addEventListener("pointerup", up);
		});
	}

	const chat = document.querySelector("[data-rail-chat]");
	if (!chat) return;
	const base = chat.getAttribute("data-chat-base") || "";
	const peers = chat.querySelector("[data-rail-peers]");
	const thread = chat.querySelector("[data-rail-thread]");
	const msgs = chat.querySelector("[data-rail-msgs]");
	const form = chat.querySelector("[data-rail-chat-form]");
	const input = chat.querySelector("[data-rail-chat-input]");
	const nameEl = chat.querySelector("[data-rail-thread-name]");
	if (!peers || !thread || !msgs || !form) return;

	let peerId = 0;
	let last = 0;
	let sending = false;
	let timer = null;

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function appendMessage(msg) {
		if (!msg || msgs.querySelector('[data-id="' + msg.id + '"]')) return;
		const el = document.createElement("div");
		el.className = "chat-bubble " + (msg.from_me ? "is-mine" : "is-theirs");
		el.setAttribute("data-id", String(msg.id));
		el.innerHTML = '<div class="chat-bubble-body">' + escapeHtml(msg.body).replace(/\n/g, "<br>")
			+ "</div><time>" + escapeHtml(msg.created_at || "") + "</time>";
		msgs.appendChild(el);
		msgs.scrollTop = msgs.scrollHeight;
		last = Math.max(last, Number(msg.id) || 0);
	}

	function tick() {
		if (!peerId || document.hidden) return;
		const requested = peerId;
		const after = last;
		fetch(base + requested + "/mensajes?despues=" + after, {
			credentials: "same-origin",
			headers: { Accept: "application/json" },
		})
			.then(function (res) { return res.ok ? res.json() : null; })
			.then(function (data) {
				if (requested !== peerId) return;
				if (!data || !data.ok || !Array.isArray(data.messages)) return;
				data.messages.forEach(appendMessage);
			})
			.catch(function () {});
	}

	function openPeer(id, name) {
		peerId = id;
		last = 0;
		msgs.innerHTML = "";
		if (nameEl) nameEl.textContent = name || "Chat";
		peers.hidden = true;
		thread.hidden = false;
		try { sessionStorage.setItem("mizo-crm-rail-peer", String(id)); } catch (e) {}
		tick();
		if (timer) clearInterval(timer);
		timer = setInterval(tick, 2500);
		if (input) input.focus();
	}

	function closePeer() {
		peerId = 0;
		thread.hidden = true;
		peers.hidden = false;
		if (timer) clearInterval(timer);
		try { sessionStorage.removeItem("mizo-crm-rail-peer"); } catch (e) {}
	}

	peers.querySelectorAll("[data-rail-peer]").forEach(function (btn) {
		btn.addEventListener("click", function () {
			openPeer(Number(btn.getAttribute("data-rail-peer")), btn.getAttribute("data-rail-peer-name") || "");
		});
	});
	const back = chat.querySelector("[data-rail-back]");
	if (back) back.addEventListener("click", closePeer);

	function sendNow() {
		if (!peerId || !input || sending) return;
		const body = String(input.value || "").trim();
		if (!body) return;
		sending = true;
		const data = new FormData(form);
		data.set("body", body);
		fetch(base + peerId, {
			method: "POST",
			body: data,
			credentials: "same-origin",
			headers: { Accept: "application/json", "X-Requested-With": "fetch" },
		})
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (json && json.ok && json.message) {
					appendMessage(json.message);
					input.value = "";
					input.focus();
				}
			})
			.catch(function () {})
			.finally(function () { sending = false; });
	}

	if (input) {
		input.addEventListener("keydown", function (event) {
			if (event.key !== "Enter" || event.shiftKey) return;
			event.preventDefault();
			sendNow();
		});
	}
	form.addEventListener("submit", function (event) {
		event.preventDefault();
		sendNow();
	});

	let restore = "";
	try { restore = sessionStorage.getItem("mizo-crm-rail-peer") || ""; } catch (e) {}
	const buttons = peers.querySelectorAll("[data-rail-peer]");
	if (buttons.length === 1) {
		openPeer(Number(buttons[0].getAttribute("data-rail-peer")), buttons[0].getAttribute("data-rail-peer-name") || "");
	} else if (restore) {
		const match = peers.querySelector('[data-rail-peer="' + restore + '"]');
		if (match) openPeer(Number(restore), match.getAttribute("data-rail-peer-name") || "");
	}
})();

(function () {
	const gmail = document.querySelector("[data-gmail]");
	if (!gmail) return;
	const FULL_KEY = "mizo-crm-mail-full";

	function setFull(on) {
		gmail.classList.toggle("is-mail-full", on);
		document.body.classList.toggle("is-mail-full-active", on);
		document.querySelectorAll("[data-mail-full]").forEach(function (btn) {
			btn.textContent = on ? "Salir de pantalla completa" : "Pantalla completa";
		});
		try { sessionStorage.setItem(FULL_KEY, on ? "1" : "0"); } catch (e) {}
		if (on) {
			const url = new URL(location.href);
			url.searchParams.set("full", "1");
			history.replaceState(null, "", url.toString());
		} else {
			const url = new URL(location.href);
			url.searchParams.delete("full");
			history.replaceState(null, "", url.toString());
		}
	}

	let wantFull = false;
	try { wantFull = sessionStorage.getItem(FULL_KEY) === "1"; } catch (e) {}
	if (new URL(location.href).searchParams.get("full") === "1") wantFull = true;
	if (wantFull && gmail.classList.contains("has-open")) setFull(true);

	document.querySelectorAll("[data-mail-full]").forEach(function (btn) {
		btn.addEventListener("click", function () {
			setFull(!gmail.classList.contains("is-mail-full"));
		});
	});
})();

(function () {
	const board = document.querySelector("[data-board]");
	if (!board) return;
	const base = (document.body.getAttribute("data-crm-base") || "/crm").replace(/\/$/, "");
	const moveUrl = board.getAttribute("data-move");
	const csrf = board.getAttribute("data-csrf") || "";
	const drawer = document.querySelector("[data-drawer]");
	const drawerBody = document.querySelector("[data-drawer-body]");
	const drawerTitle = document.querySelector("[data-drawer-title]");
	const drawerKicker = document.querySelector("[data-drawer-kicker]");
	let origin = null;
	let dragged = null;
	let moved = false;

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function recount() {
		board.querySelectorAll("[data-stage]").forEach(function (col) {
			const visible = col.querySelectorAll(".kb-card:not([hidden])").length;
			const badge = col.querySelector("[data-kb-count]");
			if (badge) badge.textContent = String(visible);
		});
	}

	function applyFilters() {
		const q = (board.querySelector("[data-kb-q]") || {}).value || "";
		const term = q.trim().toLowerCase();
		const service = (board.querySelector("[data-kb-service]") || {}).value || "";
		const owner = (board.querySelector("[data-kb-owner]") || {}).value || "";
		const priority = (board.querySelector("[data-kb-priority]") || {}).value || "";
		const when = (board.querySelector("[data-kb-when]") || {}).value || "";
		board.querySelectorAll(".kb-card").forEach(function (card) {
			const hay = (card.getAttribute("data-hay") || "").toLowerCase();
			const age = Number(card.getAttribute("data-age") || 99);
			let ok = true;
			if (term && hay.indexOf(term) === -1) ok = false;
			if (service && card.getAttribute("data-service") !== service) ok = false;
			if (owner && card.getAttribute("data-owner") !== owner) ok = false;
			if (priority && card.getAttribute("data-priority") !== priority) ok = false;
			if (when === "7" && age > 7) ok = false;
			if (when === "30" && age > 30) ok = false;
			if (when === "stale" && age < 7) ok = false;
			card.hidden = !ok;
		});
		recount();
	}

	board.querySelectorAll("[data-kb-q],[data-kb-service],[data-kb-owner],[data-kb-priority],[data-kb-when]").forEach(function (el) {
		el.addEventListener("input", applyFilters);
		el.addEventListener("change", applyFilters);
	});

	function bindCard(card) {
		card.addEventListener("dragstart", function (event) {
			dragged = card;
			origin = card.parentElement;
			moved = false;
			card.classList.add("is-dragging");
			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = "move";
				event.dataTransfer.setData("text/plain", card.getAttribute("data-client") || "");
			}
		});
		card.addEventListener("drag", function () { moved = true; });
		card.addEventListener("dragend", function () {
			card.classList.remove("is-dragging");
			board.querySelectorAll(".kb-drop").forEach(function (zone) { zone.classList.remove("is-over"); });
		});
		card.addEventListener("click", function () {
			if (moved) {
				moved = false;
				return;
			}
			openDrawer(card);
		});
	}

	board.querySelectorAll(".kb-card").forEach(bindCard);

	board.querySelectorAll("[data-drop]").forEach(function (zone) {
		zone.addEventListener("dragover", function (event) {
			event.preventDefault();
			zone.classList.add("is-over");
		});
		zone.addEventListener("dragleave", function () { zone.classList.remove("is-over"); });
		zone.addEventListener("drop", function (event) {
			event.preventDefault();
			zone.classList.remove("is-over");
			if (!dragged) return;
			const stage = zone.getAttribute("data-drop");
			const from = origin;
			zone.appendChild(dragged);
			recount();
			const data = new FormData();
			data.set("_csrf", csrf);
			data.set("deal_id", dragged.getAttribute("data-deal") || "");
			data.set("stage", stage || "");
			const card = dragged;
			fetch(moveUrl, {
				method: "POST",
				body: data,
				credentials: "same-origin",
				headers: { Accept: "application/json", "X-Requested-With": "fetch" },
			})
				.then(function (res) { return res.json(); })
				.then(function (json) {
					if (!json || !json.ok) throw new Error("fail");
					if (json.deal_id) card.setAttribute("data-deal", String(json.deal_id));
				})
				.catch(function () {
					if (from) from.appendChild(card);
					recount();
				});
		});
	});

	const mailPop = document.querySelector("[data-mail-pop]");
	const mailForm = document.querySelector("[data-mail-pop-form]");
	const mailTo = mailForm ? mailForm.querySelector("[data-mail-to]") : null;
	const mailWho = mailForm ? mailForm.querySelector("[data-mail-who]") : null;
	const mailSubject = mailForm ? mailForm.querySelector("[data-mail-subject]") : null;
	const mailBody = mailForm ? mailForm.querySelector("textarea") : null;
	const mailStatus = mailForm ? mailForm.querySelector("[data-mail-status]") : null;
	let mailClient = "";

	function closeMail() {
		if (mailPop) mailPop.hidden = true;
	}
	document.querySelectorAll("[data-mail-close]").forEach(function (btn) {
		btn.addEventListener("click", closeMail);
	});
	if (mailForm) {
		mailForm.addEventListener("submit", function (event) {
			event.preventDefault();
			const body = new FormData(mailForm);
			body.set("_csrf", csrf);
			body.set("client_id", mailClient || "");
			if (mailStatus) mailStatus.textContent = "EnviandoÃÂÃÂ¢ÃÂÃÂÃÂÃÂ¦";
			fetch(base + "/correo", {
				method: "POST",
				body: body,
				credentials: "same-origin",
				headers: { Accept: "application/json", "X-Requested-With": "fetch" },
			})
				.then(function (res) { return res.json(); })
				.then(function (json) {
					if (!json || !json.ok) {
						if (mailStatus) mailStatus.textContent = (json && json.message) || "No se pudo enviar.";
						return;
					}
					if (mailStatus) mailStatus.textContent = json.message || "Correo enviado.";
					if (mailBody) mailBody.value = "";
				})
				.catch(function () {
					if (mailStatus) mailStatus.textContent = "No se pudo enviar.";
				});
		});
	}

	function closeDrawer() {
		closeMail();
		if (drawer) drawer.hidden = true;
	}
	document.querySelectorAll("[data-drawer-close]").forEach(function (el) {
		el.addEventListener("click", closeDrawer);
	});
	document.addEventListener("keydown", function (event) {
		if (event.key !== "Escape") return;
		if (mailPop && !mailPop.hidden) {
			closeMail();
			return;
		}
		closeDrawer();
	});

	function contactLine(label, value) {
		return "<div><dt>" + label + "</dt><dd>" + (value || "ÃÂÃÂ¢ÃÂÃÂÃÂÃÂ") + "</dd></div>";
	}

	function paintPerson(person) {
		const box = drawerBody.querySelector("[data-person]");
		const mailBtn = drawerBody.querySelector("[data-open-mail]");
		if (!box) return;
		if (!person) {
			box.innerHTML = "<p class=\"muted\">Elige quiÃÂÃÂÃÂÃÂ©n estÃÂÃÂÃÂÃÂ¡ a cargo.</p>";
			if (mailBtn) mailBtn.hidden = true;
			return;
		}
		const phone = person.phone
			? '<a href="tel:' + escapeHtml(person.phone) + '">' + escapeHtml(person.phone) + "</a>"
			: "ÃÂÃÂ¢ÃÂÃÂÃÂÃÂ";
		box.innerHTML = ""
			+ contactLine("Nombre", escapeHtml(person.name || "Contacto"))
			+ contactLine("Cargo", escapeHtml(person.title || "ÃÂÃÂ¢ÃÂÃÂÃÂÃÂ"))
			+ contactLine("TelÃÂÃÂÃÂÃÂ©fono", phone)
			+ contactLine("Correo", person.email ? escapeHtml(person.email) : "ÃÂÃÂ¢ÃÂÃÂÃÂÃÂ");
		if (mailBtn) {
			mailBtn.hidden = false;
			mailBtn.disabled = !person.email;
			mailBtn.textContent = person.email ? "Enviar correo" : "Sin correo";
		}
	}

	function contactPicker(dealId, contacts) {
		if (!contacts.length) {
			return '<section class="kb-block"><h3>Contacto a cargo</h3><p class="muted">Este cliente no tiene contactos. AgrÃÂÃÂÃÂÃÂ©galos en sus datos.</p></section>';
		}
		const options = contacts.map(function (person) {
			return '<option value="' + person.id + '"' + (person.on ? " selected" : "") + ">" + escapeHtml(person.name || "Contacto") + "</option>";
		}).join("");
		return '<section class="kb-block"><h3>Contacto a cargo</h3>'
			+ '<form data-charge-form action="' + base + "/proyectos/" + dealId + '/contactos">'
			+ '<select name="contact_id" data-charge>'
			+ '<option value="">Sin contacto a cargo</option>' + options + "</select></form>"
			+ '<dl class="kb-person" data-person></dl>'
			+ '<button type="button" class="kb-mail-btn" data-open-mail hidden>Enviar correo</button></section>';
	}

	function renderDrawer(card, data) {
		const client = data.client || {};
		const deal = data.deal || {};
		const id = deal.id;
		if (drawerTitle) drawerTitle.textContent = deal.title || "Proyecto";
		if (drawerKicker) drawerKicker.textContent = (client.name || "Cliente") + " ÃÂÃÂÃÂÃÂ· " + (deal.stage_label || "Prospecto");
		const notes = (data.notes || []).map(function (note) {
			const prefix = note.type === "recordatorio" ? "Recordatorio: " : "";
			return "<li><p>" + escapeHtml(prefix + note.message) + "</p><small>" + escapeHtml(note.who) + " ÃÂÃÂÃÂÃÂ· " + escapeHtml(note.when) + "</small></li>";
		}).join("");
		const quotes = (data.quotes || []).map(function (quote) {
			return '<li><a href="' + base + "/cotizaciones/" + quote.id + '">' + escapeHtml(quote.number) + "</a>"
				+ "<small>" + escapeHtml(quote.status) + "</small></li>";
		}).join("");
		drawerBody.innerHTML = ''
			+ '<p class="muted">' + escapeHtml(deal.service_label || "") + "</p>"
			+ contactPicker(id, data.contacts || [])
			+ '<section class="kb-block"><h3>Cotizaciones de este proyecto</h3><ul class="kb-notes">' + (quotes || "<li><p>Sin cotizaciones todavÃÂÃÂÃÂÃÂ­a.</p></li>") + "</ul>"
			+ '<div class="kb-actions"><a class="is-primary" href="' + base + "/proyectos/" + id + '/cotizacion">Nueva cotizaciÃÂÃÂÃÂÃÂ³n</a>'
			+ '<form method="post" action="' + base + "/proyectos/" + id + '/eliminar" onsubmit="return confirm(\'ÃÂÃÂÃÂÃÂ¿Eliminar este proyecto? Se borran sus cotizaciones y notas. El cliente se mantiene.\');">'
			+ '<input type="hidden" name="_csrf" value="' + escapeHtml(csrf) + '">'
			+ '<input type="hidden" name="volver" value="tablero">'
			+ '<button type="submit" class="is-danger">Eliminar proyecto</button></form></div></section>'
			+ '<section class="kb-block"><h3>Notas del proyecto</h3><ul class="kb-notes" data-project-notes>' + (notes || "<li><p>Sin notas de este proyecto.</p></li>") + "</ul>"
			+ '<form class="kb-note-form" data-note-form><textarea name="message" required placeholder="Nota de este proyecto"></textarea><button type="submit">Guardar nota</button></form></section>';

		const people = data.contacts || [];
		const charge = drawerBody.querySelector("[data-charge]");
		function selectedPerson() {
			if (!charge) return null;
			const picked = people.filter(function (person) { return String(person.id) === charge.value; })[0];
			return picked || null;
		}
		paintPerson(selectedPerson());
		if (charge) {
			charge.addEventListener("change", function () {
				paintPerson(selectedPerson());
				const body = new FormData();
				body.set("_csrf", csrf);
				body.set("contact_id", charge.value || "");
				body.set("volver", "tablero");
				fetch(base + "/proyectos/" + id + "/contactos", {
					method: "POST",
					body: body,
					credentials: "same-origin",
					headers: { "X-Requested-With": "fetch" },
				});
			});
		}
		const openMail = drawerBody.querySelector("[data-open-mail]");
		if (openMail) {
			openMail.addEventListener("click", function () {
				const person = selectedPerson();
				if (!person || !person.email || !mailPop || !mailForm) return;
				mailTo.value = person.email;
				mailWho.textContent = (person.name || "Contacto") + (person.phone ? " ÃÂÃÂÃÂÃÂ· " + person.phone : "");
				mailSubject.value = deal.title ? deal.title : "";
				mailBody.value = "";
				mailStatus.textContent = "";
				mailClient = client.id || "";
				mailPop.hidden = false;
				mailBody.focus();
			});
		}

		const form = drawerBody.querySelector("[data-note-form]");
		if (form) {
			form.addEventListener("submit", function (event) {
				event.preventDefault();
				const area = form.querySelector("textarea");
				const body = new FormData();
				body.set("_csrf", csrf);
				body.set("message", area.value || "");
				fetch(base + "/tablero/proyecto/" + id + "/nota", {
					method: "POST",
					body: body,
					credentials: "same-origin",
					headers: { Accept: "application/json", "X-Requested-With": "fetch" },
				})
					.then(function (res) { return res.json(); })
					.then(function (json) {
						if (!json || !json.ok) return;
						const list = drawerBody.querySelector("[data-project-notes]");
						const li = document.createElement("li");
						li.innerHTML = "<p>" + escapeHtml(json.note.message) + "</p><small>" + escapeHtml(json.note.who) + " ÃÂÃÂÃÂÃÂ· " + escapeHtml(json.note.when) + "</small>";
						const empty = list.querySelector("li");
						if (empty && empty.textContent.indexOf("Sin notas") === 0) empty.remove();
						list.prepend(li);
						const snippet = card.querySelector(".kb-snippet");
						if (snippet) snippet.textContent = json.note.message;
						area.value = "";
					});
			});
		}
	}

	function openDrawer(card) {
		if (!drawer || !drawerBody) return;
		drawer.hidden = false;
		drawerBody.innerHTML = '<p class="muted">CargandoÃÂÃÂ¢ÃÂÃÂÃÂÃÂ¦</p>';
		fetch(card.getAttribute("data-detail"), { credentials: "same-origin", headers: { Accept: "application/json" } })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.ok) {
					drawerBody.innerHTML = '<p class="muted">No se pudo abrir esta tarjeta.</p>';
					return;
				}
				renderDrawer(card, data);
			})
			.catch(function () {
				drawerBody.innerHTML = '<p class="muted">No se pudo abrir esta tarjeta.</p>';
			});
	}
})();

(function () {
	const nav = document.querySelector("[data-file-tabs]");
	if (!nav) return;
	const key = "mizo-ficha-tab-" + (nav.getAttribute("data-file-tabs") || "0");
	const buttons = nav.querySelectorAll("[data-file-tab]");
	const panels = document.querySelectorAll("[data-file-panel]");
	function show(id) {
		let found = false;
		panels.forEach(function (panel) {
			const on = panel.getAttribute("data-file-panel") === id;
			panel.hidden = !on;
			if (on) found = true;
		});
		if (!found) id = "datos";
		buttons.forEach(function (button) {
			const on = button.getAttribute("data-file-tab") === id;
			button.classList.toggle("is-on", on);
			if (on) button.setAttribute("aria-current", "page");
			else button.removeAttribute("aria-current");
		});
		if (!found) {
			panels.forEach(function (panel) {
				panel.hidden = panel.getAttribute("data-file-panel") !== "datos";
			});
		}
		try { sessionStorage.setItem(key, id); } catch (e) {}
	}
	buttons.forEach(function (button) {
		button.addEventListener("click", function () {
			show(button.getAttribute("data-file-tab"));
		});
	});
	let start = "datos";
	const hash = (location.hash || "").replace("#", "");
	if (hash && nav.querySelector('[data-file-tab="' + hash + '"]')) start = hash;
	else {
		try {
			const saved = sessionStorage.getItem(key);
			if (saved && nav.querySelector('[data-file-tab="' + saved + '"]')) start = saved;
		} catch (e) {}
	}
	show(start);
})();
