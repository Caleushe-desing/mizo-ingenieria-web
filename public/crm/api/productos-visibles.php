<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Autoload.php';

use MizoCrm\Models\Product;

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
	$link = trim((string) ($row['proveedor_link'] ?? ''));
	$scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
	if (!in_array($scheme, ['http', 'https'], true)) {
		$link = '';
	}
	$productos[] = [
		'sku' => plain($row['sku'] ?? '', 80),
		'nombre' => plain($row['nombre'] ?? '', 180),
		'descripcion' => plain($row['descripcion'] ?? '', 4000),
		'categoria' => plain($row['categoria'] ?? '', 80),
		'proveedor_empresa' => plain($row['proveedor_empresa'] ?? '', 160),
		'proveedor_link' => $link,
	];
}

echo json_encode(['ok' => true, 'productos' => $productos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function plain(mixed $value, int $max): string
{
	$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?: '');
	return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}
