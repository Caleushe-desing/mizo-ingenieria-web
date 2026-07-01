<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin-push.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function admin_push_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_push_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST ?: $_GET;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = admin_push_input();
$action = trim((string) ($input['action'] ?? $_GET['action'] ?? ''));

if ($action === 'config' || $action === 'vapid-public') {
    $vapid = mizo_admin_push_vapid_keys();
    admin_push_response([
        'ok' => true,
        'vapidPublicKey' => $vapid['public'],
        'pushEnabled' => $vapid['configured'],
        'pollIntervalMs' => 15000,
    ]);
}

if ($action === 'peek') {
    $token = trim((string) ($input['token'] ?? $_GET['token'] ?? ''));
    if ($token === '') {
        admin_push_response(['ok' => false, 'error' => 'Token requerido.'], 422);
    }
    $result = mizo_admin_push_peek_device($token);
    admin_push_response($result, ($result['ok'] ?? false) ? 200 : 404);
}

if ($action === 'status') {
    $token = trim((string) ($input['token'] ?? $_GET['token'] ?? ''));
    if ($token === '') {
        admin_push_response(['ok' => false, 'error' => 'Token requerido.'], 422);
    }
    $result = mizo_admin_push_device_status($token);
    admin_push_response($result, ($result['ok'] ?? false) ? 200 : 404);
}

if ($action === 'check') {
    $token = trim((string) ($input['token'] ?? $_GET['token'] ?? ''));
    if ($token === '') {
        admin_push_response(['ok' => false, 'error' => 'Token requerido.'], 422);
    }
    $result = mizo_admin_push_check_device($token);
    admin_push_response($result, ($result['ok'] ?? false) ? 200 : 404);
}

if ($action === 'ack') {
    $token = trim((string) ($input['token'] ?? $_GET['token'] ?? ''));
    $sequence = (int) ($input['sequence'] ?? $_GET['sequence'] ?? 0);
    if ($token === '' || $sequence <= 0) {
        admin_push_response(['ok' => false, 'error' => 'Token o secuencia inválidos.'], 422);
    }
    if (!mizo_admin_push_find_device($token)) {
        admin_push_response(['ok' => false, 'error' => 'Dispositivo no registrado.'], 404);
    }
    mizo_admin_push_ack_device($token, $sequence);
    admin_push_response(['ok' => true, 'sequence' => $sequence]);
}

if ($method !== 'POST') {
    admin_push_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

if (!mizo_admin_password_ok($input['password'] ?? null)) {
    admin_push_response(['ok' => false, 'error' => 'Clave incorrecta.'], 401);
}

if ($action === 'register') {
    $device = mizo_admin_push_register_device([
        'existingToken' => trim((string) ($input['deviceToken'] ?? '')),
        'subscription' => $input['subscription'] ?? null,
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
    $vapid = mizo_admin_push_vapid_keys();
    admin_push_response([
        'ok' => true,
        'deviceToken' => $device['token'],
        'vapidPublicKey' => $vapid['public'],
        'pushEnabled' => $vapid['configured'],
        'message' => 'Dispositivo registrado para notificaciones.',
    ]);
}

if ($action === 'subscribe') {
    $token = trim((string) ($input['deviceToken'] ?? ''));
    $subscription = $input['subscription'] ?? null;
    if ($token === '' || !is_array($subscription)) {
        admin_push_response(['ok' => false, 'error' => 'Token o suscripción inválidos.'], 422);
    }
    if (!mizo_admin_push_update_subscription($token, $subscription)) {
        admin_push_response(['ok' => false, 'error' => 'No se pudo guardar la suscripción push.'], 404);
    }
    admin_push_response(['ok' => true, 'message' => 'Suscripción push actualizada.']);
}

if ($action === 'unregister') {
    $token = trim((string) ($input['deviceToken'] ?? ''));
    $devices = array_values(array_filter(
        mizo_admin_push_read_json(mizo_admin_push_devices_file()),
        static fn(array $device): bool => ($device['token'] ?? '') !== $token
    ));
    mizo_admin_push_write_json(mizo_admin_push_devices_file(), $devices);
    admin_push_response(['ok' => true, 'message' => 'Dispositivo eliminado.']);
}

if ($action === 'test') {
    mizo_admin_notify_event(
        'Prueba Mizo Admin',
        'Si ves esta alerta, las notificaciones funcionan correctamente.',
        '/admin/#leads'
    );
    admin_push_response(['ok' => true, 'message' => 'Notificación de prueba enviada.']);
}

admin_push_response(['ok' => false, 'error' => 'Acción no reconocida.'], 400);
