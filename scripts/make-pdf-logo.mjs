import sharp from 'sharp';
import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const source = 'public/mizo-logo-pdf.png';
const target = 'public/api/assets/mizo-logo-pdf.jpg';

mkdirSync(dirname(target), { recursive: true });

await sharp(source)
	.resize({ width: 420, withoutEnlargement: true })
	.flatten({ background: '#ffffff' })
	.jpeg({ quality: 92, chromaSubsampling: '4:4:4' })
	.toFile(target);

console.log('Logo PDF JPEG generado en', target);
