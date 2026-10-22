// @ts-check
import sitemap from '@astrojs/sitemap';
import tailwind from '@astrojs/tailwind';
import { defineConfig } from 'astro/config';

export default defineConfig({
	site: 'https://mizo.cl',
	output: 'static',
	compressHTML: true,
	redirects: {
		'/servicios': '/industrias',
		'/estandares': '/industrias',
		'/instalaciones/instalacion-de-proyectores': '/instalaciones',
		'/instalaciones/proyectores-interactivos': '/instalaciones',
		'/instalaciones/instalacion-de-video-wall': '/instalaciones',
	},
	integrations: [
		tailwind(),
		sitemap({
			filter: (page) =>
				!page.includes('/404') &&
				!page.includes('/instalacion-de-proyectores') &&
				!page.includes('/proyectores-interactivos') &&
				!page.includes('/instalacion-de-video-wall'),
			serialize(item) {
				const pathname = new URL(item.url).pathname.replace(/\/$/, '') || '/';
				const priority =
					pathname === '/'
						? 1
						: ['/contacto', '/nosotros', '/industrias', '/equipos'].includes(pathname) || pathname.startsWith('/industrias/')
							? 0.9
							: 0.4;
				return {
					...item,
					priority,
					changefreq: pathname === '/' ? 'weekly' : 'monthly',
					lastmod: new Date().toISOString(),
				};
			},
		}),
	],
});
