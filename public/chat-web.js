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
	style.textContent = '#mizo-chat-btn{position:fixed;left:16px;bottom:16px;z-index:60;border:0;background:#0f2744;color:#fff;font:700 14px/1 sans-serif;padding:14px 16px;cursor:pointer;box-shadow:0 8px 24px rgba(15,39,68,.25)}#mizo-chat-btn.has-alert{background:#e87722;animation:mizo-chat-pulse 1s ease-in-out infinite}@keyframes mizo-chat-pulse{50%{transform:scale(1.04)}}#mizo-chat{position:fixed;left:16px;bottom:68px;z-index:60;width:min(380px,calc(100vw - 32px));background:#fff;color:#172033;border:1px solid rgba(15,39,68,.12);box-shadow:0 16px 40px rgba(15,39,68,.2);display:none;font:400 15px/1.45 sans-serif}#mizo-chat.is-open{display:grid}#mizo-chat header{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:12px 14px;background:#0f2744;color:#fff}#mizo-chat header button{border:0;background:transparent;color:#fff;font-size:20px;cursor:pointer}#mizo-chat form,#mizo-chat .log{padding:12px 14px}#mizo-chat .log{display:grid;gap:8px;max-height:320px;overflow:auto}#mizo-chat .msg{padding:8px 10px;background:#f4f7fb}#mizo-chat .msg.is-mizo{background:#fff4ea}#mizo-chat input,#mizo-chat textarea{width:100%;box-sizing:border-box;border:1px solid #d5dde8;padding:8px;font:inherit}#mizo-chat button.send{border:0;background:#e87722;color:#fff;font:700 14px sans-serif;padding:10px 12px;cursor:pointer}@media(max-width:640px){#mizo-chat-btn{bottom:76px}#mizo-chat{bottom:132px;width:min(380px,calc(100vw - 24px))}}';
	document.head.appendChild(style);

	var button = document.createElement('button');
	button.id = 'mizo-chat-btn';
	button.type = 'button';
	button.textContent = 'Chatea con Mizo';
	var panel = document.createElement('section');
	panel.id = 'mizo-chat';
	panel.innerHTML = '<header><strong>Mizo <span data-code></span></strong><button type="button" data-close aria-label="Cerrar">×</button></header><div class="log" data-log><p>Cuéntanos qué necesitas. Un asesor te responde aquí.</p></div><form><input name="name" placeholder="Tu nombre" maxlength="80"><textarea name="body" rows="3" placeholder="Escribe tu mensaje" required maxlength="2000"></textarea><button class="send" type="submit">Enviar</button></form>';
	document.body.appendChild(button);
	document.body.appendChild(panel);

	var log = panel.querySelector('[data-log]');
	var codeNode = panel.querySelector('[data-code]');
	var form = panel.querySelector('form');
	var open = false;
	var knownMizo = null;
	var baseTitle = document.title;

	function esc(value) {
		return String(value || '').replace(/[&<>"']/g, function (c) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
		});
	}
	function beep() {
		try {
			var ctx = new (window.AudioContext || window.webkitAudioContext)();
			var osc = ctx.createOscillator();
			var gain = ctx.createGain();
			osc.frequency.value = 740;
			osc.connect(gain);
			gain.connect(ctx.destination);
			gain.gain.setValueAtTime(0.07, ctx.currentTime);
			osc.start();
			osc.stop(ctx.currentTime + 0.22);
			setTimeout(function () { ctx.close(); }, 500);
		} catch (error) {}
		if (navigator.vibrate) navigator.vibrate([160, 50, 160]);
	}
	function paint(data) {
		var messages = data && data.messages ? data.messages : [];
		if (data && data.code) codeNode.textContent = '· ' + data.code;
		var mizo = messages.filter(function (msg) { return msg.author === 'mizo'; }).length;
		if (knownMizo !== null && mizo > knownMizo) {
			beep();
			button.classList.add('has-alert');
			button.textContent = 'Mizo te escribió';
			if (!open) document.title = '(1) Mizo te escribió';
			if (window.Notification && Notification.permission === 'granted' && !open) {
				new Notification('Mizo', { body: 'Tienes una respuesta en el chat.' });
			}
		}
		if (open) {
			button.classList.remove('has-alert');
			button.textContent = 'Chatea con Mizo';
			document.title = baseTitle;
		}
		knownMizo = mizo;
		if (!messages.length) return;
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
			.then(paint)
			.catch(function () {});
	}
	button.addEventListener('click', function () {
		open = !open;
		panel.classList.toggle('is-open', open);
		if (open) {
			button.classList.remove('has-alert');
			button.textContent = 'Chatea con Mizo';
			document.title = baseTitle;
			if (window.Notification && Notification.permission === 'default') Notification.requestPermission();
		}
		read();
	});
	panel.querySelector('[data-close]').addEventListener('click', function () {
		open = false;
		panel.classList.remove('is-open');
	});
	form.addEventListener('submit', function (event) {
		event.preventDefault();
		var visitor = visitorId();
		if (!visitor) return;
		fetch('/crm/api/sitio-vivo.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				action: 'mensaje',
				visitor: visitor,
				name: form.name.value,
				body: form.body.value,
				path: window.location.pathname || '/'
			})
		}).then(function (res) { return res.json(); }).then(function (data) {
			if (data && data.ok) {
				form.body.value = '';
				paint(data);
			}
		}).catch(function () {});
	});
	read();
	setInterval(read, 4000);
})();
