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
	{ title: 'Servicios', href: '/servicios' },
	{ title: 'Industrias', href: '/industrias' },
	{ title: 'Nosotros', href: '/nosotros' },
	{ title: 'Contacto', href: '/contacto' },
] as const;

export const SERVICES = [
	{
		id: 'sonido',
		title: 'Sistemas de Sonido Profesional y Comercial',
		short: 'Diseño, calibración e instalación de audio para espacios comerciales, iglesias, recintos educacionales y salas de conferencias.',
		description:
			'Diseño, calibración e instalación de sistemas de audio para espacios comerciales, iglesias, recintos educacionales y salas de conferencias. Incluye sonido profesional, música ambiental y llamado por zonas.',
		works: [
			'Instalación de parlantes de cielo y muralla',
			'Amplificación multizona',
			'Sistemas de música ambiental',
			'Sistemas de llamado multizona',
			'Configuración de procesadores de audio DSP',
			'Montaje de sistemas line array o columnas acústicas',
			'Optimización de inteligibilidad de la palabra',
		],
		href: '/servicios#sonido',
	},
	{
		id: 'video',
		title: 'Videoproyección y Audiovisuales',
		short: 'Integración de sistemas de visualización de alto impacto para auditorios, salas de reuniones y espacios corporativos.',
		description:
			'Integración de sistemas de visualización de alto impacto para auditorios, salas de reuniones y espacios corporativos.',
		works: [
			'Montaje de proyectores láser de alta luminosidad',
			'Instalación de telones motorizados',
			'Integración de matrices HDMI/HDBaseT',
			'Configuración de sistemas de presentación inalámbrica',
		],
		href: '/servicios#video',
	},
	{
		id: 'cctv',
		title: 'Instalación de Cámaras de Seguridad (CCTV)',
		short: 'Televigilancia avanzada para protección perimetral e interior en entornos residenciales, comerciales e industriales.',
		description:
			'Sistemas de televigilancia avanzados para protección perimetral e interior en entornos residenciales, comerciales e industriales.',
		works: [
			'Instalación de cámaras IP de alta resolución con visión nocturna',
			'Configuración de grabadores NVR/DVR',
			'Cableado estructurado certificado',
			'Acceso remoto seguro para monitoreo en tiempo real desde dispositivos móviles',
		],
		href: '/servicios#cctv',
	},
	{
		id: 'ti',
		title: 'Soporte TI e Infraestructura',
		short: 'Mantenimiento preventivo y correctivo, redes, soporte técnico y desarrollo de aplicaciones a medida.',
		description:
			'Mantenimiento preventivo y correctivo, redes, soporte técnico especializado y desarrollo de aplicaciones a medida para la continuidad operacional de tu negocio.',
		works: [
			'Certificación y armado de gabinetes de red (Racks)',
			'Configuración de routers y switches corporativos',
			'Cableado de red UTP / fibra óptica',
			'Soporte a equipos de computación',
			'Desarrollo de aplicaciones a medida',
		],
		href: '/servicios#ti',
	},
] as const;

export type ServiceId = (typeof SERVICES)[number]['id'];

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
