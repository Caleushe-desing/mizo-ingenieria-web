<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$candidates = [
    __DIR__ . '/../data/productos-en-revision.json',
    __DIR__ . '/data/productos-en-revision.json',
    dirname(__DIR__) . '/data/productos-en-revision.json',
];

$path = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $path = $candidate;
        break;
    }
}

if ($path === null) {
    echo json_encode(['ok' => true, 'products' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents($path);
$data = json_decode($raw ?: '[]', true);

if (!is_array($data)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Reporte de revisión inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'products' => $data], JSON_UNESCAPED_UNICODE);
