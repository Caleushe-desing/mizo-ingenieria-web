import { whatsappUrl } from './site';

export type Landing = {
	slug: string;
	nav: string;
	keyword: string;
	title: string;
	seoTitle: string;
	seoDescription: string;
	lead: string;
	detail: string;
	points: readonly string[];
	galleryAlts: readonly [string, string, string];
};

export const LANDINGS: readonly Landing[] = [
	{
		slug: 'parlantes-para-iglesias',
		nav: 'Parlantes para iglesias',
		keyword: 'parlantes para iglesias',
		title: 'Parlantes para iglesias',
		seoTitle: 'Parlantes para iglesias | Instalación de audio | Mizo Chile',
		seoDescription:
			'Instalación de parlantes para iglesias en Chile. Cobertura de palabra y música en templo, con micrófonos, procesador y soporte local de Mizo.',
		lead: 'El mensaje se entiende en cada banco. Diseñamos e instalamos parlantes para iglesias con cobertura pareja, sin acoples y con música que acompaña el culto.',
		detail:
			'Una iglesia no se sonoriza como un salón de eventos. La palabra del púlpito tiene que llegar nítida al fondo, el coro y la banda necesitan monitores, y el volumen no puede fatigar a quien está en la primera fila. Mizo dimensiona parlantes, amplificación y procesamiento según el templo: nave, balcón, patio y salas anexas. Trabajamos en Frutillar, Santiago y el resto de Chile, con puesta en marcha y 12 meses de garantía en la integración.',
		points: [
			'Cobertura de palabra en nave, balcón y anexos',
			'Micrófonos de púlpito, coro y banda sin acople',
			'Procesamiento y zonas para culto, ensayo y avisos',
			'Instalación, calibración y postventa en Chile',
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
		title: 'Instalación de música ambiental',
		seoTitle: 'Instalación de música ambiental | Locales y restaurantes | Mizo',
		seoDescription:
			'Instalación de música ambiental por zonas para restaurantes, retail y oficinas en Chile. Volumen independiente y audio que no tapa la conversación.',
		lead: 'Instalamos música ambiental por zonas para que cada ambiente del local tenga el volumen justo, sin saturar la conversación ni el servicio.',
		detail:
			'La música ambiental bien instalada se nota por lo que no interrumpe. En un restaurante el comedor va más bajo que la terraza; en una tienda el acceso puede invitar y el probador quedar discreto. Mizo diseña la instalación de música ambiental con parlantes de techo o de muro, amplificación por zonas y una fuente simple de operar para el equipo del local. Cubrimos proyectos en Santiago, Frutillar y todo Chile.',
		points: [
			'Zonas independientes: comedor, terraza, barra o retail',
			'Audio pensado para conversar, no para un concierto',
			'Control simple para el personal del local',
			'Integración prolija en cielo, muro o exterior cubierto',
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
		title: 'Instalación de parlantes',
		seoTitle: 'Instalación de parlantes | Audio profesional | Mizo Chile',
		seoDescription:
			'Instalación de parlantes para locales, auditorios, iglesias y empresas en Chile. Diseño de cobertura, amplificación y calibración por Mizo.',
		lead: 'Instalamos parlantes según el recinto: cobertura, potencia y ubicación para que el audio se escuche parejo, no solo fuerte junto al equipo.',
		detail:
			'La instalación de parlantes parte del plano del lugar. Medimos distancia, altura de cielo y uso real —avisos, música, palabra o espectáculo— antes de definir cantidad y modelo. Canalizamos de forma ordenada, amplificamos por zonas y ecualizamos para quitar resonancias. Mizo ejecuta la instalación completa en Chile y deja el sistema con garantía de integración.',
		points: [
			'Diseño de cobertura según el plano del recinto',
			'Parlantes de techo, muro o arreglo según el uso',
			'Amplificación, cableado y ecualización',
			'Garantía de 12 meses en la integración',
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
