(function () {
	'use strict';

	var main = document.getElementById('contenido');
	if (!main) return;

	function esc(value) {
		return String(value || '').replace(/[&<>"']/g, function (char) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
		});
	}

	function section(inner) {
		return '<section class="mizo-band mizo-band-white border-b border-ink/10 py-16 sm:py-20"><div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">' + inner + '</div></section>';
	}

	function render(block) {
		var type = block.type;
		if (type === 'hero') {
			return '<section class="mizo-band mizo-band-white relative overflow-hidden border-b border-ink/10 pt-28 text-ink sm:pt-32"><div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pb-16 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8"><div>' +
				(block.kicker ? '<p class="mizo-kicker">' + esc(block.kicker) + '</p>' : '') +
				'<h1 class="mt-3 text-4xl font-extrabold leading-[1.08] sm:text-5xl">' + esc(block.title) + '</h1>' +
				(block.text ? '<p class="mt-5 max-w-xl text-lg leading-8 text-ink/80">' + esc(block.text) + '</p>' : '') +
				(block.button_label ? '<a class="mizo-btn mt-8 inline-flex" href="' + esc(block.button_href || '/contacto') + '">' + esc(block.button_label) + '</a>' : '') +
				'</div>' +
				(block.image ? '<img src="' + esc(block.image) + '" alt="" class="aspect-[16/10] w-full object-cover">' : '') +
				'</div></section>';
		}
		if (type === 'texto') {
			return section(
				(block.kicker ? '<p class="mizo-kicker">' + esc(block.kicker) + '</p>' : '') +
				(block.title ? '<h2 class="mt-3 text-3xl font-extrabold">' + esc(block.title) + '</h2>' : '') +
				'<div class="mt-5 max-w-3xl text-base leading-8 text-ink/80">' + (block.html || '') + '</div>'
			);
		}
		if (type === 'imagen') {
			return section('<figure><img src="' + esc(block.image) + '" alt="' + esc(block.alt) + '" class="w-full object-cover">' +
				(block.text ? '<figcaption class="mt-3 text-sm text-ink/70">' + esc(block.text) + '</figcaption>' : '') + '</figure>');
		}
		if (type === 'tarjetas') {
			var cards = (block.items || []).map(function (item) {
				return '<li class="border border-ink/10 bg-surface p-6"><h3 class="text-xl font-extrabold">' + esc(item.title) + '</h3><p class="mt-3 text-sm leading-7 text-ink/75">' + esc(item.text) + '</p></li>';
			}).join('');
			return section((block.title ? '<h2 class="text-3xl font-extrabold">' + esc(block.title) + '</h2>' : '') + '<ul class="mt-8 grid gap-4 md:grid-cols-3">' + cards + '</ul>');
		}
		if (type === 'faq') {
			var faqs = (block.items || []).map(function (item) {
				return '<details class="py-4"><summary class="cursor-pointer text-base font-extrabold">' + esc(item.q) + '</summary><p class="mt-3 text-sm leading-7 text-ink/75">' + esc(item.a) + '</p></details>';
			}).join('');
			return section((block.title ? '<h2 class="text-3xl font-extrabold">' + esc(block.title) + '</h2>' : '') + '<div class="mt-6 divide-y divide-ink/10 border-y border-ink/10">' + faqs + '</div>');
		}
		if (type === 'cta') {
			return section('<h2 class="text-3xl font-extrabold">' + esc(block.title) + '</h2><p class="mt-4 max-w-xl text-base leading-7 text-ink/75">' + esc(block.text) + '</p>' +
				(block.button_label ? '<a class="mizo-btn mt-6 inline-flex" href="' + esc(block.button_href || '/contacto') + '">' + esc(block.button_label) + '</a>' : ''));
		}
		if (type === 'productos') {
			return '<section class="mizo-band mizo-band-gray border-b border-ink/10 py-16 sm:py-20"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">' +
				'<h2 class="text-3xl font-extrabold">' + esc(block.title || 'Productos destacados') + '</h2>' +
				(block.text ? '<p class="mt-3 max-w-2xl text-sm leading-7 text-ink/70">' + esc(block.text) + '</p>' : '') +
				'<div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5" data-cms-products="' + esc(block.landing) + '"></div></div></section>';
		}
		return '';
	}

	function paintProducts(root) {
		var slug = root.getAttribute('data-cms-products');
		if (!slug) return;
		fetch('/crm/api/productos-visibles.php?landing=' + encodeURIComponent(slug), { headers: { Accept: 'application/json' } })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				var items = data && Array.isArray(data.productos) ? data.productos : [];
				root.innerHTML = items.map(function (item) {
					var photo = Array.isArray(item.imagenes) && item.imagenes[0] ? item.imagenes[0] : '';
					var href = '/contacto?sku=' + encodeURIComponent(item.sku || '') + '&mensaje=' + encodeURIComponent('Quiero cotizar ' + (item.nombre || item.sku || ''));
					return '<article class="flex h-full flex-col border border-ink/10 bg-surface">' +
						(photo ? '<img src="' + esc(photo) + '" alt="' + esc(item.nombre) + '" class="aspect-[4/3] w-full object-cover">' : '') +
						'<div class="flex flex-1 flex-col p-4"><h3 class="text-sm font-extrabold leading-snug">' + esc(item.nombre || item.sku) + '</h3>' +
						'<a class="mt-3 text-sm font-bold text-accent-dark" href="' + href + '">Cotizar</a></div></article>';
				}).join('');
			})
			.catch(function () {});
	}

	var path = window.location.pathname.replace(/\/$/, '') || '/';
	fetch('/crm/api/sitio.php?path=' + encodeURIComponent(path), { headers: { Accept: 'application/json' } })
		.then(function (res) { return res.json(); })
		.then(function (data) {
			if (!data || !data.active || !Array.isArray(data.blocks) || !data.blocks.length) return;
			main.innerHTML = data.blocks.map(render).join('');
			if (data.title) document.title = data.title;
			var meta = document.querySelector('meta[name="description"]');
			if (meta && data.description) meta.setAttribute('content', data.description);
			main.querySelectorAll('[data-cms-products]').forEach(paintProducts);
		})
		.catch(function () {});
})();
