<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const QUOTE_PREFIX = 'M';
const DEFAULT_COUNTER = 1001;
const DEFAULT_FROM = 'ventas@mizo.cl';
const TAX_RATE = 0.19;

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_payload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function storage_root(): string
{
    $candidates = [
        dirname(__DIR__, 2) . '/data',
        dirname(__DIR__) . '/data',
        __DIR__ . '/../data',
    ];

    foreach ($candidates as $candidate) {
        if (is_dir($candidate) || @mkdir($candidate, 0775, true)) {
            if (is_writable($candidate)) {
                return $candidate;
            }
        }
    }

    json_response(['ok' => false, 'error' => 'No se pudo preparar el almacenamiento local de cotizaciones.'], 500);
}

function quotes_dir(): string
{
    $dir = storage_root() . '/quotes';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        json_response(['ok' => false, 'error' => 'No se pudo crear la carpeta de cotizaciones.'], 500);
    }
    return $dir;
}

function read_json(string $path, array $fallback): array
{
    if (!is_file($path)) {
        return $fallback;
    }

    $data = json_decode(file_get_contents($path) ?: '', true);
    return is_array($data) ? $data : $fallback;
}

function write_json(string $path, array $data): void
{
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $path)) {
        @unlink($tmp);
        json_response(['ok' => false, 'error' => 'No se pudo escribir el archivo local.'], 500);
    }
}

function config_path(): string
{
    return storage_root() . '/quotes-config.json';
}

function counter_path(): string
{
    return storage_root() . '/quotes-counter.json';
}

function default_config(): array
{
    return [
        'companyName' => 'Mizo',
        'phone' => '+56 9 0000 0000',
        'email' => DEFAULT_FROM,
        'address' => 'Puerto Montt, Chile',
        'website' => 'https://mizo.cl',
        'logo' => '/mizo-logo.svg',
        'defaultConditions' => [
            'Vigencia de la cotización: 5 días.',
            'Forma de pago: Transferencia electrónica.',
            'Tiempo de entrega: Inmediata según disponibilidad de stock.',
        ],
    ];
}

function quote_config(): array
{
    return array_replace_recursive(default_config(), read_json(config_path(), []));
}

function next_quote_number(): string
{
    $counter = read_json(counter_path(), ['next' => DEFAULT_COUNTER]);
    $next = max(DEFAULT_COUNTER, (int)($counter['next'] ?? DEFAULT_COUNTER));
    return QUOTE_PREFIX . $next;
}

function claim_quote_number(): string
{
    $path = counter_path();
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        json_response(['ok' => false, 'error' => 'No se pudo bloquear el correlativo.'], 500);
    }

    $raw = stream_get_contents($handle);
    $counter = json_decode($raw ?: '', true);
    $next = max(DEFAULT_COUNTER, (int)($counter['next'] ?? DEFAULT_COUNTER));
    $number = QUOTE_PREFIX . $next;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode(['next' => $next + 1], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $number;
}

function clean_text($value, int $max = 500): string
{
    $text = trim((string)($value ?? ''));
    return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
}

function money_value($value): int
{
    return max(0, (int)round((float)preg_replace('/[^\d.-]/', '', (string)($value ?? 0))));
}

function sanitize_items(array $items): array
{
    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = clean_text($item['name'] ?? '', 180);
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $unitPrice = money_value($item['unitPrice'] ?? 0);
        if ($name === '' || $unitPrice <= 0) {
            continue;
        }

        $clean[] = [
            'source' => clean_text($item['source'] ?? 'manual', 30),
            'sku' => clean_text($item['sku'] ?? '', 80),
            'name' => $name,
            'quantity' => $quantity,
            'unitPrice' => $unitPrice,
            'total' => $quantity * $unitPrice,
        ];
    }

    return $clean;
}

function sanitize_conditions(array $conditions): array
{
    $clean = [];
    foreach ($conditions as $condition) {
        $text = clean_text($condition, 250);
        if ($text !== '') {
            $clean[] = $text;
        }
    }
    return $clean;
}

