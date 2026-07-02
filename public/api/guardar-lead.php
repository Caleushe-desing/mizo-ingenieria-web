<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog-db.php';
require_once __DIR__ . '/_admin-push.php';
require_once __DIR__ . '/generar-pdf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const MIZO_LEADS_EMAIL = 'ventas@mizo.cl';

function lead_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lead_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST ?: $_GET;
}

function lead_clean($value, int $max = 1000): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?: '';
    $text = preg_replace('/\s+/u', ' ', $text) ?: '';
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

function lead_multiline($value, int $max = 12000): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $text) ?: '';
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

function lead_storage_dir(): string
{
    return __DIR__ . '/../mizo-data';
}

function lead_storage_file(): string
{
    return lead_storage_dir() . '/leads.json';
}

function ensure_lead_storage(): array
{
    $dir = lead_storage_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (is_dir($dir) && !is_file(lead_storage_file())) {
        @file_put_contents(lead_storage_file(), json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, LOCK_EX);
    }
    return [
        'dir' => $dir,
        'file' => lead_storage_file(),
        'dirExists' => is_dir($dir),
        'dirWritable' => is_dir($dir) && is_writable($dir),
        'fileWritable' => is_file(lead_storage_file()) && is_writable(lead_storage_file()),
    ];
}

function admin_password_ok(?string $password): bool
{
    $expected = mizo_env(['ADMIN_PASSWORD', 'MIZO_ADMIN_PASSWORD'], 'mizo2026') ?? 'mizo2026';
    return is_string($password) && hash_equals($expected, $password);
}

function normalize_lead(array $payload): array
{
    $name = lead_clean($payload['nombre'] ?? '', 140);
    $company = lead_clean($payload['empresa'] ?? '', 180);
    $phone = lead_clean($payload['telefono'] ?? '', 90);
    $email = lead_clean($payload['correo'] ?? '', 180);
    $details = lead_multiline($payload['detalles'] ?? '', 12000);
    $products = lead_multiline($payload['productos'] ?? '', 12000);
    $report = lead_multiline($payload['reporte'] ?? '', 60000);

    return [
        'id' => 'lead_' . gmdate('Ymd_His') . '_' . substr(sha1($email . $phone . microtime(true)), 0, 10),
        'createdAt' => gmdate('c'),
        'status' => 'nuevo',
        'source' => lead_clean($payload['pack'] ?? 'Asistente de Diagnóstico Técnico Mizo', 220),
        'nombre' => $name,
        'empresa' => $company,
        'telefono' => $phone,
        'correo' => $email,
        'espacio' => lead_clean($payload['espacio'] ?? '', 180),
        'especialidad' => lead_clean($payload['especialidad'] ?? '', 220),
        'dimension' => lead_clean($payload['dimension'] ?? '', 260),
        'proposito' => lead_clean($payload['proposito'] ?? '', 260),
        'productos' => $products,
        'detalles' => $details,
        'reporte' => $report,
        'resumenTecnico' => trim($details . "\n\n" . $products),
        'mailAdminOk' => false,
        'mailClientOk' => false,
    ];
}

function validate_lead(array $lead): ?string
{
    if ($lead['nombre'] === '' || $lead['empresa'] === '' || $lead['telefono'] === '') {
        return 'Completa nombre, empresa y teléfono.';
    }
    if (!filter_var($lead['correo'], FILTER_VALIDATE_EMAIL)) {
        return 'Ingresa un correo válido.';
    }
    return null;
}

