// @ts-check

import mdx from '@astrojs/mdx';
import sitemap from '@astrojs/sitemap';
import tailwind from '@astrojs/tailwind';
import { defineConfig } from 'astro/config';

const NOINDEX_PATHS = [
	'/admin',
	'/carrito',
	'/gracias',
	'/gracias-encuesta',
	'/encuesta-satisfaccion',
	'/about',
	'/blog',
];

function isIndexablePage(url) {
	const pathname = new URL(url).pathname.replace(/\/$/, '') || '/';
	return !NOINDEX_PATHS.some((blocked) => pathname === blocked || pathname.startsWith(`${blocked}/`));
}

function pagePriority(url) {
	const pathname = new URL(url).pathname;
	if (pathname === '/' || pathname === '') return 1;
	if (pathname.startsWith('/productos/')) return 0.8;
	if (['/servicios', '/contacto', '/productos', '/nosotros', '/equipamiento'].includes(pathname)) return 0.9;
	if (['/testimonios', '/configurador'].includes(pathname)) return 0.7;
	if (['/terminos', '/politica-privacidad'].includes(pathname)) return 0.3;
	return 0.6;
}

function pageChangeFreq(url) {
	const pathname = new URL(url).pathname;
	if (pathname === '/' || pathname === '') return 'weekly';
	if (pathname.startsWith('/productos')) return 'weekly';
	if (['/servicios', '/contacto', '/nosotros', '/equipamiento'].includes(pathname)) return 'monthly';
	return 'monthly';
}

export default defineConfig({
	site: 'https://mizo.cl',
	output: 'static',
	integrations: [
		mdx(),
		sitemap({
			filter: (page) => isIndexablePage(page),
			serialize(item) {
				return {
					...item,
					priority: pagePriority(item.url),
					changefreq: pageChangeFreq(item.url),
					lastmod: item.lastmod || new Date().toISOString(),
				};
			},
		}),
		tailwind(),
	],
});
