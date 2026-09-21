<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Método no permitido.']);
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$honeypot = trim((string) ($input['website'] ?? ''));
if ($honeypot !== '') {
    respond(200, ['ok' => true]);
}

function clean(mixed $value, int $max = 500): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?: '');
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

$nombre = clean($input['nombre'] ?? '', 120);
$telefono = clean($input['telefono'] ?? '', 40);
$correo = clean($input['correo'] ?? '', 160);
$servicio = clean($input['servicio'] ?? '', 160);
$mensaje = clean($input['mensaje'] ?? '', 4000);

if ($nombre === '' || $telefono === '' || $correo === '' || $servicio === '' || $mensaje === '') {
    respond(422, ['ok' => false, 'error' => 'Completa todos los campos.']);
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['ok' => false, 'error' => 'Correo inválido.']);
}

$to = 'ventas@mizo.cl';
$subject = 'Nueva cotización Mizo: ' . $servicio;
$body = "Nombre: {$nombre}\nTeléfono: {$telefono}\nCorreo: {$correo}\nServicio: {$servicio}\n\nMensaje:\n{$mensaje}\n";
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: Mizo Web <ventas@mizo.cl>',
    'Reply-To: ' . $correo,
];

$sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
if (!$sent) {
    respond(500, ['ok' => false, 'error' => 'No se pudo enviar.']);
}

try {
    require_once dirname(__DIR__) . '/crm/src/Autoload.php';
    \MizoCrm\LeadIngest::fromContactForm($nombre, $telefono, $correo, $servicio, $mensaje);
} catch (Throwable) {
    // El correo ya salió; el CRM no debe bloquear el contacto.
}

respond(200, ['ok' => true]);
