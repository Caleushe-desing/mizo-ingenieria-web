<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog-db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function showcase_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function showcase_request_payload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    try {
        $config = mizo_read_showcase_config();
        $result = mizo_fetch_showcase_products();
        $categories = [];
        $brands = [];

        foreach ($result['products'] as $product) {
            $category = trim((string) ($product['category'] ?? ''));
            if ($category !== '') {
                $categories[$category] = (string) ($product['categoryLabel'] ?? mizo_category_label($category));
            }
            $brand = trim((string) ($product['brand'] ?? ''));
            if ($brand !== '' && strtolower($brand) !== 'mizo') {
                $brands[$brand] = true;
            }
        }

        asort($categories, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($brands, SORT_NATURAL | SORT_FLAG_CASE);

        showcase_response([
            'ok' => true,
            'count' => $result['count'],
            'mode' => $result['mode'] ?? ($config['mode'] ?? 'all'),
            'updatedAt' => $result['updatedAt'] ?? ($config['updatedAt'] ?? null),
            'source' => $result['source'],
            'items' => $config['items'] ?? [],
            'filters' => [
                'categories' => $categories,
                'brands' => array_keys($brands),
            ],
            'products' => $result['products'],
        ]);
    } catch (Throwable $error) {
        showcase_response([
            'ok' => false,
            'error' => $error->getMessage(),
            'products' => [],
        ], 500);
    }
}

if ($method === 'POST') {
    $input = showcase_request_payload();
    if (!mizo_admin_password_ok($input['password'] ?? null)) {
        showcase_response(['ok' => false, 'error' => 'Clave de administrador inválida.'], 403);
    }

    $items = $input['items'] ?? null;
    $mode = trim((string) ($input['mode'] ?? ''));

    if ($mode === 'all') {
        try {
            $saved = mizo_write_showcase_mode('all');
            showcase_response([
                'ok' => true,
                'message' => 'Vitrina configurada para mostrar todo el catálogo.',
                'mode' => 'all',
                'count' => mizo_fetch_showcase_products()['count'],
                'updatedAt' => $saved['updatedAt'],
            ]);
        } catch (Throwable $error) {
            showcase_response(['ok' => false, 'error' => $error->getMessage()], 500);
        }
    }

    if (!is_array($items)) {
        showcase_response(['ok' => false, 'error' => 'Se requiere un arreglo items.'], 422);
    }

    try {
        $saved = mizo_write_showcase_config($items);
        showcase_response([
            'ok' => true,
            'message' => 'Vitrina web actualizada.',
            'mode' => 'curated',
            'count' => count($saved['items']),
            'updatedAt' => $saved['updatedAt'],
            'items' => $saved['items'],
        ]);
    } catch (Throwable $error) {
        showcase_response(['ok' => false, 'error' => $error->getMessage()], 500);
    }
}

showcase_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
