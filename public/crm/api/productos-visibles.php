<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=30');
header('Access-Control-Allow-Origin: *');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'productos' => [], 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
	exit;
}

/**
 * API pública liviana: lee SQLite en solo lectura y no dispara migraciones del CRM.
 * Así evitamos 500 por side-effects de Database::pdo() en cada hit del catálogo.
 */
try {
	$crmRoot = dirname(__DIR__);
	$dbPath = dirname($crmRoot) . '/crm-data/crm.sqlite';
	if (!is_file($dbPath)) {
		echo json_encode(['ok' => true, 'productos' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	$pdo = new PDO('sqlite:' . $dbPath, null, null, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
	// Solo lectura lógica: no escribimos ni migraremos desde este endpoint.
	$pdo->exec('PRAGMA query_only = ON');

	$cols = $pdo->query('PRAGMA table_info(products)')->fetchAll();
	if ($cols === []) {
		echo json_encode(['ok' => true, 'productos' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}
	$names = array_column($cols, 'name');
	$select = ['id', 'sku', 'nombre', 'descripcion', 'categoria'];
	if (in_array('imagenes', $names, true)) {
		$select[] = 'imagenes';
	}
	$sql = 'SELECT ' . implode(', ', $select) . '
		FROM products
		WHERE activo = 1
		ORDER BY categoria COLLATE NOCASE, nombre COLLATE NOCASE, sku COLLATE NOCASE';
	$rows = $pdo->query($sql)->fetchAll();

	$productos = [];
	foreach ($rows as $row) {
		$productos[] = [
			'sku' => catalog_plain($row['sku'] ?? '', 80),
			'nombre' => catalog_plain($row['nombre'] ?? '', 180),
			'descripcion' => catalog_plain($row['descripcion'] ?? '', 4000),
			'categoria' => catalog_plain($row['categoria'] ?? '', 80),
			'imagenes' => catalog_resolve_images($row, $crmRoot),
		];
	}

	$json = json_encode(['ok' => true, 'productos' => $productos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		throw new RuntimeException('No se pudo serializar el catálogo.');
	}
	echo $json;
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'ok' => false,
		'productos' => [],
		'error' => 'No se pudo leer el catálogo.',
		'detail' => $e->getMessage(),
	], JSON_UNESCAPED_UNICODE);
}

/**
 * @param array<string, mixed> $row
 * @return list<string>
 */
function catalog_resolve_images(array $row, string $crmRoot): array
{
	$candidates = [];
	$raw = $row['imagenes'] ?? '[]';
	if (is_array($raw)) {
		$decoded = $raw;
	} else {
		$decoded = json_decode((string) $raw, true);
	}
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
		$normalized = catalog_normalize_image_path($path);
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
			$files = @scandir($dir);
			if (!is_array($files)) {
				continue;
			}
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

function catalog_normalize_image_path(string $path): ?string
{
	$path = trim($path);
	if ($path === '') {
		return null;
	}
	if (preg_match('#^https?://[^/]+(/crm/uploads/productos/.+)$#i', $path, $m)) {
		$path = $m[1];
	}
	$path = str_replace('\\', '/', $path);
	if (strpos($path, 'crm/uploads/productos/') === 0) {
		$path = '/' . $path;
	} elseif (strpos($path, 'uploads/productos/') === 0) {
		$path = '/crm/' . $path;
	} elseif (strpos($path, '/uploads/productos/') === 0) {
		$path = '/crm' . $path;
	}
	if (!preg_match('#^/crm/uploads/productos/[A-Za-z0-9_-]+/[^/]+\.(jpe?g|png|webp|gif)$#i', $path)) {
		return null;
	}
	return $path;
}

function catalog_plain($value, int $max): string
{
	$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?: '');
	if (function_exists('mb_substr')) {
		return mb_substr($text, 0, $max, 'UTF-8');
	}
	return substr($text, 0, $max);
}
