(function () {
	const FALLBACK = '/site-content-default.json';

	function esc(value) {
		return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
			'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
		}[ch]));
	}

	function getPath(obj, path) {
		return path.split('.').reduce((acc, key) => (acc && acc[key] !== undefined ? acc[key] : undefined), obj);
	}

	function setPath(obj, path, value) {
		const keys = path.split('.');
		let cur = obj;
		for (let i = 0; i < keys.length - 1; i += 1) {
			const key = keys[i];
			if (!cur[key] || typeof cur[key] !== 'object') cur[key] = {};
			cur = cur[key];
		}
		cur[keys[keys.length - 1]] = value;
	}

	async function fetchContent() {
		try {
			const res = await fetch('/api/site-content.php', { headers: { Accept: 'application/json' }, cache: 'no-store' });
			const payload = await res.json();
			if (payload?.ok && payload.content) return payload.content;
		} catch (e) {
			console.warn('site-content.php', e);
		}
		const fallback = await fetch(FALLBACK, { headers: { Accept: 'application/json' }, cache: 'no-store' });
		return fallback.json();
	}

	function applyText(sel, value) {
		document.querySelectorAll(sel).forEach((el) => {
			if (value !== undefined && value !== null) el.textContent = value;
		});
	}

	function applyHtml(sel, value) {
		document.querySelectorAll(sel).forEach((el) => {
			if (value !== undefined && value !== null) el.innerHTML = value;
		});
	}

	function applySrc(sel, value) {
		document.querySelectorAll(sel).forEach((el) => {
			if (value) el.setAttribute('src', value);
		});
	}

	function applyHref(sel, value) {
		document.querySelectorAll(sel).forEach((el) => {
			if (value) el.setAttribute('href', value);
		});
	}

	function applyGlobal(g) {
		if (!g) return;
		applyText('[data-cms="global.phoneDisplay"]', g.phoneDisplay);
		applyHref('[data-cms-href="global.phone"]', g.phone ? `tel:${g.phone}` : '');
		applyHref('[data-cms-href="global.email"]', g.email ? `mailto:${g.email}` : '');
		applyText('[data-cms="global.email"]', g.email);
		applyText('[data-cms="global.address"]', g.address);
		applyText('[data-cms="global.hours"]', g.hours);
		applySrc('[data-cms-src="global.logo"]', g.logo);
		applySrc('[data-cms-src="global.logoFooter"]', g.logoFooter);
		if (g.header) {
			applyText('[data-cms="global.header.cintilloBadge"]', g.header.cintilloBadge);
			applyText('[data-cms="global.header.cintilloText"]', g.header.cintilloText);
		}
		if (g.footer) {
			applyText('[data-cms="global.footer.description"]', g.footer.description);
			applyText('[data-cms="global.footer.tagline"]', g.footer.tagline);
		}
		const wa = g.whatsappMessage
			? `https://wa.me/56994390870?text=${encodeURIComponent(g.whatsappMessage)}`
			: '';
		applyHref('[data-cms-href="global.whatsapp"]', wa);
	}

	function renderHeroSlides(slides) {
		const root = document.getElementById('hero-carousel');
		if (!root || !Array.isArray(slides) || !slides.length) return;

		root.innerHTML = slides.map((slide, index) => `
			<article class="hero-slide absolute inset-0 transition-opacity duration-700 ease-in-out ${index === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none'}" data-slide-index="${index}" aria-hidden="${index !== 0}">
				<img src="${esc(slide.image)}" alt="${esc(slide.alt)}" class="absolute inset-0 h-full w-full object-cover" loading="${index === 0 ? 'eager' : 'lazy'}" width="1600" height="900" />
				<div class="absolute inset-0 bg-gradient-to-r from-ink/55 via-ink/35 to-ink/20"></div>
				<div class="absolute inset-0 bg-gradient-to-t from-ink/50 via-transparent to-transparent"></div>
				<div class="relative z-10 mx-auto flex h-full max-w-7xl items-end px-4 pb-16 pt-28 sm:px-6 sm:pb-20 lg:px-8 lg:pb-24">
					<div class="max-w-2xl">
						<p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-orange">${esc(slide.tag)}</p>
						<h1 class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl lg:text-5xl">${esc(slide.title)}</h1>
						<p class="mt-4 text-base leading-relaxed text-white/80 sm:text-lg">${esc(slide.desc)}</p>
						<p class="mt-3 text-sm font-semibold text-accent-light">${esc(slide.detail)}</p>
					</div>
				</div>
			</article>
		`).join('') + `
			<div class="absolute bottom-6 left-0 right-0 z-20 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
				<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
					<div class="flex items-center gap-2" role="tablist" aria-label="Seleccionar proyecto">
						${slides.map((slide, index) => `<button type="button" class="hero-dot h-2 rounded-full transition-all duration-300 ${index === 0 ? 'w-8 bg-brand-orange' : 'w-2 bg-white/35 hover:bg-white/60'}" data-dot-index="${index}" aria-label="Ver proyecto: ${esc(slide.title)}" aria-selected="${index === 0}"></button>`).join('')}
					</div>
					<div class="flex flex-wrap gap-3">
						<a href="/servicios" class="inline-flex items-center justify-center rounded-md bg-white px-5 py-2.5 text-sm font-bold text-ink transition hover:bg-gray-100">Nuestros servicios</a>
						<a href="/contacto" class="inline-flex items-center justify-center rounded-md border border-white/25 px-5 py-2.5 text-sm font-bold text-white transition hover:border-accent-main hover:text-accent-light">Cotizar proyecto</a>
					</div>
				</div>
			</div>`;

		document.dispatchEvent(new CustomEvent('mizo:hero-rebuild'));
	}

	function renderGridCards(containerId, items, cardHtml) {
		const el = document.getElementById(containerId);
		if (!el || !Array.isArray(items)) return;
		el.innerHTML = items.map(cardHtml).join('');
	}

	function applyHome(h) {
		if (!h) return;
		if (h.meta) {
			if (h.meta.title) document.title = h.meta.title;
		}
		if (h.heroSlides) renderHeroSlides(h.heroSlides);
		if (h.intro) {
			applyText('[data-cms="pages.home.intro.eyebrow"]', h.intro.eyebrow);
			applyText('[data-cms="pages.home.intro.title"]', h.intro.title);
			applyText('[data-cms="pages.home.intro.paragraph1"]', h.intro.paragraph1);
			applyText('[data-cms="pages.home.intro.paragraph2"]', h.intro.paragraph2);
			applySrc('[data-cms-src="pages.home.intro.image"]', h.intro.image);
		}
		if (h.sectors) {
			applyText('[data-cms="pages.home.sectors.title"]', h.sectors.title);
			applyText('[data-cms="pages.home.sectors.subtitle"]', h.sectors.subtitle);
			renderGridCards('cms-home-sectors', h.sectors.items, (s) => `
				<article class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm transition-all hover:border-brand-orange/30 hover:shadow-md">
					${s.image ? `<img src="${esc(s.image)}" alt="" class="mb-3 h-24 w-full rounded-lg object-cover" loading="lazy" />` : `<span class="mb-3 block text-3xl" aria-hidden="true">${esc(s.icon)}</span>`}
					<h3 class="mb-2 text-lg font-bold text-ink">${esc(s.title)}</h3>
					<p class="text-sm leading-relaxed text-gray-600">${esc(s.desc)}</p>
				</article>`);
		}
		if (h.services) {
			applyText('[data-cms="pages.home.services.title"]', h.services.title);
			applyText('[data-cms="pages.home.services.subtitle"]', h.services.subtitle);
			renderGridCards('cms-home-services', h.services.items, (s) => `
				<a href="${esc(s.href || '#')}" class="group block rounded-xl border border-gray-200 p-6 transition-all hover:border-brand-orange hover:shadow-lg">
					${s.image ? `<img src="${esc(s.image)}" alt="" class="mb-4 h-28 w-full rounded-lg object-cover" loading="lazy" />` : ''}
					<h3 class="mb-2 font-bold text-ink transition-colors group-hover:text-brand-orange">${esc(s.title)}</h3>
					<p class="text-sm text-gray-600">${esc(s.desc)}</p>
				</a>`);
		}
		if (h.process) {
			applyText('[data-cms="pages.home.process.eyebrow"]', h.process.eyebrow);
			applyText('[data-cms="pages.home.process.title"]', h.process.title);
			applyText('[data-cms="pages.home.process.description"]', h.process.description);
			applySrc('[data-cms-src="pages.home.process.image"]', h.process.image);
			const stepsEl = document.getElementById('cms-home-process-steps');
			if (stepsEl && Array.isArray(h.process.steps)) {
				stepsEl.innerHTML = h.process.steps.map((step, index) => `
					<div class="flex gap-4 rounded-xl border border-gray-200 bg-white p-5">
						<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-charcoal text-sm font-black text-brand-orange">${index + 1}</span>
						<p class="self-center text-sm font-semibold leading-relaxed text-gray-800">${esc(step)}</p>
					</div>`).join('');
			}
		}
		if (h.whyChoose) {
			applyText('[data-cms="pages.home.whyChoose.title"]', h.whyChoose.title);
			renderGridCards('cms-home-why', h.whyChoose.items, (item) => `
				<div>
					${item.image ? `<img src="${esc(item.image)}" alt="" class="mb-3 h-24 w-full rounded-lg object-cover" loading="lazy" />` : ''}
					<h3 class="mb-2 font-bold text-brand-orange">${esc(item.title)}</h3>
					<p class="text-sm leading-relaxed text-gray-300">${esc(item.desc)}</p>
				</div>`);
		}
		if (h.faq) {
			applyText('[data-cms="pages.home.faq.title"]', h.faq.title);
			const faqEl = document.getElementById('cms-home-faq');
			if (faqEl && Array.isArray(h.faq.items)) {
				faqEl.innerHTML = h.faq.items.map((item) => `
					<details class="group overflow-hidden rounded-xl border border-gray-200 bg-white">
						<summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-4 font-semibold text-ink hover:bg-gray-50">
							<span>${esc(item.q)}</span>
							<svg class="h-5 w-5 shrink-0 text-brand-orange transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
						</summary>
						<div class="border-t border-gray-100 px-6 pb-4 pt-4 text-sm leading-relaxed text-gray-600">${esc(item.a)}</div>
					</details>`).join('');
			}
		}
		if (h.contactCta) {
			applyText('[data-cms="pages.home.contactCta.eyebrow"]', h.contactCta.eyebrow);
			applyText('[data-cms="pages.home.contactCta.title"]', h.contactCta.title);
			applyText('[data-cms="pages.home.contactCta.description"]', h.contactCta.description);
			applySrc('[data-cms-src="pages.home.contactCta.image"]', h.contactCta.image);
		}
		if (h.projectsSection) {
			applyText('[data-cms="pages.home.projectsSection.eyebrow"]', h.projectsSection.eyebrow);
			applyText('[data-cms="pages.home.projectsSection.title"]', h.projectsSection.title);
			applyText('[data-cms="pages.home.projectsSection.description"]', h.projectsSection.description);
		}
		if (h.brands) {
			applyText('[data-cms="pages.home.brands.title"]', h.brands.title);
			applyText('[data-cms="pages.home.brands.subtitle"]', h.brands.subtitle);
			const brandsEl = document.getElementById('cms-brands-grid');
			if (brandsEl && Array.isArray(h.brands.items)) {
				brandsEl.innerHTML = h.brands.items.map((brand) => `
					<a href="/productos?marca=${encodeURIComponent(brand.name)}" class="group flex h-24 items-center justify-center rounded-2xl border border-gray-200 bg-gradient-to-b from-white to-slate-50 px-4 transition hover:-translate-y-0.5 hover:border-accent-main/30 hover:shadow-md" title="Ver productos ${esc(brand.name)}">
						<img src="${esc(brand.image || `/images/marcas/${brand.slug}.svg`)}" alt="Logo ${esc(brand.name)}" class="max-h-10 max-w-[88%] object-contain opacity-90 grayscale transition group-hover:opacity-100 group-hover:grayscale-0" loading="lazy" width="120" height="40" />
					</a>`).join('');
			}
		}
		if (h.showcase) {
			applyText('[data-cms="pages.home.showcase.eyebrow"]', h.showcase.eyebrow);
			applyText('[data-cms="pages.home.showcase.title"]', h.showcase.title);
			applyText('[data-cms="pages.home.showcase.description"]', h.showcase.description);
		}
	}

	function applyServicios(s) {
		if (!s) return;
		if (s.hero) {
			applyText('[data-cms="pages.servicios.hero.eyebrow"]', s.hero.eyebrow);
			applyText('[data-cms="pages.servicios.hero.title"]', s.hero.title);
			applyText('[data-cms="pages.servicios.hero.subtitle"]', s.hero.subtitle);
			applySrc('[data-cms-src="pages.servicios.hero.image"]', s.hero.image);
		}
		const list = document.getElementById('cms-servicios-list');
		if (list && Array.isArray(s.services)) {
			list.innerHTML = s.services.map((service, index) => `
				<div id="${esc(service.id)}" class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 items-center ${index % 2 === 1 ? 'lg:grid-flow-col-dense' : ''}">
					<div class="${index % 2 === 1 ? 'lg:col-start-2' : ''}">
						<span class="inline-flex rounded-md bg-brand-orange/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-brand-orange">${esc(service.kicker)}</span>
						<h2 class="mt-4 text-2xl font-black leading-tight text-ink lg:text-3xl">${esc(service.title)}</h2>
						<p class="mt-4 text-base leading-relaxed text-gray-600">${esc(service.description)}</p>
						<ul class="mt-6 grid gap-2 text-gray-700">
							${(service.details || []).map((d) => `<li class="flex items-start rounded-lg border border-gray-200 bg-gray-50 p-3"><svg class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-accent-main" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span class="text-sm font-medium leading-relaxed">${esc(d)}</span></li>`).join('')}
						</ul>
						<div class="mt-7"><a href="/contacto" class="inline-flex rounded-md bg-charcoal px-6 py-3 text-sm font-bold text-white transition hover:bg-charcoal-light">Cotizar este servicio</a></div>
					</div>
					<div class="relative min-h-[320px] overflow-hidden rounded-2xl border border-gray-200 bg-slate-100 shadow-lg ${index % 2 === 1 ? 'lg:col-start-1' : ''}">
						<img src="${esc(service.image)}" alt="${esc(service.title)}" class="h-full min-h-[320px] w-full object-cover" loading="lazy" width="1200" height="760" />
						<div class="absolute inset-0 bg-gradient-to-t from-ink/35 via-transparent to-transparent"></div>
					</div>
				</div>`).join('');
		}
	}

	function applyContent(content) {
		window.MIZO_SITE_CONTENT = content;
		applyGlobal(content.global);
		const path = window.location.pathname.replace(/\/$/, '') || '/';
		if (path === '/' || path === '/index.html') applyHome(content.pages?.home);
		if (path === '/servicios') applyServicios(content.pages?.servicios);
		document.dispatchEvent(new CustomEvent('mizo:content-ready', { detail: content }));
	}

	async function hydrate() {
		try {
			const content = await fetchContent();
			applyContent(content);
		} catch (e) {
			console.warn('CMS hydrate failed', e);
		}
	}

	document.addEventListener('DOMContentLoaded', hydrate);
	document.addEventListener('astro:page-load', hydrate);
})();
