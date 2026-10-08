(function () {
	'use strict';
	if (/[?&]editar=1(?:&|$)/.test(window.location.search)) return;
	if ((window.location.pathname || '').indexOf('/crm') === 0) return;

	function visitorId() {
		try {
			var current = localStorage.getItem('mizo_vid');
			if (current && /^[a-zA-Z0-9-]{8,64}$/.test(current)) return current;
		} catch (error) {}
		return '';
	}

	var style = document.createElement('style');
	style.textContent = '#mizo-chat-btn{position:fixed;left:16px;bottom:16px;z-index:60;border:0;background:#0f2744;color:#fff;font:700 14px/1 sans-serif;padding:14px 16px;cursor:pointer;box-shadow:0 8px 24px rgba(15,39,68,.25)}#mizo-chat{position:fixed;left:16px;bottom:68px;z-index:60;width:min(340px,calc(100vw - 32px));background:#fff;color:#172033;border:1px solid rgba(15,39,68,.12);box-shadow:0 16px 40px rgba(15,39,68,.2);display:none;font:400 14px/1.4 sans-serif}#mizo-chat.is-open{display:grid}#mizo-chat header{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:#0f2744;color:#fff}#mizo-chat header button{border:0;background:transparent;color:#fff;font-size:20px;cursor:pointer}#mizo-chat form,#mizo-chat .log{padding:12px 14px}#mizo-chat .log{display:grid;gap:8px;max-height:240px;overflow:auto}#mizo-chat .msg{padding:8px 10px;background:#f4f7fb}#mizo-chat .msg.is-mizo{background:#fff4ea}#mizo-chat input,#mizo-chat textarea{width:100%;box-sizing:border-box;border:1px solid #d5dde8;padding:8px;font:inherit}#mizo-chat button.send{border:0;background:#e87722;color:#fff;font:700 14px sans-serif;padding:10px 12px;cursor:pointer}@media(max-width:640px){#mizo-chat-btn,#mizo-chat{bottom:76px}#mizo-chat{bottom:132px}}';
	document.head.appendChild(style);

	var button = document.createElement('button');
	button.id = 'mizo-chat-btn';
	button.type = 'button';
	button.textContent = 'Chatea con Mizo';
	var panel = document.createElement('section');
	panel.id = 'mizo-chat';
	panel.innerHTML = '<header><strong>Mizo</strong><button type="button" data-close aria-label="Cerrar">×</button></header><div class="log" data-log><p>Cuéntanos qué necesitas. Un asesor te responde aquí.</p></div><form><input name="name" placeholder="Tu nombre" maxlength="80"><textarea name="body" rows="3" placeholder="Escribe tu mensaje" required maxlength="2000"></textarea><button class="send" type="submit">Enviar</button></form>';
	document.body.appendChild(button);
	document.body.appendChild(panel);

	var log = panel.querySelector('[data-log]');
	var form = panel.querySelector('form');
	var open = false;
	var timer = 0;

	function esc(value) {
		return String(value || '').replace(/[&<>"']/g, function (c) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
		});
	}
	function paint(messages) {
		if (!messages || !messages.length) return;
		log.innerHTML = messages.map(function (msg) {
			return '<div class="msg' + (msg.author === 'mizo' ? ' is-mizo' : '') + '">' + esc(msg.body) + '</div>';
		}).join('');
		log.scrollTop = log.scrollHeight;
	}
	function read() {
		var visitor = visitorId();
		if (!visitor) return;
		fetch('/crm/api/sitio-vivo.php?action=mensajes&visitor=' + encodeURIComponent(visitor), { headers: { Accept: 'application/json' } })
			.then(function (res) { return res.json(); })
			.then(function (data) { if (data && data.messages) paint(data.messages); })
			.catch(function () {});
	}
	button.addEventListener('click', function () {
		open = !open;
		panel.classList.toggle('is-open', open);
		if (open) {
			read();
			timer = window.setInterval(read, 4000);
		} else {
			window.clearInterval(timer);
		}
	});
	panel.querySelector('[data-close]').addEventListener('click', function () {
		open = false;
		panel.classList.remove('is-open');
		window.clearInterval(timer);
	});
	form.addEventListener('submit', function (event) {
		event.preventDefault();
		var visitor = visitorId();
		if (!visitor) return;
		var payload = {
			action: 'mensaje',
			visitor: visitor,
			name: form.name.value,
			body: form.body.value,
			path: window.location.pathname || '/'
		};
		fetch('/crm/api/sitio-vivo.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		}).then(function (res) { return res.json(); }).then(function (data) {
			if (data && data.ok) {
				form.body.value = '';
				paint(data.messages || []);
			}
		}).catch(function () {});
	});
})();
