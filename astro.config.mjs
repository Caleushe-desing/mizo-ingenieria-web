// astro.config.mjs
// @ts-check

import mdx from '@astrojs/mdx';
import sitemap from '@astrojs/sitemap';
import { defineConfig } from 'astro/config';

// ELIMINA: import tailwindcss from '@tailwindcss/vite'; 
import tailwind from "@astrojs/tailwind"; // <--- NUEVA IMPORTACIÓN

// https://astro.build/config
export default defineConfig({
  site: 'https://example.com',
  integrations: [
      mdx(), 
      sitemap(),
      tailwind(), // <--- NUEVA INTEGRACIÓN
  ],

  // ELIMINA COMPLETAMENTE ESTE BLOQUE:
  // vite: {
  //   plugins: [tailwindcss()],
  // },
});