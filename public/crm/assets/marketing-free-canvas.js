(function () {
	'use strict';

	const root = document.querySelector('[data-free-studio]');
	if (!root || typeof fabric === 'undefined') return;

	const W = parseInt(root.getAttribute('data-width') || '1080', 10);
	const H = parseInt(root.getAttribute('data-height') || '1350', 10);
	const saveUrl = root.getAttribute('data-save-url') || '';
	const csrf = root.getAttribute('data-csrf') || '';
	const logoUrl = root.getAttribute('data-logo') || '/mizo-logo-footer.png';
	const stock = JSON.parse(root.getAttribute('data-stock') || '[]');
	const stockCategories = JSON.parse(root.getAttribute('data-stock-categories') || '[]');
	let resourceId = parseInt(root.getAttribute('data-resource-id') || '0', 10) || 0;
	const initialJson = root.getAttribute('data-design-json') || '';

	const canvasEl = document.getElementById('mizo-free-canvas');
	const canvas = new fabric.Canvas(canvasEl, {
		width: W,
		height: H,
		backgroundColor: '#0b1c2c',
		preserveObjectStacking: true,
		selection: true,
	});

	const statusEl = root.querySelector('[data-studio-status]');
	const stockFilters = root.querySelector('[data-stock-filters]');
	const stockGallery = root.querySelector('[data-stock-gallery]');
	const fontSelect = root.querySelector('[data-font]');
	const fontSize = root.querySelector('[data-font-size]');
	const textColor = root.querySelector('[data-text-color]');
	const bgColor = root.querySelector('[data-bg-color]');
	let stockFilter = '';

	function setStatus(msg, isError) {
		if (!statusEl) return;
		statusEl.textContent = msg || '';
		statusEl.classList.toggle('is-error', !!isError);
	}

	function selected() {
		return canvas.getActiveObject();
	}

	function addText() {
		const text = new fabric.IText('Escribe aquí', {
			left: W * 0.15,
			top: H * 0.35,
			fontFamily: 'Segoe UI',
			fontSize: 56,
			fill: '#ffffff',
			fontWeight: '700',
			width: W * 0.7,
		});
		canvas.add(text);
		canvas.setActiveObject(text);
		canvas.requestRenderAll();
		syncTextControls();
	}

	function addLogo() {
		fabric.Image.fromURL(logoUrl, function (img) {
			if (!img) {
				setStatus('No se pudo cargar el logo.', true);
				return;
			}
			const maxW = 280;
			img.scaleToWidth(maxW);
			img.set({ left: 64, top: 48, selectable: true });
			canvas.add(img);
			canvas.setActiveObject(img);
			canvas.requestRenderAll();
		}, { crossOrigin: 'anonymous' });
	}

	function addImageFromUrl(url) {
		setStatus('Cargando imagen…');
		fabric.Image.fromURL(url, function (img) {
			if (!img) {
				setStatus('No se pudo cargar la imagen.', true);
				return;
			}
			const maxSide = Math.min(W, H) * 0.7;
			if (img.width >= img.height) img.scaleToWidth(maxSide);
			else img.scaleToHeight(maxSide);
			img.set({
				left: (W - img.getScaledWidth()) / 2,
				top: (H - img.getScaledHeight()) / 2,
				selectable: true,
			});
			canvas.add(img);
			canvas.setActiveObject(img);
			canvas.requestRenderAll();
			setStatus('Imagen insertada. Arrástrala o redimensiónala.');
		}, { crossOrigin: 'anonymous' });
	}

	function deleteSelected() {
		const obj = selected();
		if (!obj) return;
		canvas.remove(obj);
		canvas.discardActiveObject();
		canvas.requestRenderAll();
	}

	function bringFront() {
		const obj = selected();
		if (!obj) return;
		canvas.bringToFront(obj);
		canvas.requestRenderAll();
	}

	function sendBack() {
		const obj = selected();
		if (!obj) return;
		canvas.sendToBack(obj);
		canvas.requestRenderAll();
	}

	function syncTextControls() {
		const obj = selected();
		if (!obj || (obj.type !== 'i-text' && obj.type !== 'text' && obj.type !== 'textbox')) return;
		if (fontSelect) fontSelect.value = obj.fontFamily || 'Segoe UI';
		if (fontSize) fontSize.value = String(obj.fontSize || 48);
		if (textColor) textColor.value = toHex(obj.fill) || '#ffffff';
	}

	function toHex(color) {
		if (!color || typeof color !== 'string') return '#ffffff';
		if (color.charAt(0) === '#') return color.length === 7 ? color : '#ffffff';
		const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
		if (!m) return '#ffffff';
		return '#' + [m[1], m[2], m[3]].map(function (n) {
			return ('0' + parseInt(n, 10).toString(16)).slice(-2);
		}).join('');
	}

	function applyTextProp(prop, value) {
		const obj = selected();
		if (!obj || (obj.type !== 'i-text' && obj.type !== 'text' && obj.type !== 'textbox')) return;
		obj.set(prop, value);
		canvas.requestRenderAll();
	}

	function clearCanvas() {
		if (!confirm('¿Empezar un lienzo nuevo? Se pierde lo no guardado.')) return;
		canvas.clear();
		canvas.backgroundColor = '#0b1c2c';
		resourceId = 0;
		root.setAttribute('data-resource-id', '0');
		canvas.requestRenderAll();
		setStatus('Lienzo nuevo listo.');
	}

	function saveDesign() {
		const title = (root.querySelector('[data-save-title]') || {}).value || 'Diseño Mizo';
		const category = (root.querySelector('[data-save-category]') || {}).value || 'Redes Sociales';
		const kind = (root.querySelector('[data-save-kind]') || {}).value || 'flyer';
		const format = (root.querySelector('[data-save-format]') || {}).value || 'jpg';
		const description = (root.querySelector('[data-save-desc]') || {}).value || '';
		canvas.discardActiveObject();
		canvas.requestRenderAll();
		setStatus('Guardando diseño…');
		const mime = format === 'jpg' ? 'image/jpeg' : 'image/png';
		const quality = format === 'jpg' ? 0.92 : 1;
		const dataUrl = canvas.toDataURL({ format: format === 'jpg' ? 'jpeg' : 'png', quality: quality, multiplier: 1 });
		const json = JSON.stringify(canvas.toJSON(['selectable', 'evented']));
		const body = new FormData();
		body.append('_csrf', csrf);
		body.append('title', title.trim() || 'Diseño Mizo');
		body.append('category', category);
		body.append('kind', kind);
		body.append('format', format);
		body.append('description', description);
		body.append('image', dataUrl);
		body.append('design_json', json);
		if (resourceId > 0) body.append('resource_id', String(resourceId));

		const btn = root.querySelector('[data-act="save"]');
		if (btn) btn.disabled = true;
		fetch(saveUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
			.then(function (result) {
				if (!result.ok || !result.data.ok) throw new Error((result.data && result.data.error) || 'No se pudo guardar.');
				resourceId = result.data.id || resourceId;
				root.setAttribute('data-resource-id', String(resourceId));
				setStatus(result.data.message || 'Guardado.');
				const base = (document.body.getAttribute('data-crm-base') || '/crm').replace(/\/$/, '');
				setTimeout(function () { window.location.href = base + '/marketing/recursos#galeria'; }, 700);
			})
			.catch(function (err) {
				setStatus(err.message || 'Error al guardar.', true);
				if (btn) btn.disabled = false;
			});
	}

	function paintStock() {
		if (!stockGallery) return;
		const items = !stockFilter ? stock : stock.filter(function (s) { return s.category === stockFilter; });
		if (!items.length) {
			stockGallery.innerHTML = '<p class="muted">Sin fotos. Carga stock en Biblioteca / Stock Mizo.</p>';
			return;
		}
		stockGallery.innerHTML = items.map(function (item) {
			const thumb = item.thumb || item.url;
			return '<button type="button" class="mkt-stock-swatch" data-stock-url="' + item.url.replace(/"/g, '&quot;') + '" title="' + escapeHtml(item.title) + '">' +
				'<span class="mkt-stock-thumb" style="background-image:url(\'' + thumb.replace(/'/g, '%27') + '\')"></span>' +
				'<small>' + escapeHtml(item.category) + '</small>' +
				'<strong>' + escapeHtml(item.title) + '</strong></button>';
		}).join('');
		stockGallery.querySelectorAll('[data-stock-url]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				addImageFromUrl(btn.getAttribute('data-stock-url'));
			});
		});
	}

	function paintStockFilters() {
		if (!stockFilters) return;
		const cats = [''].concat(stockCategories);
		stockFilters.innerHTML = cats.map(function (cat) {
			const on = stockFilter === cat ? ' is-on' : '';
			return '<button type="button" class="mkt-filter' + on + '" data-stock-cat="' + escapeHtml(cat) + '">' + escapeHtml(cat || 'Todas') + '</button>';
		}).join('');
		stockFilters.querySelectorAll('[data-stock-cat]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				stockFilter = btn.getAttribute('data-stock-cat') || '';
				paintStockFilters();
				paintStock();
			});
		});
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	root.querySelectorAll('[data-act]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			const act = btn.getAttribute('data-act');
			if (act === 'add-text') addText();
			else if (act === 'add-logo') addLogo();
			else if (act === 'delete') deleteSelected();
			else if (act === 'front') bringFront();
			else if (act === 'back') sendBack();
			else if (act === 'bold') {
				const obj = selected();
				if (!obj || !obj.fontWeight) return;
				applyTextProp('fontWeight', obj.fontWeight === '700' || obj.fontWeight === 'bold' ? '400' : '700');
			}
			else if (act === 'align-left') applyTextProp('textAlign', 'left');
			else if (act === 'align-center') applyTextProp('textAlign', 'center');
			else if (act === 'align-right') applyTextProp('textAlign', 'right');
			else if (act === 'save') saveDesign();
			else if (act === 'clear') clearCanvas();
		});
	});

	const upload = root.querySelector('[data-act="upload-image"]');
	if (upload) {
		upload.addEventListener('change', function () {
			const file = upload.files && upload.files[0];
			if (!file) return;
			const reader = new FileReader();
			reader.onload = function () { addImageFromUrl(String(reader.result || '')); };
			reader.readAsDataURL(file);
			upload.value = '';
		});
	}

	root.querySelectorAll('[data-bg]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			const color = btn.getAttribute('data-bg');
			canvas.backgroundColor = color;
			if (bgColor) bgColor.value = color;
			canvas.requestRenderAll();
		});
	});
	if (bgColor) {
		bgColor.addEventListener('input', function () {
			canvas.backgroundColor = bgColor.value;
			canvas.requestRenderAll();
		});
	}
	if (fontSelect) fontSelect.addEventListener('change', function () { applyTextProp('fontFamily', fontSelect.value); });
	if (fontSize) fontSize.addEventListener('input', function () { applyTextProp('fontSize', parseInt(fontSize.value, 10) || 48); });
	if (textColor) textColor.addEventListener('input', function () { applyTextProp('fill', textColor.value); });

	canvas.on('selection:created', syncTextControls);
	canvas.on('selection:updated', syncTextControls);

	paintStockFilters();
	paintStock();

	if (initialJson) {
		try {
			canvas.loadFromJSON(initialJson, function () {
				canvas.requestRenderAll();
				setStatus('Diseño cargado para editar.');
			});
		} catch (e) {
			setStatus('No se pudo reabrir el JSON del diseño.', true);
			addText();
		}
	} else {
		addText();
		setStatus('Arrastra, redimensiona y guarda cuando esté listo.');
	}
})();
