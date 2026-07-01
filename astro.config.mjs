// @ts-check

import mdx from '@astrojs/mdx';
import sitemap from '@astrojs/sitemap';
import tailwind from '@astrojs/tailwind';
import { defineConfig } from 'astro/config';

export default defineConfig({
	site: 'https://mizo.cl',
	output: 'static',
	integrations: [
		mdx(),
		sitemap({
			filter: (page) => !page.includes('/admin'),
		}),
		tailwind(),
	],
});