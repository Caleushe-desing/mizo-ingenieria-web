// Genera public/datos-privados.json: los datos privados (valor de compra, tienda
// y enlace de cada producto) CIFRADOS con una clave. Solo quien tenga la clave
// puede descifrarlos en la página /admin. Sin la clave, el archivo es ilegible
// incluso viendo el código fuente.
//
// Uso:
//   node scripts/build-admin.mjs "MI-CLAVE-SECRETA"
//   (o define la variable de entorno ADMIN_PASSWORD)
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { webcrypto as crypto } from 'node:crypto';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const password = process.argv[2] || process.env.ADMIN_PASSWORD || 'mizo2026';
const ITERATIONS = 200000;

const products = [
	...JSON.parse(fs.readFileSync(path.join(root, 'src/data/products.json'), 'utf8')),
	...(fs.existsSync(path.join(root, 'src/data/products-hidden.json'))
		? JSON.parse(fs.readFileSync(path.join(root, 'src/data/products-hidden.json'), 'utf8'))
		: []),
];
const MARKUP = 0.2;
const sell = (b) => Math.round((b * (1 + MARKUP)) / 10) * 10;

const payload = {
	generatedAt: new Date().toISOString().slice(0, 10),
	markup: MARKUP,
	products: products.map((p) => ({
		sku: p.sku,
		name: p.name,
		brand: p.brand,
		category: p.category,
		basePrice: p.basePrice,
		sellingPrice: sell(p.basePrice),
		store: p.source.store,
		url: p.source.url,
	})),
};

const enc = new TextEncoder();
const b64 = (buf) => Buffer.from(buf).toString('base64');

const salt = crypto.getRandomValues(new Uint8Array(16));
const iv = crypto.getRandomValues(new Uint8Array(12));

const baseKey = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, [
	'deriveKey',
]);
const key = await crypto.subtle.deriveKey(
	{ name: 'PBKDF2', salt, iterations: ITERATIONS, hash: 'SHA-256' },
	baseKey,
	{ name: 'AES-GCM', length: 256 },
	false,
	['encrypt']
);
const ciphertext = await crypto.subtle.encrypt(
	{ name: 'AES-GCM', iv },
	key,
	enc.encode(JSON.stringify(payload))
);

const out = {
	v: 1,
	alg: 'AES-GCM',
	kdf: 'PBKDF2-SHA256',
	iterations: ITERATIONS,
	salt: b64(salt),
	iv: b64(iv),
	ciphertext: b64(new Uint8Array(ciphertext)),
};

const outPath = path.join(root, 'public/datos-privados.json');
fs.writeFileSync(outPath, JSON.stringify(out));
console.log(`Datos privados cifrados -> public/datos-privados.json (${payload.products.length} productos)`);
console.log(`Clave usada: "${password}"`);
console.log('Para cambiar la clave: node scripts/build-admin.mjs "TU-NUEVA-CLAVE"');
