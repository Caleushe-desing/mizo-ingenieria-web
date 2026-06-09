<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const MIZO_SALES_EMAIL = 'ventas@mizo.cl';

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_text($value, int $max = 500): string
{
    $text = trim((string)($value ?? ''));
    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?: '';
    return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['ok' => false, 'error' => 'Solicitud inválida.'], 400);
}

if (clean_text($payload['website'] ?? '') !== '') {
    json_response(['ok' => true, 'message' => 'Solicitud recibida.']);
}

$name = clean_text($payload['nombre'] ?? '', 120);
$company = clean_text($payload['empresa'] ?? '', 160);
$phone = clean_text($payload['telefono'] ?? '', 80);
$email = clean_text($payload['correo'] ?? '', 160);
$space = clean_text($payload['espacio'] ?? '', 160);
$specialty = clean_text($payload['especialidad'] ?? '', 200);
$projection = clean_text($payload['proyeccion'] ?? '', 200);
$dimension = clean_text($payload['dimension'] ?? '', 160);
$purpose = clean_text($payload['proposito'] ?? '', 200);
$products = clean_text($payload['productos'] ?? '', 4000);
$details = clean_text($payload['detalles'] ?? '', 4000);
$pack = clean_text($payload['pack'] ?? 'Configurador audiovisual Mizo', 200);

if ($name === '' || $company === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Completa nombre, empresa, teléfono y un correo válido.'], 422);
}

$subject = 'Nuevo lead configurador audiovisual Mizo';
$lines = [
    'Nuevo lead recibido desde mizo.cl',
    '',
    'Origen: ' . $pack,
    'Nombre: ' . $name,
    'Empresa: ' . $company,
    'Teléfono: ' . $phone,
    'Correo: ' . $email,
    '',
    'Entorno: ' . ($space !== '' ? $space : 'No indicado'),
    'Especialidad técnica: ' . ($specialty !== '' ? $specialty : 'No indicada'),
    'Proyección / Pantallas: ' . ($projection !== '' ? $projection : 'No indicado'),
    'Dimensión: ' . ($dimension !== '' ? $dimension : 'No indicada'),
    'Propósito: ' . ($purpose !== '' ? $purpose : 'No indicado'),
    '',
    'Equipamiento pre-calculado por IA:',
    $products !== '' ? $products : 'Sin desglose de productos.',
    '',
    'Detalle técnico:',
    $details !== '' ? $details : 'Sin detalle adicional.',
    '',
    'Compromiso comercial: contactar en menos de 2 horas.',
];

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: Mizo Web <' . MIZO_SALES_EMAIL . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
];

if (!@mail(MIZO_SALES_EMAIL, $subject, implode("\r\n", $lines), implode("\r\n", $headers))) {
    json_response(['ok' => false, 'error' => 'El servidor no confirmó el envío. Intenta nuevamente o escribe a ventas@mizo.cl.'], 500);
}

json_response([
    'ok' => true,
    'message' => 'Solicitud recibida. Un ingeniero de Mizo te contactará en menos de 2 horas.',
]);
