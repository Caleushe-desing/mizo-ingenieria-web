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
        // Acento corporativo: Azul
        'accent-main': '#2563eb',   // blue-600 (botones / enlaces)
        'accent-hover': '#1d4ed8',  // blue-700 (hover)
        'accent-dark': '#1e3a8a',   // blue-900 (contrastes / footer)
        'accent-light': '#3b82f6',  // blue-500
        'accent-soft': '#eff6ff',   // blue-50 (fondos suaves)
      },
      fontFamily: {
        sans: ['Atkinson', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
