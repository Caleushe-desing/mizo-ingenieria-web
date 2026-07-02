<?php
declare(strict_types=1);

require_once __DIR__ . '/api/_catalog-db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond_catalog(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$result = mizo_fetch_public_products([
    'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 220,
]);

$products = array_values(array_filter($result['products'], function ($product) {
    return ($product['published'] ?? true) !== false;
}));
$brands = [];
$categories = [];

foreach ($products as $product) {
    $brand = trim((string) ($product['brand'] ?? ''));
    if ($brand !== '' && strtolower($brand) !== 'mizo') {
        $brands[$brand] = true;
    }
    $category = trim((string) ($product['category'] ?? ''));
    if ($category !== '') {
        $categories[$category] = $product['categoryLabel'] ?? mizo_category_label($category);
    }
}

ksort($brands, SORT_NATURAL | SORT_FLAG_CASE);
asort($categories, SORT_NATURAL | SORT_FLAG_CASE);

respond_catalog(200, [
    'ok' => true,
    'source' => $result['source'] === 'mysql' ? 'MySQL tendencias mercado audiovisual' : $result['source'],
    'generatedAt' => gmdate('c'),
    'count' => count($products),
    'filters' => [
        'brands' => array_keys($brands),
        'categories' => $categories,
    ],
    'products' => $products,
]);
