export type LandingBlock = {
	title: string;
	text: string;
};

export type LandingFaq = {
	q: string;
	a: string;
};

export type RelatedSolution = {
	industry: string;
	solution: string;
};

export type LandingExtra = {
	audiences: readonly LandingBlock[];
	included: readonly string[];
	steps: readonly LandingBlock[];
	faqs: readonly LandingFaq[];
	related: readonly RelatedSolution[];
};

export const landingExtras: Record<string, LandingExtra> = {
	'parlantes-para-iglesias': {
		audiences: [
			{ title: 'Nave y bancos', text: 'La palabra del púlpito llega pareja hasta la última fila, sin gritar al micrófono ni saturar a quien está adelante.' },
			{ title: 'Coro y banda', text: 'Monitores y micrófonos para que el grupo se escuche entre sí y el templo reciba música clara, no un acople.' },
			{ title: 'Balcón, patio y anexos', text: 'Zonas aparte para el balcón, el patio de niños o la sala de reuniones, con el volumen que corresponde a cada una.' },
		],
		included: [
			'Visita al templo',
			'Parlantes y micrófonos',
			'Instalación y prueba en un culto',
			'Garantía de 12 meses',
		],
		steps: [
			{ title: 'Vemos el templo', text: 'Cuántas personas y si hay banda o coro.' },
			{ title: 'Instalamos', text: 'Parlantes, micrófonos y cables ordenados.' },
			{ title: 'Probamos el culto', text: 'Ajustamos la voz antes de irnos.' },
		],
		faqs: [
			{ q: '¿Sirve si ya hay parlantes viejos?', a: 'Sí. Dejamos lo que funciona y cambiamos lo que no se entiende.' },
			{ q: '¿Y si algo falla después?', a: 'Tienes 12 meses de garantía y un contacto directo.' },
		],
		related: [
			{ industry: 'auditorios', solution: 'sonorizacion' },
			{ industry: 'auditorios', solution: 'dsp' },
			{ industry: 'instituciones-educativas', solution: 'sonorizacion' },
		],
	},
	'instalacion-de-proyectores': {
		audiences: [
			{ title: 'Salas de clases', text: 'Imagen grande y legible con luz de sala, para que la clase no dependa de bajar todas las cortinas.' },
			{ title: 'Auditorios e iglesias', text: 'Proyección de culto, conferencia o evento, alineada con el audio del recinto y sin que el haz cruce al público.' },
			{ title: 'Salas de reunión', text: 'Montaje de techo, fuente HDMI o inalámbrica y una pantalla proporcionada a la distancia de quienes miran.' },
		],
		included: [
			'Cálculo de distancia y tamaño de imagen',
			'Proyector según la luz del lugar',
			'Soporte de techo o muro y canalización',
			'Pantalla o superficie de proyección',
			'Conexión de video y audio asociado',
			'Ajuste de geometría, foco y brillo',
			'Prueba con el computador de la sala',
			'Garantía de 12 meses en la integración',
		],
		steps: [
			{ title: 'Medimos la sala', text: 'Luz ambiente, distancia de visión y obstáculos. Con eso se elige luminosidad y tamaño, no el proyector más grande del catálogo.' },
			{ title: 'Definimos el montaje', text: 'Techo, muro o estructura. El haz no debe cruzar pasillos ni quedar tapado por luminarias.' },
			{ title: 'Instalamos equipo y pantalla', text: 'Fijación, cableado oculto y fuente de video lista para el uso diario.' },
			{ title: 'Dejamos la imagen calibrada', text: 'Encuadre, foco y brillo. Quien presenta prueba la sala antes del cierre.' },
		],
		faqs: [
			{ q: '¿Se ve con las luces encendidas?', a: 'Si el proyector se elige según la luz real de la sala, sí. Por eso medimos el recinto antes de proponer el equipo.' },
			{ q: '¿Incluyen la pantalla?', a: 'Sí, cuando el muro no sirve como superficie. Lo dejamos definido en la cotización: pantalla, tamaño y tipo.' },
			{ q: '¿Pueden conectar el computador que ya tenemos?', a: 'Sí. Dejamos HDMI o la vía inalámbrica que use la sala, y lo probamos con ese equipo.' },
			{ q: '¿Hacen mantención después?', a: 'La instalación queda con 12 meses de garantía. También se puede coordinar revisión de foco, lámpara o filtro según el modelo.' },
		],
		related: [
			{ industry: 'instituciones-educativas', solution: 'proyeccion' },
			{ industry: 'auditorios', solution: 'video' },
			{ industry: 'entretenimiento-residencial', solution: 'home-cinema' },
		],
	},
	'instalacion-de-musica-ambiental': {
		audiences: [
			{ title: 'Restaurantes', text: 'Comedor, terraza y barra con volúmenes distintos para que la música acompañe y no tape la conversación.' },
			{ title: 'Tiendas y retail', text: 'El acceso invita y el interior mantiene un nivel parejo, sin un parlante gritando en una esquina.' },
			{ title: 'Oficinas y halls', text: 'Audio discreto en recepción o zonas de espera, simple de encender para el equipo del lugar.' },
		],
		included: [
			'Una zona por ambiente',
			'Parlantes de techo o terraza',
			'Control simple para el personal',
			'Garantía de 12 meses',
		],
		steps: [
			{ title: 'Recorremos el local', text: 'Comedor, terraza y barra, cada uno aparte.' },
			{ title: 'Instalamos', text: 'Sin dejar cables a la vista.' },
			{ title: 'Dejamos el control', text: 'El personal sube o baja el volumen solo.' },
		],
		faqs: [
			{ q: '¿La terraza puede sonar distinto?', a: 'Sí. Cada ambiente tiene su volumen.' },
			{ q: '¿Hay que ser técnico para usarlo?', a: 'No. Queda un control simple.' },
		],
		related: [
			{ industry: 'restaurantes', solution: 'sonido-ambiental' },
			{ industry: 'gimnasios', solution: 'audio-zonas' },
			{ industry: 'entretenimiento-residencial', solution: 'quinchos' },
		],
	},
	'proyectores-interactivos': {
		audiences: [
			{ title: 'Salas de clases', text: 'La pizarra y la imagen quedan en la misma superficie. Quien enseña escribe sin tapar la proyección ni forzar la postura.' },
			{ title: 'Capacitación', text: 'Salas donde se anota sobre el contenido, con altura y calibración pensadas para varias personas de pie.' },
			{ title: 'Salas de directorio', text: 'Reuniones cortas con una superficie táctil clara, conectada al computador que ya usa la empresa.' },
		],
		included: [
			'Definición de altura de escritura',
			'Proyector interactivo o de corto alcance',
			'Superficie apta para el tacto',
			'Calibración del lápiz o del toque',
			'Conexión al computador de la sala',
			'Prueba de sombra y ángulo',
			'Inducción a docentes o anfitriones',
			'Garantía de 12 meses en la integración',
		],
		steps: [
			{ title: 'Vemos quién escribe', text: 'Niños, adultos o un presentador de pie cambian la altura y el tipo de proyector. Lo definimos en la sala, no por catálogo.' },
			{ title: 'Elegimos alcance y superficie', text: 'Corto alcance para reducir sombra, y una pared o pizarra que responda bien al tacto.' },
			{ title: 'Instalamos y calibramos', text: 'Montaje, software de la sala y calibración del punto de toque en toda la imagen.' },
			{ title: 'Enseñamos a usarlo', text: 'Una inducción breve con quien va a dar la clase o la reunión, sobre el computador real de esa sala.' },
		],
		faqs: [
			{ q: '¿Reemplaza la pizarra tradicional?', a: 'Puede quedar como única superficie de escritura, o convivir con la pizarra si la sala ya la usa. Lo vemos en la visita.' },
			{ q: '¿Funciona con el computador del colegio?', a: 'Lo conectamos y probamos con ese equipo. Si falta un adaptador o un puerto, queda anotado en la instalación.' },
			{ q: '¿La sombra del profesor tapa la imagen?', a: 'El montaje de corto alcance y la posición del proyector se eligen justamente para reducir esa sombra.' },
			{ q: '¿Se puede recalibrar después?', a: 'Sí. Dejamos el procedimiento explicado y, dentro de la garantía, apoyamos si la sala se desalinea.' },
		],
		related: [
			{ industry: 'instituciones-educativas', solution: 'proyeccion' },
			{ industry: 'instituciones-educativas', solution: 'intercomunicacion' },
			{ industry: 'auditorios', solution: 'video' },
		],
	},
	'instalacion-de-parlantes': {
		audiences: [
			{ title: 'Locales y oficinas', text: 'Parlantes de techo o muro para avisos y música, con cobertura pareja y un volumen controlable.' },
			{ title: 'Salones y auditorios', text: 'Arreglo según la distancia: palabra clara al fondo y sin exceso junto al escenario.' },
			{ title: 'Espacios mixtos', text: 'Un mismo proyecto puede tener zona de público, terraza y sala técnica, cada una con el parlante que le corresponde.' },
		],
		included: [
			'Visita al recinto',
			'Parlantes en el lugar correcto',
			'Prueba caminando la sala',
			'Garantía de 12 meses',
		],
		steps: [
			{ title: 'Vemos el espacio', text: 'Tamaño, altura y para qué se usa.' },
			{ title: 'Instalamos', text: 'Los parlantes donde realmente se escuchan.' },
			{ title: 'Probamos', text: 'Recorremos la sala y ajustamos el volumen.' },
		],
		faqs: [
			{ q: '¿Cuántos parlantes necesito?', a: 'Depende del lugar. Te lo decimos después de verlo.' },
			{ q: '¿Sirve para música y para avisos?', a: 'Sí. Los dos, en el mismo sistema.' },
		],
		related: [
			{ industry: 'auditorios', solution: 'sonorizacion' },
			{ industry: 'restaurantes', solution: 'sonido-ambiental' },
			{ industry: 'gimnasios', solution: 'audio-zonas' },
			{ industry: 'instituciones-educativas', solution: 'sonorizacion' },
		],
	},
	'instalacion-de-video-wall': {
		audiences: [
			{ title: 'Lobbies y empresas', text: 'Un muro LED que se lee desde la recepción, con brillo de día y contenido fácil de cambiar.' },
			{ title: 'Retail', text: 'Imagen de vidrio o fachada interior proporcionada a la distancia de quien pasa, sin pixeles marcados de cerca.' },
			{ title: 'Auditorios e iglesias', text: 'Apoyo visual de gran formato, alineado al ancho del escenario y al procesamiento de video del evento.' },
		],
		included: [
			'Distancia de visión y elección de pitch',
			'Estructura y alimentación',
			'Módulos LED y procesamiento',
			'Calibración de brillo y color',
			'Fuente de contenido operable',
			'Prueba con el material del cliente',
			'Inducción de encendido y cambio de contenido',
			'Garantía de 12 meses en la integración',
		],
		steps: [
			{ title: 'Medimos quién mira', text: 'A dos metros o a veinte cambia el pitch y el tamaño. Definimos el muro para esa distancia, no para una foto de catálogo.' },
			{ title: 'Proyectamos estructura', text: 'Peso, alimentación y acceso para mantenimiento. El muro no puede quedar pegado sin poder intervenir un módulo.' },
			{ title: 'Montamos y procesamos', text: 'Paneles, controlador y la fuente de video que el lugar va a usar cada día.' },
			{ title: 'Calibramos la imagen', text: 'Juntas, color y brillo de día. Probamos con contenido real antes de entregar.' },
		],
		faqs: [
			{ q: '¿Se nota la separación entre paneles?', a: 'Con el pitch correcto para la distancia de visión y una calibración de juntas, el muro se lee como una sola imagen.' },
			{ q: '¿Funciona con luz de día?', a: 'Sí, si el brillo se elige para ese lugar. Un lobby con vidrio no usa el mismo panel que una sala oscura.' },
			{ q: '¿Quién cambia el contenido?', a: 'Dejamos una fuente simple y una inducción. No hace falta un técnico para publicar el aviso del día.' },
			{ q: '¿Se puede ampliar después?', a: 'Si la estructura y el procesamiento se dejan previstos, el muro puede crecer. Lo conversamos al definir el primer tamaño.' },
		],
		related: [
			{ industry: 'auditorios', solution: 'video' },
			{ industry: 'restaurantes', solution: 'pantallas-led' },
			{ industry: 'gimnasios', solution: 'pantallas' },
		],
	},
};
