// tailwind.config.mjs - Paleta basada en el logo Mizo: azul de la onda, naranja del diafragma y negro

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './src/**/*.{astro,html,js,jsx,md,mdx,ts,tsx}',
  ],
  theme: {
    extend: {
      maxWidth: {
        '8xl': '90rem',
        '9xl': '100rem',
        '10xl': '112rem',
      },
      colors: {
        // Azul del logo (la onda de sonido)
        'accent-main': '#1c9bd8',
        'accent-hover': '#1684bc',
        'accent-dark': '#0e6491',
        'accent-light': '#54b9e6',
        'accent-soft': '#e2f3fb',
        // Naranja del logo (el diafragma)
        'brand-orange': '#f47b20',
        'brand-orange-hover': '#e06814',
        'brand-orange-dark': '#c2560d',
        'brand-orange-soft': '#fdebd9',
        // Verde fosforescente (cintillo superior)
        'brand-neon': '#39ff14',
        'brand-neon-dark': '#1a8f0a',
        // Negro suavizado: gris muy oscuro, cercano a negro (reemplaza al negro puro)
        'ink': '#181b20',
        // Sustituye los tonos "negros" usados en el sitio por un gris muy oscuro
        black: '#181b20',
        slate: {
          950: '#181b20',
        },
        gray: {
          950: '#181b20',
        },
      },
      fontFamily: {
        sans: ['Helvetica', 'Arial', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
