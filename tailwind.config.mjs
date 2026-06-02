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
        // Acento corporativo: azul estilo Facebook
        'accent-main': '#1877f2',   // azul principal (botones / enlaces)
        'accent-hover': '#166fe5',  // azul hover
        'accent-dark': '#0d5bd7',   // azul intenso (banner / footer)
        'accent-light': '#4d9cf7',  // azul claro
        'accent-soft': '#e7f0ff',   // azul muy suave (fondos)
      },
      fontFamily: {
        sans: ['Atkinson', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
