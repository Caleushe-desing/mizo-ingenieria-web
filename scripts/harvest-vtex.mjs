// Parsea la respuesta de la API pública de catálogo VTEX.
// Uso: node scripts/harvest-vtex.mjs <archivo.json> <host> <categoria>
import fs from 'node:fs';

const file = process.argv[2];
const host = process.argv[3]; // ej: https://www.dartel.cl
const category = process.argv[4] || 'electrico';

const data = JSON.parse(fs.readFileSync(file, 'utf8'));
const out = [];

for (const p of data) {
	const item = p.items?.[0];
	const offer = item?.sellers?.[0]?.commertialOffer;
	const price = offer?.Price ?? offer?.ListPrice ?? null;
	const image = item?.images?.[0]?.imageUrl || '';
	const url = p.link || (p.linkText ? `${host}/${p.linkText}/p` : '');
	if (!price || !image) continue;
	out.push({
		name: p.productName,
		brand: p.brand || '',
		image,
		url,
		basePrice: price,
	});
}

console.log(JSON.stringify({ category, count: out.length, products: out }, null, 2));
