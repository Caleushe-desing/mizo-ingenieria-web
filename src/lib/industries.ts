import { whatsappUrl } from './site';

export type IndustrySolution = {
	id: string;
	title: string;
	eyebrow: string;
	description: string;
	points: readonly string[];
	imageLabel: string;
};

export type Industry = {
	slug: string;
	nav: string;
	title: string;
	short: string;
	lead: string;
	imageLabel: string;
	solutions: readonly IndustrySolution[];
};

export const INDUSTRIES: readonly Industry[] = [
	{
		slug: 'restaurantes',
		nav: 'Restaurantes',
		title: 'Restaurantes y locales gastronómicos',
		short: 'Sonido por zonas, pantallas de alta definición y karaoke para que cada ambiente del local tenga su propia atmósfera.',
		lead: 'El comedor, la terraza y la barra se diseñan como zonas independientes de alta fidelidad, con el volumen que cada una admite. Un ingeniero de Mizo define el sistema para ese local y lo acompaña hasta la puesta en marcha.',
		imageLabel: 'Comedor, terraza y barra de un local gastronómico',
		solutions: [
			{
				id: 'sonido-ambiental',
				title: 'Sonido ambiental por zonas',
				eyebrow: 'Ambient Sound',
				description:
					'Sistemas de audio de alta fidelidad para ambientar cada espacio del local de forma independiente: volumen suave en el comedor, más presente en la terraza o en la barra. La música acompaña la estadía sin tapar la conversación ni saturar al cliente.',
				points: [
					'Volumen y programa distintos en comedor, terraza y barra',
					'Alta fidelidad pensada para conversar, no para un recinto de concierto',
					'Atmósfera continua durante todo el servicio',
				],
				imageLabel: 'Audio ambiental en comedor y terraza',
			},
			{
				id: 'pantallas-led',
				title: 'Pantallas LED y visuales de alta definición',
				eyebrow: 'Imagen',
				description:
					'Instalación, calibración y configuración de pantallas LED o proyectores profesionales. Quedan listos para transmitir eventos deportivos, contenido multimedia o cartelería digital comercial con la mayor nitidez que permite el recinto.',
				points: [
					'Eventos deportivos y contenido en vivo',
					'Cartelería digital del local',
					'Calibración de brillo, color y encuadre',
				],
				imageLabel: 'Pantalla LED en barra o salón',
			},
			{
				id: 'karaoke',
				title: 'Sistemas interactivos de karaoke y animación',
				eyebrow: 'Entretenimiento',
				description:
					'Audio y video de alta potencia para noches de animación. El sistema se integra para que los micrófonos respondan con claridad, los acoplamientos queden controlados y la experiencia se mantenga impecable de principio a fin.',
				points: [
					'Micrófonos con respuesta clara sobre la música',
					'Control de acoplamientos en el salón',
					'Video y audio coordinados para la animación',
				],
				imageLabel: 'Noche de karaoke y animación',
			},
		],
	},
	{
		slug: 'instituciones-educativas',
		nav: 'Educación',
		title: 'Instituciones educativas',
		short: 'Sonido para salones y patios, proyección de gran formato e intercomunicación para que el colegio se escuche y se vea con claridad.',
		lead: 'Patio, auditorio y sala de clases se dimensionan para que la palabra se entienda y la imagen se lea. El proyecto se asesora, se instala y se deja operando, con soporte local después de la entrega.',
		imageLabel: 'Patio, auditorio y sala de clases',
		solutions: [
			{
				id: 'sonorizacion',
				title: 'Audio y sonorización para salones y patios',
				eyebrow: 'Cobertura',
				description:
					'Altavoces de alta cobertura, micrófonos inalámbricos y consolas para ceremonias, actos masivos, clases magistrales y avisos generales. La palabra se entiende en el fondo del patio y en la primera fila, sin gritar al micrófono.',
				points: [
					'Actos, licenciaturas y ceremonias',
					'Clases magistrales y avisos generales',
					'Micrófonos inalámbricos y consola de control',
				],
				imageLabel: 'Acto en patio o salón',
			},
			{
				id: 'proyeccion',
				title: 'Proyección y pantallas de gran formato',
				eyebrow: 'Aulas y auditorios',
				description:
					'Proyectores láser de alta luminosidad y pantallas interactivas o de gran formato para aulas y auditorios. La imagen se mantiene legible con luz de sala y acompaña clases, conferencias y presentaciones.',
				points: [
					'Proyección láser de alta luminosidad',
					'Pantallas interactivas o de gran formato',
					'Conferencias y aprendizaje visual',
				],
				imageLabel: 'Aula o auditorio con proyección',
			},
			{
				id: 'intercomunicacion',
				title: 'Intercomunicación y automatización escolar',
				eyebrow: 'Gestión',
				description:
					'Gestión centralizada de timbres automatizados, perifoneo por zonas y control de dispositivos en salas de clases. La institución avisa, llama y programa la jornada desde un punto, sin recorrer cada edificio.',
				points: [
					'Timbres automatizados de la jornada',
					'Perifoneo independiente por zonas',
					'Control de dispositivos en salas de clases',
				],
				imageLabel: 'Control central de avisos y timbres',
			},
		],
	},
	{
		slug: 'auditorios',
		nav: 'Auditorios',
		title: 'Auditorios y centros de eventos',
		short: 'Audio digital Dante, procesamiento DSP, sonorización de concierto y video de gran escala para recintos que cambian de formato cada semana.',
		lead: 'El recinto pasa de conferencia a show sobre una red Dante, con DSP, calibración y video de gran formato. El diseño es de ese auditorio: asesoría, instalación y puesta en marcha con el mismo equipo.',
		imageLabel: 'Auditorio con audio y pantalla de gran formato',
		solutions: [
			{
				id: 'dante',
				title: 'Redes de audio digital y protocolo Dante',
				eyebrow: 'Audio sobre IP',
				description:
					'Infraestructura de audio sobre IP con Dante. Consolas, procesadores y amplificadores se conectan con cable de red estándar. Se eliminan las mangueras analógicas, la latencia se mantiene en cero a efectos prácticos y el recinto puede reconfigurarse sin tender audio de nuevo.',
				points: [
					'Consolas, procesadores y amplificadores en la misma red',
					'Sin mangueras analógicas por el escenario',
					'Latencia imperceptible y cambios de montaje rápidos',
				],
				imageLabel: 'Red Dante en cabina y escenario',
			},
			{
				id: 'dsp',
				title: 'Procesamiento digital y gestión de zonas',
				eyebrow: 'DSP',
				description:
					'Matrices de audio digital para ecualización paramétrica, control de dinámica, supresión de acoplamientos y alineación de tiempos. Cada zona del recinto —platea, foyer, escenario— recibe la señal que le corresponde.',
				points: [
					'Ecualización paramétrica por zona',
					'Control de dinámica y feedback',
					'Alineación de tiempos entre arreglos',
				],
				imageLabel: 'Procesador y matriz de audio',
			},
			{
				id: 'sonorizacion',
				title: 'Sonorización profesional y acústica de alta gama',
				eyebrow: 'Sala',
				description:
					'Sistemas de audio de concierto, arreglos lineales y monitores integrados a la red digital del recinto. La sala queda preparada para palabra, música en vivo y eventos corporativos con el mismo estándar.',
				points: [
					'Arreglos lineales y sistemas de concierto',
					'Monitores integrados a la red del recinto',
					'Palabra y música con el mismo sistema',
				],
				imageLabel: 'Line array y monitores de escenario',
			},
			{
				id: 'video',
				title: 'Video e imagen de gran escala',
				eyebrow: 'Visuales',
				description:
					'Pantallas LED gigantes modulares y proyectores de alta potencia, sincronizados con matrices de video. Sirven para visuales inmersivas, refuerzo de imagen y transmisiones corporativas o artísticas.',
				points: [
					'LED modular de gran formato',
					'Proyectores de alta potencia',
					'Matrices de video para varias fuentes a la vez',
				],
				imageLabel: 'Pantalla LED de gran formato',
			},
		],
	},
	{
		slug: 'gimnasios',
		nav: 'Gimnasios',
		title: 'Centros de entrenamiento y gimnasios',
		short: 'Audio de alto rendimiento por zonas, micrófonos para clases grupales y pantallas para rutinas, cronómetros y contenido del centro.',
		lead: 'Cada sala lleva audio multizona de alta fidelidad, dimensionado para horas de uso continuo. El instructor se escucha con claridad y el sistema queda calibrado, con soporte local cuando el centro ya está operando.',
		imageLabel: 'Sala de entrenamiento y clase grupal',
		solutions: [
			{
				id: 'audio-zonas',
				title: 'Audio de alto rendimiento por zonas',
				eyebrow: 'Motivación',
				description:
					'Sistemas robustos y de alta potencia para operación continua en entrenamiento funcional, zonas de pesas y áreas cardiovasculares. Cada zona tiene su volumen, para que una clase intensa no invada la sala de máquinas.',
				points: [
					'Operación continua en funcional, pesas y cardio',
					'Volumen independiente por sala',
					'Potencia suficiente sin distorsión en clase',
				],
				imageLabel: 'Zona de pesas y cardio',
			},
			{
				id: 'instructores',
				title: 'Inalámbricos para instructores y clases grupales',
				eyebrow: 'Clases',
				description:
					'Micrófonos de diadema resistentes al sudor y receptores de alta fidelidad para spinning, zumba y clases dirigidas. El profesor se escucha con claridad sobre la música, sin saturar ni perder la voz a mitad de la sesión.',
				points: [
					'Diademas pensadas para el uso intenso',
					'Voz clara sobre la música de la clase',
					'Spinning, zumba y entrenamientos dirigidos',
				],
				imageLabel: 'Instructor con micrófono de diadema',
			},
			{
				id: 'pantallas',
				title: 'Pantallas multimedia y cartelería dinámica',
				eyebrow: 'Visual',
				description:
					'Pantallas y monitores de alta resistencia, ubicados donde el alumno los necesita: rutinas, cronómetros de alta visibilidad y entretenimiento del centro. La imagen acompaña el entrenamiento en lugar de competir con él.',
				points: [
					'Rutinas y contenido de la clase',
					'Cronómetros visibles desde la sala',
					'Cartelería y entretenimiento interno',
				],
				imageLabel: 'Pantallas de rutina y cronómetro',
			},
		],
	},
	{
		slug: 'entretenimiento-residencial',
		nav: 'Residencial',
		title: 'Entretenimiento residencial y quinchos',
		short: 'Cine en casa, audio de exterior para quincho y terraza, y domótica para iluminación, cortinas y equipos desde un toque o la voz.',
		lead: 'La sala se calibra a Dolby Atmos o DTS:X y el quincho lleva audio de intemperie en su propia zona. Es un diseño para esa casa, acompañado desde la asesoría hasta dejar el control en manos de quien vive ahí.',
		imageLabel: 'Sala de cine, quincho y terraza',
		solutions: [
			{
				id: 'home-cinema',
				title: 'Salas de cine en casa de alta fidelidad',
				eyebrow: 'Home Cinema',
				description:
					'Experiencias cinematográficas a medida: proyectores 4K de alto contraste, pantallas acústicamente transparentes y audio multicanal envolvente Dolby Atmos o DTS:X, con calibración acústica. La butaca y la iluminación pueden sincronizarse con la función.',
				points: [
					'Proyección 4K de alto contraste',
					'Pantalla acústicamente transparente',
					'Dolby Atmos o DTS:X calibrado a la sala',
				],
				imageLabel: 'Sala de cine en casa',
			},
			{
				id: 'quinchos',
				title: 'Sonorización para quinchos y terrazas',
				eyebrow: 'Exterior',
				description:
					'Audio de intemperie, resistente a humedad, cambios de temperatura y rayos UV, distribuido en quincho y terraza. Se integra con amplificadores de streaming multizona y subwoofers de exterior ocultos, para que la música esté presente sin equipos a la vista.',
				points: [
					'Parlantes de intemperie para humedad y sol',
					'Streaming multizona desde el interior',
					'Subwoofers de exterior integrados al espacio',
				],
				imageLabel: 'Quincho y terraza con audio de exterior',
			},
			{
				id: 'domotica',
				title: 'Domótica y automatización residencial',
				eyebrow: 'Control',
				description:
					'El hogar se centraliza en un control inteligente: iluminación arquitectónica y de quincho, cortinas motorizadas, climatización y encendido de los equipos audiovisuales. Todo responde a un toque o a la voz, sin recorrer cada ambiente.',
				points: [
					'Iluminación de casa y quincho',
					'Cortinas motorizadas y climatización',
					'Encendido de audio y video por toque o voz',
				],
				imageLabel: 'Control de iluminación y cortinas',
			},
		],
	},
];

