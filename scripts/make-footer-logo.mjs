// Genera una versión del logo para el footer (fondo oscuro):
// recolorea a blanco únicamente los píxeles oscuros y desaturados
// (el texto "MIZO" y el subtítulo que están en negro), conservando
// el naranja del diafragma, el azul de la onda y la transparencia.
import sharp from 'sharp';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const input = path.join(root, 'public', 'mizo-logo.png');
const output = path.join(root, 'public', 'mizo-logo-footer.png');

const { data, info } = await sharp(input)
	.ensureAlpha()
	.raw()
	.toBuffer({ resolveWithObject: true });

const channels = info.channels; // 4 (RGBA)
for (let i = 0; i < data.length; i += channels) {
	const r = data[i];
	const g = data[i + 1];
	const b = data[i + 2];
	const a = data[i + 3];
	if (a === 0) continue;

	const max = Math.max(r, g, b);
	const min = Math.min(r, g, b);
	const saturation = max - min;

	// Píxel oscuro y casi gris/negro (texto): se invierte para volverlo claro,
	// preservando el suavizado (anti-aliasing) de los bordes.
	if (saturation < 45 && max < 130) {
		data[i] = 255 - r;
		data[i + 1] = 255 - g;
		data[i + 2] = 255 - b;
	}
}

await sharp(data, { raw: { width: info.width, height: info.height, channels } })
	.png()
	.toFile(output);

console.log(`Logo de footer generado en: ${output}`);
