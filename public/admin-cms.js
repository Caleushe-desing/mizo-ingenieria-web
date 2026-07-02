(function () {
	'use strict';

	const IMG_FALLBACK = '/mizo-logo.png';
	const INPUT_CLASS =
		'w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-accent-main focus:ring-2 focus:ring-blue-100';
	const TEXTAREA_CLASS = INPUT_CLASS + ' min-h-[96px] resize-y';
	const LABEL_CLASS = 'mb-1 block text-xs font-black uppercase tracking-wide text-slate-500';
	const BTN_PRIMARY =
		'rounded-full bg-accent-main px-5 py-2.5 text-xs font-black text-white hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-60';
	const BTN_SECONDARY =
		'rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50';
	const BTN_DANGER =
		'rounded-full border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-black text-red-700 hover:bg-red-100';

	const TABS = [
		{ id: 'general', label: 'General' },
		{ id: 'inicio', label: 'Inicio' },
		{ id: 'servicios', label: 'Servicios' },
		{ id: 'nosotros', label: 'Nosotros' },
		{ id: 'contacto', label: 'Contacto' },
		{ id: 'productos', label: 'Productos' },
		{ id: 'legal', label: 'Legal' },
	];

	const META_FIELDS = [
		{ path: 'title', label: 'Título SEO', type: 'text' },
		{ path: 'description', label: 'Descripción SEO', type: 'textarea' },
		{ path: 'keywords', label: 'Palabras clave', type: 'textarea' },
		{ path: 'image', label: 'Imagen Open Graph', type: 'image' },
	];

	const HERO_FIELDS = [
		{ path: 'eyebrow', label: 'Etiqueta superior', type: 'text' },
		{ path: 'title', label: 'Título', type: 'text' },
		{ path: 'subtitle', label: 'Subtítulo', type: 'textarea' },
		{ path: 'image', label: 'Imagen de fondo', type: 'image' },
	];

	let password = '';
	let mountEl = null;
	let content = null;
	let activeTab = 'general';
	let statusMessage = '';
	let statusKind = 'info';
	let busy = false;

	function esc(value) {
		return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#39;',
		}[ch]));
	}

	function getByPath(obj, path) {
		if (!obj || !path) return undefined;
		return String(path)
			.split('.')
			.reduce((acc, key) => (acc != null && acc[key] !== undefined ? acc[key] : undefined), obj);
	}

	function setByPath(obj, path, value) {
		const keys = String(path).split('.');
		let cur = obj;
		for (let i = 0; i < keys.length - 1; i += 1) {
			const key = keys[i];
			if (!cur[key] || typeof cur[key] !== 'object') cur[key] = /^\d+$/.test(keys[i + 1]) ? [] : {};
			cur = cur[key];
		}
		cur[keys[keys.length - 1]] = value;
	}

	function deepClone(value) {
		return JSON.parse(JSON.stringify(value));
	}

	function formatDate(value) {
		if (!value) return 'Sin registro';
		const date = new Date(value);
		if (Number.isNaN(date.getTime())) return String(value);
		return date.toLocaleString('es-CL', {
			dateStyle: 'medium',
			timeStyle: 'short',
		});
	}

	function setStatus(message, kind) {
		statusMessage = message || '';
		statusKind = kind || 'info';
		renderShell();
	}

	function fieldPath(base, fieldPath) {
		return base ? `${base}.${fieldPath}` : fieldPath;
	}

	function renderField(basePath, field) {
		const path = fieldPath(basePath, field.path);
		const value = getByPath(content, path);
		const dataPath = esc(path);

		if (field.type === 'textarea') {
			return `<label class="block">
				<span class="${LABEL_CLASS}">${esc(field.label)}</span>
				<textarea data-cms-path="${dataPath}" data-cms-type="textarea" class="${TEXTAREA_CLASS}" rows="${field.rows || 4}">${esc(value ?? '')}</textarea>
			</label>`;
		}

		if (field.type === 'image') {
			const src = value || '';
			return `<div class="space-y-2" data-cms-image-wrap="${dataPath}">
				<span class="${LABEL_CLASS}">${esc(field.label)}</span>
				<div class="flex flex-wrap items-start gap-3">
					<div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
						<img data-cms-preview="${dataPath}" src="${esc(src || IMG_FALLBACK)}" alt="" class="max-h-full max-w-full object-contain" onerror="this.onerror=null;this.src='${IMG_FALLBACK}'" />
					</div>
					<div class="min-w-0 flex-1 space-y-2">
						<input data-cms-path="${dataPath}" data-cms-type="text" type="text" value="${esc(src)}" placeholder="URL o ruta de imagen" class="${INPUT_CLASS}" />
						<div class="flex flex-wrap items-center gap-2">
							<label class="cursor-pointer rounded-full bg-accent-soft px-4 py-2 text-xs font-black text-accent-main hover:bg-blue-100">
								Subir archivo
								<input type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="hidden" data-cms-upload="${dataPath}" />
							</label>
							<span data-cms-upload-status="${dataPath}" class="text-xs font-semibold text-slate-500"></span>
						</div>
					</div>
				</div>
			</div>`;
		}

		if (field.type === 'stringList') {
			const items = Array.isArray(value) ? value : [];
			const rows = items
				.map(
					(item, index) => `<div class="flex items-center gap-2" data-cms-string-item="${dataPath}.${index}">
						<input data-cms-path="${dataPath}.${index}" data-cms-type="text" type="text" value="${esc(item)}" class="${INPUT_CLASS}" />
						<button type="button" data-cms-action="remove-string" data-cms-list-path="${dataPath}" data-cms-index="${index}" class="${BTN_DANGER}">Quitar</button>
					</div>`
				)
				.join('');
			return `<div class="space-y-2" data-cms-string-list="${dataPath}">
				<div class="flex items-center justify-between gap-2">
					<span class="${LABEL_CLASS} mb-0">${esc(field.label)}</span>
					<button type="button" data-cms-action="add-string" data-cms-list-path="${dataPath}" data-cms-default="${esc(field.defaultItem || '')}" class="${BTN_SECONDARY}">Agregar</button>
				</div>
				<div class="space-y-2">${rows || '<p class="text-xs text-slate-400">Sin elementos.</p>'}</div>
			</div>`;
		}

		if (field.type === 'list') {
			const items = Array.isArray(value) ? value : [];
			const itemLabel = field.itemLabel || 'Elemento';
			const cards = items
				.map((item, index) => renderListItem(dataPath, index, itemLabel, field.itemFields || [], item))
				.join('');
			const template = JSON.stringify(field.itemTemplate || {});
			return `<div class="space-y-3" data-cms-list="${dataPath}">
				<div class="flex items-center justify-between gap-2">
					<span class="${LABEL_CLASS} mb-0">${esc(field.label)}</span>
					<button type="button" data-cms-action="add-list-item" data-cms-list-path="${dataPath}" data-cms-template="${esc(template)}" class="${BTN_SECONDARY}">Agregar ${esc(itemLabel.toLowerCase())}</button>
				</div>
				<div class="space-y-3">${cards || '<p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-xs text-slate-500">No hay elementos todavía.</p>'}</div>
			</div>`;
		}

		return `<label class="block">
			<span class="${LABEL_CLASS}">${esc(field.label)}</span>
			<input data-cms-path="${dataPath}" data-cms-type="text" type="text" value="${esc(value ?? '')}" class="${INPUT_CLASS}" />
		</label>`;
	}

	function renderListItem(basePath, index, itemLabel, itemFields, item) {
		const itemPath = `${basePath}.${index}`;
		const titleField = itemFields.find((f) => f.path === 'title' || f.path === 'name' || f.path === 'heading' || f.path === 'q');
		const titleValue =
			titleField && item && item[titleField.path] != null
				? item[titleField.path]
				: item && item.title
					? item.title
					: item && item.name
						? item.name
						: item && item.heading
							? item.heading
							: item && item.q
								? item.q
								: `${itemLabel} ${index + 1}`;
		const fieldsHtml = itemFields.map((field) => renderField(itemPath, field)).join('');
		return `<details class="rounded-2xl border border-slate-200 bg-white" open>
			<summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
				<span class="text-sm font-black text-slate-800">${esc(titleValue)}</span>
				<button type="button" data-cms-action="remove-list-item" data-cms-list-path="${esc(basePath)}" data-cms-index="${index}" class="${BTN_DANGER}" onclick="event.stopPropagation()">Eliminar</button>
			</summary>
			<div class="space-y-3 border-t border-slate-100 px-4 py-4">${fieldsHtml}</div>
		</details>`;
	}

	function renderSection(section) {
		const fieldsHtml = (section.fields || []).map((field) => renderField(section.basePath || '', field)).join('');
		return `<details class="rounded-2xl border border-slate-200 bg-white" ${section.open ? 'open' : ''}>
			<summary class="cursor-pointer list-none px-5 py-4 text-sm font-black text-slate-900">${esc(section.title)}</summary>
			<div class="space-y-4 border-t border-slate-100 px-5 py-4">${fieldsHtml}</div>
		</details>`;
	}

	function renderTabPanel(tabId) {
		const sections = TAB_SECTIONS[tabId] || [];
		return `<div class="space-y-4">${sections.map(renderSection).join('')}</div>`;
	}

	const TAB_SECTIONS = {
		general: [
			{
				title: 'Datos de contacto globales',
				open: true,
				fields: [
					{ path: 'global.phone', label: 'Teléfono (enlace tel:)', type: 'text' },
					{ path: 'global.phoneDisplay', label: 'Teléfono visible', type: 'text' },
					{ path: 'global.email', label: 'Correo electrónico', type: 'text' },
					{ path: 'global.whatsappMessage', label: 'Mensaje predeterminado WhatsApp', type: 'textarea', rows: 2 },
					{ path: 'global.address', label: 'Dirección', type: 'text' },
					{ path: 'global.hours', label: 'Horario de atención', type: 'text' },
				],
			},
			{
				title: 'Logos y marca',
				fields: [
					{ path: 'global.logo', label: 'Logo principal', type: 'image' },
					{ path: 'global.logoFooter', label: 'Logo pie de página', type: 'image' },
					{ path: 'global.logoAlt', label: 'Texto alternativo del logo', type: 'text' },
				],
			},
			{
				title: 'Cintillo del header',
				fields: [
					{ path: 'global.header.cintilloBadge', label: 'Badge', type: 'text' },
					{ path: 'global.header.cintilloText', label: 'Texto', type: 'textarea', rows: 2 },
					{ path: 'global.header.cintilloImage', label: 'Imagen', type: 'image' },
				],
			},
			{
				title: 'Pie de página',
				fields: [
					{ path: 'global.footer.description', label: 'Descripción', type: 'textarea' },
					{ path: 'global.footer.tagline', label: 'Tagline', type: 'text' },
					{ path: 'global.footer.image', label: 'Imagen', type: 'image' },
				],
			},
			{
				title: 'Menú de navegación',
				fields: [
					{
						path: 'global.nav',
						label: 'Enlaces del menú',
						type: 'list',
						itemLabel: 'Enlace',
						itemTemplate: { title: '', url: '' },
						itemFields: [
							{ path: 'title', label: 'Texto', type: 'text' },
							{ path: 'url', label: 'URL', type: 'text' },
						],
					},
				],
			},
			{
				title: 'Experiencia móvil (celulares)',
				open: true,
				fields: [
					{
						path: 'global.mobile.navPrimary',
						label: 'Menú principal en celular',
						type: 'list',
						itemLabel: 'Enlace',
						itemTemplate: { title: '', url: '' },
						itemFields: [
							{ path: 'title', label: 'Texto', type: 'text' },
							{ path: 'url', label: 'URL', type: 'text' },
						],
					},
					{
						path: 'global.mobile.navMore',
						label: 'Enlaces en “Más secciones”',
						type: 'list',
						itemLabel: 'Enlace',
						itemTemplate: { title: '', url: '' },
						itemFields: [
							{ path: 'title', label: 'Texto', type: 'text' },
							{ path: 'url', label: 'URL', type: 'text' },
						],
					},
					{ path: 'global.mobile.navMoreLabel', label: 'Texto “Más secciones”', type: 'text' },
					{ path: 'global.mobile.menuWhatsapp', label: 'Botón WhatsApp en menú', type: 'text' },
					{ path: 'global.mobile.menuQuote', label: 'Botón cotizar en menú (tablet)', type: 'text' },
					{ path: 'global.mobile.menuQuoteUrl', label: 'Enlace cotizar en menú', type: 'text' },
					{ path: 'global.mobile.headerWhatsapp', label: 'Texto WhatsApp en header (tablet+)', type: 'text' },
					{ path: 'global.mobile.headerQuote', label: 'Texto Cotizar en header (tablet+)', type: 'text' },
					{ path: 'global.mobile.headerQuoteUrl', label: 'Enlace Cotizar en header', type: 'text' },
					{ path: 'global.mobile.contactBar.call', label: 'Barra inferior — Llamar', type: 'text' },
					{ path: 'global.mobile.contactBar.whatsapp', label: 'Barra inferior — WhatsApp', type: 'text' },
					{ path: 'global.mobile.contactBar.quote', label: 'Barra inferior — Cotizar', type: 'text' },
					{ path: 'global.mobile.contactBar.quoteUrl', label: 'Barra inferior — Enlace cotizar', type: 'text' },
				],
			},
		],
		inicio: [
			{
				title: 'SEO — Página de inicio',
				open: true,
				fields: META_FIELDS.map((field) => ({
					...field,
					path: `pages.home.meta.${field.path}`,
				})),
			},
			{
				title: 'Hero — Carrusel principal',
				fields: [
					{
						path: 'pages.home.heroSlides',
						label: 'Diapositivas del hero',
						type: 'list',
						itemLabel: 'Diapositiva',
						itemTemplate: { image: '', alt: '', tag: '', title: '', desc: '', detail: '' },
						itemFields: [
							{ path: 'image', label: 'Imagen', type: 'image' },
							{ path: 'alt', label: 'Texto alternativo', type: 'text' },
							{ path: 'tag', label: 'Etiqueta', type: 'text' },
							{ path: 'title', label: 'Título', type: 'text' },
							{ path: 'desc', label: 'Descripción', type: 'textarea' },
							{ path: 'detail', label: 'Detalle', type: 'text' },
						],
					},
				],
			},
			{
				title: 'Introducción',
				fields: [
					{ path: 'pages.home.intro.eyebrow', label: 'Etiqueta superior', type: 'text' },
					{ path: 'pages.home.intro.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.intro.paragraph1', label: 'Párrafo 1', type: 'textarea' },
					{ path: 'pages.home.intro.paragraph2', label: 'Párrafo 2', type: 'textarea' },
					{ path: 'pages.home.intro.image', label: 'Imagen', type: 'image' },
					{ path: 'pages.home.intro.cta1.text', label: 'CTA 1 — Texto', type: 'text' },
					{ path: 'pages.home.intro.cta1.href', label: 'CTA 1 — Enlace', type: 'text' },
					{ path: 'pages.home.intro.cta2.text', label: 'CTA 2 — Texto', type: 'text' },
					{ path: 'pages.home.intro.cta2.href', label: 'CTA 2 — Enlace', type: 'text' },
					{ path: 'pages.home.intro.ctaMobile.text', label: 'CTA móvil — Texto (solo celular)', type: 'text' },
					{ path: 'pages.home.intro.ctaMobile.href', label: 'CTA móvil — Enlace', type: 'text' },
				],
			},
			{
				title: 'Hero — Botones de acción',
				fields: [
					{ path: 'pages.home.heroCtas.services.text', label: 'Servicios — Texto', type: 'text' },
					{ path: 'pages.home.heroCtas.services.href', label: 'Servicios — Enlace', type: 'text' },
					{ path: 'pages.home.heroCtas.whatsapp.text', label: 'WhatsApp — Texto', type: 'text' },
					{ path: 'pages.home.heroCtas.quote.text', label: 'Cotizar — Texto', type: 'text' },
					{ path: 'pages.home.heroCtas.quote.href', label: 'Cotizar — Enlace', type: 'text' },
				],
			},
			{
				title: 'Inicio en celular — Secciones colapsables',
				fields: [
					{ path: 'pages.home.mobileCollapse.sectors', label: 'Sectores', type: 'text' },
					{ path: 'pages.home.mobileCollapse.services', label: 'Servicios', type: 'text' },
					{ path: 'pages.home.mobileCollapse.projects', label: 'Proyectos', type: 'text' },
					{ path: 'pages.home.mobileCollapse.process', label: 'Proceso', type: 'text' },
					{ path: 'pages.home.mobileCollapse.whyChoose', label: 'Por qué elegirnos', type: 'text' },
					{ path: 'pages.home.mobileCollapse.brands', label: 'Marcas', type: 'text' },
					{ path: 'pages.home.mobileCollapse.showcase', label: 'Productos destacados', type: 'text' },
				],
			},
			{
				title: 'Sectores / espacios',
				fields: [
					{ path: 'pages.home.sectors.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.sectors.subtitle', label: 'Subtítulo', type: 'textarea' },
					{ path: 'pages.home.sectors.image', label: 'Imagen de sección', type: 'image' },
					{
						path: 'pages.home.sectors.items',
						label: 'Tarjetas de sectores',
						type: 'list',
						itemLabel: 'Sector',
						itemTemplate: { title: '', desc: '', icon: '', image: '' },
						itemFields: [
							{ path: 'title', label: 'Título', type: 'text' },
							{ path: 'desc', label: 'Descripción', type: 'textarea' },
							{ path: 'icon', label: 'Icono (emoji)', type: 'text' },
							{ path: 'image', label: 'Imagen', type: 'image' },
						],
					},
				],
			},
			{
				title: 'Servicios destacados (inicio)',
				fields: [
					{ path: 'pages.home.services.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.services.subtitle', label: 'Subtítulo', type: 'textarea' },
					{ path: 'pages.home.services.image', label: 'Imagen de sección', type: 'image' },
					{
						path: 'pages.home.services.items',
						label: 'Tarjetas de servicios',
						type: 'list',
						itemLabel: 'Servicio',
						itemTemplate: { title: '', desc: '', href: '', image: '' },
						itemFields: [
							{ path: 'title', label: 'Título', type: 'text' },
							{ path: 'desc', label: 'Descripción', type: 'textarea' },
							{ path: 'href', label: 'Enlace', type: 'text' },
							{ path: 'image', label: 'Imagen', type: 'image' },
						],
					},
				],
			},
			{
				title: 'Proceso de trabajo',
				fields: [
					{ path: 'pages.home.process.eyebrow', label: 'Etiqueta superior', type: 'text' },
					{ path: 'pages.home.process.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.process.description', label: 'Descripción', type: 'textarea' },
					{ path: 'pages.home.process.image', label: 'Imagen', type: 'image' },
					{ path: 'pages.home.process.cta.text', label: 'CTA — Texto', type: 'text' },
					{ path: 'pages.home.process.cta.href', label: 'CTA — Enlace', type: 'text' },
					{
						path: 'pages.home.process.steps',
						label: 'Pasos del proceso',
						type: 'stringList',
						defaultItem: 'Nuevo paso',
					},
				],
			},
			{
				title: '¿Por qué elegirnos?',
				fields: [
					{ path: 'pages.home.whyChoose.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.whyChoose.image', label: 'Imagen de sección', type: 'image' },
					{
						path: 'pages.home.whyChoose.items',
						label: 'Razones',
						type: 'list',
						itemLabel: 'Razón',
						itemTemplate: { title: '', desc: '', image: '' },
						itemFields: [
							{ path: 'title', label: 'Título', type: 'text' },
							{ path: 'desc', label: 'Descripción', type: 'textarea' },
							{ path: 'image', label: 'Imagen', type: 'image' },
						],
					},
				],
			},
			{
				title: 'Preguntas frecuentes',
				fields: [
					{ path: 'pages.home.faq.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.faq.image', label: 'Imagen', type: 'image' },
					{
						path: 'pages.home.faq.items',
						label: 'Preguntas',
						type: 'list',
						itemLabel: 'Pregunta',
						itemTemplate: { q: '', a: '' },
						itemFields: [
							{ path: 'q', label: 'Pregunta', type: 'text' },
							{ path: 'a', label: 'Respuesta', type: 'textarea' },
						],
					},
				],
			},
			{
				title: 'CTA de contacto',
				fields: [
					{ path: 'pages.home.contactCta.eyebrow', label: 'Etiqueta superior', type: 'text' },
					{ path: 'pages.home.contactCta.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.contactCta.description', label: 'Descripción', type: 'textarea' },
					{ path: 'pages.home.contactCta.image', label: 'Imagen', type: 'image' },
				],
			},
			{
				title: 'Sección de proyectos',
				fields: [
					{ path: 'pages.home.projectsSection.eyebrow', label: 'Etiqueta superior', type: 'text' },
					{ path: 'pages.home.projectsSection.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.projectsSection.description', label: 'Descripción', type: 'textarea' },
					{ path: 'pages.home.projectsSection.image', label: 'Imagen', type: 'image' },
				],
			},
			{
				title: 'Marcas',
				fields: [
					{ path: 'pages.home.brands.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.brands.subtitle', label: 'Subtítulo', type: 'textarea' },
					{ path: 'pages.home.brands.image', label: 'Imagen de sección', type: 'image' },
					{
						path: 'pages.home.brands.items',
						label: 'Logos de marcas',
						type: 'list',
						itemLabel: 'Marca',
						itemTemplate: { name: '', slug: '', image: '' },
						itemFields: [
							{ path: 'name', label: 'Nombre', type: 'text' },
							{ path: 'slug', label: 'Slug', type: 'text' },
							{ path: 'image', label: 'Logo', type: 'image' },
						],
					},
				],
			},
			{
				title: 'Equipamiento de referencia (bloque inicio)',
				fields: [
					{ path: 'pages.home.showcase.eyebrow', label: 'Etiqueta superior', type: 'text' },
					{ path: 'pages.home.showcase.title', label: 'Título', type: 'text' },
					{ path: 'pages.home.showcase.description', label: 'Descripción', type: 'textarea' },
					{ path: 'pages.home.showcase.image', label: 'Imagen', type: 'image' },
				],
			},
		],
		servicios: [
			{
				title: 'SEO — Servicios',
				open: true,
				fields: META_FIELDS.map((field) => ({
					...field,
					path: `pages.servicios.meta.${field.path}`,
				})),
			},
			{
				title: 'Hero — Servicios',
				fields: [
					...HERO_FIELDS.map((field) => ({
						...field,
						path: `pages.servicios.hero.${field.path}`,
					})),
					{
						path: 'pages.servicios.hero.chips',
						label: 'Chips / etiquetas',
						type: 'stringList',
						defaultItem: 'Nueva etiqueta',
					},
				],
			},
			{
				title: 'Proceso (lista simple)',
				fields: [
					{
						path: 'pages.servicios.process',
						label: 'Pasos del proceso',
						type: 'stringList',
						defaultItem: 'Nuevo paso',
					},
				],
			},
			{
				title: 'Servicios detallados',
				fields: [
					{
						path: 'pages.servicios.services',
						label: 'Bloques de servicio',
						type: 'list',
						itemLabel: 'Servicio',
						itemTemplate: {
							id: '',
							title: '',
							description: '',
							kicker: '',
							details: [],
							image: '',
						},
						itemFields: [
							{ path: 'id', label: 'ID ancla (#)', type: 'text' },
							{ path: 'kicker', label: 'Etiqueta', type: 'text' },
							{ path: 'title', label: 'Título', type: 'text' },
							{ path: 'description', label: 'Descripción', type: 'textarea' },
							{ path: 'image', label: 'Imagen', type: 'image' },
							{
								path: 'details',
								label: 'Detalles / bullets',
								type: 'stringList',
								defaultItem: 'Nuevo detalle',
							},
						],
					},
				],
			},
		],
		nosotros: [
			{
				title: 'SEO — Nosotros',
				open: true,
				fields: META_FIELDS.map((field) => ({
					...field,
					path: `pages.nosotros.meta.${field.path}`,
				})),
			},
			{
				title: 'Hero — Nosotros',
				fields: [
					...HERO_FIELDS.map((field) => ({
						...field,
						path: `pages.nosotros.hero.${field.path}`,
					})),
					{
						path: 'pages.nosotros.hero.chips',
						label: 'Chips / etiquetas',
						type: 'stringList',
						defaultItem: 'Nueva etiqueta',
					},
				],
			},
			{
				title: 'Misión',
				fields: [
					{ path: 'pages.nosotros.mission.eyebrow', label: 'Etiqueta superior', type: 'text' },
					{ path: 'pages.nosotros.mission.title', label: 'Título', type: 'text' },
					{ path: 'pages.nosotros.mission.p1', label: 'Párrafo 1', type: 'textarea' },
					{ path: 'pages.nosotros.mission.p2', label: 'Párrafo 2', type: 'textarea' },
					{ path: 'pages.nosotros.mission.image', label: 'Imagen', type: 'image' },
				],
			},
			{
				title: 'Valores',
				fields: [
					{
						path: 'pages.nosotros.values',
						label: 'Tarjetas de valores',
						type: 'list',
						itemLabel: 'Valor',
						itemTemplate: { kicker: '', title: '', description: '', image: '' },
						itemFields: [
							{ path: 'kicker', label: 'Etiqueta', type: 'text' },
							{ path: 'title', label: 'Título', type: 'text' },
							{ path: 'description', label: 'Descripción', type: 'textarea' },
							{ path: 'image', label: 'Imagen', type: 'image' },
						],
					},
				],
			},
			{
				title: 'Capacidades',
				fields: [
					{
						path: 'pages.nosotros.capabilities',
						label: 'Lista de capacidades',
						type: 'stringList',
						defaultItem: 'Nueva capacidad',
					},
					{ path: 'pages.nosotros.sideImage', label: 'Imagen lateral', type: 'image' },
				],
			},
		],
		contacto: [
			{
				title: 'SEO — Contacto',
				open: true,
				fields: META_FIELDS.map((field) => ({
					...field,
					path: `pages.contacto.meta.${field.path}`,
				})),
			},
			{
				title: 'Hero — Contacto',
				fields: [
					...HERO_FIELDS.map((field) => ({
						...field,
						path: `pages.contacto.hero.${field.path}`,
					})),
					{
						path: 'pages.contacto.hero.badges',
						label: 'Insignias / badges',
						type: 'stringList',
						defaultItem: 'Nueva insignia',
					},
				],
			},
			{
				title: 'Formulario de cotización',
				fields: [
					{ path: 'pages.contacto.form.title', label: 'Título del formulario', type: 'text' },
					{ path: 'pages.contacto.form.subtitle', label: 'Subtítulo', type: 'textarea' },
					{ path: 'pages.contacto.form.labels.nombre', label: 'Etiqueta — Nombre', type: 'text' },
					{ path: 'pages.contacto.form.labels.telefono', label: 'Etiqueta — Teléfono', type: 'text' },
					{ path: 'pages.contacto.form.labels.empresa', label: 'Etiqueta — Empresa', type: 'text' },
					{ path: 'pages.contacto.form.labels.correo', label: 'Etiqueta — Correo', type: 'text' },
					{ path: 'pages.contacto.form.labels.espacio', label: 'Etiqueta — Espacio', type: 'text' },
					{ path: 'pages.contacto.form.labels.metros', label: 'Etiqueta — Metros', type: 'text' },
					{ path: 'pages.contacto.form.labels.detalles', label: 'Etiqueta — Detalles', type: 'text' },
					{ path: 'pages.contacto.form.placeholders.metros', label: 'Placeholder — Metros', type: 'text' },
					{ path: 'pages.contacto.form.placeholders.detalles', label: 'Placeholder — Detalles', type: 'textarea' },
					{
						path: 'pages.contacto.form.spaces',
						label: 'Opciones de espacio',
						type: 'stringList',
						defaultItem: 'Nuevo espacio',
					},
					{ path: 'pages.contacto.form.defaultSpace', label: 'Espacio predeterminado', type: 'text' },
					{ path: 'pages.contacto.form.submitButton', label: 'Texto del botón enviar', type: 'text' },
					{ path: 'pages.contacto.form.privacyNote', label: 'Nota de privacidad', type: 'textarea' },
				],
			},
			{
				title: 'Contacto directo',
				fields: [
					{ path: 'pages.contacto.directContact.title', label: 'Título', type: 'text' },
					{ path: 'pages.contacto.directContact.email', label: 'Correo', type: 'text' },
					{ path: 'pages.contacto.directContact.phone', label: 'Teléfono', type: 'text' },
					{ path: 'pages.contacto.directContact.hours', label: 'Horario', type: 'text' },
				],
			},
		],
		productos: [
			{
				title: 'SEO — Productos',
				open: true,
				fields: META_FIELDS.map((field) => ({
					...field,
					path: `pages.productos.meta.${field.path}`,
				})),
			},
			{
				title: 'Hero — Productos',
				fields: [
					...HERO_FIELDS.map((field) => ({
						...field,
						path: `pages.productos.hero.${field.path}`,
					})),
					{
						path: 'pages.productos.hero.chips',
						label: 'Chips / etiquetas',
						type: 'stringList',
						defaultItem: 'Nueva etiqueta',
					},
				],
			},
			{
				title: 'SEO — Equipamiento (/equipamiento)',
				fields: META_FIELDS.map((field) => ({
					...field,
					path: `pages.equipamiento.meta.${field.path}`,
				})),
			},
			{
				title: 'Hero — Equipamiento',
				fields: [
					...HERO_FIELDS.map((field) => ({
						...field,
						path: `pages.equipamiento.hero.${field.path}`,
					})),
					{
						path: 'pages.equipamiento.hero.chips',
						label: 'Chips / etiquetas',
						type: 'stringList',
						defaultItem: 'Nueva etiqueta',
					},
				],
			},
		],
		legal: [
			{
				title: 'Términos y Condiciones',
				open: true,
				fields: [
					{ path: 'pages.legal.terminos.title', label: 'Título de la página', type: 'text' },
					{ path: 'pages.legal.terminos.lastUpdated', label: 'Última actualización (texto)', type: 'text' },
					{ path: 'pages.legal.terminos.image', label: 'Imagen', type: 'image' },
					{
						path: 'pages.legal.terminos.content',
						label: 'Secciones del documento',
						type: 'list',
						itemLabel: 'Sección',
						itemTemplate: { heading: '', body: '', list: [] },
						itemFields: [
							{ path: 'heading', label: 'Encabezado', type: 'text' },
							{ path: 'body', label: 'Cuerpo', type: 'textarea' },
							{
								path: 'list',
								label: 'Lista opcional',
								type: 'stringList',
								defaultItem: 'Nuevo ítem',
							},
						],
					},
				],
			},
			{
				title: 'Política de Privacidad',
				fields: [
					{ path: 'pages.legal.privacidad.title', label: 'Título de la página', type: 'text' },
					{ path: 'pages.legal.privacidad.lastUpdated', label: 'Última actualización (texto)', type: 'text' },
					{ path: 'pages.legal.privacidad.image', label: 'Imagen', type: 'image' },
					{
						path: 'pages.legal.privacidad.content',
						label: 'Secciones del documento',
						type: 'list',
						itemLabel: 'Sección',
						itemTemplate: { heading: '', body: '', list: [] },
						itemFields: [
							{ path: 'heading', label: 'Encabezado', type: 'text' },
							{ path: 'body', label: 'Cuerpo', type: 'textarea' },
							{
								path: 'list',
								label: 'Lista opcional',
								type: 'stringList',
								defaultItem: 'Nuevo ítem',
							},
						],
					},
				],
			},
		],
	};

	function statusClass(kind) {
		if (kind === 'success') return 'text-emerald-700';
		if (kind === 'error') return 'text-red-600';
		if (kind === 'warn') return 'text-orange-700';
		return 'text-slate-600';
	}

	function renderShell() {
		if (!mountEl) return;
		const updatedAt = content?.updatedAt;
		const tabButtons = TABS.map(
			(tab) =>
				`<button type="button" data-cms-tab="${tab.id}" class="rounded-2xl px-4 py-2.5 text-sm font-black ${
					activeTab === tab.id
						? 'bg-accent-main text-white shadow-lg shadow-blue-500/20'
						: 'bg-white text-slate-600 hover:bg-accent-soft'
				}">${esc(tab.label)}</button>`
		).join('');

		mountEl.innerHTML = `<div class="space-y-5">
			<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
				<div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
					<div>
						<p class="text-xs font-black uppercase tracking-[0.2em] text-accent-main">Editor de contenido</p>
						<p class="mt-1 text-sm text-slate-500">Edita textos, imágenes y listas del sitio público Mizo.</p>
						<p class="mt-2 text-xs font-semibold text-slate-500">Última actualización: <span class="font-black text-slate-800">${esc(formatDate(updatedAt))}</span></p>
					</div>
					<div class="flex flex-wrap items-center gap-3">
						<button type="button" id="cms-save-all" class="${BTN_PRIMARY}" ${busy ? 'disabled' : ''}>${busy ? 'Guardando…' : 'Guardar todo el sitio'}</button>
						<button type="button" id="cms-reload" class="${BTN_SECONDARY}" ${busy ? 'disabled' : ''}>Recargar</button>
					</div>
				</div>
				<p id="cms-status" class="mt-3 text-sm font-semibold ${statusClass(statusKind)}">${esc(statusMessage)}</p>
			</div>

			<div class="flex flex-wrap gap-2">${tabButtons}</div>

			<div id="cms-tab-panel">${renderTabPanel(activeTab)}</div>
		</div>`;

		mountEl.querySelector('#cms-save-all')?.addEventListener('click', () => {
			save().catch((error) => setStatus(error.message || 'Error al guardar.', 'error'));
		});
		mountEl.querySelector('#cms-reload')?.addEventListener('click', () => {
			load().catch((error) => setStatus(error.message || 'Error al recargar.', 'error'));
		});
		mountEl.querySelectorAll('[data-cms-tab]').forEach((button) => {
			button.addEventListener('click', () => {
				activeTab = button.getAttribute('data-cms-tab') || 'general';
				renderShell();
			});
		});

		bindFieldEvents();
	}

	function bindFieldEvents() {
		if (!mountEl) return;

		mountEl.querySelectorAll('[data-cms-path]').forEach((input) => {
			input.addEventListener('input', (event) => {
				const target = event.currentTarget;
				const path = target.getAttribute('data-cms-path');
				if (!path) return;
				setByPath(content, path, target.value);
				if (target.getAttribute('data-cms-type') === 'text' && target.closest('[data-cms-image-wrap]')) {
					const preview = mountEl.querySelector(`[data-cms-preview="${path}"]`);
					if (preview) preview.src = target.value || IMG_FALLBACK;
				}
			});
		});

		mountEl.querySelectorAll('[data-cms-upload]').forEach((input) => {
			input.addEventListener('change', async (event) => {
				const fileInput = event.currentTarget;
				const path = fileInput.getAttribute('data-cms-upload');
				const file = fileInput.files && fileInput.files[0];
				if (!path || !file) return;
				const statusEl = mountEl.querySelector(`[data-cms-upload-status="${path}"]`);
				try {
					if (statusEl) statusEl.textContent = 'Subiendo…';
					const uploadedPath = await uploadImage(file, 'cms');
					setByPath(content, path, uploadedPath);
					const textInput = mountEl.querySelector(`[data-cms-path="${path}"]`);
					if (textInput) textInput.value = uploadedPath;
					const preview = mountEl.querySelector(`[data-cms-preview="${path}"]`);
					if (preview) preview.src = uploadedPath;
					if (statusEl) statusEl.textContent = 'Imagen subida.';
					setStatus('Imagen subida. Recuerda guardar todo el sitio.', 'warn');
				} catch (error) {
					if (statusEl) statusEl.textContent = error.message || 'Error al subir.';
					setStatus(error.message || 'Error al subir imagen.', 'error');
				} finally {
					fileInput.value = '';
				}
			});
		});

		mountEl.querySelectorAll('[data-cms-action="add-string"]').forEach((button) => {
			button.addEventListener('click', () => {
				const path = button.getAttribute('data-cms-list-path');
				const defaultItem = button.getAttribute('data-cms-default') || '';
				if (!path) return;
				const list = getByPath(content, path);
				const next = Array.isArray(list) ? list.slice() : [];
				next.push(defaultItem);
				setByPath(content, path, next);
				renderShell();
			});
		});

		mountEl.querySelectorAll('[data-cms-action="remove-string"]').forEach((button) => {
			button.addEventListener('click', () => {
				const path = button.getAttribute('data-cms-list-path');
				const index = Number(button.getAttribute('data-cms-index'));
				if (!path || Number.isNaN(index)) return;
				const list = getByPath(content, path);
				if (!Array.isArray(list)) return;
				const next = list.slice();
				next.splice(index, 1);
				setByPath(content, path, next);
				renderShell();
			});
		});

		mountEl.querySelectorAll('[data-cms-action="add-list-item"]').forEach((button) => {
			button.addEventListener('click', () => {
				const path = button.getAttribute('data-cms-list-path');
				const templateRaw = button.getAttribute('data-cms-template') || '{}';
				if (!path) return;
				let template = {};
				try {
					template = JSON.parse(templateRaw);
				} catch (_error) {
					template = {};
				}
				const list = getByPath(content, path);
				const next = Array.isArray(list) ? list.slice() : [];
				next.push(deepClone(template));
				setByPath(content, path, next);
				renderShell();
			});
		});

		mountEl.querySelectorAll('[data-cms-action="remove-list-item"]').forEach((button) => {
			button.addEventListener('click', () => {
				const path = button.getAttribute('data-cms-list-path');
				const index = Number(button.getAttribute('data-cms-index'));
				if (!path || Number.isNaN(index)) return;
				const list = getByPath(content, path);
				if (!Array.isArray(list)) return;
				const next = list.slice();
				next.splice(index, 1);
				setByPath(content, path, next);
				renderShell();
			});
		});
	}

	async function uploadImage(file, prefix) {
		if (!password) throw new Error('Falta la clave de administrador.');
		const formData = new FormData();
		formData.append('password', password);
		formData.append('image', file);
		formData.append('prefix', prefix || 'cms');
		const response = await fetch('/api/site-media.php', {
			method: 'POST',
			body: formData,
		});
		const payload = await response.json().catch(() => ({}));
		if (!response.ok || !payload.ok) {
			throw new Error(payload.error || 'No se pudo subir la imagen.');
		}
		return payload.path || payload.url || '';
	}

	async function load() {
		if (!password) throw new Error('Falta la clave de administrador.');
		busy = true;
		renderShell();
		try {
			const response = await fetch('/api/site-content.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify({ password, action: 'get' }),
			});
			const payload = await response.json().catch(() => ({}));
			if (!response.ok || !payload.ok) {
				throw new Error(payload.error || 'No se pudo cargar el contenido.');
			}
			content = deepClone(payload.content || {});
			setStatus('Contenido cargado correctamente.', 'success');
			return content;
		} catch (error) {
			setStatus(error.message || 'Error al cargar.', 'error');
			throw error;
		} finally {
			busy = false;
			renderShell();
		}
	}

	async function save() {
		if (!password) throw new Error('Falta la clave de administrador.');
		if (!content) throw new Error('No hay contenido para guardar.');
		busy = true;
		renderShell();
		try {
			const payloadContent = deepClone(content);
			payloadContent.updatedAt = new Date().toISOString();
			const response = await fetch('/api/site-content.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify({
					password,
					action: 'save',
					content: payloadContent,
				}),
			});
			const payload = await response.json().catch(() => ({}));
			if (!response.ok || !payload.ok) {
				throw new Error(payload.error || 'No se pudo guardar el contenido.');
			}
			content = deepClone(payload.content || payloadContent);
			setStatus(payload.message || 'Contenido del sitio guardado correctamente.', 'success');
			return content;
		} catch (error) {
			setStatus(error.message || 'Error al guardar.', 'error');
			throw error;
		} finally {
			busy = false;
			renderShell();
		}
	}

	function init(nextPassword, nextMountEl) {
		password = String(nextPassword || '');
		mountEl = nextMountEl || null;
		activeTab = 'general';
		statusMessage = '';
		statusKind = 'info';
		if (!mountEl) {
			throw new Error('Se requiere un elemento contenedor para el CMS.');
		}
		renderShell();
		return load();
	}

	window.MizoAdminCms = {
		init,
		load,
		save,
		getByPath,
		setByPath,
		esc,
	};
})();
