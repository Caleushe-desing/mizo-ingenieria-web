import { whatsappUrl } from './site';

export type Landing = {
	slug: string;
	nav: string;
	keyword: string;
	title: string;
	seoTitle: string;
	seoDescription: string;
	lead: string;
	consult?: string;
	detail: string;
	points: readonly string[];
	galleryAlts: readonly [string, string, string];
};

export const LANDINGS: readonly Landing[] = [
	{
		slug: 'parlantes-para-iglesias',
		nav: 'Parlantes para iglesias',
		keyword: 'parlantes para iglesias',
		title: 'Instalación de sonido para iglesias',
		seoTitle: 'Instalación de sonido para iglesias | Mizo Chile',
		seoDescription: 'Sonido para iglesias en Chile. La palabra se entiende en cada banco, sin acoples. Cotiza con Mizo.',
		lead: 'La palabra se entiende en cada banco. Sin acoples.',
		consult: '¿Tienes dudas con la acústica o los equipos que necesita tu iglesia? Consúltanos directamente y un especialista te asesora.',
		detail: 'Instalamos el sonido del templo para que la predicación y la música se escuchen claras.',
		points: [
			'Se entiende hasta la última fila',
			'Sin acoples en el culto',
			'Música y palabra, cada una en su lugar',
			'Listo para usar, con garantía de 12 meses',
		],
		galleryAlts: [
			'Parlantes en el interior de una iglesia',
			'Columnas de audio junto al altar',
			'Culto con parlantes en el escenario',
		],
	},
	{
		slug: 'instalacion-de-proyectores',
		nav: 'Instalación de proyectores',
		keyword: 'instalacion de proyectores',
		title: 'Instalación de proyectores',
		seoTitle: 'Instalación de proyectores | Salas y auditorios | Mizo Chile',
		seoDescription:
			'Instalación de proyectores láser para salas, auditorios, iglesias y empresas en Chile. Montaje, pantalla y calibración por Mizo.',
		lead: 'Instalamos proyectores láser de alta luminosidad para que la imagen se lea con luz de sala, en clases, cultos, reuniones y eventos.',
		detail:
			'La instalación de proyectores no termina en colgar el equipo. Definimos distancia, tamaño de imagen, tipo de pantalla y canalización para que el haz no cruce pasillos ni quede tapado por luminarias. En colegios, empresas, iglesias y auditorios dejamos el sistema listo: fuente HDMI o inalámbrica, audio asociado y ajuste de geometría. Mizo integra la proyección como parte del recinto, con soporte en Chile.',
		points: [
			'Proyectores láser según luz ambiente del recinto',
			'Pantalla, soporte y canalización ordenada',
			'Imagen legible en clases, cultos y reuniones',
			'Puesta en marcha y capacitación de uso',
		],
		galleryAlts: [
			'Proyector de techo en un auditorio',
			'Montaje de un proyector',
			'Sala con pantalla de proyección',
		],
	},
	{
		slug: 'instalacion-de-musica-ambiental',
		nav: 'Música ambiental',
		keyword: 'instalacion de musica ambiental',
		title: 'Instalación de sonido ambiental para restaurantes y locales',
		seoTitle: 'Sonido ambiental para restaurantes y locales | Mizo',
		seoDescription: 'Música ambiental por zonas para restaurantes y locales. Cada ambiente con su volumen, sin tapar la conversación.',
		lead: 'Cada ambiente con su volumen. La música acompaña, no interrumpe.',
		consult: '¿Tienes dudas con la acústica o los equipos que necesitas para tu local? Consúltanos directamente y un especialista te asesora.',
		detail: 'Comedor, terraza y barra suenan aparte. El personal lo maneja en un toque.',
		points: [
			'Comedor, terraza y barra por separado',
			'Se puede conversar',
			'El personal lo enciende fácil',
			'Sin cables a la vista',
		],
		galleryAlts: [
			'Música ambiental en un restaurante',
			'Parlante de techo en un local',
			'Parlantes de terraza',
		],
	},
	{
		slug: 'proyectores-interactivos',
		nav: 'Proyectores interactivos',
		keyword: 'proyectores interactivos',
		title: 'Proyectores interactivos',
		seoTitle: 'Proyectores interactivos | Aulas y salas | Mizo Chile',
		seoDescription:
			'Instalación de proyectores interactivos para colegios, capacitaciones y salas de reunión en Chile. Montaje, calibración táctil y soporte Mizo.',
		lead: 'Instalamos proyectores interactivos para que la clase o la reunión se escriba sobre la imagen, sin depender de una pizarra aparte.',
		detail:
			'Un proyector interactivo solo rinde si el montaje respeta la altura de quien escribe, la sombra del presentador y la calibración táctil. En colegios y salas de capacitación Mizo instala el proyector, la superficie de proyección y la conexión con el computador de la sala. Dejamos el sistema probado con el equipo docente o de capacitación, y disponible para soporte posterior en Chile.',
		points: [
			'Calibración táctil y altura de escritura',
			'Salas de clases, capacitación y directorio',
			'Conexión estable con el computador de la sala',
			'Inducción de uso para quien opera el equipo',
		],
		galleryAlts: [
			'Proyector interactivo en un aula',
			'Uso táctil de la proyección',
			'Proyector de corto alcance en sala',
		],
	},
	{
		slug: 'instalacion-de-parlantes',
		nav: 'Instalación de parlantes',
		keyword: 'instalación de parlantes',
		title: 'Instalación de parlantes para locales, salas y empresas',
		seoTitle: 'Instalación de parlantes | Mizo Chile',
		seoDescription: 'Instalamos parlantes para que el sonido se escuche parejo en todo el recinto. Cotiza con Mizo.',
		lead: 'Se escucha en todo el lugar, no solo junto al equipo.',
		consult: '¿Tienes dudas con la acústica o los equipos que necesitas para tu sala o empresa? Consúltanos directamente y un especialista te asesora.',
		detail: 'Definimos cuántos parlantes van y dónde, según el tamaño y el uso del espacio.',
		points: [
			'Sonido parejo en toda la sala',
			'Techo o muro, según el lugar',
			'Música, avisos o voz, bien claros',
			'Instalado y con garantía de 12 meses',
		],
		galleryAlts: [
			'Instalación de un parlante de techo',
			'Parlantes de muro en un salón',
			'Rack de amplificación',
		],
	},
	{
		slug: 'instalacion-de-video-wall',
		nav: 'Video wall',
		keyword: 'instalación de video wall',
		title: 'Instalación de video wall',
		seoTitle: 'Instalación de video wall | Pantallas LED | Mizo Chile',
		seoDescription:
			'Instalación de video wall y pantallas LED para retail, auditorios y empresas en Chile. Estructura, procesamiento de imagen y calibración Mizo.',
		lead: 'Instalamos video wall LED para que la imagen se vea como una sola pieza: brillo, juntas y contenido alineados al espacio.',
		detail:
			'Un video wall mal instalado se nota en las juntas, el brillo desigual y el contenido que no calza con el módulo. Mizo define tamaño, pitch y estructura según la distancia de visión, deja el procesamiento de video y la fuente de contenido operables, y calibra color para que la pared se lea de día. Proyectos de retail, auditorios, iglesias y salas corporativas en todo Chile.',
		points: [
			'Tamaño y pitch según la distancia de visión',
			'Estructura, alimentación y procesamiento de video',
			'Calibración de brillo y color del muro',
			'Contenido listo para operar en el día a día',
		],
		galleryAlts: [
			'Video wall LED en un lobby',
			'Montaje de paneles LED',
			'Video wall en un local',
		],
	},
];

const SOUND_SLUGS = new Set([
	'parlantes-para-iglesias',
	'instalacion-de-musica-ambiental',
	'instalacion-de-parlantes',
]);

export const SOUND_LANDINGS: readonly Landing[] = LANDINGS.filter((landing) => SOUND_SLUGS.has(landing.slug));

export function landingPath(slug: string): string {
	return `/instalaciones/${slug}`;
}

export function landingBySlug(slug: string): Landing | undefined {
	return LANDINGS.find((landing) => landing.slug === slug);
}

export function landingQuoteUrl(landing: Landing): string {
	const mensaje = `Hola, quiero cotizar ${landing.keyword}.`;
	const params = new URLSearchParams({
		servicio: landing.title,
		mensaje,
	});
	return `/contacto?${params.toString()}`;
}

export function landingWhatsapp(landing: Landing): string {
	return whatsappUrl(`Hola Mizo, quiero cotizar ${landing.keyword}.`);
}
