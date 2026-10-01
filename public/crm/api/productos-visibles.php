<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Autoload.php';

use MizoCrm\Models\Product;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');
header('Access-Control-Allow-Origin: *');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'productos' => []], JSON_UNESCAPED_UNICODE);
	exit;
}

try {
	$rows = Product::visible();
} catch (Throwable) {
	$rows = [];
}

$crmRoot = dirname(__DIR__);
$productos = [];
foreach ($rows as $row) {
	$productos[] = [
		'sku' => plain($row['sku'] ?? '', 80),
		'nombre' => plain($row['nombre'] ?? '', 180),
		'descripcion' => plain($row['descripcion'] ?? '', 4000),
		'categoria' => plain($row['categoria'] ?? '', 80),
		'imagenes' => resolveProductImages($row, $crmRoot),
	];
}

echo json_encode(['ok' => true, 'productos' => $productos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/**
 * Solo rutas locales existentes. No descarga remota (eso colgaba el catálogo).
 *
 * @param array<string, mixed> $row
 * @return list<string>
 */
function resolveProductImages(array $row, string $crmRoot): array
{
	$candidates = [];
	$decoded = json_decode((string) ($row['imagenes'] ?? ''), true);
	if (is_array($decoded)) {
		foreach ($decoded as $path) {
			if (is_string($path) && $path !== '') {
				$candidates[] = $path;
			}
		}
	}

	$found = [];
	$folders = [];
	foreach ($candidates as $path) {
		$normalized = normalizeProductImagePath($path);
		if ($normalized === null) {
			continue;
		}
		if (preg_match('#^/crm/uploads/productos/([^/]+)/#', $normalized, $m)) {
			$folders[$m[1]] = true;
		}
		$absolute = $crmRoot . substr($normalized, strlen('/crm'));
		if (is_file($absolute)) {
			$found[] = $normalized;
		}
	}

	$id = (int) ($row['id'] ?? 0);
	if ($id > 0) {
		$folders[(string) $id] = true;
	}

	if ($found === []) {
		foreach (array_keys($folders) as $folder) {
			if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $folder)) {
				continue;
			}
			$dir = $crmRoot . '/uploads/productos/' . $folder;
			if (!is_dir($dir)) {
				continue;
			}
			$files = scandir($dir) ?: [];
			natcasesort($files);
			foreach ($files as $name) {
				if ($name === '.' || $name === '..') {
					continue;
				}
				if (!preg_match('/\.(jpe?g|png|webp|gif)$/i', $name)) {
					continue;
				}
				$absolute = $dir . DIRECTORY_SEPARATOR . $name;
				if (!is_file($absolute)) {
					continue;
				}
				$found[] = '/crm/uploads/productos/' . $folder . '/' . $name;
			}
		}
	}

	return array_values(array_unique($found));
}

function normalizeProductImagePath(string $path): ?string
{
	$path = trim($path);
	if ($path === '') {
		return null;
	}
	if (preg_match('#^https?://[^/]+(/crm/uploads/productos/.+)$#i', $path, $m)) {
		$path = $m[1];
	}
	$path = str_replace('\\', '/', $path);
	if (str_starts_with($path, 'crm/uploads/productos/')) {
		$path = '/' . $path;
	} elseif (str_starts_with($path, 'uploads/productos/')) {
		$path = '/crm/' . $path;
	} elseif (str_starts_with($path, '/uploads/productos/')) {
		$path = '/crm' . $path;
	}
	if (!preg_match('#^/crm/uploads/productos/[A-Za-z0-9_-]+/[^/]+\.(jpe?g|png|webp|gif)$#i', $path)) {
		return null;
	}
	return $path;
}

function plain(mixed $value, int $max): string
{
	$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?: '');
	return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}
