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
	},
	integrations: [
		tailwind(),
		sitemap({
			filter: (page) => !page.includes('/404'),
			serialize(item) {
				const pathname = new URL(item.url).pathname.replace(/\/$/, '') || '/';
				const priority =
					pathname === '/'
						? 1
						: ['/contacto', '/nosotros', '/industrias'].includes(pathname) || pathname.startsWith('/industrias/')
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
