/** @type {import('tailwindcss').Config} */
export default {
	content: ['./src/**/*.{astro,html,js,ts}'],
	theme: {
		extend: {
			colors: {
				accent: {
					DEFAULT: '#1c9bd8',
					hover: '#1684bc',
					dark: '#0e6491',
					soft: '#e2f3fb',
				},
				orange: {
					DEFAULT: '#f47b20',
					hover: '#e06814',
					soft: '#fdebd9',
				},
				ink: '#181b20',
				charcoal: {
					DEFAULT: '#0f1114',
					light: '#1c1f26',
					muted: '#2a2e36',
				},
			},
			fontFamily: {
				sans: ['Helvetica', 'Arial', 'ui-sans-serif', 'system-ui', 'sans-serif'],
			},
			boxShadow: {
				card: '0 8px 30px rgba(15, 17, 20, 0.08)',
				glow: '0 0 0 1px rgba(28, 155, 216, 0.45), 0 16px 48px rgba(28, 155, 216, 0.16)',
				'glow-orange': '0 0 0 1px rgba(244, 123, 32, 0.45), 0 14px 40px rgba(244, 123, 32, 0.22)',
			},
		},
	},
	plugins: [],
};
