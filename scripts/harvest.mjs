// Extrae productos (nombre, marca, imagen, precio, url) desde los bloques
// JSON-LD ItemList/Product de un HTML guardado. Uso:
//   node scripts/harvest.mjs <archivo.html> <categoria>
import fs from 'node:fs';

const file = process.argv[2];
const category = process.argv[3] || 'sonido';
const html = fs.readFileSync(file, 'utf8');

const blocks = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].map(
	(m) => m[1]
);

const out = [];

function priceOf(offers) {
	if (!offers) return null;
	if (offers.lowPrice) return offers.lowPrice;
	if (offers.price) return offers.price;
	if (Array.isArray(offers.offers) && offers.offers.length) return offers.offers[0].price;
	return null;
}

function pushProduct(p) {
	if (!p || p['@type'] !== 'Product') return;
	out.push({
		name: p.name,
		brand: p.brand?.name || '',
		image: typeof p.image === 'string' ? p.image : Array.isArray(p.image) ? p.image[0] : '',
		url: p['@id'] || p.url || '',
		basePrice: priceOf(p.offers),
	});
}

for (const b of blocks) {
	let data;
	try {
		data = JSON.parse(b);
	} catch {
		continue;
	}
	const arr = Array.isArray(data) ? data : [data];
	for (const node of arr) {
		if (node['@type'] === 'ItemList' && Array.isArray(node.itemListElement)) {
			for (const el of node.itemListElement) pushProduct(el.item);
		} else if (node['@type'] === 'Product') {
			pushProduct(node);
		}
	}
}

console.log(JSON.stringify({ category, count: out.length, products: out }, null, 2));
