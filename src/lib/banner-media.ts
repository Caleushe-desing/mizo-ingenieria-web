import type { ImageMetadata } from 'astro';
import ambiental from '../assets/banners/banner-ambiental.jpg';
import auditorio from '../assets/banners/banner-auditorio.jpg';
import educacion from '../assets/banners/banner-educacion.jpg';
import gimnasio from '../assets/banners/banner-gimnasio.jpg';
import iglesia from '../assets/banners/banner-iglesia.jpg';
import parlantes from '../assets/banners/banner-parlantes.jpg';
import quincho from '../assets/banners/banner-quincho.jpg';
import restaurante from '../assets/banners/banner-restaurante.jpg';

export const landingBanners: Record<string, ImageMetadata> = {
	'parlantes-para-iglesias': iglesia,
	'instalacion-de-musica-ambiental': ambiental,
	'instalacion-de-parlantes': parlantes,
	restaurantes: restaurante,
	'instituciones-educativas': educacion,
	auditorios: auditorio,
	gimnasios: gimnasio,
	'entretenimiento-residencial': quincho,
};

export const landingBannerAlts: Record<string, string> = {
	'parlantes-para-iglesias': 'Interior de una iglesia con parlantes junto al altar',
	'instalacion-de-musica-ambiental': 'Comedor de un restaurante con parlantes de techo',
	'instalacion-de-parlantes': 'Hall comercial con un parlante integrado en el muro',
	restaurantes: 'Mesa de un restaurante con un parlante discreto en el techo',
	'instituciones-educativas': 'Auditorio de un colegio con un parlante junto al escenario',
	auditorios: 'Auditorio vacío con un arreglo de sonido junto al escenario',
	gimnasios: 'Sala de pesas de un gimnasio con parlantes en el techo',
	'entretenimiento-residencial': 'Quincho de madera al anochecer con parlantes bajo el alero',
};
