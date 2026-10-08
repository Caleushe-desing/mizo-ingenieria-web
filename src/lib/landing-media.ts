import type { ImageMetadata } from 'astro';
import auditoriosHero from '../assets/industrias/auditorios-hero.jpg';
import auditoriosLinearray from '../assets/industrias/auditorios-linearray.jpg';
import auditoriosDante from '../assets/industrias/auditorios-dante.jpg';
import educacionProyeccion from '../assets/industrias/educacion-proyeccion.jpg';
import educacionActo from '../assets/industrias/educacion-acto.jpg';
import auditoriosLed from '../assets/industrias/auditorios-led.jpg';
import restaurantesSonido from '../assets/industrias/restaurantes-sonido.jpg';
import restaurantesHero from '../assets/industrias/restaurantes-hero.jpg';
import gimnasiosZonas from '../assets/industrias/gimnasios-zonas.jpg';
import educacionHero from '../assets/industrias/educacion-hero.jpg';
import educacionControl from '../assets/industrias/educacion-control.jpg';
import gimnasiosPantallas from '../assets/industrias/gimnasios-pantallas.jpg';
import restaurantesPantalla from '../assets/industrias/restaurantes-pantalla.jpg';

export type LandingMedia = {
	hero: ImageMetadata;
	gallery: readonly [ImageMetadata, ImageMetadata, ImageMetadata];
};

export const landingMedia: Record<string, LandingMedia> = {
	'parlantes-para-iglesias': {
		hero: auditoriosHero,
		gallery: [auditoriosLinearray, auditoriosDante, educacionActo],
	},
	'instalacion-de-proyectores': {
		hero: educacionProyeccion,
		gallery: [educacionHero, auditoriosLed, educacionActo],
	},
	'instalacion-de-musica-ambiental': {
		hero: restaurantesSonido,
		gallery: [restaurantesHero, gimnasiosZonas, restaurantesPantalla],
	},
	'proyectores-interactivos': {
		hero: educacionProyeccion,
		gallery: [educacionControl, educacionHero, educacionActo],
	},
	'instalacion-de-parlantes': {
		hero: auditoriosLinearray,
		gallery: [auditoriosHero, gimnasiosZonas, restaurantesSonido],
	},
	'instalacion-de-video-wall': {
		hero: auditoriosLed,
		gallery: [gimnasiosPantallas, restaurantesPantalla, auditoriosHero],
	},
};
