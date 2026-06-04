import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const distRoot = path.join(root, 'dist');
const adminIndexPath = path.join(distRoot, 'admin', 'index.html');
const adminCatalogPath = path.join(distRoot, 'admin.catalogo.php');

if (!fs.existsSync(adminIndexPath)) {
	throw new Error('No se encontró dist/admin/index.html para generar admin.catalogo.php');
}

const adminHtml = fs.readFileSync(adminIndexPath, 'utf8');
fs.writeFileSync(adminCatalogPath, adminHtml);

const redirectHtml = `<!doctype html>
<html lang="es">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<meta http-equiv="refresh" content="0; url=/admin.catalogo.php">
		<title>Redirigiendo al panel Mizo</title>
		<script>
			location.replace('/admin.catalogo.php' + location.search + location.hash);
		</script>
	</head>
	<body>
		<p>Redirigiendo al panel Mizo: <a href="/admin.catalogo.php">admin.catalogo.php</a></p>
	</body>
</html>
`;

fs.writeFileSync(adminIndexPath, redirectHtml);

console.log('Admin principal generado en dist/admin.catalogo.php');
console.log('Ruta /admin/ convertida en redirección a /admin.catalogo.php');
