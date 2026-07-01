<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog-db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const MIZO_CONSULTAS_EMAIL = 'ventas@mizo.cl';

function consulta_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function consulta_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST ?: $_GET;
}

function consulta_clean($value, int $max = 500): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?: '';
    $text = preg_replace('/\s+/u', ' ', $text) ?: '';
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

function consulta_storage_dir(): string
{
    return __DIR__ . '/../mizo-data';
}

function consulta_storage_file(): string
{
    return consulta_storage_dir() . '/consultas-producto.json';
}

function ensure_consulta_storage(): array
{
    $dir = consulta_storage_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (is_dir($dir) && !is_file(consulta_storage_file())) {
        @file_put_contents(consulta_storage_file(), json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, LOCK_EX);
    }
    return [
        'dir' => $dir,
        'file' => consulta_storage_file(),
        'dirExists' => is_dir($dir),
        'dirWritable' => is_dir($dir) && is_writable($dir),
        'fileWritable' => is_file(consulta_storage_file()) && is_writable(consulta_storage_file()),
    ];
}

function consulta_lookup_product(string $productId, string $productName = ''): ?array
{
    static $catalog = null;
    if ($catalog === null) {
        $catalog = mizo_fetch_products_from_json();
    }

    $needle = trim($productId);
    foreach ($catalog as $product) {
        if ($needle === '') {
            continue;
        }
        if ((string) ($product['id'] ?? '') === $needle || (string) ($product['sku'] ?? '') === $needle) {
            return $product;
        }
    }

    $name = trim($productName);
    if ($name === '') {
        return null;
    }

    foreach ($catalog as $product) {
        if (strcasecmp((string) ($product['name'] ?? ''), $name) === 0) {
            return $product;
        }
    }

    return null;
}

function consulta_product_links(string $productId, string $productName = ''): array
{
    $product = consulta_lookup_product($productId, $productName);
    if (!is_array($product)) {
        return [
            'productoUrlProveedor' => '',
            'productoProveedor' => '',
            'productoPaginaMizo' => $productId !== '' ? 'https://mizo.cl/productos/' . rawurlencode($productId) . '/' : '',
        ];
    }

    $source = is_array($product['source'] ?? null) ? $product['source'] : [];
    $id = trim((string) ($product['id'] ?? $productId));

    return [
        'productoUrlProveedor' => trim((string) ($source['url'] ?? $product['source_url'] ?? '')),
        'productoProveedor' => trim((string) ($source['store'] ?? $product['source_store'] ?? '')),
        'productoPaginaMizo' => $id !== '' ? 'https://mizo.cl/productos/' . rawurlencode($id) . '/' : '',
    ];
}

function normalize_consulta(array $payload): array
{
    $productoId = consulta_clean($payload['productoId'] ?? '', 180);
    $productoNombre = consulta_clean($payload['productoNombre'] ?? '', 260);
    $links = consulta_product_links($productoId, $productoNombre);

    return [
        'id' => 'consulta_' . gmdate('Ymd_His') . '_' . substr(sha1((string) microtime(true)), 0, 10),
        'createdAt' => gmdate('c'),
        'status' => 'nuevo',
        'type' => 'consulta-producto',
        'source' => 'Consulta de producto · mizo.cl',
        'nombre' => consulta_clean($payload['nombre'] ?? '', 140),
        'correo' => consulta_clean($payload['correo'] ?? '', 180),
        'telefono' => consulta_clean($payload['telefono'] ?? '', 90),
        'productoId' => $productoId,
        'productoNombre' => $productoNombre,
        'productoUrlProveedor' => consulta_clean($links['productoUrlProveedor'] ?? '', 500),
        'productoProveedor' => consulta_clean($links['productoProveedor'] ?? '', 180),
        'productoPaginaMizo' => consulta_clean($links['productoPaginaMizo'] ?? '', 500),
        'mailAdminOk' => false,
    ];
}

function validate_consulta(array $consulta): ?string
{
    if ($consulta['nombre'] === '' || $consulta['telefono'] === '' || $consulta['correo'] === '') {
        return 'Completa nombre, correo y celular.';
    }
    if (!filter_var($consulta['correo'], FILTER_VALIDATE_EMAIL)) {
        return 'Ingresa un correo válido.';
    }
    if ($consulta['productoId'] === '' && $consulta['productoNombre'] === '') {
        return 'Falta identificar el producto consultado.';
    }
    return null;
}

