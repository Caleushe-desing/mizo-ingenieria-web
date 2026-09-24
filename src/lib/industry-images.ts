import type { ImageMetadata } from 'astro';
import restaurantesHero from '../assets/industrias/restaurantes-hero.jpg';
import restaurantesSonido from '../assets/industrias/restaurantes-sonido.jpg';
import restaurantesPantalla from '../assets/industrias/restaurantes-pantalla.jpg';
import restaurantesKaraoke from '../assets/industrias/restaurantes-karaoke.jpg';
import educacionHero from '../assets/industrias/educacion-hero.jpg';
import educacionActo from '../assets/industrias/educacion-acto.jpg';
import educacionProyeccion from '../assets/industrias/educacion-proyeccion.jpg';
import educacionControl from '../assets/industrias/educacion-control.jpg';
import auditoriosHero from '../assets/industrias/auditorios-hero.jpg';
import auditoriosDante from '../assets/industrias/auditorios-dante.jpg';
import auditoriosDsp from '../assets/industrias/auditorios-dsp.jpg';
import auditoriosLinearray from '../assets/industrias/auditorios-linearray.jpg';
import auditoriosLed from '../assets/industrias/auditorios-led.jpg';
import gimnasiosHero from '../assets/industrias/gimnasios-hero.jpg';
import gimnasiosZonas from '../assets/industrias/gimnasios-zonas.jpg';
import gimnasiosInstructor from '../assets/industrias/gimnasios-instructor.jpg';
import gimnasiosPantallas from '../assets/industrias/gimnasios-pantallas.jpg';
import residencialHero from '../assets/industrias/residencial-hero.jpg';
import residencialCine from '../assets/industrias/residencial-cine.jpg';
import residencialQuincho from '../assets/industrias/residencial-quincho.jpg';
import residencialDomotica from '../assets/industrias/residencial-domotica.jpg';

export type IndustryMedia = {
	hero: ImageMetadata;
	solutions: Record<string, ImageMetadata>;
};

export const industryMedia: Record<string, IndustryMedia> = {
	restaurantes: {
		hero: restaurantesHero,
		solutions: {
			'sonido-ambiental': restaurantesSonido,
			'pantallas-led': restaurantesPantalla,
			karaoke: restaurantesKaraoke,
		},
	},
	'instituciones-educativas': {
		hero: educacionHero,
		solutions: {
			sonorizacion: educacionActo,
			proyeccion: educacionProyeccion,
			intercomunicacion: educacionControl,
		},
	},
	auditorios: {
		hero: auditoriosHero,
		solutions: {
			dante: auditoriosDante,
			dsp: auditoriosDsp,
			sonorizacion: auditoriosLinearray,
			video: auditoriosLed,
		},
	},
	gimnasios: {
		hero: gimnasiosHero,
		solutions: {
			'audio-zonas': gimnasiosZonas,
			instructores: gimnasiosInstructor,
			pantallas: gimnasiosPantallas,
		},
	},
	'entretenimiento-residencial': {
		hero: residencialHero,
		solutions: {
			'home-cinema': residencialCine,
			quinchos: residencialQuincho,
			domotica: residencialDomotica,
		},
	},
};
