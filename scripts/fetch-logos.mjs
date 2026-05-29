// fetch-logos.mjs — Descarga el logo de cada marca del catálogo a
// public/images/marcas/<slug>.png. Usa el favicon oficial de cada dominio
// (que normalmente es el isotipo/logo de la marca) vía el servicio de Google,
// que es estable y devuelve PNG real. Marcas sin logo conservan el respaldo de texto.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const OUT_DIR = path.join(ROOT, 'public', 'images', 'marcas');
const PRODUCTS = path.join(ROOT, 'src', 'data', 'products.json');
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36';
const FORCE = process.argv.includes('--force');

const slugify = (s = '') =>
	s
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');

// Dominio oficial por marca (slug -> dominio). Si falta, se intenta <slug>.com.
const DOMAINS = {
	reolink: 'reolink.com',
	sonos: 'sonos.com',
	ezviz: 'ezviz.com',
	samson: 'samsontech.com',
	behringer: 'behringer.com',
	wanbo: 'wanboofficial.com',
	jbl: 'jbl.com',
	hikvision: 'hikvision.com',
	fbt: 'fbt.it',
	wharfedale: 'wharfedalepro.com',
	electrovoice: 'electrovoice.com',
	'db-technologies': 'dbtechnologies.com',
	mlab: 'mlab.cl',
	'pioneer-dj': 'pioneerdj.com',
	daewoo: 'daewoo.com',
	philco: 'philco.com.br',
	switchbot: 'switch-bot.com',
	neumann: 'en-de.neumann.com',
	yamaha: 'yamaha.com',
	bose: 'bose.com',
	mackie: 'mackie.com',
	qsc: 'qsc.com',
	tplink: 'tp-link.com',
	'tp-link': 'tp-link.com',
	dahua: 'dahuasecurity.com',
	epson: 'epson.com',
	benq: 'benq.com',
	xiaomi: 'mi.com',
};

async function download(url, dest) {
	const res = await fetch(url, { headers: { 'User-Agent': UA }, redirect: 'follow' });
	if (!res.ok) throw new Error(`HTTP ${res.status}`);
	const ct = res.headers.get('content-type') || '';
	if (!/image/i.test(ct)) throw new Error(`no-image (${ct})`);
	const buf = Buffer.from(await res.arrayBuffer());
	if (buf.length < 600) throw new Error(`muy pequeño (${buf.length}b)`);
	fs.writeFileSync(dest, buf);
	return buf.length;
}

async function main() {
	if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });
	const products = JSON.parse(fs.readFileSync(PRODUCTS, 'utf8'));
	const counts = {};
	for (const p of products) {
		if (!p.brand) continue;
		const low = p.brand.toLowerCase();
		if (low === 'genérico' || low === 'generico') continue;
		counts[p.brand] = (counts[p.brand] || 0) + 1;
	}
	const brands = Object.entries(counts)
		.sort((a, b) => b[1] - a[1])
		.map(([name]) => ({ name, slug: slugify(name) }));

	let ok = 0;
	let skip = 0;
	let fail = 0;
	for (const b of brands) {
		const dest = path.join(OUT_DIR, `${b.slug}.png`);
		if (!FORCE && fs.existsSync(dest) && fs.statSync(dest).size > 600) {
			skip++;
			continue;
		}
		const domain = DOMAINS[b.slug] || `${b.slug.replace(/-/g, '')}.com`;
		// Orden de preferencia: apple-touch-icon (logo real PNG) → favicon del sitio → servicio de Google.
		const candidates = [
			`https://${domain}/apple-touch-icon.png`,
			`https://${domain}/apple-touch-icon-precomposed.png`,
			`https://www.${domain}/apple-touch-icon.png`,
			`https://www.google.com/s2/favicons?domain=${domain}&sz=128`,
		];
		let done = false;
		let lastErr = '';
		for (const url of candidates) {
			try {
				const size = await download(url, dest);
				console.log(`  ✓ ${b.name.padEnd(18)} ${domain} (${size}b)`);
				ok++;
				done = true;
				break;
			} catch (err) {
				lastErr = err.message;
			}
		}
		if (!done) {
			console.warn(`  ✗ ${b.name.padEnd(18)} ${domain} — ${lastErr}`);
			fail++;
		}
	}
	console.log(`\nLogos: ${ok} descargados, ${skip} ya existían, ${fail} sin logo (usan texto).`);
}

main().catch((e) => {
	console.error(e);
	process.exit(1);
});
