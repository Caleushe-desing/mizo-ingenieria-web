import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { exec } from 'node:child_process';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../..');
const CONTENT_PATH = path.join(ROOT, 'src', 'data', 'siteContent.json');
const PUBLIC_DIR = path.join(__dirname, 'public');
const PORT = Number(process.env.MIZO_CONTENT_PORT || 4789);
const HOST = '127.0.0.1';

const MIME = {
	'.html': 'text/html; charset=utf-8',
	'.css': 'text/css; charset=utf-8',
	'.js': 'text/javascript; charset=utf-8',
	'.json': 'application/json; charset=utf-8',
	'.svg': 'image/svg+xml',
};

function send(res, status, body, type = 'text/plain; charset=utf-8') {
	let data = body;
	if (Buffer.isBuffer(body)) {
		data = body;
	} else if (typeof body !== 'string') {
		data = JSON.stringify(body);
	}
	res.writeHead(status, {
		'Content-Type': type,
		'Cache-Control': 'no-store',
	});
	res.end(data);
}

function readBody(req) {
	return new Promise((resolve, reject) => {
		const chunks = [];
		req.on('data', (chunk) => chunks.push(chunk));
		req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
		req.on('error', reject);
	});
}

function serveStatic(reqPath, res) {
	const cleaned = decodeURIComponent(reqPath || '/').split('?')[0];
	const relative =
		cleaned === '/' || cleaned === '' || cleaned === '\\'
			? 'index.html'
			: cleaned.replace(/^[/\\]+/, '').replace(/\//g, path.sep);
	const filePath = path.resolve(PUBLIC_DIR, relative);
	if (!filePath.startsWith(PUBLIC_DIR)) {
		send(res, 403, 'Forbidden');
		return;
	}
	if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
		send(res, 404, 'Not found');
		return;
	}
	const ext = path.extname(filePath);
	const encoding = MIME[ext]?.includes('charset') ? 'utf8' : null;
	send(res, 200, fs.readFileSync(filePath, encoding), MIME[ext] || 'application/octet-stream');
}

const server = http.createServer(async (req, res) => {
	const url = new URL(req.url || '/', `http://${HOST}:${PORT}`);

	try {
		if (req.method === 'GET' && url.pathname === '/api/content') {
			const raw = fs.readFileSync(CONTENT_PATH, 'utf8');
			send(res, 200, raw, 'application/json; charset=utf-8');
			return;
		}

		if (req.method === 'POST' && url.pathname === '/api/content') {
			const raw = await readBody(req);
			let parsed;
			try {
				parsed = JSON.parse(raw);
			} catch {
				send(res, 400, { ok: false, error: 'JSON inválido' });
				return;
			}
			if (!parsed || typeof parsed !== 'object' || !parsed.site || !parsed.pages) {
				send(res, 400, { ok: false, error: 'Estructura incompleta: faltan site o pages' });
				return;
			}
			const pretty = `${JSON.stringify(parsed, null, 2)}\n`;
			fs.writeFileSync(CONTENT_PATH, pretty, 'utf8');
			send(res, 200, {
				ok: true,
				path: CONTENT_PATH,
				savedAt: new Date().toISOString(),
			});
			return;
		}

		if (req.method === 'GET') {
			serveStatic(url.pathname, res);
			return;
		}

		send(res, 405, 'Method not allowed');
	} catch (error) {
		send(res, 500, { ok: false, error: error instanceof Error ? error.message : String(error) });
	}
});

server.on('error', (error) => {
	if (error && error.code === 'EADDRINUSE') {
		console.error(`\n[ERROR] El puerto ${PORT} ya esta en uso.`);
		console.error('Cierra la otra ventana del editor e intenta de nuevo.\n');
	} else {
		console.error('\n[ERROR] No se pudo iniciar el servidor:', error);
	}
	process.exit(1);
});

server.listen(PORT, HOST, () => {
	const url = `http://${HOST}:${PORT}/`;
	console.log(`Editor de textos Mizo: ${url}`);
	console.log(`Archivo: ${CONTENT_PATH}`);
	console.log('Cierra esta ventana para detener el editor.\n');
	const opener =
		process.platform === 'win32'
			? `cmd /c start "" "${url}"`
			: process.platform === 'darwin'
				? `open "${url}"`
				: `xdg-open "${url}"`;
	exec(opener, (err) => {
		if (err) {
			console.log(`No se pudo abrir el navegador solo. Abre manualmente: ${url}`);
		}
	});
});
