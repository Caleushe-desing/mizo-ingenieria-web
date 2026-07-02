// generate-site-content.mjs
// Genera public/site-content-default.json con el contenido CMS del sitio Mizo.
//
// Uso: node scripts/generate-site-content.mjs

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const OUT_PATH = path.join(root, 'public/site-content-default.json');

const IMG = {
	heroClassroom: 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?auto=format&fit=crop&w=1600&q=80',
	heroChurch: 'https://images.unsplash.com/photo-1507692049790-de58290a4334?auto=format&fit=crop&w=1600&q=80',
	heroAuditorium: 'https://images.unsplash.com/photo-1507901747481-84a4f64fda6d?auto=format&fit=crop&w=1600&q=80',
	heroRetail: 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1600&q=80',
	heroServices: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1600&q=80',
	heroAbout: 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=1600&q=80',
	heroAboutSide: 'https://images.unsplash.com/photo-1581090464777-f3220bbe1b8b?auto=format&fit=crop&w=1100&q=80',
	heroContact: 'https://images.unsplash.com/photo-1581093458791-9d09cc08742a?auto=format&fit=crop&w=1600&q=80',
	heroProducts: 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=1600&q=80',
	heroEquipment: 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1600&q=80',
	audio: 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1200&q=80',
	video: 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80',
	electrical: 'https://images.unsplash.com/photo-1565815017322-9d9cb14f3087?auto=format&fit=crop&w=1200&q=80',
	meeting: 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80',
	engineering: 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=1200&q=80',
	ogDefault: 'https://images.unsplash.com/photo-1507901747481-84a4f64fda6d?auto=format&fit=crop&w=1200&h=630&q=80',
	cintillo: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=800&q=80',
};