function ensure_leads_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `mizo_leads` (
            `id` VARCHAR(80) NOT NULL,
            `created_at` DATETIME NOT NULL,
            `status` VARCHAR(40) NOT NULL DEFAULT 'nuevo',
            `nombre` VARCHAR(140) NOT NULL,
            `empresa` VARCHAR(180) NOT NULL,
            `telefono` VARCHAR(90) NOT NULL,
            `correo` VARCHAR(180) NOT NULL,
            `espacio` VARCHAR(180) NOT NULL DEFAULT '',
            `especialidad` VARCHAR(220) NOT NULL DEFAULT '',
            `dimension_text` VARCHAR(260) NOT NULL DEFAULT '',
            `payload_json` MEDIUMTEXT NOT NULL,
            `mail_admin_ok` TINYINT(1) NOT NULL DEFAULT 0,
            `mail_client_ok` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_correo` (`correo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function save_lead_mysql(array $lead): bool
{
    $pdo = mizo_pdo();
    if (!$pdo) {
        return false;
    }
    ensure_leads_table($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO `mizo_leads` (
            id, created_at, status, nombre, empresa, telefono, correo, espacio,
            especialidad, dimension_text, payload_json, mail_admin_ok, mail_client_ok
        ) VALUES (
            :id, :created_at, :status, :nombre, :empresa, :telefono, :correo, :espacio,
            :especialidad, :dimension_text, :payload_json, :mail_admin_ok, :mail_client_ok
        )
    ");
    $createdAt = str_replace('T', ' ', substr($lead['createdAt'], 0, 19));
    return $stmt->execute([
        ':id' => $lead['id'],
        ':created_at' => $createdAt,
        ':status' => $lead['status'],
        ':nombre' => $lead['nombre'],
        ':empresa' => $lead['empresa'],
        ':telefono' => $lead['telefono'],
        ':correo' => $lead['correo'],
        ':espacio' => $lead['espacio'],
        ':especialidad' => $lead['especialidad'],
        ':dimension_text' => $lead['dimension'],
        ':payload_json' => json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':mail_admin_ok' => $lead['mailAdminOk'] ? 1 : 0,
        ':mail_client_ok' => $lead['mailClientOk'] ? 1 : 0,
    ]);
}

function save_lead_json(array $lead): bool
{
    $storage = ensure_lead_storage();
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
        array_unshift($items, $lead);
        ftruncate($fp, 0);
        rewind($fp);
        $ok = fwrite($fp, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL) !== false;
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $ok;
}

function update_json_mail_status(string $id, bool $adminOk, bool $clientOk): void
{
    $file = lead_storage_file();
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
            $item['mailClientOk'] = $clientOk;
            break;
        }
    }
    file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
}

function update_mysql_mail_status(string $id, bool $adminOk, bool $clientOk): void
{
    $pdo = mizo_pdo();
    if (!$pdo) {
        return;
    }
    try {
        $stmt = $pdo->prepare("UPDATE `mizo_leads` SET mail_admin_ok = :admin, mail_client_ok = :client WHERE id = :id");
        $stmt->execute([':admin' => $adminOk ? 1 : 0, ':client' => $clientOk ? 1 : 0, ':id' => $id]);
    } catch (Throwable $error) {
        return;
    }
}

function lead_closing_message(): string
{
    return defined('MIZO_PDF_CLOSING_MESSAGE')
        ? MIZO_PDF_CLOSING_MESSAGE
        : 'Hemos recibido tu solicitud de diagnóstico. Un especialista de Mizo revisará los detalles de tu proyecto y te contactará para optimizar tu propuesta de ingeniería y entregarte una solución personalizada ajustada a tus necesidades operativas y presupuestarias.';
}

