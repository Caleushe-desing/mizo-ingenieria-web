import type { ImageMetadata } from 'astro';
import dante from '../assets/estandares/estandar-dante.jpg';
import dsp from '../assets/estandares/estandar-dsp.jpg';
import intemperie from '../assets/estandares/estandar-intemperie.jpg';
import video from '../assets/estandares/estandar-video.jpg';
import type { TechStandardId } from './tech-standards';

export const techMedia: Record<TechStandardId, ImageMetadata> = {
	'audio-digital': dante,
	dsp,
	intemperie,
	video,
};
