import type { LandingFaq, LandingBlock } from './landing-extras';

export type IndustryPageCopy = {
	slug: string;
	nav: string;
	keyword: string;
	title: string;
	seoTitle: string;
	seoDescription: string;
	lead: string;
	consult: string;
	points: readonly string[];
	included: readonly string[];
	steps: readonly LandingBlock[];
	faqs: readonly LandingFaq[];
};

export const INDUSTRY_PAGES: readonly IndustryPageCopy[] = [
	{
		slug: 'restaurantes',
		nav: 'Restaurantes',
		keyword: 'sonido para restaurantes',
		title: 'Instalación de sonido para restaurantes y locales',
		seoTitle: 'Sonido para restaurantes y locales | Mizo',
		seoDescription: 'Sonido para restaurantes en Chile. Cada ambiente con su volumen, sin tapar la conversación. Cotiza con Mizo.',
		lead: 'La música acompaña. Se puede conversar.',
		consult: '¿Tienes dudas con la acústica o los equipos que necesitas para tu restaurante? Consúltanos directamente y un especialista te asesora.',
		points: [
			'Comedor, terraza y barra por separado',
			'Se puede conversar',
			'El personal lo enciende fácil',
			'Listo para usar, con garantía de 12 meses',
		],
		included: ['Visita al local', 'Parlantes por ambiente', 'Instalación y prueba', 'Garantía de 12 meses'],
		steps: [
			{ title: 'Recorremos el local', text: 'Comedor, terraza y barra, cada uno aparte.' },
			{ title: 'Instalamos', text: 'Sin dejar cables a la vista.' },
			{ title: 'Dejamos el control', text: 'El personal sube o baja el volumen solo.' },
		],
		faqs: [
			{ q: '¿La terraza puede sonar distinto?', a: 'Sí. Cada ambiente tiene su volumen.' },
			{ q: '¿Hay que ser técnico para usarlo?', a: 'No. Queda un control simple.' },
		],
	},
	{
		slug: 'instituciones-educativas',
		nav: 'Educación',
		keyword: 'sonido para colegios',
		title: 'Instalación de sonido para colegios y salas',
		seoTitle: 'Sonido para colegios y salas | Mizo',
		seoDescription: 'Sonido para colegios en Chile. La palabra se entiende en el patio y en la sala. Cotiza con Mizo.',
		lead: 'La palabra se entiende en el patio y en la sala.',
		consult: '¿Tienes dudas con la acústica o los equipos que necesita tu colegio? Consúltanos directamente y un especialista te asesora.',
		points: [
			'Se escucha en actos y clases',
			'Avisos claros en todo el recinto',
			'Fácil de usar para el profesor',
			'Listo para usar, con garantía de 12 meses',
		],
		included: ['Visita al colegio', 'Parlantes y micrófonos', 'Prueba en un acto', 'Garantía de 12 meses'],
		steps: [
			{ title: 'Vemos el colegio', text: 'Patio, auditorio y salas, cada uno aparte.' },
			{ title: 'Instalamos', text: 'Parlantes, micrófonos y cables ordenados.' },
			{ title: 'Probamos un aviso', text: 'Ajustamos la voz antes de irnos.' },
		],
		faqs: [
			{ q: '¿Sirve para el patio y la sala?', a: 'Sí. Cada espacio tiene su volumen.' },
			{ q: '¿Y si algo falla después?', a: 'Tienes 12 meses de garantía y un contacto directo.' },
		],
	},
	{
		slug: 'auditorios',
		nav: 'Auditorios',
		keyword: 'sonido para auditorios',
		title: 'Instalación de sonido para auditorios y eventos',
		seoTitle: 'Sonido para auditorios y eventos | Mizo',
		seoDescription: 'Sonido para auditorios en Chile. La voz y la música se entienden en toda la sala. Cotiza con Mizo.',
		lead: 'La voz y la música se entienden en toda la sala.',
		consult: '¿Tienes dudas con la acústica o los equipos que necesitas para tu auditorio? Consúltanos directamente y un especialista te asesora.',
		points: [
			'Se entiende hasta la última fila',
			'Conferencia o música, cada una en su lugar',
			'Listo para el evento',
			'Instalado y con garantía de 12 meses',
		],
		included: ['Visita al auditorio', 'Parlantes y micrófonos', 'Prueba del evento', 'Garantía de 12 meses'],
		steps: [
			{ title: 'Vemos la sala', text: 'Tamaño, butacas y tipo de evento.' },
			{ title: 'Instalamos', text: 'El sonido donde realmente se escucha.' },
			{ title: 'Probamos el evento', text: 'Ajustamos voz y música antes de irnos.' },
		],
		faqs: [
			{ q: '¿Sirve para charlas y para música?', a: 'Sí. Las dos, en el mismo sistema.' },
			{ q: '¿Cuántos parlantes necesito?', a: 'Depende de la sala. Te lo decimos después de verla.' },
		],
	},
	{
		slug: 'gimnasios',
		nav: 'Gimnasios',
		keyword: 'sonido para gimnasios',
		title: 'Instalación de sonido para gimnasios',
		seoTitle: 'Sonido para gimnasios | Mizo',
		seoDescription: 'Sonido para gimnasios en Chile. La música motiva y el instructor se escucha. Cotiza con Mizo.',
		lead: 'La música motiva. El instructor se escucha.',
		consult: '¿Tienes dudas con la acústica o los equipos que necesitas para tu gimnasio? Consúltanos directamente y un especialista te asesora.',
		points: [
			'Cada sala con su volumen',
			'El profesor se oye sobre la música',
			'Aguanta el uso de todo el día',
			'Listo para usar, con garantía de 12 meses',
		],
		included: ['Visita al gimnasio', 'Parlantes y micrófono', 'Prueba de una clase', 'Garantía de 12 meses'],
		steps: [
			{ title: 'Recorremos las salas', text: 'Pesas, cardio y clases, cada una aparte.' },
			{ title: 'Instalamos', text: 'Música y voz del instructor en su lugar.' },
			{ title: 'Probamos una clase', text: 'Ajustamos el volumen antes de irnos.' },
		],
		faqs: [
			{ q: '¿La clase puede sonar más fuerte?', a: 'Sí. Cada sala tiene su volumen.' },
			{ q: '¿El micrófono aguanta el entrenamiento?', a: 'Sí. Queda uno pensado para la clase.' },
		],
	},
	{
		slug: 'entretenimiento-residencial',
		nav: 'Casas y quinchos',
		keyword: 'sonido para quinchos',
		title: 'Instalación de sonido para casas y quinchos',
		seoTitle: 'Sonido para casas y quinchos | Mizo',
		seoDescription: 'Sonido para quinchos y terrazas en Chile. Música afuera, sin cables a la vista. Cotiza con Mizo.',
		lead: 'El quincho y la terraza suenan, sin cables a la vista.',
		consult: '¿Tienes dudas con la acústica o los equipos que necesitas para tu casa o quincho? Consúltanos directamente y un especialista te asesora.',
		points: [
			'Música en el quincho y la terraza',
			'Volumen aparte del interior',
			'Resiste lluvia y sol',
			'Listo para usar, con garantía de 12 meses',
		],
		included: ['Visita a la casa', 'Parlantes de exterior', 'Instalación y prueba', 'Garantía de 12 meses'],
		steps: [
			{ title: 'Vemos el quincho', text: 'Terraza, interior y dónde se controla.' },
			{ title: 'Instalamos', text: 'Parlantes de exterior, sin cables a la vista.' },
			{ title: 'Dejamos el control', text: 'Se enciende fácil desde la casa.' },
		],
		faqs: [
			{ q: '¿Se puede usar desde el celular?', a: 'Sí. Queda un control simple.' },
			{ q: '¿Aguanta la lluvia?', a: 'Sí. Van parlantes de exterior.' },
		],
	},
];

export function industryPageBySlug(slug: string): IndustryPageCopy | undefined {
	return INDUSTRY_PAGES.find((page) => page.slug === slug);
}
