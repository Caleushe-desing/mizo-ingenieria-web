// tailwind.config.mjs - Paleta clara: fondo blanco, letras negras y matices azulados

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
        'accent-main': '#0284c7',
        'accent-hover': '#0369a1',
        'accent-dark': '#075985',
        'accent-light': '#38bdf8',
        'accent-soft': '#e0f2fe',
      },
      fontFamily: {
        sans: ['Helvetica', 'Arial', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
