import type { ImageMetadata } from 'astro';
import iglesiaHero from '../assets/instalaciones/iglesia-hero.jpg';
import iglesiaParlantes from '../assets/instalaciones/iglesia-parlantes.jpg';
import iglesiaCulto from '../assets/instalaciones/iglesia-culto.jpg';
import proyectoresHero from '../assets/instalaciones/proyectores-hero.jpg';
import proyectoresMontaje from '../assets/instalaciones/proyectores-montaje.jpg';
import proyectoresSala from '../assets/instalaciones/proyectores-sala.jpg';
import ambientalHero from '../assets/instalaciones/ambiental-hero.jpg';
import ambientalTecho from '../assets/instalaciones/ambiental-techo.jpg';
import ambientalTerraza from '../assets/instalaciones/ambiental-terraza.jpg';
import interactivoHero from '../assets/instalaciones/interactivo-hero.jpg';
import interactivoToque from '../assets/instalaciones/interactivo-toque.jpg';
import interactivoAula from '../assets/instalaciones/interactivo-aula.jpg';
import parlantesHero from '../assets/instalaciones/parlantes-hero.jpg';
import parlantesMuro from '../assets/instalaciones/parlantes-muro.jpg';
import parlantesRack from '../assets/instalaciones/parlantes-rack.jpg';
import videowallHero from '../assets/instalaciones/videowall-hero.jpg';
import videowallMontaje from '../assets/instalaciones/videowall-montaje.jpg';
import videowallRetail from '../assets/instalaciones/videowall-retail.jpg';

export type LandingMedia = {
	hero: ImageMetadata;
	gallery: readonly [ImageMetadata, ImageMetadata, ImageMetadata];
};

export const landingMedia: Record<string, LandingMedia> = {
	'parlantes-para-iglesias': {
		hero: iglesiaHero,
		gallery: [iglesiaParlantes, iglesiaCulto, iglesiaHero],
	},
	'instalacion-de-proyectores': {
		hero: proyectoresHero,
		gallery: [proyectoresMontaje, proyectoresSala, proyectoresHero],
	},
	'instalacion-de-musica-ambiental': {
		hero: ambientalHero,
		gallery: [ambientalTecho, ambientalTerraza, ambientalHero],
	},
	'proyectores-interactivos': {
		hero: interactivoHero,
		gallery: [interactivoToque, interactivoAula, interactivoHero],
	},
	'instalacion-de-parlantes': {
		hero: parlantesHero,
		gallery: [parlantesMuro, parlantesRack, parlantesHero],
	},
	'instalacion-de-video-wall': {
		hero: videowallHero,
		gallery: [videowallMontaje, videowallRetail, videowallHero],
	},
};
