(function () {
	'use strict';

	const root = document.querySelector('[data-mkt-studio]');
	if (!root) return;

	const templates = JSON.parse(root.getAttribute('data-templates') || '[]');
	const backgrounds = JSON.parse(root.getAttribute('data-backgrounds') || '[]');
	const stock = JSON.parse(root.getAttribute('data-stock') || '[]');
	const stockCategories = JSON.parse(root.getAttribute('data-stock-categories') || '[]');
	const brand = JSON.parse(root.getAttribute('data-brand') || '{}');
	const saveUrl = root.getAttribute('data-save-url') || '';
	const csrf = root.getAttribute('data-csrf') || '';

	const canvas = root.querySelector('[data-studio-canvas]');
	const ctx = canvas.getContext('2d');
	const W = brand.width || 1080;
	const H = brand.height || 1350;
	canvas.width = W;
	canvas.height = H;

	const els = {
		templateGrid: root.querySelector('[data-studio-templates]'),
		bgGrid: root.querySelector('[data-studio-backgrounds]'),
		stockGrid: root.querySelector('[data-studio-stock]'),
		stockFilters: root.querySelector('[data-studio-stock-filters]'),
		title: root.querySelector('[data-studio-title]'),
		description: root.querySelector('[data-studio-description]'),
		cta: root.querySelector('[data-studio-cta]'),
		category: root.querySelector('[data-studio-category]'),
		resourceTitle: root.querySelector('[data-studio-resource-title]'),
		resourceDesc: root.querySelector('[data-studio-resource-desc]'),
		format: root.querySelector('[data-studio-format]'),
		fontSize: root.querySelector('[data-studio-font-size]'),
		align: root.querySelector('[data-studio-align]'),
		color: root.querySelector('[data-studio-color]'),
		logoMode: root.querySelector('[data-studio-logo-mode]'),
		extraText: root.querySelector('[data-studio-extra-text]'),
		addText: root.querySelector('[data-studio-add-text]'),
		layers: root.querySelector('[data-studio-layers]'),
		save: root.querySelector('[data-studio-save]'),
		status: root.querySelector('[data-studio-status]'),
	};

	const state = {
		templateId: templates[0] ? templates[0].id : null,
		backgroundId: templates[0] ? templates[0].background : 'grad-navy',
		maskPreset: templates[0] ? templates[0].background : 'grad-navy',
		accent: templates[0] ? templates[0].accent : '#1c9bd8',
		title: templates[0] ? templates[0].fields.title : '',
		description: templates[0] ? templates[0].fields.description : '',
		cta: templates[0] ? templates[0].fields.cta : '',
		category: templates[0] ? templates[0].category : 'Redes Sociales',
		fontSize: 72,
		align: 'left',
		color: '#ffffff',
		logoMode: 'corner',
		extraLayers: [],
		bgImage: null,
		photoImage: null,
		photoId: null,
		photoUrl: '',
		stockFilter: '',
		logoImage: null,
	};

	const bgCache = {};

	function currentTemplate() {
		return templates.find(function (t) { return t.id === state.templateId; }) || templates[0] || null;
	}

	function setStatus(msg, isError) {
		if (!els.status) return;
		els.status.textContent = msg || '';
		els.status.classList.toggle('is-error', !!isError);
	}

	function loadImage(src) {
		return new Promise(function (resolve, reject) {
			const img = new Image();
			img.crossOrigin = 'anonymous';
			img.onload = function () { resolve(img); };
			img.onerror = function () { reject(new Error('No se pudo cargar ' + src)); };
			img.src = src;
		});
	}

	function drawPresetBackground(id) {
		const g = ctx.createLinearGradient(0, 0, W, H);
		if (id === 'grad-warm') {
			g.addColorStop(0, '#2a1a12');
			g.addColorStop(0.45, '#5a2f16');
			g.addColorStop(1, '#f47b20');
		} else if (id === 'grad-corporate') {
			g.addColorStop(0, '#0b1c2c');
			g.addColorStop(0.55, '#0b6ea8');
			g.addColorStop(1, '#1c9bd8');
		} else if (id === 'grad-slate') {
			g.addColorStop(0, '#1f2328');
			g.addColorStop(1, '#4a5560');
		} else if (id === 'grad-light') {
			g.addColorStop(0, '#f4f8fb');
			g.addColorStop(1, '#d7e7f2');
		} else {
			g.addColorStop(0, '#071525');
			g.addColorStop(0.5, '#0b6ea8');
			g.addColorStop(1, '#1c9bd8');
		}
		ctx.fillStyle = g;
		ctx.fillRect(0, 0, W, H);
		ctx.save();
		ctx.globalAlpha = id === 'grad-light' ? 0.08 : 0.12;
		ctx.strokeStyle = '#ffffff';
		ctx.lineWidth = 2;
		for (let i = 0; i < 8; i++) {
			ctx.beginPath();
			ctx.moveTo(-100 + i * 180, H);
			ctx.lineTo(200 + i * 180, 0);
			ctx.stroke();
		}
		ctx.restore();
		if (id === 'grad-light') {
			ctx.fillStyle = 'rgba(11, 110, 168, 0.12)';
			ctx.fillRect(0, H * 0.62, W, H * 0.38);
		} else {
			ctx.fillStyle = 'rgba(0,0,0,0.28)';
			ctx.fillRect(0, H * 0.55, W, H * 0.45);
		}
	}

	function coverImage(img, withFlatOverlay) {
		const scale = Math.max(W / img.width, H / img.height);
		const tw = img.width * scale;
		const th = img.height * scale;
		ctx.drawImage(img, (W - tw) / 2, (H - th) / 2, tw, th);
		if (withFlatOverlay) {
			ctx.fillStyle = 'rgba(0,0,0,0.35)';
			ctx.fillRect(0, 0, W, H);
		}
	}

	/** Máscara de plantilla sobre foto real: degradado inferior para legibilidad. */
	function drawPhotoMask(presetId) {
		const top = ctx.createLinearGradient(0, 0, 0, H * 0.45);
		top.addColorStop(0, 'rgba(7, 21, 37, 0.45)');
		top.addColorStop(1, 'rgba(7, 21, 37, 0)');
		ctx.fillStyle = top;
		ctx.fillRect(0, 0, W, H * 0.45);

		const bottom = ctx.createLinearGradient(0, H * 0.35, 0, H);
		if (presetId === 'grad-warm') {
			bottom.addColorStop(0, 'rgba(42, 26, 18, 0)');
			bottom.addColorStop(0.45, 'rgba(42, 26, 18, 0.55)');
			bottom.addColorStop(1, 'rgba(20, 10, 6, 0.88)');
		} else if (presetId === 'grad-light') {
			bottom.addColorStop(0, 'rgba(244, 248, 251, 0)');
			bottom.addColorStop(0.5, 'rgba(11, 110, 168, 0.35)');
			bottom.addColorStop(1, 'rgba(11, 60, 100, 0.72)');
		} else {
			bottom.addColorStop(0, 'rgba(7, 21, 37, 0)');
			bottom.addColorStop(0.4, 'rgba(7, 21, 37, 0.5)');
			bottom.addColorStop(1, 'rgba(5, 12, 22, 0.9)');
		}
		ctx.fillStyle = bottom;
		ctx.fillRect(0, H * 0.35, W, H * 0.65);

		ctx.save();
		ctx.globalAlpha = 0.1;
		ctx.strokeStyle = '#ffffff';
		ctx.lineWidth = 2;
		for (let i = 0; i < 6; i++) {
			ctx.beginPath();
			ctx.moveTo(-80 + i * 200, H);
			ctx.lineTo(220 + i * 200, 0);
			ctx.stroke();
		}
		ctx.restore();
	}

	function wrapText(text, x, y, maxWidth, lineHeight, align) {
		const words = String(text || '').split(/\s+/).filter(Boolean);
		const lines = [];
		let line = '';
		words.forEach(function (word) {
			const test = line ? line + ' ' + word : word;
			if (ctx.measureText(test).width > maxWidth && line) {
				lines.push(line);
				line = word;
			} else {
				line = test;
			}
		});
		if (line) lines.push(line);
		ctx.textAlign = align;
		const drawX = align === 'center' ? x + maxWidth / 2 : align === 'right' ? x + maxWidth : x;
		lines.forEach(function (l, i) {
			ctx.fillText(l, drawX, y + i * lineHeight);
		});
		return lines.length * lineHeight;
	}

	function drawLogo() {
		if (state.logoMode === 'off' || !state.logoImage) return;
		const img = state.logoImage;
		if (state.logoMode === 'watermark') {
			ctx.save();
			ctx.globalAlpha = 0.16;
			const tw = W * 0.55;
			const th = tw * (img.height / img.width);
			ctx.drawImage(img, (W - tw) / 2, (H - th) / 2, tw, th);
			ctx.restore();
			return;
		}
		const tw = 220;
		const th = tw * (img.height / img.width);
		ctx.drawImage(img, 64, 56, tw, th);
	}

	function render() {
		ctx.clearRect(0, 0, W, H);
		const usingPhoto = !!state.photoImage;
		if (usingPhoto) {
			coverImage(state.photoImage, false);
			drawPhotoMask(state.maskPreset || state.backgroundId || 'grad-navy');
		} else {
			const bg = backgrounds.find(function (b) { return b.id === state.backgroundId; });
			if (bg && bg.type === 'image' && state.bgImage) {
				coverImage(state.bgImage, true);
			} else {
				drawPresetBackground(state.backgroundId || 'grad-navy');
			}
		}

		drawLogo();

		const lightText = !(state.maskPreset === 'grad-light' && !usingPhoto && state.backgroundId === 'grad-light');
		const textColor = lightText ? state.color : '#1f2328';
		const accent = state.accent || '#f47b20';
		const pad = 72;
		const maxW = W - pad * 2;
		let y = H * 0.42;

		ctx.fillStyle = accent;
		ctx.fillRect(pad, y - 36, 86, 8);

		ctx.fillStyle = textColor;
		ctx.font = '700 ' + state.fontSize + 'px "Segoe UI", Calibri, Arial, sans-serif';
		y += wrapText(state.title, pad, y + state.fontSize * 0.2, maxW, state.fontSize * 0.95, state.align);
		y += 28;

		ctx.globalAlpha = 0.92;
		ctx.font = '400 36px "Segoe UI", Calibri, Arial, sans-serif';
		y += wrapText(state.description, pad, y, maxW, 46, state.align);
		y += 40;
		ctx.globalAlpha = 1;

		ctx.font = '700 28px "Segoe UI", Calibri, Arial, sans-serif';
		const cta = String(state.cta || '');
		const ctaW = Math.min(maxW, ctx.measureText(cta).width + 64);
		const ctaX = state.align === 'center' ? (W - ctaW) / 2 : state.align === 'right' ? W - pad - ctaW : pad;
		const ctaY = Math.min(H - 160, y + 10);
		roundRect(ctaX, ctaY, ctaW, 64, 32);
		ctx.fillStyle = accent;
		ctx.fill();
		ctx.fillStyle = '#ffffff';
		ctx.textAlign = 'center';
		ctx.fillText(cta, ctaX + ctaW / 2, ctaY + 42);

		state.extraLayers.forEach(function (layer) {
			ctx.fillStyle = layer.color || '#ffffff';
			ctx.font = (layer.bold ? '700 ' : '400 ') + (layer.size || 32) + 'px "Segoe UI", Calibri, Arial, sans-serif';
			ctx.textAlign = layer.align || 'left';
			ctx.fillText(layer.text, layer.x, layer.y);
		});

		ctx.textAlign = 'left';
		ctx.font = '600 22px "Segoe UI", Calibri, Arial, sans-serif';
		ctx.fillStyle = lightText ? 'rgba(255,255,255,0.75)' : 'rgba(11,110,168,0.9)';
		ctx.fillText('Mizo Ingeniería · Hardware de grado profesional', pad, H - 56);
	}

	function roundRect(x, y, w, h, r) {
		ctx.beginPath();
		ctx.moveTo(x + r, y);
		ctx.arcTo(x + w, y, x + w, y + h, r);
		ctx.arcTo(x + w, y + h, x, y + h, r);
		ctx.arcTo(x, y + h, x, y, r);
		ctx.arcTo(x, y, x + w, y, r);
		ctx.closePath();
	}

	function clearPhoto() {
		state.photoImage = null;
		state.photoId = null;
		state.photoUrl = '';
	}

	function applyTemplate(tpl) {
		if (!tpl) return;
		state.templateId = tpl.id;
		state.backgroundId = tpl.background;
		state.maskPreset = tpl.background;
		state.accent = tpl.accent;
		state.title = tpl.fields.title;
		state.description = tpl.fields.description;
		state.cta = tpl.fields.cta;
		state.category = tpl.category;
		clearPhoto();
		if (els.title) els.title.value = state.title;
		if (els.description) els.description.value = state.description;
		if (els.cta) els.cta.value = state.cta;
		if (els.category) els.category.value = state.category;
		if (els.resourceTitle) els.resourceTitle.value = tpl.name + ' — ' + tpl.fields.title.slice(0, 40);
		syncBgImage().then(render);
		paintTemplateCards();
		paintBgCards();
		paintStock();
	}

	function paintTemplateCards() {
		if (!els.templateGrid) return;
		els.templateGrid.innerHTML = templates.map(function (tpl) {
			return '<button type="button" class="mkt-studio-card' + (tpl.id === state.templateId ? ' is-on' : '') + '" data-template-id="' + tpl.id + '">' +
				'<strong>' + escapeHtml(tpl.name) + '</strong>' +
				'<span>' + escapeHtml(tpl.description) + '</span>' +
				'</button>';
		}).join('');
		els.templateGrid.querySelectorAll('[data-template-id]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				const tpl = templates.find(function (t) { return t.id === btn.getAttribute('data-template-id'); });
				applyTemplate(tpl);
			});
		});
	}

	function paintBgCards() {
		if (!els.bgGrid) return;
		els.bgGrid.innerHTML = backgrounds.map(function (bg) {
			const selected = !state.photoId && bg.id === state.backgroundId;
			const swatch = bg.type === 'image'
				? 'style="background-image:url(\'' + bg.url + '\')"'
				: 'data-preset="' + bg.id + '"';
			return '<button type="button" class="mkt-bg-swatch' + (selected ? ' is-on' : '') + '" data-bg-id="' + bg.id + '" title="' + escapeHtml(bg.name) + '">' +
				'<span class="mkt-bg-thumb" ' + swatch + '></span>' +
				'<small>' + escapeHtml(bg.name) + '</small>' +
				'</button>';
		}).join('');
		els.bgGrid.querySelectorAll('[data-bg-id]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				clearPhoto();
				state.backgroundId = btn.getAttribute('data-bg-id');
				if (String(state.backgroundId).indexOf('grad-') === 0) {
					state.maskPreset = state.backgroundId;
				}
				paintBgCards();
				paintStock();
				syncBgImage().then(render);
			});
		});
		els.bgGrid.querySelectorAll('[data-preset]').forEach(function (el) {
			el.style.background = presetCss(el.getAttribute('data-preset'));
		});
	}

	function presetCss(id) {
		if (id === 'grad-warm') return 'linear-gradient(160deg,#2a1a12,#f47b20)';
		if (id === 'grad-corporate') return 'linear-gradient(160deg,#0b1c2c,#1c9bd8)';
		if (id === 'grad-slate') return 'linear-gradient(160deg,#1f2328,#4a5560)';
		if (id === 'grad-light') return 'linear-gradient(160deg,#f4f8fb,#d7e7f2)';
		return 'linear-gradient(160deg,#071525,#1c9bd8)';
	}

	function filteredStock() {
		if (!state.stockFilter) return stock;
		return stock.filter(function (item) { return item.category === state.stockFilter; });
	}

	function paintStockFilters() {
		if (!els.stockFilters) return;
		const cats = [''].concat(stockCategories);
		els.stockFilters.innerHTML = cats.map(function (cat) {
			const label = cat || 'Todas';
			const on = state.stockFilter === cat ? ' is-on' : '';
			return '<button type="button" class="mkt-filter' + on + '" data-stock-cat="' + escapeHtml(cat) + '">' + escapeHtml(label) + '</button>';
		}).join('');
		els.stockFilters.querySelectorAll('[data-stock-cat]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				state.stockFilter = btn.getAttribute('data-stock-cat') || '';
				paintStockFilters();
				paintStock();
			});
		});
	}

	function paintStock() {
		if (!els.stockGrid) return;
		const items = filteredStock();
		if (!items.length) {
			els.stockGrid.innerHTML = '<p class="muted">No hay fotos en esta categoría. Sube stock en Biblioteca / Stock Mizo.</p>';
			return;
		}
		els.stockGrid.innerHTML = items.map(function (item) {
			const on = String(state.photoId) === String(item.id) ? ' is-on' : '';
			const thumb = item.thumb || item.url;
			return '<button type="button" class="mkt-stock-swatch' + on + '" data-stock-id="' + item.id + '" title="' + escapeHtml(item.title) + '">' +
				'<span class="mkt-stock-thumb" style="background-image:url(\'' + thumb.replace(/'/g, '%27') + '\')"></span>' +
				'<small>' + escapeHtml(item.category) + '</small>' +
				'<strong>' + escapeHtml(item.title) + '</strong>' +
				'</button>';
		}).join('');
		els.stockGrid.querySelectorAll('[data-stock-id]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				const id = btn.getAttribute('data-stock-id');
				const item = stock.find(function (s) { return String(s.id) === String(id); });
				if (!item) return;
				selectStock(item);
			});
		});
	}

	function selectStock(item) {
		state.photoId = item.id;
		state.photoUrl = item.url;
		setStatus('Cargando foto…');
		const tpl = currentTemplate();
		if (tpl) {
			state.maskPreset = tpl.background;
			state.accent = tpl.accent;
		}
		const cached = bgCache[item.url];
		const done = function (img) {
			state.photoImage = img;
			paintBgCards();
			paintStock();
			setStatus('Foto aplicada al flyer.');
			render();
		};
		if (cached) {
			done(cached);
			return;
		}
		loadImage(item.url).then(function (img) {
			bgCache[item.url] = img;
			done(img);
		}).catch(function () {
			setStatus('No se pudo cargar la foto.', true);
		});
	}

	function paintLayers() {
		if (!els.layers) return;
		if (!state.extraLayers.length) {
			els.layers.innerHTML = '<p class="muted">Sin capas extra. Agrega un texto libre si lo necesitas.</p>';
			return;
		}
		els.layers.innerHTML = state.extraLayers.map(function (layer, i) {
			return '<div class="mkt-layer-row">' +
				'<span>' + escapeHtml(layer.text) + '</span>' +
				'<button type="button" class="btn-danger-text" data-del-layer="' + i + '">Quitar</button>' +
				'</div>';
		}).join('');
		els.layers.querySelectorAll('[data-del-layer]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				state.extraLayers.splice(parseInt(btn.getAttribute('data-del-layer'), 10), 1);
				paintLayers();
				render();
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

	function syncBgImage() {
		if (state.photoUrl) {
			return Promise.resolve();
		}
		const bg = backgrounds.find(function (b) { return b.id === state.backgroundId; });
		if (!bg || bg.type !== 'image' || !bg.url) {
			state.bgImage = null;
			return Promise.resolve();
		}
		if (bgCache[bg.url]) {
			state.bgImage = bgCache[bg.url];
			return Promise.resolve();
		}
		return loadImage(bg.url).then(function (img) {
			bgCache[bg.url] = img;
			state.bgImage = img;
		}).catch(function () {
			state.bgImage = null;
		});
	}

	function bindInputs() {
		[['title', 'title'], ['description', 'description'], ['cta', 'cta']].forEach(function (pair) {
			const el = els[pair[0]];
			if (!el) return;
			el.addEventListener('input', function () {
				state[pair[1]] = el.value;
				render();
			});
		});
		if (els.category) {
			els.category.addEventListener('change', function () { state.category = els.category.value; });
		}
		if (els.fontSize) {
			els.fontSize.addEventListener('input', function () {
				state.fontSize = parseInt(els.fontSize.value, 10) || 72;
				render();
			});
		}
		if (els.align) {
			els.align.addEventListener('change', function () {
				state.align = els.align.value;
				render();
			});
		}
		if (els.color) {
			els.color.addEventListener('input', function () {
				state.color = els.color.value;
				render();
			});
		}
		if (els.logoMode) {
			els.logoMode.addEventListener('change', function () {
				state.logoMode = els.logoMode.value;
				render();
			});
		}
		if (els.addText) {
			els.addText.addEventListener('click', function () {
				const text = (els.extraText && els.extraText.value || '').trim();
				if (!text) return;
				state.extraLayers.push({
					text: text,
					x: 72,
					y: 220 + state.extraLayers.length * 48,
					size: 34,
					color: state.color,
					align: 'left',
					bold: true,
				});
				els.extraText.value = '';
				paintLayers();
				render();
			});
		}
		if (els.save) {
			els.save.addEventListener('click', saveDesign);
		}
	}

	function exportDataUrl(format) {
		if (format === 'jpg' || format === 'jpeg' || format === 'pdf') {
			const tmp = document.createElement('canvas');
			tmp.width = W;
			tmp.height = H;
			const tctx = tmp.getContext('2d');
			tctx.fillStyle = '#0b1c2c';
			tctx.fillRect(0, 0, W, H);
			tctx.drawImage(canvas, 0, 0);
			return tmp.toDataURL('image/jpeg', 0.92);
		}
		return canvas.toDataURL('image/png');
	}

	function saveDesign() {
		const format = (els.format && els.format.value) || 'png';
		const title = (els.resourceTitle && els.resourceTitle.value.trim()) || state.title || 'Flyer Mizo';
		const description = (els.resourceDesc && els.resourceDesc.value.trim()) || state.description || '';
		const category = (els.category && els.category.value) || state.category;
		setStatus('Generando archivo…');
		els.save.disabled = true;
		const image = exportDataUrl(format);
		const body = new FormData();
		body.append('_csrf', csrf);
		body.append('title', title);
		body.append('description', description);
		body.append('category', category);
		body.append('format', format);
		body.append('image', image);
		fetch(saveUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
		})
			.then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
			.then(function (result) {
				if (!result.ok || !result.data.ok) {
					throw new Error((result.data && result.data.error) || 'No se pudo guardar.');
				}
				setStatus(result.data.message || 'Guardado.');
				if (result.data.download) {
					setTimeout(function () { window.location.href = result.data.download; }, 400);
					setTimeout(function () { window.location.reload(); }, 1200);
				} else {
					window.location.reload();
				}
			})
			.catch(function (err) {
				setStatus(err.message || 'Error al guardar.', true);
				els.save.disabled = false;
			});
	}

	bindInputs();
	paintTemplateCards();
	paintBgCards();
	paintStockFilters();
	paintStock();
	paintLayers();
	if (els.title) els.title.value = state.title;
	if (els.description) els.description.value = state.description;
	if (els.cta) els.cta.value = state.cta;
	if (els.category) els.category.value = state.category;
	if (els.resourceTitle && currentTemplate()) {
		els.resourceTitle.value = currentTemplate().name;
	}

	const logoSrc = brand.logo || '/mizo-logo-footer.png';
	loadImage(logoSrc)
		.catch(function () { return loadImage(brand.logoFallback || '/mizo-logo.svg'); })
		.then(function (img) { state.logoImage = img; })
		.catch(function () { state.logoImage = null; })
		.then(function () { return syncBgImage(); })
		.then(render);
})();
