<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog-db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    try {
        $content = mizo_read_site_content();
        mizo_json_response([
            'ok' => true,
            'updatedAt' => $content['updatedAt'] ?? null,
            'source' => $content['source'] ?? 'unknown',
            'content' => $content,
        ]);
    } catch (Throwable $error) {
        mizo_json_response(['ok' => false, 'error' => $error->getMessage()], 500);
    }
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        mizo_json_response(['ok' => false, 'error' => 'JSON inválido.'], 400);
    }
    if (!mizo_admin_password_ok($input['password'] ?? null)) {
        mizo_json_response(['ok' => false, 'error' => 'Clave de administrador inválida.'], 403);
    }

    $action = trim((string) ($input['action'] ?? 'save'));
    try {
        if ($action === 'get') {
            $content = mizo_read_site_content();
            mizo_json_response([
                'ok' => true,
                'updatedAt' => $content['updatedAt'] ?? null,
                'source' => $content['source'] ?? 'unknown',
                'content' => $content,
            ]);
        }

        if ($action === 'save') {
            $content = $input['content'] ?? null;
            if (!is_array($content)) {
                mizo_json_response(['ok' => false, 'error' => 'Se requiere el objeto content.'], 422);
            }
            $saved = mizo_write_site_content($content);
            mizo_json_response([
                'ok' => true,
                'message' => 'Contenido del sitio guardado correctamente.',
                'updatedAt' => $saved['updatedAt'] ?? null,
                'content' => $saved,
            ]);
        }

        if ($action === 'reset') {
            $saved = mizo_reset_site_content();
            mizo_json_response([
                'ok' => true,
                'message' => 'Contenido del sitio restaurado a los valores predeterminados.',
                'updatedAt' => $saved['updatedAt'] ?? null,
                'content' => $saved,
            ]);
        }

        mizo_json_response(['ok' => false, 'error' => 'Acción no reconocida.'], 400);
    } catch (Throwable $error) {
        mizo_json_response(['ok' => false, 'error' => $error->getMessage()], 500);
    }
}

mizo_json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