export const ENGINEERING_STANDARDS = [
	{ label: 'Dante', detail: 'Audio sobre IP cuando el recinto necesita latencia imperceptible y cambios de montaje.' },
	{ label: 'Multizona', detail: 'Alta fidelidad con volumen y programa independientes en cada ambiente.' },
	{ label: 'Calibración', detail: 'Ajuste acústico real de la sala, no un nivel dejado al azar.' },
	{ label: 'Dolby Atmos', detail: 'Envolvente de cine, con DTS:X, calibrado a la geometría de la sala.' },
	{ label: 'Marcas', detail: 'Integramos Sony, Sennheiser, JBL, Bose, Shure, Epson y BenQ.' },
] as const;

export const ENGINEERING_STEPS = [
	{ title: 'Asesoría', detail: 'Un ingeniero revisa el recinto y define el alcance antes de proponer equipo de más.' },
	{ title: 'Diseño a medida', detail: 'El sistema se dimensiona para ese espacio. No hay un paquete genérico.' },
	{ title: 'Puesta en marcha', detail: 'Queda calibrado, operando y explicado a quien lo va a usar.' },
] as const;

export const SUPPORT_PROMISES = [
	{ title: 'Soporte técnico local', detail: 'Quien instala en Chile es quien responde después.' },
	{ title: 'Postventa garantizada', detail: 'La instalación queda cubierta cuando el sistema ya está en uso.' },
	{ title: 'Respuesta rápida', detail: 'Cotización y WhatsApp directos, sin un call center de por medio.' },
] as const;

export function industryPath(slug: string): string {
	return `/industrias/${slug}`;
}

export function industryBySlug(slug: string): Industry | undefined {
	return INDUSTRIES.find((industry) => industry.slug === slug);
}

export function industryQuoteUrl(industry: Industry): string {
	const mensaje = `Hola, quiero cotizar una solución para ${industry.title}.`;
	return `/contacto?mensaje=${encodeURIComponent(mensaje)}`;
}

export function industryWhatsapp(industry: Industry): string {
	return whatsappUrl(`Hola Mizo, quiero cotizar una solución para ${industry.title}.`);
}
