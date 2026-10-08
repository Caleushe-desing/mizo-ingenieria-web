export type LandingBlock = {
	title: string;
	text: string;
};

export type LandingFaq = {
	q: string;
	a: string;
};

export type LandingExtra = {
	audiences: readonly LandingBlock[];
	included: readonly string[];
	steps: readonly LandingBlock[];
	faqs: readonly LandingFaq[];
};

export const landingExtras: Record<string, LandingExtra> = {
	'parlantes-para-iglesias': {
		audiences: [
			{ title: 'Nave y bancos', text: 'La palabra del púlpito llega pareja hasta la última fila, sin gritar al micrófono ni saturar a quien está adelante.' },
			{ title: 'Coro y banda', text: 'Monitores y micrófonos para que el grupo se escuche entre sí y el templo reciba música clara, no un acople.' },
			{ title: 'Balcón, patio y anexos', text: 'Zonas aparte para el balcón, el patio de niños o la sala de reuniones, con el volumen que corresponde a cada una.' },
		],
		included: [
			'Visita o revisión del plano del templo',
			'Diseño de cobertura y cantidad de parlantes',
			'Micrófonos de púlpito, coro o inalámbricos',
			'Amplificación y procesamiento',
			'Cableado ordenado y fijaciones',
			'Calibración en un ensayo o culto de prueba',
			'Inducción a quien opera la consola',
			'Garantía de 12 meses en la integración',
		],
		steps: [
			{ title: 'Entendemos el culto', text: 'Preguntamos cómo predican, si hay banda, coro, balcón y cuánta gente se reúne. El sistema parte de ese uso, no de un paquete cerrado.' },
			{ title: 'Definimos cobertura', text: 'Ubicamos parlantes para que la voz se entienda en nave y anexos, y dejamos monitores donde el grupo los necesita.' },
			{ title: 'Instalamos y cableamos', text: 'Montaje prolijo, amplificadores y procesador. El templo queda operable sin una maraña de cables a la vista.' },
			{ title: 'Calibramos con la iglesia', text: 'Ajustamos voz, música y volumen de primera fila. Quien dirige el culto prueba el sistema antes de que nos vayamos.' },
		],
		faqs: [
			{ q: '¿Sirve si el templo ya tiene parlantes viejos?', a: 'Sí. Revisamos lo que se puede reutilizar y reemplazamos solo lo que no cubre o genera acople. La propuesta indica qué queda y qué se cambia.' },
			{ q: '¿La banda y la predicación usan el mismo sistema?', a: 'Sí. Se separan canales y monitores para que la palabra no compita con la música y el grupo se escuche en el escenario.' },
			{ q: '¿Trabajan fuera de Santiago?', a: 'Sí. Mizo instala en Frutillar, Santiago y el resto de Chile. La visita y la puesta en marcha se coordinan según el recinto.' },
			{ q: '¿Quién queda a cargo si algo falla después?', a: 'La integración tiene 12 meses de garantía y un canal de postventa para consultas de operación o ajustes.' },
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
	},
	'instalacion-de-musica-ambiental': {
		audiences: [
			{ title: 'Restaurantes', text: 'Comedor, terraza y barra con volúmenes distintos para que la música acompañe y no tape la conversación.' },
			{ title: 'Tiendas y retail', text: 'El acceso invita y el interior mantiene un nivel parejo, sin un parlante gritando en una esquina.' },
			{ title: 'Oficinas y halls', text: 'Audio discreto en recepción o zonas de espera, simple de encender para el equipo del lugar.' },
		],
		included: [
			'Plano de zonas del local',
			'Parlantes de techo, muro o exterior cubierto',
			'Amplificación independiente por zona',
			'Fuente de música operable por el personal',
			'Control de volumen por ambiente',
			'Cableado en cielo o muro',
			'Ajuste de nivel para conversar',
			'Garantía de 12 meses en la integración',
		],
		steps: [
			{ title: 'Recorremos el local', text: 'Anotamos comedor, terraza, barra, probadores o hall. Cada zona tiene un uso y un volumen.' },
			{ title: 'Diseñamos el audio', text: 'Cantidad de parlantes, tipo y amplificador. El objetivo es ambiente, no un equipo de concierto.' },
			{ title: 'Instalamos sin romper la operación', text: 'Montaje ordenado, pensado para que el local siga atendiendo. El cableado no queda a la vista.' },
			{ title: 'Dejamos el control simple', text: 'El personal sube o baja cada zona sin un manual. Probamos el nivel con el local en uso real.' },
		],
		faqs: [
			{ q: '¿La terraza puede sonar distinto al comedor?', a: 'Sí. Esa es la idea de las zonas: cada ambiente tiene su volumen y, si hace falta, su programa.' },
			{ q: '¿El personal necesita un técnico para usarlo?', a: 'No. Dejamos un control claro. La parte técnica queda en el rack o en el cielo, no en la operación diaria.' },
			{ q: '¿Sirve para un local que ya está funcionando?', a: 'Sí. Coordinamos el montaje para interferir lo menos posible con el servicio.' },
			{ q: '¿Qué música pueden poner?', a: 'Integramos la fuente que el local ya usa o una simple de operar. La licencia de la música la define el local; nosotros dejamos el sistema sonando bien.' },
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
	},
	'instalacion-de-parlantes': {
		audiences: [
			{ title: 'Locales y oficinas', text: 'Parlantes de techo o muro para avisos y música, con cobertura pareja y un volumen controlable.' },
			{ title: 'Salones y auditorios', text: 'Arreglo según la distancia: palabra clara al fondo y sin exceso junto al escenario.' },
			{ title: 'Espacios mixtos', text: 'Un mismo proyecto puede tener zona de público, terraza y sala técnica, cada una con el parlante que le corresponde.' },
		],
		included: [
			'Relevamiento del recinto',
			'Diseño de cantidad y ubicación',
			'Parlantes de techo, muro o arreglo',
			'Amplificadores y procesamiento',
			'Cableado y canalización',
			'Ecualización para quitar resonancias',
			'Prueba de cobertura caminando la sala',
			'Garantía de 12 meses en la integración',
		],
		steps: [
			{ title: 'Partimos del plano', text: 'Alto de cielo, distancia y uso: aviso, música, palabra o espectáculo. Eso define el parlante, no al revés.' },
			{ title: 'Ubicamos cada punto', text: 'Menos equipos bien puestos rinden más que muchos mal distribuidos. El diseño muestra dónde va cada uno.' },
			{ title: 'Instalamos el sistema', text: 'Montaje, rack y cableado identificados. La sala técnica queda ordenada para el mantenimiento.' },
			{ title: 'Ecualizamos en el lugar', text: 'Caminamos la sala, corregimos picos y dejamos un nivel de referencia para quien opera.' },
		],
		faqs: [
			{ q: '¿Cuántos parlantes necesita mi local?', a: 'Depende del área y del cielo. Después del relevamiento entregamos cantidad y ubicación, no un número genérico.' },
			{ q: '¿Pueden quedar ocultos en el cielo?', a: 'Sí, cuando el cielo lo permite. Si no, proponemos muro o un formato que se integre al diseño del lugar.' },
			{ q: '¿El cableado queda a la vista?', a: 'No si hay camino por cielo, muro o canaleta. Lo dejamos previsto en la instalación.' },
			{ q: '¿Sirve para música y para avisos?', a: 'Sí. Se puede separar el uso por zona o por canal, según cómo opere el recinto.' },
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
	},
};