function save_consulta_json(array $consulta): bool
{
    $storage = ensure_consulta_storage();
    if (!$storage['dirWritable'] || (!$storage['fileWritable'] && is_file($storage['file']))) {
        return false;
    }

    $file = $storage['file'];
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return false;
    }

    $ok = false;
    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $items = json_decode($raw ?: '[]', true);
        if (!is_array($items)) {
            $items = [];
        }
        array_unshift($items, $consulta);
        ftruncate($fp, 0);
        rewind($fp);
        $ok = fwrite($fp, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL) !== false;
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $ok;
}

function list_consultas_json(): array
{
    ensure_consulta_storage();
    $items = json_decode((string) @file_get_contents(consulta_storage_file()), true);
    return is_array($items) ? $items : [];
}

function consulta_encode_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function send_consulta_email(array $consulta): bool
{
    $lines = [
        'Nueva consulta de producto recibida desde mizo.cl',
        '',
        'Nombre: ' . $consulta['nombre'],
        'Correo: ' . $consulta['correo'],
        'Celular: ' . $consulta['telefono'],
        '',
        'Producto: ' . ($consulta['productoNombre'] ?: 'Sin nombre'),
        'ID producto: ' . ($consulta['productoId'] ?: 'No indicado'),
        'Página Mizo: ' . ($consulta['productoPaginaMizo'] ?: 'No indicada'),
        'Proveedor: ' . ($consulta['productoProveedor'] ?: 'No indicado'),
        'URL proveedor: ' . ($consulta['productoUrlProveedor'] ?: 'No indicada'),
        'Origen: ' . $consulta['source'],
        'Fecha: ' . $consulta['createdAt'],
    ];

    $headers = [
        'MIME-Version: 1.0',
        'From: Mizo Web <' . MIZO_CONSULTAS_EMAIL . '>',
        'Reply-To: ' . $consulta['nombre'] . ' <' . $consulta['correo'] . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    return @mail(
        MIZO_CONSULTAS_EMAIL,
        consulta_encode_subject('Nueva consulta de producto · Mizo'),
        implode("\r\n", $lines),
        implode("\r\n", $headers)
    );
}

function update_consulta_mail_status(string $id, bool $adminOk): void
{
    $file = consulta_storage_file();
    if (!is_file($file) || !is_writable($file)) {
        return;
    }
    $items = json_decode((string) file_get_contents($file), true);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as &$item) {
        if (($item['id'] ?? '') === $id) {
            $item['mailAdminOk'] = $adminOk;
            break;
        }
    }
    file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = consulta_input();

if (($input['action'] ?? '') === 'list') {
    if (!mizo_admin_password_ok($input['password'] ?? null)) {
        consulta_response(['ok' => false, 'error' => 'Clave incorrecta.'], 401);
    }
    consulta_response([
        'ok' => true,
        'consultas' => list_consultas_json(),
        'storage' => ensure_consulta_storage(),
    ]);
}

if ($method !== 'POST') {
    consulta_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

if (consulta_clean($input['website'] ?? '') !== '') {
    consulta_response(['ok' => true, 'message' => 'Consulta recibida.']);
}

$consulta = normalize_consulta($input);
$error = validate_consulta($consulta);
if ($error) {
    consulta_response(['ok' => false, 'error' => $error], 422);
}

if (!save_consulta_json($consulta)) {
    consulta_response([
        'ok' => false,
        'error' => 'No se pudo guardar la consulta. Revisa permisos de mizo-data/consultas-producto.json.',
        'storage' => ensure_consulta_storage(),
    ], 500);
}

$mailOk = send_consulta_email($consulta);
update_consulta_mail_status($consulta['id'], $mailOk);

consulta_response([
    'ok' => true,
    'id' => $consulta['id'],
    'mail' => ['admin' => $mailOk],
    'message' => $mailOk
        ? 'Consulta enviada. Un asesor de Mizo te contactará pronto.'
        : 'Consulta registrada. Un asesor de Mizo te contactará pronto.',
]);