function lead_encode_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function send_lead_email(string $to, string $subject, string $body, ?array $attachment = null, string $replyTo = ''): bool
{
    $eol = "\r\n";
    $boundary = 'mizo_' . bin2hex(random_bytes(10));
    $hasAttachment = is_array($attachment) && !empty($attachment['content']);

    $headers = [
        'MIME-Version: 1.0',
        'From: Mizo Web <' . MIZO_LEADS_EMAIL . '>',
    ];
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    if (!$hasAttachment) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        return @mail($to, lead_encode_subject($subject), $body, implode($eol, $headers));
    }

    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $message = '--' . $boundary . $eol;
    $message .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
    $message .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
    $message .= $body . $eol . $eol;

    $filename = (string) ($attachment['filename'] ?? 'Informe-Mizo.pdf');
    $mime = (string) ($attachment['mime'] ?? 'application/pdf');
    $encoded = chunk_split(base64_encode((string) $attachment['content']));
    $message .= '--' . $boundary . $eol;
    $message .= 'Content-Type: ' . $mime . '; name="' . $filename . '"' . $eol;
    $message .= 'Content-Transfer-Encoding: base64' . $eol;
    $message .= 'Content-Disposition: attachment; filename="' . $filename . '"' . $eol . $eol;
    $message .= $encoded . $eol;
    $message .= '--' . $boundary . '--';

    return @mail($to, lead_encode_subject($subject), $message, implode($eol, $headers));
}

function send_lead_emails(array $lead, ?array $pdf = null): array
{
    $closing = lead_closing_message();
    $attachment = (is_array($pdf) && !empty($pdf['content'])) ? [
        'filename' => $pdf['filename'] ?? 'Informe-Mizo.pdf',
        'mime' => $pdf['mime'] ?? 'application/pdf',
        'content' => $pdf['content'],
    ] : null;
    $attachmentNote = $attachment
        ? (($pdf['mode'] ?? '') === 'native'
            ? 'Adjuntamos el informe PDF profesional con las métricas de ingeniería y los grupos de equipamiento.'
            : 'Adjuntamos el informe técnico del diagnóstico.')
        : '';

    $adminLines = [
        'Nuevo diagnóstico técnico recibido desde mizo.cl',
        '',
        'Nombre: ' . $lead['nombre'],
        'Empresa: ' . $lead['empresa'],
        'Teléfono: ' . $lead['telefono'],
        'Correo: ' . $lead['correo'],
        '',
        'Entorno: ' . ($lead['espacio'] ?: 'No indicado'),
        'Especialidad: ' . ($lead['especialidad'] ?: 'No indicada'),
        'Dimensión: ' . ($lead['dimension'] ?: 'No indicada'),
        'Origen: ' . $lead['source'],
        '',
        'Resumen técnico:',
        $lead['resumenTecnico'] ?: 'Sin resumen técnico.',
    ];
    if ($attachmentNote !== '') {
        $adminLines[] = '';
        $adminLines[] = $attachmentNote;
    }

    $clientLines = [
        'Estimado/a ' . $lead['nombre'] . ',',
        '',
        $closing,
        '',
    ];
    if ($attachmentNote !== '') {
        $clientLines[] = $attachmentNote;
        $clientLines[] = '';
    }
    $clientLines = array_merge($clientLines, [
        'Resumen de tu diagnóstico:',
        'Entorno: ' . ($lead['espacio'] ?: 'No indicado'),
        'Especialidad: ' . ($lead['especialidad'] ?: 'No indicada'),
        'Dimensión: ' . ($lead['dimension'] ?: 'No indicada'),
        '',
        'Gracias por confiar en Mizo.',
        'Equipo de Ingeniería Audiovisual Mizo',
        'ventas@mizo.cl · www.mizo.cl',
    ]);

    $replyTo = $lead['nombre'] . ' <' . $lead['correo'] . '>';
    $adminOk = send_lead_email(MIZO_LEADS_EMAIL, 'Nuevo lead · Asistente de Diagnóstico Técnico Mizo', implode("\r\n", $adminLines), $attachment, $replyTo);
    $clientOk = send_lead_email($lead['correo'], 'Recibimos tu diagnóstico técnico · Mizo Ingeniería', implode("\r\n", $clientLines), $attachment);

    return ['admin' => (bool) $adminOk, 'client' => (bool) $clientOk];
}

