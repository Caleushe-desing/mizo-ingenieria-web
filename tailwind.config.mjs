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
        // Acento corporativo: Azul petróleo
        'accent-main': '#15616d',   // petróleo (botones / enlaces)
        'accent-hover': '#114e58',  // petróleo oscuro (hover)
        'accent-dark': '#0b3a42',   // petróleo muy oscuro (banner / footer)
        'accent-light': '#3e8e99',  // petróleo claro
        'accent-soft': '#e6f1f2',   // petróleo muy suave (fondos)
      },
      fontFamily: {
        sans: ['Atkinson', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
