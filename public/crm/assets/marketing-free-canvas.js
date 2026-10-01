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
	const bgTplFilters = root.querySelector('[data-bg-tpl-filters]');
	const bgTplGallery = root.querySelector('[data-bg-tpl-gallery]');
	const fontSelect = root.querySelector('[data-font]');
	const fontSize = root.querySelector('[data-font-size]');
	const textColor = root.querySelector('[data-text-color]');
	const bgColor = root.querySelector('[data-bg-color]');
	const previewModal = root.querySelector('[data-preview-modal]');
	const previewImage = root.querySelector('[data-preview-image]');
	const previewCaption = root.querySelector('[data-preview-caption]');
	let stockFilter = '';
	let bgTplFilter = 'minimalistas';
	let activeBgTpl = '';
	let previewFormat = 'story';

	const BG_TEMPLATES = [
		// Minimalistas
		{ id: 'min-ivory', cat: 'minimalistas', name: 'Marfil limpio', swatch: 'linear-gradient(180deg,#f7f5f2,#ebe7e1)', paint: function (ctx, w, h) {
			ctx.fillStyle = '#f7f5f2'; ctx.fillRect(0, 0, w, h);
			ctx.fillStyle = 'rgba(31,35,40,0.06)'; ctx.fillRect(0, h * 0.72, w, h * 0.28);
			ctx.strokeStyle = 'rgba(31,35,40,0.12)'; ctx.lineWidth = 2;
			ctx.beginPath(); ctx.moveTo(72, h * 0.72); ctx.lineTo(w - 72, h * 0.72); ctx.stroke();
		}},
		{ id: 'min-slate', cat: 'minimalistas', name: 'Gris estudio', swatch: 'linear-gradient(180deg,#f0f2f4,#d9dee4)', paint: function (ctx, w, h) {
			const g = ctx.createLinearGradient(0, 0, 0, h);
			g.addColorStop(0, '#f4f6f8'); g.addColorStop(1, '#d8dee5');
			ctx.fillStyle = g; ctx.fillRect(0, 0, w, h);
			ctx.fillStyle = '#ffffff'; roundRect(ctx, 48, 48, w - 96, h - 96, 28); ctx.fill();
		}},
		{ id: 'min-ink', cat: 'minimalistas', name: 'Negro editorial', swatch: 'linear-gradient(180deg,#1f2328,#111418)', paint: function (ctx, w, h) {
			ctx.fillStyle = '#14181d'; ctx.fillRect(0, 0, w, h);
			ctx.fillStyle = 'rgba(255,255,255,0.04)'; ctx.fillRect(0, 0, w, h * 0.38);
			ctx.fillStyle = '#f47b20'; ctx.fillRect(72, h * 0.42, 96, 8);
		}},
		{ id: 'min-paper', cat: 'minimalistas', name: 'Papel técnico', swatch: 'linear-gradient(180deg,#eef4f8,#ffffff)', paint: function (ctx, w, h) {
			ctx.fillStyle = '#eef4f8'; ctx.fillRect(0, 0, w, h);
			ctx.strokeStyle = 'rgba(11,110,168,0.12)'; ctx.lineWidth = 1;
			for (let y = 80; y < h; y += 48) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke(); }
			ctx.fillStyle = '#ffffff'; ctx.fillRect(0, h * 0.55, w, h * 0.45);
		}},
		// Geométricos
		{ id: 'geo-blocks', cat: 'geometricos', name: 'Bloques Mizo', swatch: 'linear-gradient(135deg,#0b6ea8 50%,#f47b20 50%)', paint: function (ctx, w, h) {
			ctx.fillStyle = '#0b1c2c'; ctx.fillRect(0, 0, w, h);
			ctx.fillStyle = '#0b6ea8'; ctx.fillRect(0, 0, w * 0.58, h);
			ctx.fillStyle = '#f47b20'; ctx.beginPath(); ctx.moveTo(w * 0.45, 0); ctx.lineTo(w, 0); ctx.lineTo(w, h); ctx.lineTo(w * 0.62, h); ctx.closePath(); ctx.fill();
			ctx.fillStyle = 'rgba(255,255,255,0.08)'; ctx.fillRect(0, h * 0.7, w, h * 0.3);
		}},
		{ id: 'geo-lines', cat: 'geometricos', name: 'Líneas diagonales', swatch: 'linear-gradient(135deg,#102536,#1c9bd8)', paint: function (ctx, w, h) {
			const g = ctx.createLinearGradient(0, 0, w, h);
			g.addColorStop(0, '#0b1c2c'); g.addColorStop(1, '#0b6ea8');
			ctx.fillStyle = g; ctx.fillRect(0, 0, w, h);
			ctx.strokeStyle = 'rgba(255,255,255,0.14)'; ctx.lineWidth = 3;
			for (let i = -h; i < w + h; i += 70) {
				ctx.beginPath(); ctx.moveTo(i, h); ctx.lineTo(i + h, 0); ctx.stroke();
			}
			ctx.fillStyle = 'rgba(0,0,0,0.35)'; ctx.fillRect(0, h * 0.55, w, h * 0.45);
		}},
		{ id: 'geo-frame', cat: 'geometricos', name: 'Marco moderno', swatch: 'linear-gradient(180deg,#1f2328,#0b6ea8)', paint: function (ctx, w, h) {
			ctx.fillStyle = '#1a222b'; ctx.fillRect(0, 0, w, h);
			ctx.strokeStyle = '#1c9bd8'; ctx.lineWidth = 10;
			ctx.strokeRect(56, 56, w - 112, h - 112);
			ctx.strokeStyle = '#f47b20'; ctx.lineWidth = 4;
			ctx.strokeRect(80, 80, w - 160, h - 160);
			ctx.fillStyle = 'rgba(28,155,216,0.15)'; ctx.fillRect(56, h * 0.68, w - 112, h * 0.22);
		}},
		{ id: 'geo-split', cat: 'geometricos', name: 'Split horizontal', swatch: 'linear-gradient(180deg,#ffffff 50%,#0b6ea8 50%)', paint: function (ctx, w, h) {
			ctx.fillStyle = '#f4f8fb'; ctx.fillRect(0, 0, w, h * 0.48);
			ctx.fillStyle = '#0b6ea8'; ctx.fillRect(0, h * 0.48, w, h * 0.52);
			ctx.fillStyle = '#f47b20'; ctx.fillRect(0, h * 0.48 - 6, w, 12);
		}},
		// Explosivos / técnicos
		{ id: 'tech-aurora', cat: 'explosivos', name: 'Aurora técnica', swatch: 'linear-gradient(160deg,#071525,#1c9bd8,#f47b20)', paint: function (ctx, w, h) {
			const g = ctx.createLinearGradient(0, 0, w, h);
			g.addColorStop(0, '#071525'); g.addColorStop(0.45, '#0b6ea8'); g.addColorStop(1, '#f47b20');
			ctx.fillStyle = g; ctx.fillRect(0, 0, w, h);
			ctx.fillStyle = 'rgba(0,0,0,0.35)'; ctx.fillRect(0, h * 0.5, w, h * 0.5);
		}},
		{ id: 'tech-pulse', cat: 'explosivos', name: 'Pulso naranja', swatch: 'radial-gradient(circle at 30% 20%,#f47b20,#0b1c2c)', paint: function (ctx, w, h) {
			ctx.fillStyle = '#0a1420'; ctx.fillRect(0, 0, w, h);
			const r = ctx.createRadialGradient(w * 0.25, h * 0.2, 40, w * 0.25, h * 0.2, w * 0.7);
			r.addColorStop(0, 'rgba(244,123,32,0.95)'); r.addColorStop(0.55, 'rgba(11,110,168,0.55)'); r.addColorStop(1, 'rgba(7,21,37,0)');
			ctx.fillStyle = r; ctx.fillRect(0, 0, w, h);
			ctx.fillStyle = 'rgba(0,0,0,0.4)'; ctx.fillRect(0, h * 0.58, w, h * 0.42);
		}},
		{ id: 'tech-grid', cat: 'explosivos', name: 'Grid neon', swatch: 'linear-gradient(180deg,#041018,#0b6ea8)', paint: function (ctx, w, h) {
			ctx.fillStyle = '#041018'; ctx.fillRect(0, 0, w, h);
			ctx.strokeStyle = 'rgba(28,155,216,0.28)'; ctx.lineWidth = 1;
			for (let x = 0; x < w; x += 54) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, h); ctx.stroke(); }
			for (let y = 0; y < h; y += 54) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke(); }
			const g = ctx.createLinearGradient(0, h * 0.35, 0, h);
			g.addColorStop(0, 'rgba(244,123,32,0)'); g.addColorStop(1, 'rgba(244,123,32,0.55)');
			ctx.fillStyle = g; ctx.fillRect(0, 0, w, h);
		}},
		{ id: 'tech-beam', cat: 'explosivos', name: 'Haz corporativo', swatch: 'linear-gradient(120deg,#0b1c2c 40%,#f47b20 100%)', paint: function (ctx, w, h) {
			ctx.fillStyle = '#0b1c2c'; ctx.fillRect(0, 0, w, h);
			ctx.fillStyle = '#0b6ea8';
			ctx.beginPath(); ctx.moveTo(0, h * 0.2); ctx.lineTo(w, 0); ctx.lineTo(w, h * 0.35); ctx.lineTo(0, h * 0.55); ctx.closePath(); ctx.fill();
			ctx.fillStyle = '#f47b20';
			ctx.beginPath(); ctx.moveTo(0, h * 0.55); ctx.lineTo(w, h * 0.35); ctx.lineTo(w, h * 0.5); ctx.lineTo(0, h * 0.7); ctx.closePath(); ctx.fill();
			ctx.fillStyle = 'rgba(0,0,0,0.45)'; ctx.fillRect(0, h * 0.62, w, h * 0.38);
		}},
	];

	const BG_CATS = [
		{ id: 'minimalistas', label: 'Minimalistas' },
		{ id: 'geometricos', label: 'Geométricos' },
		{ id: 'explosivos', label: 'Explosivos / Técnicos' },
	];

	const PREVIEW_META = {
		story: { label: 'Instagram Story / WhatsApp · 9:16', tw: 1080, th: 1920, phoneClass: 'is-story' },
		post: { label: 'Post cuadrado · 1:1', tw: 1080, th: 1080, phoneClass: 'is-post' },
		feed: { label: 'Feed vertical · 4:5', tw: 1080, th: 1350, phoneClass: 'is-feed' },
	};

	function roundRect(ctx, x, y, w, h, r) {
		ctx.beginPath();
		ctx.moveTo(x + r, y);
		ctx.arcTo(x + w, y, x + w, y + h, r);
		ctx.arcTo(x + w, y + h, x, y + h, r);
		ctx.arcTo(x, y + h, x, y, r);
		ctx.arcTo(x, y, x + w, y, r);
		ctx.closePath();
	}

	function setStatus(msg, isError) {
		if (!statusEl) return;
		statusEl.textContent = msg || '';
		statusEl.classList.toggle('is-error', !!isError);
	}

	function selected() {
		return canvas.getActiveObject();
	}

	function clearBackgroundImage(done) {
		canvas.setBackgroundImage(null, function () {
			if (typeof done === 'function') done();
			else canvas.requestRenderAll();
		});
	}

	function setSolidBackground(color) {
		activeBgTpl = '';
		paintBgTemplates();
		clearBackgroundImage(function () {
			canvas.backgroundColor = color;
			if (bgColor) bgColor.value = color;
			canvas.requestRenderAll();
		});
	}

	function applyBgTemplate(tpl) {
		if (!tpl) return;
		setStatus('Aplicando plantilla…');
		const off = document.createElement('canvas');
		off.width = W;
		off.height = H;
		const ctx = off.getContext('2d');
		tpl.paint(ctx, W, H);
		const dataUrl = off.toDataURL('image/png');
		fabric.Image.fromURL(dataUrl, function (img) {
			if (!img) {
				setStatus('No se pudo aplicar la plantilla.', true);
				return;
			}
			activeBgTpl = tpl.id;
			canvas.backgroundColor = '#0b1c2c';
			canvas.setBackgroundImage(img, function () {
				paintBgTemplates();
				canvas.requestRenderAll();
				setStatus('Fondo «' + tpl.name + '» aplicado.');
			}, {
				scaleX: canvas.width / img.width,
				scaleY: canvas.height / img.height,
				originX: 'left',
				originY: 'top',
			});
		});
	}

	function paintBgTemplateFilters() {
		if (!bgTplFilters) return;
		bgTplFilters.innerHTML = BG_CATS.map(function (cat) {
			const on = bgTplFilter === cat.id ? ' is-on' : '';
			return '<button type="button" class="mkt-filter' + on + '" data-bg-tpl-cat="' + cat.id + '">' + cat.label + '</button>';
		}).join('');
		bgTplFilters.querySelectorAll('[data-bg-tpl-cat]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				bgTplFilter = btn.getAttribute('data-bg-tpl-cat') || 'minimalistas';
				paintBgTemplateFilters();
				paintBgTemplates();
			});
		});
	}

	function paintBgTemplates() {
		if (!bgTplGallery) return;
		const items = BG_TEMPLATES.filter(function (t) { return t.cat === bgTplFilter; });
		bgTplGallery.innerHTML = items.map(function (tpl) {
			const on = activeBgTpl === tpl.id ? ' is-on' : '';
			return '<button type="button" class="mkt-bg-tpl' + on + '" data-bg-tpl="' + tpl.id + '" title="' + escapeHtml(tpl.name) + '">' +
				'<span class="mkt-bg-tpl-swatch" style="background:' + tpl.swatch + '"></span>' +
				'<strong>' + escapeHtml(tpl.name) + '</strong></button>';
		}).join('');
		bgTplGallery.querySelectorAll('[data-bg-tpl]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				const tpl = BG_TEMPLATES.find(function (t) { return t.id === btn.getAttribute('data-bg-tpl'); });
				applyBgTemplate(tpl);
			});
		});
	}

	function exportForPreview(format) {
		const meta = PREVIEW_META[format] || PREVIEW_META.story;
		const src = canvas.toDataURL({ format: 'png', multiplier: 1 });
		return new Promise(function (resolve) {
			const img = new Image();
			img.onload = function () {
				const out = document.createElement('canvas');
				out.width = meta.tw;
				out.height = meta.th;
				const ctx = out.getContext('2d');
				ctx.fillStyle = '#0b1c2c';
				ctx.fillRect(0, 0, meta.tw, meta.th);
				const scale = Math.max(meta.tw / img.width, meta.th / img.height);
				const dw = img.width * scale;
				const dh = img.height * scale;
				ctx.drawImage(img, (meta.tw - dw) / 2, (meta.th - dh) / 2, dw, dh);
				resolve(out.toDataURL('image/jpeg', 0.92));
			};
			img.onerror = function () { resolve(src); };
			img.src = src;
		});
	}

	function openPreview() {
		if (!previewModal) return;
		canvas.discardActiveObject();
		canvas.requestRenderAll();
		previewModal.hidden = false;
		document.body.classList.add('mkt-preview-open');
		refreshPreview();
	}

	function closePreview() {
		if (!previewModal) return;
		previewModal.hidden = true;
		document.body.classList.remove('mkt-preview-open');
	}

	function refreshPreview() {
		const meta = PREVIEW_META[previewFormat] || PREVIEW_META.story;
		const phone = root.querySelector('.mkt-phone');
		if (phone) {
			phone.classList.remove('is-story', 'is-post', 'is-feed');
			phone.classList.add(meta.phoneClass);
		}
		if (previewCaption) previewCaption.textContent = meta.label;
		root.querySelectorAll('[data-preview-format]').forEach(function (btn) {
			btn.classList.toggle('is-on', btn.getAttribute('data-preview-format') === previewFormat);
		});
		if (previewImage) {
			previewImage.alt = 'Cargando…';
			exportForPreview(previewFormat).then(function (url) {
				previewImage.src = url;
				previewImage.alt = 'Vista previa del diseño';
			});
		}
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
		activeBgTpl = '';
		resourceId = 0;
		root.setAttribute('data-resource-id', '0');
		setSolidBackground('#0b1c2c');
		addText();
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
			else if (act === 'preview') openPreview();
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
			setSolidBackground(btn.getAttribute('data-bg'));
		});
	});
	if (bgColor) {
		bgColor.addEventListener('input', function () {
			setSolidBackground(bgColor.value);
		});
	}
	if (fontSelect) fontSelect.addEventListener('change', function () { applyTextProp('fontFamily', fontSelect.value); });
	if (fontSize) fontSize.addEventListener('input', function () { applyTextProp('fontSize', parseInt(fontSize.value, 10) || 48); });
	if (textColor) textColor.addEventListener('input', function () { applyTextProp('fill', textColor.value); });

	root.querySelectorAll('[data-preview-close]').forEach(function (el) {
		el.addEventListener('click', closePreview);
	});
	root.querySelectorAll('[data-preview-format]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			previewFormat = btn.getAttribute('data-preview-format') || 'story';
			refreshPreview();
		});
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && previewModal && !previewModal.hidden) closePreview();
	});

	canvas.on('selection:created', syncTextControls);
	canvas.on('selection:updated', syncTextControls);

	paintBgTemplateFilters();
	paintBgTemplates();
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
		setStatus('Elige una plantilla de fondo o empieza a diseñar.');
	}
})();
