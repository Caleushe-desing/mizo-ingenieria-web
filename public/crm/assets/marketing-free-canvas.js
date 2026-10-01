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
	const shell = root.querySelector('[data-canvas-shell]');
	const canvas = new fabric.Canvas(canvasEl, {
		width: W,
		height: H,
		backgroundColor: '#0b1c2c',
		preserveObjectStacking: true,
		selection: true,
		stopContextMenu: true,
	});

	const statusEl = root.querySelector('[data-studio-status]');
	const stockFilters = root.querySelector('[data-stock-filters]');
	const stockGallery = root.querySelector('[data-stock-gallery]');
	const tplFilters = root.querySelector('[data-tpl-filters]');
	const tplGallery = root.querySelector('[data-tpl-gallery]');
	const fontSelect = root.querySelector('[data-font]');
	const fontSize = root.querySelector('[data-font-size]');
	const textColor = root.querySelector('[data-text-color]');
	const bgColor = root.querySelector('[data-bg-color]');
	const shapeColor = root.querySelector('[data-shape-color]');
	const removeBgBtn = root.querySelector('[data-remove-bg]');
	const previewModal = root.querySelector('[data-preview-modal]');
	const previewImage = root.querySelector('[data-preview-image]');
	const previewCaption = root.querySelector('[data-preview-caption]');

	let stockFilter = '';
	let tplFilter = 'minimalistas';
	let activeTpl = '';
	let previewFormat = 'story';
	let displayZoom = 1;
	let removeBgModule = null;

	const TPL_CATS = [
		{ id: 'minimalistas', label: 'Minimalistas' },
		{ id: 'geometricos', label: 'Geométricos' },
		{ id: 'corporativos', label: 'Corporativos' },
		{ id: 'explosivos', label: 'Explosivos / Técnicos' },
	];

	const PREVIEW_META = {
		story: { label: 'Instagram Story / WhatsApp · 9:16', tw: 1080, th: 1920, phoneClass: 'is-story' },
		post: { label: 'Post cuadrado · 1:1', tw: 1080, th: 1080, phoneClass: 'is-post' },
		feed: { label: 'Feed vertical · 4:5', tw: 1080, th: 1350, phoneClass: 'is-feed' },
	};

	function setStatus(msg, isError) {
		if (!statusEl) return;
		statusEl.textContent = msg || '';
		statusEl.classList.toggle('is-error', !!isError);
	}

	function selected() {
		return canvas.getActiveObject();
	}

	function isText(obj) {
		return obj && (obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox');
	}

	function isImage(obj) {
		return obj && obj.type === 'image';
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
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

	function fitToScreen() {
		if (!shell) return;
		const pad = 8;
		const availW = Math.max(120, shell.clientWidth - pad * 2);
		const availH = Math.max(120, shell.clientHeight - pad * 2);
		const zoom = Math.min(availW / W, availH / H);
		displayZoom = Math.max(0.08, Math.min(1, zoom));
		canvas.setDimensions({ width: W * displayZoom, height: H * displayZoom });
		canvas.setZoom(displayZoom);
		canvas.calcOffset();
		canvas.requestRenderAll();
	}

	function withExportZoom(fn) {
		const prev = canvas.getZoom();
		const prevW = canvas.getWidth();
		const prevH = canvas.getHeight();
		canvas.setZoom(1);
		canvas.setDimensions({ width: W, height: H });
		canvas.requestRenderAll();
		let result;
		try {
			result = fn();
		} finally {
			canvas.setZoom(prev);
			canvas.setDimensions({ width: prevW, height: prevH });
			canvas.requestRenderAll();
		}
		return result;
	}

	function exportDataUrl(format, quality) {
		return withExportZoom(function () {
			return canvas.toDataURL({
				format: format === 'jpg' || format === 'jpeg' ? 'jpeg' : 'png',
				quality: quality == null ? 0.92 : quality,
				multiplier: 1,
			});
		});
	}

	function exportJson() {
		return withExportZoom(function () {
			return JSON.stringify(canvas.toJSON(['selectable', 'evented', 'name']));
		});
	}

	/* ---------- Editable design templates (Fabric objects) ---------- */
	function rect(opts) {
		return new fabric.Rect(Object.assign({
			originX: 'left', originY: 'top', selectable: true, evented: true,
		}, opts));
	}

	function tri(opts) {
		return new fabric.Triangle(Object.assign({
			originX: 'left', originY: 'top', selectable: true, evented: true,
		}, opts));
	}

	function circle(opts) {
		return new fabric.Circle(Object.assign({
			originX: 'left', originY: 'top', selectable: true, evented: true,
		}, opts));
	}

	function line(x1, y1, x2, y2, opts) {
		return new fabric.Line([x1, y1, x2, y2], Object.assign({
			selectable: true, evented: true, strokeLineCap: 'round',
		}, opts));
	}

	function txt(text, opts) {
		return new fabric.IText(text, Object.assign({
			fontFamily: 'Montserrat',
			fontWeight: '800',
			fill: '#ffffff',
			editable: true,
			selectable: true,
			evented: true,
		}, opts));
	}

	const TEMPLATES = [
		{
			id: 'min-editorial', cat: 'minimalistas', name: 'Editorial limpio',
			swatch: 'linear-gradient(180deg,#f7f5f2 70%,#ebe7e1)',
			build: function () {
				canvas.backgroundColor = '#f7f5f2';
				canvas.add(rect({ left: 0, top: H * 0.68, width: W, height: H * 0.32, fill: '#ebe7e1', name: 'bloque-inferior' }));
				canvas.add(rect({ left: 72, top: H * 0.68, width: 120, height: 8, fill: '#f47b20', name: 'acento' }));
				canvas.add(txt('MIZO', { left: 72, top: 80, fontSize: 42, fill: '#0b6ea8', fontFamily: 'Bebas Neue', fontWeight: '400', name: 'marca' }));
				canvas.add(txt('Ingeniería\nque se ve', { left: 72, top: 200, fontSize: 92, fill: '#1f2328', fontFamily: 'Montserrat', fontWeight: '900', lineHeight: 0.95, name: 'titulo' }));
				canvas.add(txt('Soluciones técnicas con sello profesional.', { left: 72, top: H * 0.74, fontSize: 34, fill: '#4a5560', fontFamily: 'Poppins', fontWeight: '600', name: 'subtitulo' }));
			},
		},
		{
			id: 'min-blanco', cat: 'minimalistas', name: 'Marco blanco',
			swatch: 'linear-gradient(180deg,#ffffff,#e8eef3)',
			build: function () {
				canvas.backgroundColor = '#eef2f5';
				canvas.add(rect({ left: 48, top: 48, width: W - 96, height: H - 96, fill: '#ffffff', rx: 28, ry: 28, name: 'tarjeta' }));
				canvas.add(rect({ left: 96, top: 140, width: 90, height: 10, fill: '#0b6ea8', name: 'barra' }));
				canvas.add(txt('PROYECTO', { left: 96, top: 180, fontSize: 28, fill: '#0b6ea8', fontFamily: 'Oswald', fontWeight: '600', charSpacing: 120, name: 'kicker' }));
				canvas.add(txt('Calidad\ninstalada', { left: 96, top: 240, fontSize: 88, fill: '#111418', fontFamily: 'Archivo Black', fontWeight: '400', lineHeight: 0.95, name: 'titulo' }));
				canvas.add(txt('Diseño minimalista · Edita cada bloque', { left: 96, top: H - 220, fontSize: 30, fill: '#5a6570', fontFamily: 'Space Grotesk', fontWeight: '600', name: 'pie' }));
			},
		},
		{
			id: 'min-noir', cat: 'minimalistas', name: 'Noir tipográfico',
			swatch: 'linear-gradient(180deg,#14181d,#0a0c0f)',
			build: function () {
				canvas.backgroundColor = '#14181d';
				canvas.add(rect({ left: 0, top: 0, width: W, height: H * 0.36, fill: '#111418', name: 'banda' }));
				canvas.add(rect({ left: 72, top: H * 0.4, width: 140, height: 10, fill: '#f47b20', name: 'acento' }));
				canvas.add(txt('MIZO INGENIERÍA', { left: 72, top: 90, fontSize: 30, fill: '#1c9bd8', fontFamily: 'Rajdhani', fontWeight: '700', charSpacing: 80, name: 'marca' }));
				canvas.add(txt('IMPACTO\nVISUAL', { left: 72, top: 200, fontSize: 110, fill: '#ffffff', fontFamily: 'Anton', fontWeight: '400', lineHeight: 0.9, name: 'titulo' }));
				canvas.add(txt('Tipografía de alto contraste para redes.', { left: 72, top: H * 0.48, fontSize: 32, fill: '#c5ccd4', fontFamily: 'Poppins', fontWeight: '600', name: 'subtitulo' }));
			},
		},
		{
			id: 'geo-split', cat: 'geometricos', name: 'Split azul/naranja',
			swatch: 'linear-gradient(135deg,#0b6ea8 55%,#f47b20 55%)',
			build: function () {
				canvas.backgroundColor = '#0b1c2c';
				canvas.add(rect({ left: 0, top: 0, width: W * 0.58, height: H, fill: '#0b6ea8', name: 'panel-azul' }));
				const orange = new fabric.Polygon([
					{ x: W * 0.45, y: 0 }, { x: W, y: 0 }, { x: W, y: H }, { x: W * 0.62, y: H },
				], { fill: '#f47b20', selectable: true, name: 'panel-naranja' });
				canvas.add(orange);
				canvas.add(rect({ left: 0, top: H * 0.72, width: W, height: H * 0.28, fill: 'rgba(0,0,0,0.28)', name: 'velo' }));
				canvas.add(txt('MIZO', { left: 70, top: 90, fontSize: 48, fill: '#ffffff', fontFamily: 'Bebas Neue', fontWeight: '400', name: 'marca' }));
				canvas.add(txt('Diseño\ngeométrico', { left: 70, top: 280, fontSize: 82, fill: '#ffffff', fontFamily: 'Montserrat', fontWeight: '900', lineHeight: 0.95, name: 'titulo' }));
				canvas.add(txt('Mueve y recolorea cada bloque.', { left: 70, top: H * 0.78, fontSize: 30, fill: '#ffffff', fontFamily: 'Oswald', fontWeight: '600', name: 'pie' }));
			},
		},
		{
			id: 'geo-frame', cat: 'geometricos', name: 'Doble marco',
			swatch: 'linear-gradient(180deg,#1a222b,#0b6ea8)',
			build: function () {
				canvas.backgroundColor = '#1a222b';
				canvas.add(rect({ left: 56, top: 56, width: W - 112, height: H - 112, fill: 'transparent', stroke: '#1c9bd8', strokeWidth: 10, name: 'marco-1' }));
				canvas.add(rect({ left: 88, top: 88, width: W - 176, height: H - 176, fill: 'transparent', stroke: '#f47b20', strokeWidth: 4, name: 'marco-2' }));
				canvas.add(rect({ left: 56, top: H * 0.7, width: W - 112, height: H * 0.18, fill: 'rgba(28,155,216,0.2)', name: 'banda' }));
				canvas.add(txt('MARCO\nMODERNO', { left: 120, top: 220, fontSize: 96, fill: '#ffffff', fontFamily: 'Oswald', fontWeight: '700', lineHeight: 0.92, name: 'titulo' }));
				canvas.add(txt('Formas 100% seleccionables', { left: 120, top: H * 0.76, fontSize: 32, fill: '#e8f6fc', fontFamily: 'Space Grotesk', fontWeight: '700', name: 'pie' }));
			},
		},
		{
			id: 'geo-circles', cat: 'geometricos', name: 'Círculos técnicos',
			swatch: 'radial-gradient(circle at 70% 20%,#1c9bd8,#0b1c2c)',
			build: function () {
				canvas.backgroundColor = '#0b1c2c';
				canvas.add(circle({ left: W * 0.45, top: -80, radius: 280, fill: '#0b6ea8', opacity: 0.9, name: 'circulo-1' }));
				canvas.add(circle({ left: W * 0.62, top: H * 0.55, radius: 180, fill: '#f47b20', opacity: 0.85, name: 'circulo-2' }));
				canvas.add(circle({ left: -40, top: H * 0.7, radius: 140, fill: '#1c9bd8', opacity: 0.5, name: 'circulo-3' }));
				canvas.add(txt('FORMA\nLIBRE', { left: 72, top: 260, fontSize: 100, fill: '#ffffff', fontFamily: 'Anton', fontWeight: '400', lineHeight: 0.9, name: 'titulo' }));
				canvas.add(txt('Círculos · bloques · tipografía', { left: 72, top: H - 180, fontSize: 30, fill: '#d0e8f5', fontFamily: 'Rajdhani', fontWeight: '700', name: 'pie' }));
			},
		},
		{
			id: 'corp-launch', cat: 'corporativos', name: 'Lanzamiento Mizo',
			swatch: 'linear-gradient(160deg,#071525,#0b6ea8)',
			build: function () {
				canvas.backgroundColor = '#071525';
				canvas.add(rect({ left: 0, top: 0, width: W, height: H * 0.42, fill: '#0b6ea8', name: 'hero' }));
				canvas.add(tri({ left: W * 0.55, top: H * 0.28, width: W * 0.55, height: H * 0.35, fill: '#f47b20', angle: -8, name: 'acento-tri' }));
				canvas.add(txt('MIZO', { left: 72, top: 70, fontSize: 44, fill: '#ffffff', fontFamily: 'Bebas Neue', fontWeight: '400', name: 'marca' }));
				canvas.add(txt('Nueva\ncampaña', { left: 72, top: 160, fontSize: 96, fill: '#ffffff', fontFamily: 'Montserrat', fontWeight: '900', lineHeight: 0.92, name: 'titulo' }));
				canvas.add(txt('Corporativo listo para Instagram y LinkedIn.', { left: 72, top: H * 0.55, fontSize: 34, fill: '#d7eaf5', fontFamily: 'Poppins', fontWeight: '600', width: W * 0.75, name: 'subtitulo' }));
				canvas.add(rect({ left: 72, top: H * 0.72, width: 260, height: 72, fill: '#f47b20', rx: 8, ry: 8, name: 'cta-bg' }));
				canvas.add(txt('COTIZA HOY', { left: 98, top: H * 0.735, fontSize: 36, fill: '#ffffff', fontFamily: 'Oswald', fontWeight: '700', name: 'cta' }));
			},
		},
		{
			id: 'corp-service', cat: 'corporativos', name: 'Servicio técnico',
			swatch: 'linear-gradient(180deg,#ffffff 48%,#0b6ea8 48%)',
			build: function () {
				canvas.backgroundColor = '#ffffff';
				canvas.add(rect({ left: 0, top: H * 0.48, width: W, height: H * 0.52, fill: '#0b6ea8', name: 'panel' }));
				canvas.add(rect({ left: 0, top: H * 0.48 - 8, width: W, height: 16, fill: '#f47b20', name: 'linea' }));
				canvas.add(txt('SERVICIO', { left: 72, top: 100, fontSize: 28, fill: '#0b6ea8', fontFamily: 'Oswald', fontWeight: '700', charSpacing: 160, name: 'kicker' }));
				canvas.add(txt('Instalación\ncertificada', { left: 72, top: 160, fontSize: 86, fill: '#111418', fontFamily: 'Archivo Black', fontWeight: '400', lineHeight: 0.95, name: 'titulo' }));
				canvas.add(txt('Equipos · Mantención · Proyectos', { left: 72, top: H * 0.58, fontSize: 34, fill: '#ffffff', fontFamily: 'Barlow Condensed', fontWeight: '700', name: 'lista' }));
				canvas.add(txt('mizo.cl', { left: 72, top: H - 160, fontSize: 40, fill: '#e8f6fc', fontFamily: 'Space Grotesk', fontWeight: '700', name: 'web' }));
			},
		},
		{
			id: 'tech-aurora', cat: 'explosivos', name: 'Aurora técnica',
			swatch: 'linear-gradient(160deg,#071525,#1c9bd8,#f47b20)',
			build: function () {
				canvas.backgroundColor = '#071525';
				canvas.add(rect({ left: 0, top: 0, width: W, height: H, fill: '#0b6ea8', opacity: 0.55, name: 'capa-azul' }));
				canvas.add(tri({ left: -80, top: H * 0.35, width: W * 1.2, height: H * 0.8, fill: '#f47b20', opacity: 0.75, angle: -18, name: 'haz' }));
				canvas.add(rect({ left: 0, top: H * 0.55, width: W, height: H * 0.45, fill: 'rgba(0,0,0,0.4)', name: 'velo' }));
				canvas.add(txt('POTENCIA', { left: 64, top: 180, fontSize: 118, fill: '#ffffff', fontFamily: 'Anton', fontWeight: '400', name: 'titulo' }));
				canvas.add(txt('TÉCNICA', { left: 64, top: 320, fontSize: 118, fill: '#f47b20', fontFamily: 'Anton', fontWeight: '400', name: 'titulo-2' }));
				canvas.add(txt('Gradientes y contraste Mizo · 100% editable', { left: 64, top: H * 0.7, fontSize: 32, fill: '#ffffff', fontFamily: 'Montserrat', fontWeight: '700', name: 'pie' }));
			},
		},
		{
			id: 'tech-grid', cat: 'explosivos', name: 'Grid neon',
			swatch: 'linear-gradient(180deg,#041018,#0b6ea8)',
			build: function () {
				canvas.backgroundColor = '#041018';
				for (let x = 0; x < W; x += 90) {
					canvas.add(line(x, 0, x, H, { stroke: 'rgba(28,155,216,0.35)', strokeWidth: 2, name: 'grid-v' }));
				}
				for (let y = 0; y < H; y += 90) {
					canvas.add(line(0, y, W, y, { stroke: 'rgba(28,155,216,0.28)', strokeWidth: 2, name: 'grid-h' }));
				}
				canvas.add(rect({ left: 0, top: H * 0.62, width: W, height: H * 0.38, fill: 'rgba(244,123,32,0.55)', name: 'glow' }));
				canvas.add(txt('TECH\nGRID', { left: 70, top: 220, fontSize: 120, fill: '#ffffff', fontFamily: 'Bebas Neue', fontWeight: '400', lineHeight: 0.88, name: 'titulo' }));
				canvas.add(txt('Estilo técnico para productos e instalaciones.', { left: 70, top: H * 0.72, fontSize: 30, fill: '#ffffff', fontFamily: 'Rajdhani', fontWeight: '700', name: 'pie' }));
			},
		},
		{
			id: 'tech-pulse', cat: 'explosivos', name: 'Pulso naranja',
			swatch: 'radial-gradient(circle at 25% 20%,#f47b20,#0a1420)',
			build: function () {
				canvas.backgroundColor = '#0a1420';
				canvas.add(circle({ left: W * 0.05, top: -40, radius: 320, fill: '#f47b20', opacity: 0.9, name: 'pulso' }));
				canvas.add(circle({ left: W * 0.15, top: 80, radius: 180, fill: '#0b6ea8', opacity: 0.65, name: 'pulso-2' }));
				canvas.add(rect({ left: 0, top: H * 0.58, width: W, height: H * 0.42, fill: 'rgba(0,0,0,0.45)', name: 'base' }));
				canvas.add(txt('EXPLOTA\nEN REDES', { left: 64, top: H * 0.35, fontSize: 100, fill: '#ffffff', fontFamily: 'Oswald', fontWeight: '700', lineHeight: 0.9, name: 'titulo' }));
				canvas.add(txt('Azul + naranja técnico Mizo', { left: 64, top: H * 0.72, fontSize: 34, fill: '#f47b20', fontFamily: 'Barlow Condensed', fontWeight: '800', name: 'pie' }));
			},
		},
	];

	function applyTemplate(tpl) {
		if (!tpl) return;
		if (!confirm('¿Cargar la plantilla «' + tpl.name + '»? Se reemplaza el contenido actual del lienzo.')) return;
		canvas.clear();
		canvas.backgroundColor = '#0b1c2c';
		tpl.build();
		activeTpl = tpl.id;
		if (bgColor) bgColor.value = toHex(canvas.backgroundColor) || '#0b1c2c';
		paintTemplates();
		canvas.discardActiveObject();
		fitToScreen();
		setStatus('Plantilla «' + tpl.name + '» cargada. Todo es editable.');
	}

	function paintTplFilters() {
		if (!tplFilters) return;
		tplFilters.innerHTML = TPL_CATS.map(function (cat) {
			const on = tplFilter === cat.id ? ' is-on' : '';
			return '<button type="button" class="mkt-filter' + on + '" data-tpl-cat="' + cat.id + '">' + cat.label + '</button>';
		}).join('');
		tplFilters.querySelectorAll('[data-tpl-cat]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				tplFilter = btn.getAttribute('data-tpl-cat') || 'minimalistas';
				paintTplFilters();
				paintTemplates();
			});
		});
	}

	function paintTemplates() {
		if (!tplGallery) return;
		const items = TEMPLATES.filter(function (t) { return t.cat === tplFilter; });
		tplGallery.innerHTML = items.map(function (tpl) {
			const on = activeTpl === tpl.id ? ' is-on' : '';
			return '<button type="button" class="mkt-bg-tpl' + on + '" data-tpl="' + tpl.id + '" title="' + escapeHtml(tpl.name) + '">' +
				'<span class="mkt-bg-tpl-swatch" style="background:' + tpl.swatch + '"></span>' +
				'<strong>' + escapeHtml(tpl.name) + '</strong></button>';
		}).join('');
		tplGallery.querySelectorAll('[data-tpl]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				const tpl = TEMPLATES.find(function (t) { return t.id === btn.getAttribute('data-tpl'); });
				applyTemplate(tpl);
			});
		});
	}

	/* ---------- Shapes ---------- */
	function currentShapeColor() {
		return (shapeColor && shapeColor.value) || '#f47b20';
	}

	function addShape(kind) {
		const fill = currentShapeColor();
		let obj = null;
		if (kind === 'rect') {
			obj = rect({ left: W * 0.25, top: H * 0.3, width: 360, height: 220, fill: fill, name: 'rect' });
		} else if (kind === 'round') {
			obj = rect({ left: W * 0.25, top: H * 0.3, width: 360, height: 220, fill: fill, rx: 28, ry: 28, name: 'bloque' });
		} else if (kind === 'circle') {
			obj = circle({ left: W * 0.35, top: H * 0.3, radius: 140, fill: fill, name: 'circulo' });
		} else if (kind === 'triangle') {
			obj = tri({ left: W * 0.32, top: H * 0.3, width: 280, height: 240, fill: fill, name: 'triangulo' });
		} else if (kind === 'line') {
			obj = line(W * 0.2, H * 0.45, W * 0.8, H * 0.45, { stroke: fill, strokeWidth: 10, name: 'linea' });
		} else if (kind === 'bar') {
			obj = rect({ left: W * 0.15, top: H * 0.42, width: W * 0.7, height: 18, fill: fill, name: 'barra' });
		}
		if (!obj) return;
		canvas.add(obj);
		canvas.setActiveObject(obj);
		canvas.requestRenderAll();
		setStatus('Forma añadida. Arrástrala o cambia el color.');
	}

	/* ---------- Text / objects ---------- */
	function addText() {
		const text = txt('ESCRIBE AQUÍ', {
			left: W * 0.12,
			top: H * 0.35,
			fontSize: 64,
			fontFamily: (fontSelect && fontSelect.value) || 'Montserrat',
			fill: (textColor && textColor.value) || '#ffffff',
			width: W * 0.76,
		});
		canvas.add(text);
		canvas.setActiveObject(text);
		canvas.requestRenderAll();
		syncObjectControls();
	}

	function addLogo() {
		fabric.Image.fromURL(logoUrl, function (img) {
			if (!img) {
				setStatus('No se pudo cargar el logo.', true);
				return;
			}
			img.scaleToWidth(280);
			img.set({ left: 64, top: 48, selectable: true, name: 'logo' });
			canvas.add(img);
			canvas.setActiveObject(img);
			canvas.requestRenderAll();
			syncObjectControls();
		}, { crossOrigin: 'anonymous' });
	}

	function addImageFromUrl(url) {
		setStatus('Cargando imagen…');
		fabric.Image.fromURL(url, function (img) {
			if (!img) {
				setStatus('No se pudo cargar la imagen.', true);
				return;
			}
			const maxSide = Math.min(W, H) * 0.72;
			if (img.width >= img.height) img.scaleToWidth(maxSide);
			else img.scaleToHeight(maxSide);
			img.set({
				left: (W - img.getScaledWidth()) / 2,
				top: (H - img.getScaledHeight()) / 2,
				selectable: true,
				name: 'imagen',
			});
			canvas.add(img);
			canvas.setActiveObject(img);
			canvas.requestRenderAll();
			syncObjectControls();
			setStatus('Imagen insertada. Usa «Quitar fondo» si lo necesitas.');
		}, { crossOrigin: 'anonymous' });
	}

	function deleteSelected() {
		const obj = selected();
		if (!obj) return;
		if (obj.type === 'activeSelection') {
			obj.getObjects().forEach(function (o) { canvas.remove(o); });
			canvas.discardActiveObject();
		} else {
			canvas.remove(obj);
			canvas.discardActiveObject();
		}
		canvas.requestRenderAll();
		syncObjectControls();
	}

	function layerFront() {
		const obj = selected();
		if (!obj) return;
		canvas.bringToFront(obj);
		canvas.requestRenderAll();
	}

	function layerBack() {
		const obj = selected();
		if (!obj) return;
		canvas.sendToBack(obj);
		canvas.requestRenderAll();
	}

	function layerUp() {
		const obj = selected();
		if (!obj) return;
		canvas.bringForward(obj);
		canvas.requestRenderAll();
	}

	function layerDown() {
		const obj = selected();
		if (!obj) return;
		canvas.sendBackwards(obj);
		canvas.requestRenderAll();
	}

	function applyFill(value) {
		const obj = selected();
		if (!obj) return;
		if (isText(obj)) obj.set('fill', value);
		else if (obj.stroke && (!obj.fill || obj.fill === 'transparent' || obj.type === 'line')) obj.set('stroke', value);
		else obj.set('fill', value);
		canvas.requestRenderAll();
	}

	function applyTextProp(prop, value) {
		const obj = selected();
		if (!isText(obj)) return;
		obj.set(prop, value);
		canvas.requestRenderAll();
	}

	function syncObjectControls() {
		const obj = selected();
		if (removeBgBtn) removeBgBtn.disabled = !isImage(obj);
		if (!obj) return;
		if (isText(obj)) {
			if (fontSelect) fontSelect.value = obj.fontFamily || 'Montserrat';
			if (fontSize) fontSize.value = String(obj.fontSize || 64);
			if (textColor) textColor.value = toHex(obj.fill) || '#ffffff';
		} else if (obj.fill && typeof obj.fill === 'string' && obj.fill.charAt(0) === '#') {
			if (textColor) textColor.value = toHex(obj.fill);
			if (shapeColor) shapeColor.value = toHex(obj.fill);
		} else if (obj.stroke && typeof obj.stroke === 'string') {
			if (textColor) textColor.value = toHex(obj.stroke);
			if (shapeColor) shapeColor.value = toHex(obj.stroke);
		}
	}

	function setSolidBackground(color) {
		canvas.setBackgroundImage(null, function () {
			canvas.backgroundColor = color;
			if (bgColor) bgColor.value = color;
			canvas.requestRenderAll();
		});
	}

	function clearCanvas() {
		if (!confirm('¿Empezar un lienzo nuevo? Se pierde lo no guardado.')) return;
		canvas.clear();
		activeTpl = '';
		resourceId = 0;
		root.setAttribute('data-resource-id', '0');
		setSolidBackground('#0b1c2c');
		addText();
		paintTemplates();
		setStatus('Lienzo nuevo listo.');
	}

	/* ---------- Background removal ---------- */
	function blobFromDataUrl(dataUrl) {
		const parts = dataUrl.split(',');
		const mime = (parts[0].match(/:(.*?);/) || [])[1] || 'image/png';
		const bin = atob(parts[1]);
		const arr = new Uint8Array(bin.length);
		for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
		return new Blob([arr], { type: mime });
	}

	function simpleRemoveBackground(imgEl) {
		const w = imgEl.naturalWidth || imgEl.width;
		const h = imgEl.naturalHeight || imgEl.height;
		const off = document.createElement('canvas');
		off.width = w;
		off.height = h;
		const ctx = off.getContext('2d', { willReadFrequently: true });
		ctx.drawImage(imgEl, 0, 0);
		const data = ctx.getImageData(0, 0, w, h);
		const px = data.data;
		const samples = [];
		const corners = [[2, 2], [w - 3, 2], [2, h - 3], [w - 3, h - 3], [Math.floor(w / 2), 2], [2, Math.floor(h / 2)]];
		corners.forEach(function (p) {
			const i = (p[1] * w + p[0]) * 4;
			samples.push([px[i], px[i + 1], px[i + 2]]);
		});
		const avg = samples.reduce(function (a, s) {
			return [a[0] + s[0], a[1] + s[1], a[2] + s[2]];
		}, [0, 0, 0]).map(function (v) { return v / samples.length; });
		const threshold = 48;
		for (let i = 0; i < px.length; i += 4) {
			const dr = px[i] - avg[0];
			const dg = px[i + 1] - avg[1];
			const db = px[i + 2] - avg[2];
			const dist = Math.sqrt(dr * dr + dg * dg + db * db);
			if (dist < threshold) px[i + 3] = 0;
			else if (dist < threshold + 28) px[i + 3] = Math.round(255 * ((dist - threshold) / 28));
		}
		ctx.putImageData(data, 0, 0);
		return off.toDataURL('image/png');
	}

	function loadRemoveBgLib() {
		if (removeBgModule) return Promise.resolve(removeBgModule);
		return import('https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.5.5/+esm')
			.then(function (mod) {
				removeBgModule = mod;
				return mod;
			});
	}

	function removeBackgroundSelected() {
		const obj = selected();
		if (!isImage(obj)) {
			setStatus('Selecciona una imagen para quitar el fondo.', true);
			return;
		}
		setStatus('Quitando fondo… puede tardar unos segundos.');
		if (removeBgBtn) removeBgBtn.disabled = true;

		const src = (obj.getSrc && obj.getSrc()) || (obj._element && obj._element.src) || '';
		const el = obj.getElement && obj.getElement();
		if (!src && !el) {
			setStatus('No se pudo leer la imagen.', true);
			if (removeBgBtn) removeBgBtn.disabled = false;
			return;
		}

		const applyPng = function (pngUrl) {
			fabric.Image.fromURL(pngUrl, function (img) {
				if (!img) {
					setStatus('No se pudo aplicar la imagen sin fondo.', true);
					if (removeBgBtn) removeBgBtn.disabled = false;
					return;
				}
				img.set({
					left: obj.left,
					top: obj.top,
					scaleX: obj.scaleX,
					scaleY: obj.scaleY,
					angle: obj.angle || 0,
					selectable: true,
					name: (obj.name || 'imagen') + '-sin-fondo',
				});
				const idx = canvas.getObjects().indexOf(obj);
				canvas.remove(obj);
				if (typeof canvas.insertAt === 'function') {
					canvas.insertAt(img, Math.max(0, idx));
				} else {
					canvas.add(img);
				}
				canvas.setActiveObject(img);
				canvas.requestRenderAll();
				syncObjectControls();
				setStatus('Fondo eliminado. Revisa bordes y redimensiona si hace falta.');
				if (removeBgBtn) removeBgBtn.disabled = false;
			}, { crossOrigin: 'anonymous' });
		};

		const fallbackLocal = function () {
			try {
				if (el) {
					applyPng(simpleRemoveBackground(el));
					return;
				}
			} catch (e) { /* continue */ }
			const imgEl = new Image();
			imgEl.crossOrigin = 'anonymous';
			imgEl.onload = function () {
				try {
					applyPng(simpleRemoveBackground(imgEl));
				} catch (err) {
					setStatus('No se pudo quitar el fondo de esta imagen.', true);
					if (removeBgBtn) removeBgBtn.disabled = false;
				}
			};
			imgEl.onerror = function () {
				setStatus('No se pudo procesar la imagen (CORS o formato).', true);
				if (removeBgBtn) removeBgBtn.disabled = false;
			};
			imgEl.src = src;
		};

		const input = src.indexOf('data:') === 0 ? blobFromDataUrl(src) : (src || el);
		loadRemoveBgLib()
			.then(function (mod) {
				const removeBackground = mod.removeBackground || (mod.default && mod.default.removeBackground);
				if (!removeBackground) throw new Error('API no disponible');
				return removeBackground(input, {
					output: { format: 'image/png', quality: 0.9 },
				});
			})
			.then(function (blob) {
				applyPng(URL.createObjectURL(blob));
			})
			.catch(fallbackLocal);
	}

	/* ---------- Preview ---------- */
	function exportForPreview(format) {
		const meta = PREVIEW_META[format] || PREVIEW_META.story;
		const src = exportDataUrl('png', 1);
		return new Promise(function (resolve) {
			const img = new Image();
			img.onload = function () {
				const out = document.createElement('canvas');
				out.width = meta.tw;
				out.height = meta.th;
				const ctx = out.getContext('2d');
				ctx.fillStyle = canvas.backgroundColor || '#0b1c2c';
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
			exportForPreview(previewFormat).then(function (url) {
				previewImage.src = url;
			});
		}
	}

	/* ---------- Save ---------- */
	function saveDesign() {
		const title = (root.querySelector('[data-save-title]') || {}).value || 'Diseño Mizo';
		const category = (root.querySelector('[data-save-category]') || {}).value || 'Redes Sociales';
		const kind = (root.querySelector('[data-save-kind]') || {}).value || 'flyer';
		const format = (root.querySelector('[data-save-format]') || {}).value || 'jpg';
		const description = (root.querySelector('[data-save-desc]') || {}).value || '';
		canvas.discardActiveObject();
		canvas.requestRenderAll();
		setStatus('Guardando diseño…');
		const dataUrl = exportDataUrl(format, format === 'jpg' ? 0.92 : 1);
		const json = exportJson();
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

	/* ---------- Stock ---------- */
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

	/* ---------- Events ---------- */
	root.querySelectorAll('[data-act]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			const act = btn.getAttribute('data-act');
			if (act === 'add-text') addText();
			else if (act === 'add-logo') addLogo();
			else if (act === 'delete') deleteSelected();
			else if (act === 'front') layerFront();
			else if (act === 'back') layerBack();
			else if (act === 'layer-up') layerUp();
			else if (act === 'layer-down') layerDown();
			else if (act === 'preview') openPreview();
			else if (act === 'remove-bg') removeBackgroundSelected();
			else if (act === 'bold') {
				const obj = selected();
				if (!isText(obj)) return;
				const heavy = obj.fontWeight === '700' || obj.fontWeight === '800' || obj.fontWeight === '900' || obj.fontWeight === 'bold';
				applyTextProp('fontWeight', heavy ? '600' : '800');
			}
			else if (act === 'align-left') applyTextProp('textAlign', 'left');
			else if (act === 'align-center') applyTextProp('textAlign', 'center');
			else if (act === 'align-right') applyTextProp('textAlign', 'right');
			else if (act === 'save') saveDesign();
			else if (act === 'clear') clearCanvas();
		});
	});

	root.querySelectorAll('[data-shape]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			addShape(btn.getAttribute('data-shape'));
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
	if (bgColor) bgColor.addEventListener('input', function () { setSolidBackground(bgColor.value); });
	if (fontSelect) fontSelect.addEventListener('change', function () { applyTextProp('fontFamily', fontSelect.value); });
	if (fontSize) fontSize.addEventListener('input', function () { applyTextProp('fontSize', parseInt(fontSize.value, 10) || 64); });
	if (textColor) textColor.addEventListener('input', function () { applyFill(textColor.value); });
	if (shapeColor) {
		shapeColor.addEventListener('input', function () {
			const obj = selected();
			if (!obj || isText(obj) || isImage(obj)) return;
			applyFill(shapeColor.value);
		});
	}

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
		if ((e.key === 'Delete' || e.key === 'Backspace') && !isTypingTarget(e.target)) {
			const obj = selected();
			if (obj && !isText(obj)) {
				e.preventDefault();
				deleteSelected();
			}
		}
	});

	function isTypingTarget(el) {
		if (!el) return false;
		const tag = (el.tagName || '').toLowerCase();
		return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
	}

	canvas.on('selection:created', syncObjectControls);
	canvas.on('selection:updated', syncObjectControls);
	canvas.on('selection:cleared', function () {
		if (removeBgBtn) removeBgBtn.disabled = true;
	});

	window.addEventListener('resize', fitToScreen);
	if (window.ResizeObserver && shell) {
		const ro = new ResizeObserver(function () { fitToScreen(); });
		ro.observe(shell);
	}

	paintTplFilters();
	paintTemplates();
	paintStockFilters();
	paintStock();

	function boot() {
		fitToScreen();
		if (initialJson) {
			try {
				canvas.loadFromJSON(initialJson, function () {
					fitToScreen();
					setStatus('Diseño cargado para editar.');
				});
			} catch (e) {
				setStatus('No se pudo reabrir el JSON del diseño.', true);
				addText();
			}
		} else {
			setStatus('Elige una plantilla o empieza desde cero.');
		}
	}

	if (document.fonts && document.fonts.ready) {
		document.fonts.ready.then(boot).catch(boot);
	} else {
		boot();
	}
})();
