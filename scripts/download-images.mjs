// Descarga las imágenes reales de cada producto (desde source.image) hacia
// public/images/productos/<id>.jpg para que el sitio no dependa de enlaces externos.
// Uso: node scripts/download-images.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const products = JSON.parse(fs.readFileSync(path.join(root, 'src/data/products.json'), 'utf8'));
const outDir = path.join(root, 'public/images/productos');
fs.mkdirSync(outDir, { recursive: true });

let ok = 0;
let fail = 0;

for (const p of products) {
	const url = p.source?.image;
	if (!url) {
		console.warn(`SIN IMAGEN: ${p.id}`);
		fail++;
		continue;
	}
	const dest = path.join(outDir, `${p.id}.jpg`);
	try {
		const res = await fetch(url, {
			headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)' },
		});
		if (!res.ok) throw new Error(`HTTP ${res.status}`);
		const buf = Buffer.from(await res.arrayBuffer());
		if (buf.length < 1000) throw new Error(`archivo muy pequeño (${buf.length} bytes)`);
		fs.writeFileSync(dest, buf);
		console.log(`OK   ${p.id}  (${(buf.length / 1024).toFixed(0)} KB)`);
		ok++;
	} catch (err) {
		console.error(`FALLO ${p.id}: ${err.message}`);
		fail++;
	}
}

console.log(`\nListo. Descargadas: ${ok}, Fallidas: ${fail}`);
