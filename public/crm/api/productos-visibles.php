<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Autoload.php';

use MizoCrm\Models\Product;
use MizoCrm\ProductImporter;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');

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

$productos = [];
foreach ($rows as $row) {
	$images = json_decode((string) ($row['imagenes'] ?? ''), true);
	if (!is_array($images)) {
		$folder = (string) ((int) ($row['id'] ?? 0));
		$images = $folder !== '0' ? ProductImporter::saveImagesFromPage((string) ($row['proveedor_link'] ?? ''), $folder) : [];
		if ($images !== [] && !empty($row['id'])) {
			Product::update((int) $row['id'], [
				'imagenes' => json_encode($images, JSON_UNESCAPED_SLASHES),
				'updated_at' => date('c'),
			]);
		}
	}
	$publicImages = [];
	foreach ($images as $path) {
		if (is_string($path) && preg_match('#^/crm/uploads/productos/[a-z0-9-]+/\d+\.(jpg|png|webp|gif)$#', $path)) {
			$publicImages[] = $path;
		}
	}
	$productos[] = [
		'sku' => plain($row['sku'] ?? '', 80),
		'nombre' => plain($row['nombre'] ?? '', 180),
		'descripcion' => plain($row['descripcion'] ?? '', 4000),
		'categoria' => plain($row['categoria'] ?? '', 80),
		'imagenes' => $publicImages,
	];
}

echo json_encode(['ok' => true, 'productos' => $productos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function plain(mixed $value, int $max): string
{
	$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?: '');
	return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}