function quote_totals(array $items): array
{
    $subtotal = array_sum(array_map(static fn(array $item): int => (int)$item['total'], $items));
    $tax = (int)round($subtotal * TAX_RATE);
    return [
        'subtotal' => $subtotal,
        'taxRate' => TAX_RATE,
        'tax' => $tax,
        'total' => $subtotal + $tax,
    ];
}

function clp(int $value): string
{
    return '$' . number_format($value, 0, ',', '.');
}

function item_reference(array $item): string
{
    $source = strtolower((string)($item['source'] ?? ''));
    $sku = trim((string)($item['sku'] ?? ''));
    if ($source === 'manual' || strtolower($sku) === 'manual') {
        return '';
    }
    return $sku;
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quote_asset_data_uri(string $path): string
{
    if (preg_match('/^data:/i', $path)) {
        return $path;
    }

    $assetPath = parse_url($path, PHP_URL_PATH);
    $assetPath = is_string($assetPath) && $assetPath !== '' ? $assetPath : $path;
    $localPath = dirname(__DIR__) . '/' . ltrim($assetPath, '/');

    if (!is_file($localPath)) {
        return $path;
    }

    $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
    $mime = $extension === 'svg' ? 'image/svg+xml' : ($extension === 'png' ? 'image/png' : 'image/jpeg');
    $contents = file_get_contents($localPath);
    return $contents === false ? $path : 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function quote_html(array $quote): string
{
    $company = is_array($quote['company'] ?? null) ? $quote['company'] : [];
    $client = is_array($quote['client'] ?? null) ? $quote['client'] : [];
    $totals = is_array($quote['totals'] ?? null) ? $quote['totals'] : quote_totals(is_array($quote['items'] ?? null) ? $quote['items'] : []);
    $quoteDate = clean_text($quote['date'] ?? $quote['createdAt'] ?? date('Y-m-d'), 40);
    $taxLabel = 'IVA ' . number_format(((float)($totals['taxRate'] ?? TAX_RATE)) * 100, 0, ',', '.') . '%';

    $itemsRows = '';
    foreach (is_array($quote['items'] ?? null) ? $quote['items'] : [] as $item) {
        $reference = item_reference($item);
        $itemsRows .= '<tr>'
            . '<td><strong>' . esc((string)($item['name'] ?? '')) . '</strong>' . ($reference !== '' ? '<br><span style="font-size:8pt;color:#94a3b8;">' . esc($reference) . '</span>' : '') . '</td>'
            . '<td style="text-align:right;">' . (int)($item['quantity'] ?? 0) . '</td>'
            . '<td style="text-align:right;">' . clp((int)($item['unitPrice'] ?? 0)) . '</td>'
            . '<td style="text-align:right;">' . clp((int)($item['total'] ?? 0)) . '</td>'
            . '</tr>';
    }

    $conditions = '';
    foreach (is_array($quote['conditions'] ?? null) ? $quote['conditions'] : [] as $condition) {
        $conditions .= '<li style="margin:0 0 4pt 0;">' . esc((string)$condition) . '</li>';
    }

    $logoHtml = '<table class="brand-lockup">
            <tr>
                <td class="brand-symbol">M</td>
                <td class="brand-word-cell">
                    <div class="brand-word">MIZO</div>
                    <div class="brand-rule"></div>
                </td>
            </tr>
        </table>';

    return '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            @page { 
                size: letter; 
                margin: 0; 
            }
            * { 
                box-sizing: border-box; 
                margin: 0; 
                padding: 0; 
            }
            body { 
                background-color: #ffffff; 
                color: #1e293b; 
                font-family: Helvetica, Arial, sans-serif; 
                font-size: 10pt; 
                line-height: 1.5; 
                padding: 42pt 38pt;
            }
            .header-table { width: 100%; margin-bottom: 30pt; border-collapse: collapse; }
            .brand-lockup { border-collapse: collapse; width: 205pt; }
            .brand-lockup td { vertical-align: middle; }
            .brand-symbol { width: 39pt; height: 39pt; background-color: #1877f2; color: #ffffff; font-size: 21pt; line-height: 39pt; font-weight: bold; text-align: center; border-radius: 8pt; }
            .brand-word-cell { padding-left: 10pt; }
            .brand-word { color: #0f172a; font-size: 31pt; line-height: 30pt; letter-spacing: 4pt; font-weight: bold; }
            .brand-rule { width: 128pt; height: 4pt; margin-top: 5pt; background-color: #1877f2; }
            .company-title { margin-top: 5pt; font-size: 8.5pt; color: #475569; font-style: italic; }
            
            .info-table { width: 100%; margin-bottom: 26pt; border-collapse: collapse; }
            .info-box { width: 48%; padding: 13pt; background-color: #ffffff; border: 1.4pt solid #94a3b8; vertical-align: top; }
            .info-title { font-size: 9pt; font-weight: bold; color: #0f172a; text-transform: uppercase; margin-bottom: 6pt; border-bottom: 1pt solid #0284c7; padding-bottom: 4pt; }
            
            .items-table { width: 100%; border-collapse: collapse; margin-top: 12pt; margin-bottom: 24pt; }
            .items-table th { background-color: #0f172a; color: #ffffff; padding: 10pt 10pt; font-weight: bold; font-size: 8.5pt; text-align: left; border-bottom: 1.4pt solid #0f172a; text-transform: uppercase; }
            .items-table td { padding: 12pt 10pt; border-bottom: 1.2pt solid #94a3b8; font-size: 9.5pt; color: #334155; vertical-align: top; }
            
            .bottom-table { width: 100%; border-collapse: collapse; margin-top: 18pt; }
            .conditions-td { width: 55%; vertical-align: top; font-size: 8.5pt; color: #64748b; padding-right: 15pt; }
            .totals-td { width: 45%; vertical-align: top; }
            
            .totals-table { width: 100%; border-collapse: collapse; background-color: #ffffff; border-top: 2.2pt solid #0f172a; }
            .totals-table td { padding: 8pt 0 8pt 12pt; font-size: 9.5pt; border-bottom: 1.2pt solid #94a3b8; }
            .totals-table td:last-child { text-align: right; font-weight: bold; white-space: nowrap; }
            .totals-table .total-row { font-size: 12pt; font-weight: bold; color: #0f172a; background-color: #e2e8f0; }
            .footer-note { margin-top: 58pt; padding-top: 10pt; border-top: 1pt solid #cbd5e1; color: #64748b; font-size: 8pt; text-align: center; }
        </style>
    </head>
    <body>
        <table class="header-table">
            <tr>
                <td style="vertical-align: top;">
                    ' . $logoHtml . '
                    <div class="company-title">Ingeniería audiovisual e instalaciones profesionales</div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div style="font-size: 14pt; font-weight: bold; color: #0f172a; letter-spacing: 1px;">PRESUPUESTO</div>
                    <div style="font-size: 12pt; font-weight: bold; color: #0284c7; margin-top: 3pt;">' . esc((string)($quote['number'] ?? '')) . '</div>
                    <div style="font-size: 8.5pt; color: #64748b; margin-top: 2pt;">Fecha: ' . esc($quoteDate) . '</div>
                </td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <td class="info-box">
                    <div class="info-title">Cliente</div>
                    <strong>' . esc((string)($client['name'] ?? 'Cliente sin nombre')) . '</strong><br>
                    ' . esc((string)($client['email'] ?? '')) . '<br>
                    ' . esc((string)($client['phone'] ?? '')) . '<br>
                    ' . esc((string)($client['address'] ?? '')) . '
                </td>
                <td style="width:4%;"></td>
                <td class="info-box">
                    <div class="info-title">Mizo</div>
                    <strong>' . esc((string)($company['companyName'] ?? 'Mizo')) . '</strong><br>
                    ' . esc((string)($company['address'] ?? '')) . '<br>
                    ' . esc((string)($company['phone'] ?? '')) . '<br>
                    ' . esc((string)($company['email'] ?? DEFAULT_FROM)) . '
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Ítem</th>
                    <th style="text-align:right;">Cant.</th>
                    <th style="text-align:right;">Precio unit.</th>
                    <th style="text-align:right;">Neto</th>
                </tr>
            </thead>
            <tbody>' . $itemsRows . '</tbody>
        </table>

        <table class="bottom-table">
            <tr>
                <td class="conditions-td">
                    <strong style="color:#0f172a;">Condiciones comerciales</strong>
                    <ul style="margin-top:6pt; padding-left:12pt;">' . $conditions . '</ul>
                </td>
                <td class="totals-td">
                    <table class="totals-table">
                        <tr>
                            <td>Subtotal neto</td>
                            <td style="text-align:right;">' . clp((int)($totals['subtotal'] ?? 0)) . '</td>
                        </tr>
                        <tr>
                            <td>' . esc($taxLabel) . '</td>
                            <td style="text-align:right;">' . clp((int)($totals['tax'] ?? 0)) . '</td>
                        </tr>
                        <tr class="total-row">
                            <td>TOTAL A PAGAR</td>
                            <td style="text-align:right;">' . clp((int)($totals['total'] ?? 0)) . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <div class="footer-note">
            Esta cotización fue desarrollada y emitida por el Sistema de Gestión de Mizo para la administración comercial y seguimiento interno de proyectos.
        </div>
    </body>
    </html>';
}

function ensure_dompdf(): void
{
    foreach ([__DIR__ . '/../../vendor/autoload.php', __DIR__ . '/../vendor/autoload.php'] as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            break;
        }
    }

    if (!class_exists('\\Dompdf\\Dompdf')) {
        json_response(['ok' => false, 'error' => 'DOMPDF no está instalado en el servidor. Ejecuta composer install antes de generar PDFs.'], 500);
    }
}

function quote_pdf_content(array $quote): string
{
    ensure_dompdf();

    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml(quote_html($quote), 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function quote_path(string $number, string $extension): string
{
    $safe = preg_replace('/[^A-Z0-9_-]/i', '', $number);
    return quotes_dir() . '/' . $safe . '.' . $extension;
}

function purge_quote_render_cache(): void
{
    foreach (['pdf', 'html'] as $extension) {
        foreach (glob(quotes_dir() . '/*.' . $extension) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

function quote_exists(string $number): bool
{
    return is_file(quote_path($number, 'json'));
}

function quote_base_number(string $number): string
{
    $safe = preg_replace('/[^A-Z0-9_-]/i', '', $number);
    return preg_replace('/-Rev\d+$/i', '', $safe);
}

function quote_revision_number(string $number): int
{
    return preg_match('/-Rev(\d+)$/i', $number, $matches) ? max(0, (int)$matches[1]) : 0;
}

function next_revision_number(string $baseNumber): string
{
    $revision = 1;
    do {
        $number = $baseNumber . '-Rev' . $revision;
        $revision++;
    } while (quote_exists($number));

    return $number;
}

function resolve_quote_identity(array $payload): array
{
    $requested = clean_text($payload['updateOf'] ?? $payload['number'] ?? '', 40);
    if ($requested !== '') {
        $baseNumber = quote_base_number($requested);
        if ($baseNumber !== '' && (quote_exists($requested) || quote_exists($baseNumber))) {
            $number = next_revision_number($baseNumber);
            return [
                'number' => $number,
                'baseNumber' => $baseNumber,
                'revision' => quote_revision_number($number),
                'previousNumber' => $requested,
            ];
        }
    }

    $number = claim_quote_number();
    return [
        'number' => $number,
        'baseNumber' => $number,
        'revision' => 0,
        'previousNumber' => '',
    ];
}

function normalize_quote(array $quote): array
{
    if (isset($quote['items']) && is_array($quote['items'])) {
        $quote['totals'] = quote_totals($quote['items']);
    }
    if (($quote['number'] ?? '') !== '') {
        $quote['baseNumber'] = $quote['baseNumber'] ?? quote_base_number((string)$quote['number']);
        $quote['revision'] = $quote['revision'] ?? quote_revision_number((string)$quote['number']);
        $quote['pdfUrl'] = '/api/quotes.php?action=download&number=' . rawurlencode((string)$quote['number']);
        $quote['htmlPreviewUrl'] = '/api/quotes.php?action=html&number=' . rawurlencode((string)$quote['number']);
    }
    return $quote;
}

function build_quote(array $payload): array
{
    $items = sanitize_items($payload['items'] ?? []);
    if (!$items) {
        json_response(['ok' => false, 'error' => 'Agrega al menos un ítem con precio válido.'], 422);
    }

    $config = quote_config();
    $company = array_replace($config, is_array($payload['company'] ?? null) ? $payload['company'] : []);
    $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];
    $conditions = sanitize_conditions($payload['conditions'] ?? $config['defaultConditions']);
    if (!$conditions) {
        $conditions = $config['defaultConditions'];
    }

    $identity = resolve_quote_identity($payload);
    return [
        'number' => $identity['number'],
        'baseNumber' => $identity['baseNumber'],
        'revision' => $identity['revision'],
        'previousNumber' => $identity['previousNumber'],
        'createdAt' => date('Y-m-d H:i:s'),
        'company' => [
            'companyName' => clean_text($company['companyName'] ?? 'Mizo', 120),
            'phone' => clean_text($company['phone'] ?? '', 80),
            'email' => clean_text($company['email'] ?? DEFAULT_FROM, 120),
            'address' => clean_text($company['address'] ?? '', 180),
            'website' => clean_text($company['website'] ?? 'https://mizo.cl', 180),
            'logo' => clean_text($company['logo'] ?? '', 3000),
        ],
        'client' => [
            'name' => clean_text($client['name'] ?? 'Cliente sin nombre', 160),
            'email' => clean_text($client['email'] ?? '', 160),
            'phone' => clean_text($client['phone'] ?? '', 80),
            'rut' => clean_text($client['rut'] ?? '', 40),
            'address' => clean_text($client['address'] ?? '', 180),
        ],
        'items' => $items,
        'conditions' => $conditions,
        'notes' => clean_text($payload['notes'] ?? '', 1000),
        'totals' => quote_totals($items),
    ];
}

function save_quote(array $quote): array
{
    $quote = normalize_quote($quote);
    $jsonPath = quote_path($quote['number'], 'json');
    $htmlPath = quote_path($quote['number'], 'html');
    $pdfPath = quote_path($quote['number'], 'pdf');

    $quote['pdfUrl'] = '/api/quotes.php?action=download&number=' . rawurlencode($quote['number']);
    $quote['htmlPreviewUrl'] = '/api/quotes.php?action=html&number=' . rawurlencode($quote['number']);

    write_json($jsonPath, $quote);
    file_put_contents($htmlPath, quote_html($quote), LOCK_EX);
    file_put_contents($pdfPath, quote_pdf_content($quote), LOCK_EX);

    return $quote;
}

function load_quote(string $number): ?array
{
    $path = quote_path($number, 'json');
    return is_file($path) ? normalize_quote(read_json($path, [])) : null;
}

function quote_recipients(string $value): array
{
    $parts = preg_split('/[;,]+/', $value) ?: [];
    $emails = [];
    $invalid = [];

    foreach ($parts as $part) {
        $email = trim($part);
        if ($email === '') {
            continue;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $invalid[] = $email;
            continue;
        }
        $emails[strtolower($email)] = $email;
    }

    if ($invalid) {
        json_response(['ok' => false, 'error' => 'Email de destino inválido: ' . implode(', ', $invalid)], 422);
    }

    return array_values($emails);
}

function send_quote(array $payload): void
{
    $number = clean_text($payload['number'] ?? '', 30);
    $quote = load_quote($number);
    if (!$quote) {
        json_response(['ok' => false, 'error' => 'Cotización no encontrada.'], 404);
    }

    $recipients = quote_recipients(clean_text($payload['to'] ?? $quote['client']['email'] ?? '', 1000));
    if (!$recipients) {
        json_response(['ok' => false, 'error' => 'Ingresa al menos un email de destino válido.'], 422);
    }

    $pdfPath = quote_path($number, 'pdf');
    file_put_contents($pdfPath, quote_pdf_content($quote), LOCK_EX);

    $subject = 'Cotización Mizo ' . $number;
    $body = clean_text($payload['message'] ?? "Hola,\n\nAdjuntamos la cotización solicitada.\n\nSaludos,\nMizo", 2000);
    $attachment = chunk_split(base64_encode(file_get_contents($pdfPath) ?: ''));
    $sent = [];
    $failed = [];

    foreach ($recipients as $to) {
        $boundary = 'mizo_' . bin2hex(random_bytes(12));
        $headers = [
            'From: Mizo Ventas <' . DEFAULT_FROM . '>',
            'Reply-To: ' . DEFAULT_FROM,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $body . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/pdf; name=\"{$number}.pdf\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=\"{$number}.pdf\"\r\n\r\n"
            . $attachment . "\r\n"
            . "--{$boundary}--";

        if (@mail($to, $subject, $message, implode("\r\n", $headers))) {
            $sent[] = $to;
        } else {
            $failed[] = $to;
        }
    }

    $ok = count($sent) > 0 && count($failed) === 0;
    json_response([
        'ok' => $ok,
        'sent' => $ok,
        'recipients' => $sent,
        'failedRecipients' => $failed,
        'error' => $ok ? null : 'El servidor no confirmó el envío a: ' . implode(', ', $failed),
    ], $sent ? 200 : 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? null;
$payload = $method === 'POST' ? request_payload() : [];
$action = $action ?: ($payload['action'] ?? 'bootstrap');

if ($method === 'GET' && $action === 'download') {
    $number = clean_text($_GET['number'] ?? '', 30);
    $path = quote_path($number, 'pdf');
    $quote = load_quote($number);
    if ($quote) {
        file_put_contents($path, quote_pdf_content($quote), LOCK_EX);
    }
    if (!is_file($path)) {
        json_response(['ok' => false, 'error' => 'PDF no encontrado.'], 404);
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

if ($method === 'GET' && $action === 'html') {
    $number = clean_text($_GET['number'] ?? '', 30);
    $path = quote_path($number, 'html');
    $quote = load_quote($number);
    if ($quote) {
        file_put_contents($path, quote_html($quote), LOCK_EX);
    }
    if (!is_file($path)) {
        json_response(['ok' => false, 'error' => 'Vista HTML no encontrada.'], 404);
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile($path);
    exit;
}

if ($action === 'bootstrap') {
    purge_quote_render_cache();
    json_response([
        'ok' => true,
        'nextNumber' => next_quote_number(),
        'config' => quote_config(),
    ]);
}

if ($action === 'save_config') {
    $config = array_replace(default_config(), is_array($payload['config'] ?? null) ? $payload['config'] : []);
    $config['defaultConditions'] = sanitize_conditions($config['defaultConditions'] ?? []);
    write_json(config_path(), $config);
    json_response(['ok' => true, 'config' => quote_config()]);
}

if ($action === 'create_quote') {
    $quote = save_quote(build_quote($payload));
    json_response(['ok' => true, 'quote' => $quote, 'nextNumber' => next_quote_number()]);
}

if ($action === 'list') {
    purge_quote_render_cache();
    $quotes = [];
    foreach (glob(quotes_dir() . '/*.json') ?: [] as $file) {
        $quote = normalize_quote(read_json($file, []));
        if ($quote) {
            $quotes[] = [
                'number' => $quote['number'] ?? basename($file, '.json'),
                'createdAt' => $quote['createdAt'] ?? '',
                'client' => $quote['client']['name'] ?? '',
                'total' => $quote['totals']['total'] ?? 0,
                'pdfUrl' => $quote['pdfUrl'] ?? '',
            ];
        }
    }
    usort($quotes, static fn(array $a, array $b): int => strcmp((string)$b['createdAt'], (string)$a['createdAt']));
    json_response(['ok' => true, 'quotes' => array_slice($quotes, 0, 100)]);
}

if ($action === 'get') {
    $quote = load_quote(clean_text($payload['number'] ?? $_GET['number'] ?? '', 30));
    json_response($quote ? ['ok' => true, 'quote' => $quote] : ['ok' => false, 'error' => 'Cotización no encontrada.'], $quote ? 200 : 404);
}

if ($action === 'send_quote') {
    send_quote($payload);
}

json_response(['ok' => false, 'error' => 'Acción de cotizaciones no soportada.'], 400);
