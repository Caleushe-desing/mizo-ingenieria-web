// sync-catalogo.mjs
// Sincroniza el catálogo público (audio y video) trayendo precio y stock REAL
// desde las APIs de catálogo (VTEX) de las tiendas chilenas de referencia.
//
// - Reúne 100+ productos disponibles.
// - Guarda el "basePrice" (precio de la tienda); el sitio publica ese valor + 20%.
// - Guarda el stock real (AvailableQuantity) y el enlace original al producto.
// - Descarga las imágenes a public/images/productos/<id>.jpg (omite las ya existentes).
// - Escribe src/data/products.json.
//
// Uso:  node scripts/sync-catalogo.mjs            (sincroniza precios/stock e imágenes faltantes)
//       node scripts/sync-catalogo.mjs --force-images   (vuelve a descargar todas las imágenes)
//
// Este mismo script se ejecuta en cada build (ver netlify.toml) para mantener
// precios y stock al día automáticamente.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const OUT_JSON = path.join(root, 'src/data/products.json');
const IMG_DIR = path.join(root, 'public/images/productos');
const REVIEW_JSON = path.join(root, 'data/productos-en-revision.json');
const FORCE_IMAGES = process.argv.includes('--force-images');

const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

// Tiendas de referencia y términos de búsqueda por categoría.
const SOURCES = [
	{
		store: 'Audiomúsica',
		type: 'vtex',
		host: 'https://www.audiomusica.com',
		category: 'sonido',
		target: 55,
		terms: [
			'parlante activo', 'parlante portatil', 'parlante bluetooth', 'caja activa',
			'caja acustica', 'subwoofer activo', 'line array', 'monitor activo',
			'monitor de estudio', 'bafle', 'columna de sonido', 'barra de sonido',
		],
	},
	{
		store: 'Promusic',
		type: 'shopify',
		host: 'https://www.promusic.cl',
		category: 'sonido',
		target: 30,
		// Solo parlantes/altavoces (no audífonos, micrófonos, interfaces, etc.).
		keep: /parlante|altavoz|altoparlante|caja activa|caja ac[uú]stica|line array|subwoofer|bafle|columna|sistema de sonido|pa system|monitor de piso|monitor activo|monitor de estudio/i,
	},
	{
		store: 'Casa Royal',
		type: 'vtex',
		host: 'https://www.casaroyal.cl',
		category: 'video', // se divide en 'proyector' o 'camara' según el producto
		target: 60,
		terms: [
			'proyector', 'proyector wanbo', 'proyector philco', 'proyector epson',
			'proyector full hd', 'proyector 4k', 'mini proyector', 'home theater proyector',
			'camara seguridad', 'camara wifi', 'camara vigilancia', 'camara ip',
		],
	},
];

// SKU estable y legible derivado del id del producto. No cambia entre
// sincronizaciones (sirve para buscar el producto rápido en el panel /admin).
const CAT_CODE = { sonido: 'SON', proyector: 'PRO', camara: 'CAM' };
function skuFor(id, category) {
	let h = 0;
	for (let i = 0; i < id.length; i++) h = (Math.imul(h, 31) + id.charCodeAt(i)) >>> 0;
	const code = h.toString(36).toUpperCase().padStart(6, '0').slice(-6);
	return `MZ-${CAT_CODE[category] || 'GEN'}-${code}`;
}

// Convierte la categoría de la fuente en la categoría pública final.
// Los productos de "video" se separan en proyectores y cámaras.
function finalCategory(srcCategory, name) {
	if (srcCategory !== 'video') return srcCategory;
	if (/c[aá]mara/i.test(name)) return 'camara';
	if (/proyector/i.test(name)) return 'proyector';
	return null;
}

