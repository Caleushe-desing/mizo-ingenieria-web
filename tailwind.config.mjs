// 123. tailwind.config.mjs - Actualización a Colores Corporativos (Azul Acero)

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './src/**/*.{astro,html,js,jsx,md,mdx,ts,tsx}',
  ],
  theme: {
    extend: {
      // Nuevos anchos máximos para pantallas grandes (mantener)
      maxWidth: {
        '8xl': '90rem',   
        '9xl': '100rem',  
        '10xl': '112rem', 
      },
      colors: {
        // Paleta de Mizo: Negro, Azul Acero, Blanco, Gris
        // Fondo Oscuro (Negro/Gris Oscuro)
        'gray-900': '#111827',
        'gray-800': '#1f2937',
        'gray-700': '#374151',
        
        // Color de Acento Profesional (Azul Acero - Blue 500)
        'accent-main': '#3b82f6', // Color primario (Blue-500)
        'accent-hover': '#60a5fa', // Blue-400 para hover
        'accent-dark': '#2563eb',  // Blue-600 para activos/contrastes
      },
    },
  },
  plugins: [],
};