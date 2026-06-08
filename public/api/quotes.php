<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const QUOTE_PREFIX = 'M';
const DEFAULT_COUNTER = 1001;
const DEFAULT_FROM = 'ventas@mizo.cl';

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
        'logo' => '/mizo-logo.png',
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
    return [
        'subtotal' => $subtotal,
        'tax' => 0,
        'total' => $subtotal,
    ];
}

function clp(int $value): string
{
    return '$' . number_format($value, 0, ',', '.');
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quote_html(array $quote): string
{
    $company = $quote['company'];
    $client = $quote['client'];
    $rows = '';
    foreach ($quote['items'] as $item) {
        $rows .= '<tr>'
            . '<td><strong>' . esc($item['name']) . '</strong><br><span>' . esc($item['sku'] ?: 'Manual') . '</span></td>'
            . '<td class="right">' . (int)$item['quantity'] . '</td>'
            . '<td class="right">' . clp((int)$item['unitPrice']) . '</td>'
            . '<td class="right">' . clp((int)$item['total']) . '</td>'
            . '</tr>';
    }

    $conditions = '';
    foreach ($quote['conditions'] as $condition) {
        $conditions .= '<li>' . esc($condition) . '</li>';
    }

    $logo = clean_text($company['logo'] ?? '', 3000);
    $logoHtml = $logo !== '' ? '<img src="' . esc($logo) . '" alt="' . esc($company['companyName']) . '">' : '<div class="logo-fallback">Mizo</div>';

    return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><title>' . esc($quote['number']) . '</title>'
        . '<style>
            body{font-family:Arial,Helvetica,sans-serif;color:#111827;margin:0;background:#f3f4f6}
            .page{max-width:920px;margin:0 auto;background:#fff;padding:42px}
            .top{display:flex;justify-content:space-between;gap:24px;border-bottom:4px solid #1877f2;padding-bottom:24px}
            img{max-width:150px;max-height:70px;object-fit:contain}.logo-fallback{font-size:32px;font-weight:900;color:#1877f2}
            h1{margin:0;font-size:30px}.muted{color:#6b7280;font-size:13px;line-height:1.5}.box{border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-top:22px}
            .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}table{width:100%;border-collapse:collapse;margin-top:18px;font-size:13px}
            th{background:#111827;color:#fff;text-align:left;padding:12px}td{border-bottom:1px solid #e5e7eb;padding:12px;vertical-align:top}
            td span{color:#6b7280;font-size:11px}.right{text-align:right}.total{font-size:22px;font-weight:900;color:#1877f2}
            ul{margin:10px 0 0;padding-left:18px}.footer{margin-top:28px;font-size:12px;color:#6b7280;text-align:center}
        </style></head><body><main class="page">'
        . '<section class="top"><div>' . $logoHtml . '<p class="muted">' . esc($company['companyName']) . '<br>' . esc($company['address']) . '<br>' . esc($company['phone']) . ' · ' . esc($company['email']) . '</p></div>'
        . '<div style="text-align:right"><h1>Presupuesto: ' . esc($quote['number']) . '</h1><p class="muted">Fecha: ' . esc($quote['createdAt']) . '<br>Emitido por ventas@mizo.cl</p></div></section>'
        . '<section class="grid"><div class="box"><strong>Cliente</strong><p class="muted">' . esc($client['name']) . '<br>' . esc($client['email']) . '<br>' . esc($client['phone']) . '<br>' . esc($client['address']) . '</p></div>'
        . '<div class="box"><strong>Resumen</strong><p class="muted">Subtotal</p><p class="total">' . clp((int)$quote['totals']['total']) . '</p></div></section>'
        . '<section class="box"><strong>Detalle de productos y servicios</strong><table><thead><tr><th>Ítem</th><th class="right">Cant.</th><th class="right">Precio unit.</th><th class="right">Total</th></tr></thead><tbody>' . $rows . '</tbody></table></section>'
        . '<section class="box"><strong>Condiciones comerciales</strong><ul>' . $conditions . '</ul></section>'
        . '<p class="footer">Cotización generada automáticamente por Mizo. ' . esc($company['website']) . '</p>'
        . '</main></body></html>';
}

function pdf_escape(string $value): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value) ?: $value);
}

function quote_pdf_content(array $quote): string
{
    $lines = [
        'Presupuesto: ' . $quote['number'],
        'Fecha: ' . $quote['createdAt'],
        $quote['company']['companyName'] . ' | ' . $quote['company']['email'] . ' | ' . $quote['company']['phone'],
        'Cliente: ' . $quote['client']['name'],
        'Email: ' . $quote['client']['email'],
        ' ',
        'Detalle:',
    ];

    foreach ($quote['items'] as $item) {
        $lines[] = sprintf('%s | %s | x%d | %s | Total %s', $item['sku'] ?: 'Manual', $item['name'], $item['quantity'], clp((int)$item['unitPrice']), clp((int)$item['total']));
    }

    $lines[] = ' ';
    $lines[] = 'Total: ' . clp((int)$quote['totals']['total']);
    $lines[] = ' ';
    $lines[] = 'Condiciones comerciales:';
    foreach ($quote['conditions'] as $condition) {
        $lines[] = '- ' . $condition;
    }

    $stream = "BT\n/F1 12 Tf\n50 790 Td\n";
    foreach ($lines as $index => $line) {
        if ($index > 0) {
            $stream .= "0 -18 Td\n";
        }
        $stream .= '(' . pdf_escape($line) . ") Tj\n";
    }
    $stream .= "ET";

    $objects = [
        "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
        "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n",
        "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj\n",
        "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
        "5 0 obj << /Length " . strlen($stream) . " >> stream\n" . $stream . "\nendstream endobj\n",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    return $pdf;
}

function quote_path(string $number, string $extension): string
{
    $safe = preg_replace('/[^A-Z0-9_-]/i', '', $number);
    return quotes_dir() . '/' . $safe . '.' . $extension;
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

    $number = claim_quote_number();
    return [
        'number' => $number,
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
    return is_file($path) ? read_json($path, []) : null;
}

function send_quote(array $payload): void
{
    $number = clean_text($payload['number'] ?? '', 30);
    $quote = load_quote($number);
    if (!$quote) {
        json_response(['ok' => false, 'error' => 'Cotización no encontrada.'], 404);
    }

    $to = clean_text($payload['to'] ?? $quote['client']['email'] ?? '', 160);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'error' => 'Email de destino inválido.'], 422);
    }

    $pdfPath = quote_path($number, 'pdf');
    if (!is_file($pdfPath)) {
        file_put_contents($pdfPath, quote_pdf_content($quote), LOCK_EX);
    }

    $boundary = 'mizo_' . bin2hex(random_bytes(12));
    $subject = 'Cotización Mizo ' . $number;
    $body = clean_text($payload['message'] ?? "Hola,\n\nAdjuntamos la cotización solicitada.\n\nSaludos,\nMizo", 2000);
    $attachment = chunk_split(base64_encode(file_get_contents($pdfPath) ?: ''));

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

    $sent = @mail($to, $subject, $message, implode("\r\n", $headers));
    json_response(['ok' => $sent, 'sent' => $sent, 'error' => $sent ? null : 'El servidor no confirmó el envío con mail().']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? null;
$payload = $method === 'POST' ? request_payload() : [];
$action = $action ?: ($payload['action'] ?? 'bootstrap');

if ($method === 'GET' && $action === 'download') {
    $number = clean_text($_GET['number'] ?? '', 30);
    $path = quote_path($number, 'pdf');
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
    if (!is_file($path)) {
        json_response(['ok' => false, 'error' => 'Vista HTML no encontrada.'], 404);
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile($path);
    exit;
}

if ($action === 'bootstrap') {
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
    $quotes = [];
    foreach (glob(quotes_dir() . '/*.json') ?: [] as $file) {
        $quote = read_json($file, []);
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
