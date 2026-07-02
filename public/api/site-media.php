<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog-db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    mizo_json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$password = (string) ($_POST['password'] ?? '');
if (!mizo_admin_password_ok($password)) {
    mizo_json_response(['ok' => false, 'error' => 'Clave de administrador inválida.'], 403);
}

if (!isset($_FILES['image'])) {
    mizo_json_response(['ok' => false, 'error' => 'No se recibió ninguna imagen.'], 422);
}

try {
    $prefix = trim((string) ($_POST['prefix'] ?? 'cms'));
    $path = mizo_store_site_media($_FILES['image'], $prefix !== '' ? $prefix : 'cms');
    mizo_json_response([
        'ok' => true,
        'message' => 'Imagen subida correctamente.',
        'path' => $path,
        'url' => $path,
    ]);
} catch (Throwable $error) {
    mizo_json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
