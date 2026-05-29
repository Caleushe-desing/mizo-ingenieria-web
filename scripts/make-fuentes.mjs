// Genera PRECIOS-FUENTES.md: documento PRIVADO con el origen de cada precio.
// Este archivo NO se publica en el sitio (no está en src/pages ni en public),
// por lo que solo es visible para quien tiene los archivos del proyecto.
// Uso: node scripts/make-fuentes.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const products = JSON.parse(fs.readFileSync(path.join(root, 'src/data/products.json'), 'utf8'));

const MARKUP = 0.2;
const sell = (b) => Math.round((b * (1 + MARKUP)) / 10) * 10;
const clp = (v) => '$' + v.toLocaleString('es-CL');

const catLabel = { sonido: 'Audio', video: 'Video' };
const catOrder = ['sonido', 'video'];

let md = `# Fuentes de precios (PRIVADO)\n\n`;
md += `> Documento interno. **No se publica en el sitio web.** Indica de dónde se obtuvo el precio de referencia de cada producto. El precio publicado = precio base + 20%.\n\n`;
md += `Generado: ${new Date().toISOString().slice(0, 10)} · Productos: ${products.length} · Recargo aplicado: ${MARKUP * 100}%\n\n`;

for (const cat of catOrder) {
	const items = products.filter((p) => p.category === cat);
	if (!items.length) continue;
	md += `## ${catLabel[cat]} (${items.length})\n\n`;
	md += `| Producto | Marca | Precio base | Precio +20% | Tienda | Enlace de origen |\n`;
	md += `| --- | --- | ---: | ---: | --- | --- |\n`;
	for (const p of items) {
		md += `| ${p.name} | ${p.brand} | ${clp(p.basePrice)} | ${clp(sell(p.basePrice))} | ${p.source.store} | ${p.source.url} |\n`;
	}
	md += `\n`;
}

fs.writeFileSync(path.join(root, 'PRECIOS-FUENTES.md'), md, 'utf8');
console.log('PRECIOS-FUENTES.md generado con', products.length, 'productos.');
