export const TECH_STANDARDS = [
	{
		id: 'audio-digital',
		kicker: 'AoIP / Dante',
		title: 'Redes de audio digital',
		lead: 'Transmisión multicanal sin pérdida sobre red estándar.',
		detail:
			'El audio viaja por la infraestructura de red del recinto, con canales independientes y latencia baja. Sirve cuando hay muchas fuentes o el montaje cambia de un evento a otro.',
	},
	{
		id: 'dsp',
		kicker: 'DSP',
		title: 'Procesamiento de señal',
		lead: 'Matrices de audio avanzado y control de ecualización.',
		detail:
			'La matriz enruta, ecualiza y limita cada zona. El sistema queda calibrado al uso real de la sala, con el nivel y la ruta que esa operación necesita.',
	},
	{
		id: 'intemperie',
		kicker: 'Audio',
		title: 'Audio e intemperie',
		lead: 'Altavoces de alta eficiencia, amplificación multizona y sistemas acústicos.',
		detail:
			'Cobertura uniforme en sala y en exterior, con programa y volumen independientes por ambiente. El equipo se elige según el clima, la distancia y el nivel que el espacio exige.',
	},
	{
		id: 'video',
		kicker: 'Visuales',
		title: 'Video y visuales de gran formato',
		lead: 'Pantallas LED y proyección láser de alta luminosidad.',
		detail:
			'La imagen se dimensiona al muro o a la sala, para que se lea con luz ambiente. El formato, el brillo y la distancia de visión se definen en el diseño, antes de instalar.',
	},
] as const;

export type TechStandardId = (typeof TECH_STANDARDS)[number]['id'];
