export const SITE = {
	name: 'Mizo',
	legalName: 'Mizo',
	url: 'https://mizo.cl',
	title: 'Mizo | Ingeniería en sonido, video, CCTV y soporte TI en Chile',
	description:
		'Instalación profesional de sistemas de sonido, videoproyección, cámaras de seguridad y soporte TI en Frutillar, Santiago y el sur de Chile.',
	email: 'ventas@mizo.cl',
	phone: '+56994390870',
	phoneDisplay: '+56 9 9439 0870',
	whatsapp: '56994390870',
	whatsappMessage: 'Hola Mizo, quiero cotizar un servicio profesional.',
	address: 'Frutillar y Santiago, Chile',
	hours: 'Lunes a viernes, 9:00 a 18:00 hrs.',
	locale: 'es_CL',
	language: 'es-CL',
	ogImage: '/mizo-social-preview.jpg.png',
	logo: '/mizo-logo.png',
	logoFooter: '/mizo-logo-footer.png',
	areas: ['Frutillar', 'Santiago', 'Sur de Chile'],
} as const;

export const NAV = [
	{ title: 'Inicio', href: '/' },
	{ title: 'Soluciones', href: '/industrias' },
	{ title: 'Nosotros', href: '/nosotros' },
	{ title: 'Contacto', href: '/contacto' },
] as const;

export const BRANDS = [
	{ name: 'Sony', slug: 'sony' },
	{ name: 'Sennheiser', slug: 'sennheiser' },
	{ name: 'JBL', slug: 'jbl' },
	{ name: 'Bose', slug: 'bose' },
	{ name: 'Shure', slug: 'shure' },
	{ name: 'Epson', slug: 'epson' },
	{ name: 'BenQ', slug: 'benq' },
] as const;

export function whatsappUrl(message = SITE.whatsappMessage): string {
	return `https://wa.me/${SITE.whatsapp}?text=${encodeURIComponent(message)}`;
}

export function telUrl(): string {
	return `tel:${SITE.phone}`;
}

export function mailUrl(): string {
	return `mailto:${SITE.email}`;
}