const decodeEntities = (s = '') =>
	s
		.replace(/&quot;/g, '"')
		.replace(/&#34;/g, '"')
		.replace(/&#39;/g, "'")
		.replace(/&apos;/g, "'")
		.replace(/&amp;/g, '&')
		.replace(/&lt;/g, '<')
		.replace(/&gt;/g, '>')
		.replace(/&ntilde;/gi, 'ñ')
		.replace(/&aacute;/gi, 'á')
		.replace(/&eacute;/gi, 'é')
		.replace(/&iacute;/gi, 'í')
		.replace(/&oacute;/gi, 'ó')
		.replace(/&uacute;/gi, 'ú')
		.replace(/&nbsp;/g, ' ');

const stripTags = (s = '') => decodeEntities(s.replace(/<[^>]*>/g, ' ')).replace(/\s+/g, ' ').trim();
const clean = (s = '') => decodeEntities(String(s)).replace(/\s+/g, ' ').trim();
// Corta a `max` caracteres SOLO si excede, eliminando la última palabra parcial.
const truncate = (s = '', max = 200) => (s.length <= max ? s : s.slice(0, max).replace(/\s+\S*$/, '') + '…');

// Peso por defecto (kg) por categoría cuando el producto no lo declara.
const DEFAULT_WEIGHT = { sonido: 14, proyector: 2.5, camara: 0.7 };

// Intenta extraer el peso en kg desde un texto ("Peso 12,5 kg", "850 g", etc.).
function parseWeightKg(text = '') {
	const m = String(text).match(/(\d{1,3}(?:[.,]\d{1,2})?)\s*(kgs?|kilos?|kilogramos|grs?|gramos|g)\b/i);
	if (!m) return null;
	let v = parseFloat(m[1].replace(',', '.'));
	if (!isFinite(v) || v <= 0) return null;
	if (/^(grs?|gramos|g)$/i.test(m[2])) v = v / 1000;
	if (v <= 0 || v > 120) return null;
	return Math.round(v * 100) / 100;
}

// Determina el peso de un producto: usa la spec "Peso", luego la descripción,
// y finalmente un valor por defecto según la categoría.
function weightFor(category, specs, desc, fallbackGrams) {
	const pesoSpec = (specs || []).find((s) => /peso/i.test(s.label));
	const fromSpec = pesoSpec ? parseWeightKg(pesoSpec.value) : null;
	const fromDesc = fromSpec ? null : parseWeightKg(desc);
	const fromGrams = fallbackGrams && fallbackGrams > 0 ? Math.round((fallbackGrams / 1000) * 100) / 100 : null;
	return fromSpec || fromDesc || fromGrams || DEFAULT_WEIGHT[category] || 5;
}

function stockFromCasaRoyalStores(raw) {
	const stockText = Array.isArray(raw?.StockTiendas) ? raw.StockTiendas[0] : '';
	if (!stockText) return null;

	let total = 0;
	for (const match of String(stockText).matchAll(/\b(R\d+)=(\d+)\b/g)) {
		const quantity = Number(match[2]) || 0;
		total += quantity;
	}

	return total > 0 ? total : null;
}

// Intenta extraer "Etiqueta: valor" desde el HTML de descripción (Shopify) usando
// los bloques naturales (<li>, <tr>, <p>, <br>).
function specsFromHtml(html = '') {
	const blocks = html.split(/<\s*(?:li|tr|p|br|\/h\d)[^>]*>/i);
	const specs = [];
	const seen = new Set();
	for (const block of blocks) {
		const text = stripTags(block);
		const m = text.match(/^([\wáéíóúñü .,/+()-]{2,40}?)\s*[:：]\s*(.+)$/i);
		if (!m) continue;
		const label = clean(m[1]);
		const value = truncate(clean(m[2]), 180);
		if (!value || value.length < 2 || /^https?:/i.test(value)) continue;
		const key = label.toLowerCase();
		if (seen.has(key)) continue;
		seen.add(key);
		specs.push({ label, value });
		if (specs.length >= 14) break;
	}
	return specs;
}

// Slug ASCII, limpio y seguro para URL y nombre de archivo.
const slugify = (s = '') =>
	s
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '')
		.replace(/-{2,}/g, '-');

// Filtros para publicar SOLO parlantes/equipos de sonido y proyectores/cámaras de video.
// Excluye instrumentos musicales, focos/luminarias LED, accesorios y juguetes.
const BLOCKLIST = new RegExp(
	[
		// juguetes y decoración
		'juguete', '\\btoy(s)?\\b', 'infantil', 'disney', 'luz nocturna', 'sistema solar',
		'galax', 'estrellas', 'kids', 'niñ', 'cielo estrellado',
		// instrumentos musicales (no son "parlantes")
		'guitarra', '\\bbajo\\b', 'teclado', 'pedalera', 'cabezal', 'gabinete',
		'ukelele', 'viol', 'bater', 'piano', 'multiefecto', 'platillo', 'baqueta',
		'zildjian', 'parche', 'atril', 'metronomo', 'afinador',
		// luminarias LED (no son proyectores de video)
		'proyector de area', 'proyector de área', 'proyector led', 'led solar',
		'panel solar', 'reflector', '\\bflat\\b', '\\bsmd\\b', 'area led', 'megabright',
		'interlight',
		// accesorios
		'soporte', '\\bbolso\\b', 'tripode', 'trípode', '\\bbase para\\b', '\\bcable\\b',
		'maleta', 'funda', 'pantalla de proyec', 'mochila',
		// equipos de audio que NO son parlantes
		'audifono', 'audífono', '\\bin[- ]ear\\b', 'monitoreo', 'micr[oó]fono',
		'grabadora', 'preamplificad', '\\bpreamp\\b', 'tornamesa', 'controlador',
		'interfaz', 'mezclador', 'consola', '\\bmixer\\b',
		// accesorios de audio
		'tapa superior', 'rejilla', 'montaje array',
		// cámaras de auto (no aplican a instalación)
		'dashcam', 'dash cam', 'auto dvr', 'camara auto', 'camara espejo', 'camara de auto',
	].join('|'),
	'i',
);

const titleCase = (s = '') =>
	s
		.toLowerCase()
		.split(' ')
		.map((w) => (w.length > 2 ? w.charAt(0).toUpperCase() + w.slice(1) : w))
		.join(' ')
		.trim();

async function fetchJson(url) {
	const res = await fetch(url, { headers: { 'User-Agent': UA, Accept: 'application/json' } });
	if (!res.ok) throw new Error(`HTTP ${res.status} en ${url}`);
	return res.json();
}

async function searchTerm(host, term) {
	const out = [];
	// Hasta 3 páginas de 50 resultados por término.
	for (let from = 0; from <= 100; from += 50) {
		const to = from + 49;
		const url = `${host}/api/catalog_system/pub/products/search?ft=${encodeURIComponent(term)}&_from=${from}&_to=${to}`;
		try {
			const page = await fetchJson(url);
			if (!Array.isArray(page) || page.length === 0) break;
			out.push(...page);
			if (page.length < 50) break;
		} catch (err) {
			console.warn(`  (aviso) ${term} [${from}-${to}]: ${err.message}`);
			break;
		}
	}
	return out;
}

function normalize(raw, store, host, category) {
	const item = raw.items?.[0];
	const seller = item?.sellers?.find((s) => s.commertialOffer?.IsAvailable) ?? item?.sellers?.[0];
	const offer = seller?.commertialOffer;
	if (!offer || !offer.IsAvailable) return null;

	const price = Number(offer.Price) || Number(offer.ListPrice) || 0;
	if (price <= 0) return null;

	const image = item?.images?.[0]?.imageUrl;
	if (!image) return null;

	const id = slugify(raw.linkText || raw.productName || '');
	if (!id) return null;

	const nameForFilter = `${raw.brand || ''} ${raw.productName || ''}`;
	if (BLOCKLIST.test(nameForFilter)) return null;

	const name = clean(raw.productName);
	const finalCat = finalCategory(category, name);
	if (!finalCat) return null;

	const rawQty = Number(offer.AvailableQuantity) || 0;
	const visibleStoreStock = store === 'Casa Royal' ? stockFromCasaRoyalStores(raw) : null;
	// VTEX usa valores enormes (10000+) para "stock infinito": lo tratamos como disponible sin número.
	const stock = visibleStoreStock ?? (rawQty >= 1 && rawQty < 1000 ? rawQty : null);

	const productUrl = `${host}/${id}/p`;
	const brand = clean(raw.brand) || 'Genérico';
	const fullDesc = stripTags(raw.description || raw.metaTagDescription || '');

	// Ficha técnica desde las especificaciones de VTEX.
	const SPEC_SKIP = /stock|promote|^manual$|full proteccion|^marca$|garant[ií]a|^sku$|tiendas|disponibil|^ean$|c[oó]digo|^id$/i;
	const specs = [];
	for (const key of raw.allSpecifications || []) {
		if (SPEC_SKIP.test(key)) continue;
		const val = raw[key];
		if (!Array.isArray(val) || !val.length) continue;
		const text = truncate(stripTags(val.join(' · ')).replace(/\s*\|\s*/g, ' · '), 180);
		if (text && text.length > 1) specs.push({ label: clean(key), value: text });
		if (specs.length >= 14) break;
	}

	return {
		id,
		sku: skuFor(id, finalCat),
		name,
		brand: titleCase(brand),
		category: finalCat,
		description: fullDesc ? fullDesc.slice(0, 200).replace(/\s+\S*$/, '') : `${titleCase(brand)} — ${name}.`,
		descriptionLong: fullDesc ? fullDesc.slice(0, 1200) : '',
		specs,
		weightKg: weightFor(finalCat, specs, fullDesc),
		basePrice: price,
		stock,
		available: true,
		image: `/images/productos/${id}.jpg`,
		source: {
			store,
			url: productUrl,
			image,
			capturedAt: new Date().toISOString().slice(0, 10),
		},
	};
}

// Trae productos desde una tienda Shopify (endpoint público products.json).
async function searchShopify(src) {
	const out = [];
	for (let page = 1; page <= 12; page++) {
		let data;
		try {
			data = await fetchJson(`${src.host}/products.json?limit=250&page=${page}`);
		} catch (err) {
			console.warn(`  (aviso) Shopify pág ${page}: ${err.message}`);
			break;
		}
		const prods = data.products || [];
		if (!prods.length) break;
		for (const p of prods) {
			const hay = `${p.product_type || ''} ${p.title || ''}`;
			if (src.keep && !src.keep.test(hay)) continue;
			if (BLOCKLIST.test(`${p.vendor || ''} ${p.title || ''}`)) continue;

			const variant = p.variants?.find((v) => v.available) ?? p.variants?.[0];
			if (variant && variant.available === false) continue;
			const price = Math.round(Number(variant?.price) || 0);
			if (price <= 0) continue;

			const image = p.images?.[0]?.src;
			if (!image) continue;

			const id = slugify(p.handle || p.title || '');
			if (!id) continue;

			const name = clean(p.title);
			const desc = stripTags(p.body_html || '');
			const specs = specsFromHtml(p.body_html || '');
			out.push({
				id,
				sku: skuFor(id, src.category),
				name,
				brand: titleCase(clean(p.vendor || 'Genérico')),
				category: src.category,
				description: desc ? desc.slice(0, 200).replace(/\s+\S*$/, '') : `${name}.`,
				descriptionLong: desc ? desc.slice(0, 1200) : '',
				specs,
				weightKg: weightFor(src.category, specs, desc, Number(variant?.grams)),
				basePrice: price,
				stock: null,
				available: true,
				image: `/images/productos/${id}.jpg`,
				source: {
					store: src.store,
					url: `${src.host}/products/${p.handle}`,
					image,
					capturedAt: new Date().toISOString().slice(0, 10),
				},
			});
		}
		if (prods.length < 250) break;
	}
	return out;
}

async function downloadImage(product) {
	const dest = path.join(IMG_DIR, `${product.id}.jpg`);
	if (!FORCE_IMAGES && fs.existsSync(dest) && fs.statSync(dest).size > 1500) return 'skip';
	try {
		const res = await fetch(product.source.image, { headers: { 'User-Agent': UA } });
		if (!res.ok) throw new Error(`HTTP ${res.status}`);
		const buf = Buffer.from(await res.arrayBuffer());
		if (buf.length < 1500) throw new Error(`muy pequeña (${buf.length}b)`);
		fs.writeFileSync(dest, buf);
		return 'ok';
	} catch (err) {
		console.warn(`  imagen ${product.id}: ${err.message}`);
		return 'fail';
	}
}

function sameInventoryValue(a, b) {
	return (a ?? null) === (b ?? null);
}

function samePriceValue(a, b) {
	return Number(a ?? 0) === Number(b ?? 0);
}

function auditWrittenCatalog(providerProducts) {
	const written = JSON.parse(fs.readFileSync(OUT_JSON, 'utf8'));
	const writtenBySku = new Map(written.map((product) => [product.sku, product]));
	const productsInReview = [];

	for (const providerProduct of providerProducts) {
		const mizoProduct = writtenBySku.get(providerProduct.sku);
		const stockProveedor = providerProduct.stock ?? null;
		const stockMizo = mizoProduct?.stock ?? null;
		const precioProveedor = Number(providerProduct.basePrice ?? 0);
		const precioMizo = mizoProduct ? Number(mizoProduct.basePrice ?? 0) : null;

		if (!mizoProduct || !sameInventoryValue(stockProveedor, stockMizo) || !samePriceValue(precioProveedor, precioMizo)) {
			productsInReview.push({
				sku: providerProduct.sku,
				stock_proveedor: stockProveedor,
				stock_mizo: stockMizo,
				precio_proveedor: precioProveedor,
				precio_mizo: precioMizo,
			});
		}
	}

	fs.mkdirSync(path.dirname(REVIEW_JSON), { recursive: true });
	fs.writeFileSync(REVIEW_JSON, JSON.stringify(productsInReview, null, '\t') + '\n', 'utf8');

	if (productsInReview.length) {
		console.log('\n⚠️ PRODUCTOS BAJO REVISIÓN MANUAL (DETECTADAS DISCREPANCIAS)');
		for (const product of productsInReview) {
			console.log(
				`  ${product.sku} | stock proveedor=${product.stock_proveedor} stock Mizo=${product.stock_mizo} | precio proveedor=${product.precio_proveedor} precio Mizo=${product.precio_mizo}`,
			);
		}
		console.log(`Reporte escrito en ${path.relative(root, REVIEW_JSON)}`);
	} else {
		console.log('\n✅ Sincronización 100% idéntica');
	}

	return productsInReview;
}

async function main() {
	fs.mkdirSync(IMG_DIR, { recursive: true });
	const byId = new Map();

	for (const src of SOURCES) {
		const collected = new Map();
		console.log(`\n== ${src.store} (${src.category}) [${src.type}] ==`);

		if (src.type === 'shopify') {
			const results = await searchShopify(src);
			for (const p of results) {
				if (collected.size >= src.target) break;
				if (collected.has(p.id) || byId.has(p.id)) continue;
				collected.set(p.id, p);
			}
			console.log(`  Shopify: ${collected.size} productos`);
		} else {
			for (const term of src.terms) {
				if (collected.size >= src.target) break;
				const results = await searchTerm(src.host, term);
				let added = 0;
				for (const raw of results) {
					const p = normalize(raw, src.store, src.host, src.category);
					if (!p) continue;
					if (collected.has(p.id) || byId.has(p.id)) continue;
					collected.set(p.id, p);
					added++;
					if (collected.size >= src.target) break;
				}
				console.log(`  "${term}": +${added}  (acumulado ${collected.size})`);
			}
		}
		for (const [id, p] of collected) byId.set(id, p);
	}

	const products = [...byId.values()];
	console.log(`\nTotal productos: ${products.length}`);

	// Descarga de imágenes (en paralelo, en lotes).
	console.log('\nDescargando imágenes...');
	let ok = 0, skip = 0, fail = 0;
	const batch = 8;
	const valid = [];
	for (let i = 0; i < products.length; i += batch) {
		const slice = products.slice(i, i + batch);
		const res = await Promise.all(slice.map(downloadImage));
		res.forEach((r, idx) => {
			if (r === 'ok') ok++;
			else if (r === 'skip') skip++;
			else fail++;
			// Solo publicamos productos cuya imagen existe en disco.
			const dest = path.join(IMG_DIR, `${slice[idx].id}.jpg`);
			if (fs.existsSync(dest) && fs.statSync(dest).size > 1500) valid.push(slice[idx]);
		});
	}
	console.log(`Imágenes -> nuevas: ${ok}, reutilizadas: ${skip}, fallidas: ${fail}`);

	valid.sort((a, b) => (a.category === b.category ? a.name.localeCompare(b.name) : a.category.localeCompare(b.category)));
	const providerSnapshot = valid.map((product) => ({ ...product }));

	// Productos ocultos (prueba interna) se conservan entre sincronizaciones.
	const hiddenPath = path.join(root, 'src/data/products-hidden.json');
	if (fs.existsSync(hiddenPath)) {
		const hidden = JSON.parse(fs.readFileSync(hiddenPath, 'utf8'));
		for (const p of hidden) {
			const idx = valid.findIndex((x) => x.id === p.id);
			if (idx >= 0) valid[idx] = p;
			else valid.push(p);
		}
	}

	fs.writeFileSync(OUT_JSON, JSON.stringify(valid, null, '\t') + '\n', 'utf8');
	console.log(`\nEscrito ${path.relative(root, OUT_JSON)} con ${valid.length} productos.`);
	auditWrittenCatalog(providerSnapshot);
	const count = (c) => valid.filter((p) => p.category === c).length;
	console.log(`  Parlantes: ${count('sonido')}  |  Proyectores: ${count('proyector')}  |  Cámaras: ${count('camara')}`);
}

main().catch((err) => {
	console.error('ERROR fatal:', err);
	process.exit(1);
});
