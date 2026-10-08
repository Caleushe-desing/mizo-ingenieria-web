(function () {
	'use strict';

	if (!/[?&]editar=1(?:&|$)/.test(window.location.search)) return;

	var TEXT = 'h1,h2,h3,h4,h5,h6,p,li,a,button,span,summary,figcaption,label,blockquote';
	var csrf = '';
	var chromeDirty = false;
	var path = window.location.pathname.replace(/\/$/, '') || '/';
	var started = false;

	function boot() {
		if (started) return;
		started = true;
		fetch('/crm/api/sitio-visual.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.admin) {
					banner('Inicia sesión en el CRM como administrador para editar esta página.');
					return;
				}
				csrf = data.csrf || '';
				mount();
			})
			.catch(function () {
				banner('No se pudo abrir el editor.');
			});
	}

	window.addEventListener('mizo:cms-listo', boot);

	function banner(message) {
		var bar = document.createElement('div');
		bar.setAttribute('data-editor-ui', '');
		bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:10000;background:#0f2744;color:#fff;padding:12px 16px;font:600 14px/1.4 sans-serif';
		bar.textContent = message;
		document.body.appendChild(bar);
	}

	function mount() {
		var style = document.createElement('style');
		style.setAttribute('data-editor-ui', '');
		style.textContent = ''
			+ '#mizo-editor-bar{position:fixed;left:0;right:0;bottom:4.5rem;z-index:10000;display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:10px 14px;background:#0f2744;color:#fff;font:600 14px/1.2 sans-serif}'
			+ '#mizo-editor-bar button{border:0;background:#fff;color:#0f2744;padding:8px 12px;cursor:pointer;font:inherit}'
			+ '#mizo-editor-bar button.accent{background:#e87722;color:#fff}'
			+ '#mizo-editor-bar .status{margin-left:auto;font-weight:500;opacity:.9}'
			+ '.mizo-sec-tools{position:absolute;top:8px;right:8px;z-index:30;display:flex;gap:4px}'
			+ '.mizo-sec-tools button{border:0;background:#0f2744;color:#fff;padding:4px 8px;font:600 12px sans-serif;cursor:pointer}'
			+ '#contenido > section{position:relative}'
			+ 'img.mizo-img-edit{cursor:pointer}'
			+ 'img.mizo-img-edit:hover,[contenteditable="true"]{outline:2px solid #e87722;outline-offset:2px}';
		document.head.appendChild(style);

		var bar = document.createElement('div');
		bar.id = 'mizo-editor-bar';
		bar.setAttribute('data-editor-ui', '');
		bar.innerHTML = '<strong>Editando esta página</strong>'
			+ '<button type="button" data-act="section">Agregar sección</button>'
			+ '<button type="button" data-act="image">Agregar imagen</button>'
			+ '<button type="button" data-act="save" class="accent">Guardar</button>'
			+ '<button type="button" data-act="restore">Restaurar página</button>'
			+ '<button type="button" data-act="restore-chrome">Restaurar menú y pie</button>'
			+ '<a href="/crm/sitio" style="color:#fff;margin-left:8px">Volver al CRM</a>'
			+ '<span class="status" data-status>Haz clic en un texto o una imagen</span>';
		document.body.appendChild(bar);

		var file = document.createElement('input');
		file.type = 'file';
		file.accept = 'image/jpeg,image/png,image/webp,image/gif';
		file.setAttribute('data-editor-ui', '');
		file.hidden = true;
		document.body.appendChild(file);
		var pendingImg = null;

		bar.addEventListener('click', function (event) {
			var act = event.target.getAttribute && event.target.getAttribute('data-act');
			if (act === 'section') addSection(false);
			if (act === 'image') addSection(true);
			if (act === 'save') save(false);
			if (act === 'restore') save(true);
			if (act === 'restore-chrome') restoreChrome();
		});

		document.addEventListener('click', function (event) {
			if (event.target.closest('[data-editor-ui]')) return;
			var img = event.target.closest('img');
			if (img && inPage(img) && !img.closest('[data-featured-list],[data-cms-products]')) {
				event.preventDefault();
				event.stopPropagation();
				pendingImg = img;
				file.click();
				return;
			}
			var el = event.target.closest(TEXT);
			if (!el || !inPage(el) || el.closest('[data-featured-list],[data-cms-products],[data-editor-ui]')) return;
			if (el.closest('header, footer')) chromeDirty = true;
			event.preventDefault();
			event.stopPropagation();
			el.setAttribute('contenteditable', 'true');
			el.focus();
		}, true);

		document.addEventListener('input', function (event) {
			if (event.target.closest && event.target.closest('header, footer')) chromeDirty = true;
		});

		file.addEventListener('change', function () {
			var image = pendingImg;
			var chosen = file.files && file.files[0];
			file.value = '';
			if (!image || !chosen) return;
			if (image.closest('header, footer')) chromeDirty = true;
			status('Subiendo imagen…');
			var body = new FormData();
			body.append('_csrf', csrf);
			body.append('imagen', chosen);
			fetch('/crm/sitio/imagen', { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (!data || !data.ok || !data.url) throw new Error((data && data.error) || 'No se pudo subir');
					image.src = data.url;
					image.removeAttribute('srcset');
					image.classList.add('mizo-img-edit');
					status('Imagen lista. Guarda para publicarla.');
				})
				.catch(function (error) { status(error.message || 'No se pudo subir la imagen'); });
		});

		decorate();
		document.querySelectorAll('#contenido img, header img, footer img').forEach(function (img) {
			if (!img.closest('[data-featured-list],[data-cms-products]')) img.classList.add('mizo-img-edit');
		});
	}

	function inPage(el) {
		return !!(el.closest('#contenido') || el.closest('header') || el.closest('footer'));
	}

	function status(text) {
		var node = document.querySelector('[data-status]');
		if (node) node.textContent = text;
	}

	function decorate() {
		document.querySelectorAll('#contenido > section').forEach(function (section) {
			if (section.querySelector(':scope > [data-editor-ui]')) return;
			var tools = document.createElement('div');
			tools.className = 'mizo-sec-tools';
			tools.setAttribute('data-editor-ui', '');
			tools.innerHTML = '<button type="button" data-move="-1">Subir</button><button type="button" data-move="1">Bajar</button><button type="button" data-remove="1">Quitar</button>';
			tools.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				var move = event.target.getAttribute('data-move');
				if (move === '-1' && section.previousElementSibling) section.parentNode.insertBefore(section, section.previousElementSibling);
				if (move === '1' && section.nextElementSibling) section.parentNode.insertBefore(section.nextElementSibling, section);
				if (event.target.getAttribute('data-remove') === '1') section.remove();
			});
			section.appendChild(tools);
		});
	}

	function addSection(withImage) {
		var section = document.createElement('section');
		section.className = 'mizo-band mizo-band-white border-b border-ink/10 py-16 sm:py-20';
		var image = withImage
			? '<figure class="mt-6"><img alt="" class="w-full object-cover mizo-img-edit" src="data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="480"><rect width="100%" height="100%" fill="#e8eef5"/><text x="50%" y="50%" fill="#5b6b7c" font-size="28" text-anchor="middle" font-family="sans-serif">Clic para subir imagen</text></svg>') + '"></figure>'
			: '';
		section.innerHTML = '<div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><h2 class="text-3xl font-extrabold">Nueva sección</h2><p class="mt-4 max-w-3xl text-base leading-8 text-ink/80">Escribe aquí el texto de esta sección.</p>' + image + '</div>';
		var main = document.getElementById('contenido');
		main.appendChild(section);
		decorate();
		section.scrollIntoView({ behavior: 'smooth', block: 'center' });
		status('Sección agregada. Edita el texto y guarda.');
	}

	function snapshot(node, outer) {
		var clone = node.cloneNode(true);
		clone.querySelectorAll('[data-editor-ui]').forEach(function (el) { el.remove(); });
		clone.querySelectorAll('[contenteditable]').forEach(function (el) { el.removeAttribute('contenteditable'); });
		clone.querySelectorAll('img').forEach(function (img) { img.classList.remove('mizo-img-edit'); });
		clone.querySelectorAll('[data-featured-list],[data-cms-products]').forEach(function (el) { el.innerHTML = ''; });
		var featured = clone.querySelector ? clone.querySelector('[data-featured]') : null;
		if (!outer && featured) featured.setAttribute('hidden', '');
		if (outer) {
			clone.querySelectorAll('[data-featured-list],[data-cms-products]').forEach(function (el) { el.innerHTML = ''; });
		}
		return outer ? clone.outerHTML : clone.innerHTML;
	}

	function save(restore) {
		if (restore && !window.confirm('¿Volver esta página al diseño original?')) return;
		status(restore ? 'Restaurando…' : 'Guardando…');
		var body = new FormData();
		body.append('_csrf', csrf);
		body.append('path', path);
		body.append('action', restore ? 'restaurar' : 'publicar');
		if (!restore) {
			var main = document.getElementById('contenido');
			var header = document.querySelector('body > header');
			var footer = document.querySelector('body > footer');
			body.append('html', snapshot(main, false));
			if (chromeDirty && header && footer) {
				body.append('chrome', '1');
				body.append('header', snapshot(header, true));
				body.append('footer', snapshot(footer, true));
			}
		}
		fetch('/crm/api/sitio-visual.php', { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.ok) throw new Error((data && data.error) || 'No se pudo guardar');
				status(restore ? 'Página original restaurada.' : 'Guardado. Recarga sin ?editar=1 para verla publicada.');
				if (restore) window.location.href = path;
			})
			.catch(function (error) { status(error.message || 'No se pudo guardar'); });
	}

	function restoreChrome() {
		if (!window.confirm('¿Volver el menú y el pie al diseño original en todo el sitio?')) return;
		var body = new FormData();
		body.append('_csrf', csrf);
		body.append('path', path);
		body.append('action', 'restaurar-chrome');
		fetch('/crm/api/sitio-visual.php', { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.ok) throw new Error((data && data.error) || 'No se pudo restaurar');
				window.location.href = path + '?editar=1';
			})
			.catch(function (error) { status(error.message || 'No se pudo restaurar'); });
	}
})();
