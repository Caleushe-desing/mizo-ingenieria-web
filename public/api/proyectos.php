<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog-db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function proyectos_response(array $payload, int $status = 200): void
{
    mizo_json_response($payload, $status);
}

function proyectos_request_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function proyectos_public_payload(): array
{
    $config = mizo_read_proyectos_config(true);
    return [
        'ok' => true,
        'count' => count($config['projects']),
        'updatedAt' => $config['updatedAt'],
        'source' => $config['source'],
        'projects' => $config['projects'],
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    try {
        proyectos_response(proyectos_public_payload());
    } catch (Throwable $error) {
        proyectos_response([
            'ok' => false,
            'error' => $error->getMessage(),
            'projects' => [],
        ], 500);
    }
}

if ($method === 'POST') {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    $isMultipart = strpos($contentType, 'multipart/form-data') !== false;

    if ($isMultipart) {
        $password = (string) ($_POST['password'] ?? '');
        if (!mizo_admin_password_ok($password)) {
            proyectos_response(['ok' => false, 'error' => 'Clave de administrador inválida.'], 403);
        }

        $action = trim((string) ($_POST['action'] ?? 'upload'));
        $projectId = trim((string) ($_POST['projectId'] ?? $_POST['id'] ?? ''));

        if ($action === 'upload') {
            if ($projectId === '') {
                proyectos_response(['ok' => false, 'error' => 'Se requiere projectId.'], 422);
            }
            if (!isset($_FILES['image'])) {
                proyectos_response(['ok' => false, 'error' => 'No se recibió ninguna imagen.'], 422);
            }

            try {
                $imagePath = mizo_store_proyecto_image($_FILES['image'], $projectId);
                $project = mizo_append_proyecto_image($projectId, $imagePath);
                proyectos_response([
                    'ok' => true,
                    'message' => 'Imagen subida correctamente.',
                    'image' => $imagePath,
                    'projectId' => $projectId,
                    'project' => $project,
                ]);
            } catch (Throwable $error) {
                proyectos_response(['ok' => false, 'error' => $error->getMessage()], 500);
            }
        }

        proyectos_response(['ok' => false, 'error' => 'Acción multipart no reconocida.'], 400);
    }

    $input = proyectos_request_json();
    if (!mizo_admin_password_ok($input['password'] ?? null)) {
        proyectos_response(['ok' => false, 'error' => 'Clave de administrador inválida.'], 403);
    }

    $action = trim((string) ($input['action'] ?? 'save'));

    try {
        if ($action === 'list') {
            $config = mizo_read_proyectos_config(false);
            proyectos_response([
                'ok' => true,
                'count' => count($config['projects']),
                'updatedAt' => $config['updatedAt'],
                'source' => $config['source'],
                'projects' => $config['projects'],
            ]);
        }

        if ($action === 'save') {
            $incoming = $input['project'] ?? null;
            if (!is_array($incoming)) {
                proyectos_response(['ok' => false, 'error' => 'Se requiere un objeto project.'], 422);
            }

            $config = mizo_read_proyectos_config(false);
            $projects = $config['projects'];
            $incomingId = trim((string) ($incoming['id'] ?? ''));
            if (str_starts_with($incomingId, 'borrador-')) {
                $incoming['id'] = '';
            }
            $normalized = mizo_normalize_proyecto($incoming, count($projects) + 1);
            if ($normalized === null) {
                proyectos_response(['ok' => false, 'error' => 'El proyecto debe tener al menos un nombre.'], 422);
            }

            $index = mizo_find_proyecto_index($projects, $normalized['id']);
            if ($index < 0) {
                $normalized['id'] = mizo_unique_proyecto_id($projects, $normalized['id']);
                $projects[] = $normalized;
            } else {
                $existing = $projects[$index];
                $incomingImages = $normalized['images'];
                $existingImages = is_array($existing['images'] ?? null) ? $existing['images'] : [];
                if ($incomingImages === [] && $existingImages !== []) {
                    $normalized['images'] = $existingImages;
                }
                $projects[$index] = $normalized;
            }

            $saved = mizo_write_proyectos_config($projects);
            $savedProject = null;
            foreach ($saved['projects'] as $project) {
                if (($project['id'] ?? '') === $normalized['id']) {
                    $savedProject = $project;
                    break;
                }
            }

            proyectos_response([
                'ok' => true,
                'message' => 'Proyecto guardado.',
                'updatedAt' => $saved['updatedAt'],
                'project' => $savedProject,
                'count' => count($saved['projects']),
            ]);
        }

        if ($action === 'delete') {
            $id = trim((string) ($input['id'] ?? ''));
            if ($id === '') {
                proyectos_response(['ok' => false, 'error' => 'Se requiere id.'], 422);
            }

            $config = mizo_read_proyectos_config(false);
            $projects = [];
            $removed = null;
            foreach ($config['projects'] as $project) {
                if (($project['id'] ?? '') === $id) {
                    $removed = $project;
                    continue;
                }
                $projects[] = $project;
            }
            if ($removed === null) {
                proyectos_response(['ok' => false, 'error' => 'Proyecto no encontrado.'], 404);
            }

            foreach (($removed['images'] ?? []) as $image) {
                mizo_delete_proyecto_image_file((string) $image);
            }

            $saved = mizo_write_proyectos_config($projects);
            proyectos_response([
                'ok' => true,
                'message' => 'Proyecto eliminado.',
                'updatedAt' => $saved['updatedAt'],
                'count' => count($saved['projects']),
            ]);
        }

        if ($action === 'remove_image') {
            $id = trim((string) ($input['id'] ?? ''));
            $image = trim((string) ($input['image'] ?? ''));
            if ($id === '' || $image === '') {
                proyectos_response(['ok' => false, 'error' => 'Se requiere id e image.'], 422);
            }

            $config = mizo_read_proyectos_config(false);
            $index = mizo_find_proyecto_index($config['projects'], $id);
            if ($index < 0) {
                proyectos_response(['ok' => false, 'error' => 'Proyecto no encontrado.'], 404);
            }

            $project = $config['projects'][$index];
            $project['images'] = array_values(array_filter(
                $project['images'] ?? [],
                static fn(string $item): bool => $item !== $image
            ));
            $config['projects'][$index] = $project;
            $saved = mizo_write_proyectos_config($config['projects']);
            mizo_delete_proyecto_image_file($image);

            proyectos_response([
                'ok' => true,
                'message' => 'Imagen eliminada.',
                'project' => $project,
                'updatedAt' => $saved['updatedAt'],
            ]);
        }

        if ($action === 'reorder') {
            $order = $input['order'] ?? null;
            if (!is_array($order)) {
                proyectos_response(['ok' => false, 'error' => 'Se requiere un arreglo order con ids.'], 422);
            }

            $config = mizo_read_proyectos_config(false);
            $byId = [];
            foreach ($config['projects'] as $project) {
                $byId[$project['id']] = $project;
            }

            $reordered = [];
            $position = 1;
            foreach ($order as $id) {
                $key = trim((string) $id);
                if ($key === '' || !isset($byId[$key])) {
                    continue;
                }
                $project = $byId[$key];
                $project['sortOrder'] = $position;
                $reordered[] = $project;
                unset($byId[$key]);
                $position++;
            }
            foreach ($byId as $project) {
                $project['sortOrder'] = $position;
                $reordered[] = $project;
                $position++;
            }

            $saved = mizo_write_proyectos_config($reordered);
            proyectos_response([
                'ok' => true,
                'message' => 'Orden actualizado.',
                'updatedAt' => $saved['updatedAt'],
                'projects' => $saved['projects'],
            ]);
        }

        proyectos_response(['ok' => false, 'error' => 'Acción no reconocida.'], 400);
    } catch (Throwable $error) {
        proyectos_response(['ok' => false, 'error' => $error->getMessage()], 500);
    }
}

proyectos_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