function list_leads_mysql(): ?array
{
    $pdo = mizo_pdo();
    if (!$pdo) {
        return null;
    }
    try {
        ensure_leads_table($pdo);
        $rows = $pdo->query("SELECT payload_json FROM `mizo_leads` ORDER BY created_at DESC LIMIT 300")->fetchAll();
        $leads = [];
        foreach ($rows as $row) {
            $lead = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (is_array($lead)) {
                $leads[] = $lead;
            }
        }
        return $leads;
    } catch (Throwable $error) {
        return null;
    }
}

function list_leads_json(): array
{
    ensure_lead_storage();
    $items = json_decode((string) @file_get_contents(lead_storage_file()), true);
    return is_array($items) ? $items : [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = lead_input();

if ($method === 'GET' && (($input['action'] ?? '') === 'status')) {
    lead_response(['success' => true, 'ok' => true, 'storage' => ensure_lead_storage(), 'mysql' => (bool) mizo_pdo()]);
}

if (($input['action'] ?? '') === 'list') {
    if (!admin_password_ok($input['password'] ?? null)) {
        lead_response(['success' => false, 'ok' => false, 'error' => 'Clave incorrecta.'], 401);
    }
    $mysqlLeads = list_leads_mysql();
    $leads = is_array($mysqlLeads) ? $mysqlLeads : list_leads_json();
    lead_response(['success' => true, 'ok' => true, 'source' => is_array($mysqlLeads) ? 'mysql' : 'json', 'leads' => $leads, 'storage' => ensure_lead_storage()]);
}

if ($method !== 'POST') {
    lead_response(['success' => false, 'ok' => false, 'error' => 'Método no permitido.'], 405);
}

if (lead_clean($input['website'] ?? '') !== '') {
    lead_response(['success' => true, 'ok' => true, 'message' => 'Diagnóstico recibido.']);
}

$lead = normalize_lead($input);
$validationError = validate_lead($lead);
if ($validationError) {
    lead_response(['success' => false, 'ok' => false, 'error' => $validationError], 422);
}

$savedTo = null;
try {
    if (save_lead_mysql($lead)) {
        $savedTo = 'mysql';
    } elseif (save_lead_json($lead)) {
        $savedTo = 'json';
    }
} catch (Throwable $error) {
    if (save_lead_json($lead)) {
        $savedTo = 'json';
    }
}

if (!$savedTo) {
    lead_response([
        'success' => false,
        'ok' => false,
        'error' => 'No se pudo guardar el diagnóstico. Revisa permisos de escritura de mizo-data/leads.json.',
        'storage' => ensure_lead_storage(),
    ], 500);
}

$pdf = null;
try {
    if (function_exists('mizo_generar_pdf_informe')) {
        $pdf = mizo_generar_pdf_informe($lead);
    }
} catch (Throwable $error) {
    $pdf = null;
}

$mail = send_lead_emails($lead, $pdf);
if ($savedTo === 'mysql') {
    update_mysql_mail_status($lead['id'], $mail['admin'], $mail['client']);
} else {
    update_json_mail_status($lead['id'], $mail['admin'], $mail['client']);
}

try {
    mizo_admin_notify_event(
        'Nuevo diagnóstico técnico',
        ($lead['nombre'] ?: 'Cliente') . ' · ' . ($lead['empresa'] ?: 'Sin empresa'),
        '/admin/#leads'
    );
} catch (Throwable $error) {
    /* ignore push errors */
}

lead_response([
    'success' => true,
    'ok' => true,
    'id' => $lead['id'],
    'savedTo' => $savedTo,
    'mail' => $mail,
    'pdf' => is_array($pdf) ? ['ok' => (bool) ($pdf['ok'] ?? false), 'mode' => $pdf['mode'] ?? 'none'] : ['ok' => false, 'mode' => 'none'],
    'message' => $mail['admin'] || $mail['client']
        ? 'Diagnóstico guardado y notificación enviada. Un ingeniero de Mizo te contactará en breve.'
        : 'Diagnóstico guardado. Un ingeniero de Mizo te contactará en breve.',
]);