function buildSiteContent() {
	return {
		updatedAt: new Date().toISOString(),
		global: {
			phone: '+56994390870',
			phoneDisplay: '+56 9 9439 0870',
			email: 'ventas@mizo.cl',
			whatsappMessage: 'Hola Mizo, quiero diseñar una solución audiovisual',
			address: 'Frutillar y Santiago, Chile',
			hours: 'Lunes a viernes, 9:00 a 18:00 hrs.',
			logo: '/mizo-logo.png',
			logoFooter: '/mizo-logo-footer.png',
			logoAlt: 'Mizo - Sonido, Audiovisual e Instalaciones',
			header: {
				cintilloBadge: 'Mizo',
				cintilloText: 'Ingeniería e instalación de audio y videoproyección en Chile',
				cintilloImage: IMG.cintillo,
			},
			footer: {
				description:
					'Ingeniería e instalación de sistemas de sonido y videoproyección en Chile. Colegios, iglesias, auditorios, comercio e industria.',
				tagline: 'Ingeniería audiovisual · Integración · Soporte técnico',
				image: '/mizo-logo-footer.png',
			},
			nav: [
				{ title: 'Inicio', url: '/' },
				{ title: 'Servicios', url: '/servicios' },
				{ title: 'Productos', url: '/productos' },
				{ title: 'Proyectos', url: '/#proyectos' },
				{ title: 'Nosotros', url: '/nosotros' },
				{ title: 'Contacto', url: '/contacto' },
			],
		},
		pages: {
			home: {
				meta: {
					title: 'Mizo Ingeniería | Sonido, Video, Instalaciones Eléctricas en Chile',
					description:
						'Empresa chilena de ingeniería en sonido, videoproyección e instalaciones eléctricas. Sistemas de audio profesional, proyección, tableros y circuitos para iglesias, colegios, auditorios y empresas. Cotización en Santiago, Frutillar y regiones.',
					keywords:
						'sonido profesional chile, instalación audio iglesias, videoproyección santiago, instalaciones eléctricas chile, sistemas audiovisuales, electricista salas técnicas, Mizo ingeniería',
					image: IMG.ogDefault,
				},
				heroSlides: [
					{
						image: IMG.heroClassroom,
						alt: 'Instalación de sonido profesional y videoproyección en sala de clases en Chile',
						tag: 'Mizo Ingeniería · Chile',
						title: 'Sonido, video e instalaciones eléctricas en Chile',
						desc: 'Diseñamos, instalamos y damos soporte a iglesias, colegios, auditorios y empresas. Audio, videoproyección y electricidad en un solo proyecto.',
						detail: 'Audio PA, proyección, instalaciones eléctricas y control integrado.',
					},
					{
						image: IMG.heroChurch,
						alt: 'Sistema de sonido profesional instalado en iglesia',
						tag: 'Iglesias y templos',
						title: 'Instalación de audio para iglesias y cultos en vivo',
						desc: 'Cobertura uniforme en naves amplias: voces inteligibles, música equilibrada y operación sencilla para el equipo técnico.',
						detail: 'Parlantes de columna, mezcladores digitales, monitoreo y transmisión.',
					},
					{
						image: IMG.heroAuditorium,
						alt: 'Auditorio con proyección láser y sistema de sonido profesional',
						tag: 'Auditorios',
						title: 'Auditorios con proyección láser y audio distribuido',
						desc: 'Acústica del recinto, proyección de gran formato y microfonía para presentaciones, conciertos y conferencias.',
						detail: 'Proyección 4K/láser, line array, consola digital y cabina técnica.',
					},
					{
						image: IMG.heroRetail,
						alt: 'Sistema de audio y pantallas para restaurante o local comercial',
						tag: 'Empresas y retail',
						title: 'Audio y video para salas de reuniones y locales comerciales',
						desc: 'Videoconferencia, pantallas, música por zonas y control centralizado para operar el espacio día a día.',
						detail: 'Audio multizona, señalización digital y soporte remoto.',
					},
				],
				intro: {
					eyebrow: 'Ingeniería audiovisual en Chile',
					title: 'Sonido, video, electricidad e instalación con respaldo técnico real',
					paragraph1:
						'Mizo Ingeniería diseña e instala sistemas de sonido profesional, videoproyección, instalaciones eléctricas y control para espacios que necesitan calidad y operación simple. No solo vendemos equipos: dimensionamos cada proyecto, instalamos con estándares profesionales y acompañamos con soporte postventa.',
					paragraph2:
						'Si buscas instalación de audio para iglesia, auditorio escolar, sala de reuniones o local comercial en Santiago, Frutillar o regiones, cotiza con nosotros sin compromiso.',
					image: IMG.heroServices,
					cta1: { text: 'Ver todos los servicios', href: '/servicios' },
					cta2: { text: 'Catálogo de productos', href: '/productos' },
				},
				sectors: {
					title: 'Soluciones por tipo de espacio',
					subtitle: 'Cada proyecto es distinto. Estos son los sectores donde más experiencia tenemos en Chile.',
					image: IMG.heroClassroom,
					items: [
						{
							title: 'Iglesias y cultos',
							desc: 'Sonido claro para voz e instrumentos, monitoreo, transmisión en vivo y proyección para congregaciones de todos los tamaños.',
							icon: '⛪',
							image: IMG.heroChurch,
						},
						{
							title: 'Colegios y universidades',
							desc: 'Auditorios, salas de clases y espacios multipropósito con audio, video y micrófonos inalámbricos.',
							icon: '🎓',
							image: IMG.heroClassroom,
						},
						{
							title: 'Empresas y salas',
							desc: 'Videoconferencia, presentaciones y control de salas de reuniones con equipos confiables y fáciles de usar.',
							icon: '🏢',
							image: IMG.meeting,
						},
						{
							title: 'Retail y eventos',
							desc: 'Ambientación sonora, pantallas y sistemas portátiles o fijos para tiendas, ferias y eventos corporativos.',
							icon: '🎪',
							image: IMG.heroRetail,
						},
					],
				},
				services: {
					title: 'Nuestros servicios',
					subtitle:
						'Desde el diseño acústico y la electricidad de la sala hasta la puesta en marcha y el mantenimiento de tu sistema.',
					image: IMG.audio,
					items: [
						{
							title: 'Sonido profesional',
							desc: 'Diseño, instalación y calibración de sistemas PA, monitoreo y refuerzo sonoro.',
							href: '/servicios#sonido',
							image: IMG.audio,
						},
						{
							title: 'Videoproyección',
							desc: 'Proyectores láser, pantallas, videowalls y señalización para salas y auditorios.',
							href: '/servicios#video',
							image: IMG.video,
						},
						{
							title: 'Instalaciones eléctricas',
							desc: 'Tableros, circuitos dedicados, iluminación y alimentación segura para salas técnicas.',
							href: '/servicios#electricas',
							image: IMG.electrical,
						},
						{
							title: 'Streaming en vivo',
							desc: 'Transmisión para cultos, eventos y conferencias con audio y video de calidad.',
							href: '/servicios#streaming',
							image: IMG.heroChurch,
						},
						{
							title: 'Control y automatización',
							desc: 'Paneles táctiles y escenas para operar sonido, luces y video con un solo toque.',
							href: '/servicios#control',
							image: IMG.heroProducts,
						},
					],
				},
				process: {
					eyebrow: 'Cómo trabajamos',
					title: 'De la visita técnica a la entrega',
					description:
						'Sin pasos innecesarios. Un ingeniero revisa cada propuesta antes de comprometer equipos y fechas.',
					cta: { text: 'Agendar visita técnica', href: '/contacto' },
					image: IMG.engineering,
					steps: [
						'Visita al recinto: medidas, uso del espacio y restricciones técnicas.',
						'Propuesta técnica con equipos, planos de instalación y presupuesto.',
						'Ejecución en terreno con cableado oculto y montaje certificado.',
						'Pruebas, calibración, capacitación y entrega documentada.',
					],
				},
				whyChoose: {
					title: '¿Por qué elegir Mizo Ingeniería?',
					image: IMG.heroAuditorium,
					items: [
						{
							title: 'Experiencia en terreno',
							desc: 'Proyectos reales en iglesias, colegios y empresas. Conocemos los problemas típicos y cómo resolverlos.',
							image: IMG.heroChurch,
						},
						{
							title: 'Marcas de confianza',
							desc: 'Trabajamos con fabricantes reconocidos en audio y video profesional, con garantía y repuestos.',
							image: IMG.audio,
						},
						{
							title: 'Soporte continuo',
							desc: 'No desaparecemos después de la instalación. Capacitación, mantenimiento y asistencia técnica.',
							image: IMG.engineering,
						},
						{
							title: 'Proyecto a medida',
							desc: 'Cada espacio tiene acústica y uso distintos. Diseñamos la solución correcta, no la más cara.',
							image: IMG.meeting,
						},
						{
							title: 'Cotización clara',
							desc: 'Presupuesto detallado antes de comenzar. Sin sorpresas en equipos ni mano de obra.',
							image: IMG.heroServices,
						},
						{
							title: 'Proyecto integral',
							desc: 'Sonido, video e instalaciones eléctricas coordinadas en un solo equipo. Sin contratistas sueltos ni incompatibilidades.',
							image: IMG.electrical,
						},
						{
							title: 'Chile, de norte a sur',
							desc: 'Bases en Frutillar y Santiago con capacidad de coordinar instalaciones en regiones.',
							image: IMG.heroClassroom,
						},
					],
				},
				faq: {
					title: 'Preguntas frecuentes',
					image: IMG.heroServices,
					items: [
						{
							q: '¿Qué servicios ofrece Mizo Ingeniería?',
							a: 'Diseño e instalación de sistemas de sonido profesional, videoproyección, instalaciones eléctricas, streaming en vivo, control y automatización, y soporte técnico. Atendemos iglesias, colegios, auditorios, empresas y locales comerciales en Chile.',
						},
						{
							q: '¿En qué zonas trabajan?',
							a: 'Nuestra base operativa está en Frutillar y Santiago. Realizamos proyectos en la Región Metropolitana, Los Lagos y coordinamos instalaciones en otras regiones según el alcance del proyecto.',
						},
						{
							q: '¿Puedo cotizar sin compromiso?',
							a: 'Sí. Puedes solicitar una cotización por el formulario de contacto, WhatsApp o teléfono. Te respondemos con una propuesta adaptada a tu espacio y presupuesto.',
						},
						{
							q: '¿Venden equipos además de instalar?',
							a: 'Sí. Tenemos catálogo de productos de audio, video y accesorios. Puedes consultar disponibilidad y precios en nuestra sección de productos.',
						},
					],
				},
				contactCta: {
					eyebrow: 'Contacto',
					title: '¿Listo para mejorar el sonido o video de tu espacio?',
					description:
						'Cuéntanos tu proyecto por teléfono, WhatsApp o formulario. Te respondemos con una propuesta a tu medida.',
					image: IMG.heroContact,
				},
				projectsSection: {
					eyebrow: 'Proyectos',
					title: 'Algunos trabajos que hemos desarrollado',
					description:
						'Cada instalación es distinta. Estos ejemplos muestran el tipo de recintos y soluciones que entregamos habitualmente.',
					image: IMG.heroAuditorium,
				},
				brands: {
					title: 'Trabajamos con las mejores marcas',
					subtitle: 'Referentes globales en audio, video, proyección e integración tecnológica.',
					image: IMG.heroProducts,
					items: [
						{ name: 'Sony', slug: 'sony', image: '/images/marcas/sony.svg' },
						{ name: 'Sennheiser', slug: 'sennheiser', image: '/images/marcas/sennheiser.svg' },
						{ name: 'JBL', slug: 'jbl', image: '/images/marcas/jbl.svg' },
						{ name: 'Bose', slug: 'bose', image: '/images/marcas/bose.svg' },
						{ name: 'Shure', slug: 'shure', image: '/images/marcas/shure.svg' },
						{ name: 'Epson', slug: 'epson', image: '/images/marcas/epson.svg' },
						{ name: 'BenQ', slug: 'benq', image: '/images/marcas/benq.svg' },
					],
				},
				showcase: {
					eyebrow: 'Equipamiento de referencia',
					title: 'Tecnología que integramos en proyectos reales',
					description: 'Equipos que usamos habitualmente en proyectos de sonido y videoproyección.',
					image: IMG.heroEquipment,
				},
			},
			servicios: {
				meta: {
					title: 'Servicios de Sonido, Videoproyección e Instalaciones Eléctricas | Mizo',
					description:
						'Instalación profesional de sistemas de sonido, videoproyección, instalaciones eléctricas, streaming y control AV para iglesias, colegios, auditorios, empresas y retail en Santiago, Frutillar y regiones.',
					keywords:
						'instalación sonido profesional chile, videoproyección iglesias, instalaciones eléctricas santiago, audio auditorios, salas audiovisuales, electricista salas técnicas',
					image: IMG.heroServices,
				},
				hero: {
					eyebrow: 'Servicios',
					title: 'Audio, videoproyección e instalaciones eléctricas que funcionan en el día a día.',
					subtitle:
						'Diseño, suministro, montaje, calibración y soporte en Chile. Trabajamos el proyecto completo — sonido, imagen y electricidad — para iglesias, colegios, auditorios, empresas y locales comerciales.',
					image: IMG.heroServices,
					chips: ['Audio profesional', 'Videoproyección', 'Instalaciones eléctricas', 'Instalación en terreno'],
				},
				process: [
					'Visita al recinto y evaluación técnica del espacio',
					'Propuesta con equipos, planos y presupuesto',
					'Instalación coordinada con obra o operación',
					'Pruebas, calibración y entrega documentada',
				],
				services: [
					{
						id: 'sonido',
						title: 'Sistemas de sonido profesional',
						description:
							'Calculamos cobertura, potencia e inteligibilidad según el uso del recinto. Instalamos, calibramos y dejamos el sistema listo para operar.',
						kicker: 'Audio',
						details: [
							'Diseño acústico y distribución por zonas',
							'Parlantes activos, pasivos, columnas y subwoofers',
							'Consolas, DSP, amplificación y microfonía',
							'Medición, ecualización y control de feedback',
							'Capacitación del operador y documentación',
						],
						image: IMG.audio,
					},
					{
						id: 'video',
						title: 'Videoproyección y salas audiovisuales',
						description:
							'Proyectores, pantallas, videoconferencia y control integrado. El usuario final enciende, selecciona la fuente y presenta sin complicaciones.',
						kicker: 'Imagen',
						details: [
							'Proyectores láser, LED y pantallas interactivas',
							'Soportes motorizados y ajuste geométrico',
							'Matrices HDMI, extensores y switchers AV',
							'Videoconferencia para salas híbridas',
							'Escenas de control y automatización básica',
						],
						image: IMG.video,
					},
					{
						id: 'electricas',
						title: 'Instalaciones eléctricas',
						description:
							'Alimentación segura y normada para salas audiovisuales, tableros, circuitos dedicados e iluminación. La base eléctrica correcta evita ruidos, cortes y riesgos en tu instalación.',
						kicker: 'Electricidad',
						details: [
							'Tableros, protecciones y circuitos dedicados para equipos AV',
							'Tendido, canalización y puntos de enchufe según proyecto',
							'Iluminación escénica, perimetral y de emergencia',
							'UPS y estabilización para equipos críticos',
							'Pruebas, certificación y documentación de la instalación',
						],
						image: IMG.electrical,
					},
					{
						id: 'colegios',
						title: 'Colegios y salas de clases',
						description:
							'Aulas con proyección nítida, audio claro y equipos que resisten el uso diario. Coordinamos la instalación para no interrumpir las clases más de lo necesario.',
						kicker: 'Educación',
						details: [
							'Proyectores y pantallas por aula o auditorio',
							'Audio para profesor y reproducción multimedia',
							'Micrófonos inalámbricos y conexión a notebook',
							'Cableado oculto y protección de equipos',
							'Soporte post-instalación para el establecimiento',
						],
						image: IMG.heroClassroom,
					},
					{
						id: 'streaming',
						title: 'Iglesias y templos',
						description:
							'Sonido uniforme en naves amplias, voces inteligibles y música con presencia. Pensado para cultos, eventos y operación por voluntarios o técnicos.',
						kicker: 'Templos',
						details: [
							'Columnas, line array o sistemas distribuidos',
							'Mezcladores digitales con escenas guardadas',
							'Monitoreo de escenario y grabación básica',
							'Integración con transmisión en vivo',
							'Mantención y respuesta ante fallas',
						],
						image: IMG.heroChurch,
					},
					{
						id: 'auditorios',
						title: 'Auditorios y salas multiuso',
						description:
							'Proyección de gran formato, audio de alta inteligibilidad y cabina técnica ordenada. Para conferencias, obras, conciertos y actos institucionales.',
						kicker: 'Auditorios',
						details: [
							'Proyección láser o LED de alto brillo',
							'Sistemas de sonido para platea y escenario',
							'Microfonía de mano, diadema y ambiental',
							'Control de iluminación básico (si aplica)',
							'Documentación técnica y planos as-built',
						],
						image: IMG.heroAuditorium,
					},
					{
						id: 'retail',
						title: 'Restaurantes, bares y retail',
						description:
							'Música por zonas, pantallas de menú o branding y control sencillo para el encargado del local. Instalación fuera de horario cuando es posible.',
						kicker: 'Comercial',
						details: [
							'Audio multizona con control independiente',
							'Pantallas comerciales y señalética digital',
							'Integración con streaming o reproductor',
							'Cableado discreto en salones y terrazas',
							'Soporte remoto para ajustes menores',
						],
						image: IMG.heroRetail,
					},
					{
						id: 'control',
						title: 'Canalización, cableado e infraestructura AV',
						description:
							'La instalación se sostiene en rutas limpias, fijaciones confiables y terminaciones que se pueden mantener. Sin cables colgando ni cajas improvisadas.',
						kicker: 'Infraestructura',
						details: [
							'Canalización técnica y bandejas',
							'Cableado estructurado y patching ordenado',
							'Soportería para proyectores, pantallas y parlantes',
							'Anclajes, pasadas y terminaciones prolijas',
							'Etiquetado y registro de conexiones',
						],
						image: IMG.heroProducts,
					},
				],
			},
			nosotros: {
				meta: {
					title: 'Nosotros | Mizo Ingeniería - Instalación Audiovisual en Chile',
					description:
						'Mizo Ingeniería: más de 10 años instalando sonido profesional, videoproyección e instalaciones eléctricas en Chile. Equipo técnico en Frutillar y Santiago para iglesias, colegios, auditorios y empresas.',
					keywords:
						'Mizo ingeniería, empresa audiovisual Chile, instaladores sonido profesional, instalaciones eléctricas, integradores AV, ingeniería acústica Chile',
					image: IMG.heroAbout,
				},
				hero: {
					eyebrow: 'Nosotros',
					title: 'La ingeniería detrás de instalaciones audiovisuales confiables en Chile.',
					subtitle:
						'Mizo combina selección de equipamiento, diseño técnico, instalaciones eléctricas, ejecución en terreno y soporte posterior. Más de una década instalando sonido y videoproyección para instituciones y empresas.',
					image: IMG.heroAbout,
					chips: ['Criterio técnico', 'AV + infraestructura', 'Soporte local'],
				},
				mission: {
					eyebrow: 'Integradora audiovisual',
					title: 'Nuestra misión: tecnología premium, instalada con criterio y responsabilidad.',
					p1: 'Ofrecemos a empresas, instituciones y hogares soluciones integrales que abarcan visita técnica en terreno, diseño de sistema, suministro de marcas líderes, montaje profesional y calibración final.',
					p2: 'Con bases en Frutillar y Santiago, hemos instalado más de 120 proyectos en iglesias, colegios, auditorios, restaurantes e industria. Buscamos que cada espacio tenga una experiencia audiovisual clara, segura y fácil de operar.',
					image: IMG.heroAboutSide,
				},
				values: [
					{
						kicker: 'Criterio técnico',
						title: 'Ingeniería aplicada',
						description:
							'Cada decisión de marca, ubicación, potencia, cableado y control se toma desde el comportamiento real del recinto.',
						image: IMG.engineering,
					},
					{
						kicker: 'AV + infraestructura',
						title: 'Integración completa',
						description:
							'Unimos audio, video, electricidad, infraestructura, control y soporte para evitar compras aisladas que no conversan entre sí.',
						image: IMG.electrical,
					},
					{
						kicker: 'Soporte local',
						title: 'Entrega responsable',
						description:
							'Instalamos, calibramos, explicamos y dejamos documentación clara para que el sistema pueda operarse con confianza.',
						image: IMG.heroAbout,
					},
				],
				capabilities: [
					'Diseño acústico y cobertura sonora',
					'Proyección láser y salas audiovisuales',
					'Instalaciones eléctricas y tableros para salas técnicas',
					'Canalización y cableado estructural oculto',
					'Soportería pesada y montaje técnico',
					'Control, escenas y capacitación de usuarios',
					'Calibración final y soporte posterior',
				],
				sideImage: IMG.heroAboutSide,
			},
			contacto: {
				meta: {
					title: 'Contacto | Mizo - Cotiza instalación de audio y video en Chile',
					description:
						'Contáctanos para cotizaciones de instalación de sonido, audiovisual, electricidad o venta de equipos profesionales en Chile.',
					keywords:
						'contacto Mizo, cotizar instalación sonido, cotización audiovisual Chile, ventas equipos profesionales',
					image: IMG.heroContact,
				},
				hero: {
					eyebrow: 'Cotizador guiado de proyectos',
					title: 'Cuéntanos tu espacio y un ingeniero de Mizo arma la solución.',
					subtitle:
						'Recibimos tu requerimiento, revisamos dimensiones, uso del espacio y equipamiento esperado para coordinar una visita técnica o propuesta inicial.',
					image: IMG.heroContact,
					badges: ['Ingeniería Certificada', 'Garantía de Satisfacción', 'Soporte Local'],
				},
				form: {
					title: 'Cotiza tu proyecto',
					subtitle: 'Tus datos viajan directo a ventas@mizo.cl.',
					labels: {
						nombre: 'Nombre',
						telefono: 'Teléfono',
						empresa: 'Empresa / Institución',
						correo: 'Correo',
						espacio: '¿Qué espacio necesitas equipar?',
						metros: 'Tamaño aprox. en m²',
						detalles: 'Cuéntanos los detalles de tu requerimiento',
					},
					placeholders: {
						metros: 'Ej: 45',
						detalles:
							'Ej: queremos audio para sala de clases, proyector, micrófono y conexión HDMI...',
					},
					spaces: ['Sala de Clases', 'Auditorio', 'Sala de Reuniones', 'Cine en Casa', 'Otro'],
					defaultSpace: 'Sala de Reuniones',
					submitButton: 'Enviar solicitud técnica',
					privacyNote: 'Tus datos se usan únicamente para responder esta solicitud.',
				},
				directContact: {
					title: 'Contacto directo',
					email: 'ventas@mizo.cl',
					phone: '+56 9 9439 0870',
					hours: 'Atención de lunes a viernes, 9:00 a 18:00 hrs.',
				},
			},
			productos: {
				meta: {
					title: 'Productos audiovisuales profesionales | Mizo Chile',
					description:
						'Catálogo de equipamiento audiovisual profesional integrado por Mizo en Chile. Consulta por parlantes, proyectores, consolas, microfonía y más.',
					keywords:
						'productos audiovisuales Chile, equipos sonido profesional, proyectores, consolas audio, microfonía, catálogo Mizo',
					image: IMG.heroProducts,
				},
				hero: {
					eyebrow: 'Productos',
					title: 'Equipamiento profesional para tus proyectos de audio y video',
					subtitle:
						'Explora el catálogo completo de productos que integramos en instalaciones reales. Sin precios ni stock en línea: consulta directamente con nuestro equipo de ventas.',
					image: IMG.heroProducts,
					chips: ['Audio profesional', 'Proyección AV', 'CCTV y video'],
				},
			},
			equipamiento: {
				meta: {
					title: 'Equipamiento de referencia | Mizo',
					description:
						'Conoce el equipamiento profesional que integramos en proyectos de audio, video e instalaciones audiovisuales en Chile.',
					keywords:
						'equipamiento audiovisual, referencia técnica, marcas audio video, catálogo Mizo',
					image: IMG.heroEquipment,
				},
				hero: {
					eyebrow: 'Equipamiento de referencia',
					title: 'Tecnología profesional que integramos en cada proyecto',
					subtitle:
						'Referencia técnica del equipamiento profesional que integramos en proyectos de audio, video e instalaciones AV. Sin precios ni stock: el foco es mostrar marcas, modelos y aplicaciones reales.',
					image: IMG.heroEquipment,
					chips: ['Audio profesional', 'Proyección AV', 'Video y CCTV'],
				},
			},
			legal: {
				terminos: {
					title: 'Términos y Condiciones',
					content: [
						{
							heading: '1. Aceptación de los Términos',
							body: 'Al acceder y utilizar los servicios y el sitio web de Mizo ("Servicios"), aceptas estar sujeto a estos Términos y Condiciones de Uso ("Términos"). Si no estás de acuerdo con alguna parte, no debes utilizar nuestros Servicios.',
						},
						{
							heading: '2. Servicios y Productos',
							body: 'Mizo ofrece servicios de instalación de sistemas de sonido, soluciones audiovisuales e instalaciones eléctricas, así como la venta de equipos. La contratación y los detalles de cada proyecto se rigen por contratos o cotizaciones individuales que complementan estos Términos.',
							list: [
								'Instalaciones: garantía sujeta a los términos del contrato o cotización específica.',
								'Productos: los precios publicados son referenciales y pueden variar según disponibilidad y tipo de cambio.',
							],
						},
						{
							heading: '3. Uso del Sitio Web',
							body: 'El contenido del sitio web (textos, gráficos, logotipos, etc.) es propiedad de Mizo o se utiliza bajo licencia. El uso no autorizado o la reproducción del material están estrictamente prohibidos. Te comprometes a no utilizar el sitio para fines ilegales o no autorizados.',
						},
						{
							heading: '4. Limitación de Responsabilidad',
							body: 'Mizo no será responsable por daños indirectos, incidentales, especiales o consecuentes que resulten del uso o la imposibilidad de usar nuestros Servicios o este sitio web.',
						},
						{
							heading: '5. Modificaciones a los Términos',
							body: 'Nos reservamos el derecho de actualizar o cambiar estos Términos en cualquier momento. La versión más reciente estará siempre disponible en esta página.',
						},
						{
							heading: '6. Contacto y Ley Aplicable',
							body: 'Para cualquier consulta sobre estos Términos, contáctanos a través de nuestra página de contacto. Estos términos se rigen por las leyes de Chile.',
						},
					],
					image: IMG.heroServices,
					lastUpdated: 'Mayo 2026',
				},
				privacidad: {
					title: 'Política de Privacidad',
					content: [
						{
							heading: '1. Información que Recopilamos',
							body: 'Recopilamos la información que nos proporcionas directamente al utilizar nuestro sitio web o servicios, incluyendo formularios de contacto y correo electrónico. Puede incluir tu nombre, correo, teléfono y los detalles de tu proyecto.',
						},
						{
							heading: '2. Uso de tu Información',
							body: 'Utilizamos la información para proporcionar, operar y mantener nuestros servicios; responder a tus consultas; comunicarnos contigo y mejorar el sitio web.',
							list: [
								'Responder a tus consultas y solicitudes de servicio.',
								'Proporcionar y mantener nuestro sitio web y servicios.',
								'Cumplir con obligaciones legales.',
							],
						},
						{
							heading: '3. Compartir Información',
							body: 'No vendemos, comercializamos ni transferimos a terceros tu información de identificación personal, salvo a terceros de confianza que nos asisten en la operación del sitio o la prestación de servicios, siempre que se comprometan a mantener la confidencialidad.',
						},
						{
							heading: '4. Cookies',
							body: 'Podemos usar cookies y tecnologías similares para registrar la actividad en nuestro sitio. Puedes configurar tu navegador para rechazarlas, aunque esto podría afectar la funcionalidad del sitio.',
						},
						{
							heading: '5. Seguridad de los Datos',
							body: 'La seguridad de tus datos es importante para nosotros. Nos esforzamos por utilizar medios comercialmente aceptables para proteger tu información, aunque ningún método de transmisión por Internet es 100% seguro.',
						},
						{
							heading: '6. Cambios a esta Política',
							body: 'Nos reservamos el derecho de actualizar esta Política en cualquier momento. La versión más reciente estará siempre disponible en esta página.',
						},
						{
							heading: '7. Contacto',
							body: 'Si tienes preguntas sobre esta Política, contáctanos a través de nuestra página de contacto o escribiendo a ventas@mizo.cl.',
						},
					],
					image: IMG.heroContact,
					lastUpdated: 'Mayo 2026',
				},
			},
		},
	};
}

const content = buildSiteContent();
const json = JSON.stringify(content, null, 2);

fs.mkdirSync(path.dirname(OUT_PATH), { recursive: true });
fs.writeFileSync(OUT_PATH, json, 'utf8');

const sizeBytes = Buffer.byteLength(json, 'utf8');
const sizeKb = (sizeBytes / 1024).toFixed(1);

console.log(`Site content generado -> ${OUT_PATH}`);
console.log(`Tamaño: ${sizeBytes} bytes (~${sizeKb} KB)`);
console.log(`Actualizado: ${content.updatedAt}`);
