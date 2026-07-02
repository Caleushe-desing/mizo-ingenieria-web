// Genera PNG del logo para fondos claros y oscuros desde los SVG oficiales.
import sharp from 'sharp';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const publicDir = path.join(root, 'public');

const variants = [
	{ input: 'mizo-logo.svg', output: 'mizo-logo.png', width: 720 },
	{ input: 'mizo-logo-dark.svg', output: 'mizo-logo-dark.png', width: 720 },
	{ input: 'mizo-logo-dark.svg', output: 'mizo-logo-footer.png', width: 720 },
];

for (const variant of variants) {
	const inputPath = path.join(publicDir, variant.input);
	const outputPath = path.join(publicDir, variant.output);
	await sharp(inputPath, { density: 300 })
		.resize({ width: variant.width })
		.png()
		.toFile(outputPath);
	console.log(`Generado ${variant.output}`);
}
