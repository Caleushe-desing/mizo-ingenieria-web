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
    $config = array_replace_recursive(default_config(), read_json(config_path(), []));
    if (($config['logo'] ?? '') === '/mizo-logo.png') {
        $config['logo'] = '/mizo-logo.svg';
    }
    return $config;
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

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quote_html(array $quote): string
{
    $company = $quote['company'];
    $client = $quote['client'];
    $totals = $quote['totals'];
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
    $taxLabel = 'IVA ' . number_format(((float)($totals['taxRate'] ?? TAX_RATE)) * 100, 0, ',', '.') . '%';

    return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><title>' . esc($quote['number']) . '</title>'
        . '<style>
            body{font-family:Arial,Helvetica,sans-serif;color:#111827;margin:0;background:#e5e7eb}
            .page{max-width:920px;margin:0 auto;background:#fff;min-height:100vh}
            .hero{background:#0f172a;color:#fff;padding:34px 42px 28px;border-bottom:6px solid #1877f2}
            .top{display:flex;justify-content:space-between;gap:24px;align-items:flex-start}
            img{max-width:170px;max-height:74px;object-fit:contain}.logo-fallback{font-size:34px;font-weight:900;color:#fff}
            h1{margin:0;font-size:32px}.doc-label{color:#93c5fd;font-size:12px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}
            .content{padding:34px 42px 42px}.muted{color:#6b7280;font-size:13px;line-height:1.55}.box{border:1px solid #e5e7eb;border-radius:18px;padding:18px;background:#fff}
            .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px}.box-title{font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#1877f2}
            table{width:100%;border-collapse:collapse;margin-top:14px;font-size:13px}th{background:#111827;color:#fff;text-align:left;padding:13px}td{border-bottom:1px solid #e5e7eb;padding:13px;vertical-align:top}
            td span{color:#6b7280;font-size:11px}.right{text-align:right}.totals{margin-left:auto;margin-top:20px;width:320px;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden}
            .totals-row{display:flex;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #e5e7eb;font-size:13px}.totals-row strong{font-size:20px;color:#1877f2}.grand{background:#eff6ff;font-weight:900}
            ul{margin:10px 0 0;padding-left:18px}.footer{margin-top:28px;border-top:1px solid #e5e7eb;padding-top:18px;font-size:12px;color:#6b7280;text-align:center}
        </style></head><body><main class="page">'
        . '<section class="hero"><div class="top"><div>' . $logoHtml . '<p style="margin:14px 0 0;color:#cbd5e1;font-size:13px;line-height:1.5">' . esc($company['address']) . '<br>' . esc($company['phone']) . ' · ' . esc($company['email']) . '<br>' . esc($company['website']) . '</p></div>'
        . '<div style="text-align:right"><p class="doc-label">Cotización profesional</p><h1>Presupuesto ' . esc($quote['number']) . '</h1><p style="margin:10px 0 0;color:#cbd5e1;font-size:13px">Fecha: ' . esc($quote['createdAt']) . '<br>Emitido por ventas@mizo.cl</p></div></div></section>'
        . '<section class="content"><section class="grid"><div class="box"><p class="box-title">Cliente</p><p class="muted"><strong style="color:#111827">' . esc($client['name']) . '</strong><br>' . esc($client['email']) . '<br>' . esc($client['phone']) . '<br>' . esc($client['address']) . '</p></div>'
        . '<div class="box"><p class="box-title">Resumen ejecutivo</p><p class="muted">Cotización emitida por Mizo para productos, servicios e integración audiovisual profesional.</p><p style="margin:12px 0 0;font-size:24px;font-weight:900;color:#1877f2">' . clp((int)$totals['total']) . '</p><p class="muted">Total con IVA incluido</p></div></section>'
        . '<section class="box"><p class="box-title">Detalle de productos y servicios</p><table><thead><tr><th>Ítem</th><th class="right">Cant.</th><th class="right">Precio unit.</th><th class="right">Neto</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<div class="totals"><div class="totals-row"><span>Subtotal neto</span><span>' . clp((int)$totals['subtotal']) . '</span></div><div class="totals-row"><span>' . esc($taxLabel) . '</span><span>' . clp((int)$totals['tax']) . '</span></div><div class="totals-row grand"><span>Total a pagar</span><strong>' . clp((int)$totals['total']) . '</strong></div></div></section>'
        . '<section class="box" style="margin-top:22px"><p class="box-title">Condiciones comerciales</p><ul>' . $conditions . '</ul></section>'
        . '<p class="footer">Cotización generada automáticamente por Mizo. Valores expresados en pesos chilenos e incluyen IVA cuando se indica. ' . esc($company['website']) . '</p></section>'
        . '</main></body></html>';
}

function pdf_escape(string $value): string
{
    $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted === false ? $value : $converted);
}

function pdf_color(float $r, float $g, float $b): string
{
    return sprintf('%.3F %.3F %.3F rg', $r, $g, $b);
}

function pdf_text(string $text, int $x, int $y, int $size = 10, string $font = 'F1'): string
{
    return "BT\n/{$font} {$size} Tf\n{$x} {$y} Td\n(" . pdf_escape($text) . ") Tj\nET\n";
}

function pdf_wrap(string $text, int $maxChars): array
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') {
        return [''];
    }

    $words = explode(' ', $text);
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if (strlen($candidate) > $maxChars && $current !== '') {
            $lines[] = $current;
            $current = $word;
            continue;
        }
        $current = $candidate;
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    return $lines;
}

function quote_pdf_content(array $quote): string
{
    $company = $quote['company'];
    $client = $quote['client'];
    $totals = $quote['totals'];
    $taxRate = (float)($totals['taxRate'] ?? TAX_RATE);
    $taxLabel = 'IVA ' . number_format($taxRate * 100, 0, ',', '.') . '%';

    $stream = '';
    $stream .= "1 1 1 rg\n0 0 595 842 re f\n";
    $stream .= pdf_color(0.059, 0.090, 0.165) . "\n0 742 595 100 re f\n";
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n0 742 595 7 re f\n";

    // Logo Mizo dibujado en vector/texto para no depender de librerías PDF externas.
    $stream .= "1 1 1 rg\n48 770 58 44 re f\n";
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n54 776 46 32 re f\n";
    $stream .= pdf_color(1, 1, 1) . "\n" . pdf_text('M', 68, 786, 20, 'F2');
    $stream .= pdf_color(1, 1, 1) . "\n" . pdf_text('MIZO', 118, 792, 24, 'F2');
    $stream .= pdf_color(0.796, 0.835, 0.902) . "\n" . pdf_text('Ingenieria audiovisual e integracion profesional', 120, 776, 9, 'F1');

    $stream .= pdf_color(0.580, 0.773, 0.992) . "\n" . pdf_text('COTIZACION PROFESIONAL', 386, 805, 8, 'F2');
    $stream .= pdf_color(1, 1, 1) . "\n" . pdf_text('Presupuesto ' . $quote['number'], 386, 784, 18, 'F2');
    $stream .= pdf_color(0.796, 0.835, 0.902) . "\n" . pdf_text('Fecha: ' . $quote['createdAt'], 386, 767, 9, 'F1');

    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n46 704 238 2 re f\n";
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n316 704 232 2 re f\n";
    $stream .= pdf_color(0.067, 0.094, 0.153) . "\n" . pdf_text('DATOS DE MIZO', 46, 718, 10, 'F2');
    $stream .= pdf_text('DATOS DEL CLIENTE', 316, 718, 10, 'F2');

    $stream .= pdf_color(0.275, 0.333, 0.424) . "\n";
    $stream .= pdf_text($company['companyName'] ?? 'Mizo', 46, 684, 11, 'F2');
    $stream .= pdf_text($company['address'] ?? '', 46, 669, 9, 'F1');
    $stream .= pdf_text(($company['phone'] ?? '') . ' | ' . ($company['email'] ?? DEFAULT_FROM), 46, 655, 9, 'F1');
    $stream .= pdf_text($company['website'] ?? 'https://mizo.cl', 46, 641, 9, 'F1');

    $stream .= pdf_text($client['name'] ?? 'Cliente sin nombre', 316, 684, 11, 'F2');
    $stream .= pdf_text($client['email'] ?? '', 316, 669, 9, 'F1');
    $stream .= pdf_text(($client['phone'] ?? '') . (($client['rut'] ?? '') !== '' ? ' | RUT: ' . $client['rut'] : ''), 316, 655, 9, 'F1');
    $stream .= pdf_text($client['address'] ?? '', 316, 641, 9, 'F1');

    $stream .= pdf_color(0.067, 0.094, 0.153) . "\n" . pdf_text('DETALLE DE PRODUCTOS Y SERVICIOS', 46, 600, 11, 'F2');
    $stream .= pdf_color(0.067, 0.094, 0.153) . "\n46 575 502 24 re f\n";
    $stream .= pdf_color(1, 1, 1) . "\n";
    $stream .= pdf_text('ITEM', 56, 583, 8, 'F2');
    $stream .= pdf_text('CANT.', 344, 583, 8, 'F2');
    $stream .= pdf_text('UNITARIO', 400, 583, 8, 'F2');
    $stream .= pdf_text('NETO', 493, 583, 8, 'F2');

    $y = 552;
    foreach (array_slice($quote['items'], 0, 12) as $item) {
        $stream .= pdf_color(0.898, 0.906, 0.922) . "\n46 " . ($y - 10) . " 502 1 re f\n";
        $stream .= pdf_color(0.067, 0.094, 0.153) . "\n";
        $nameLines = pdf_wrap((string)$item['name'], 42);
        $stream .= pdf_text($nameLines[0], 56, $y, 9, 'F2');
        if (isset($nameLines[1])) {
            $stream .= pdf_color(0.275, 0.333, 0.424) . "\n" . pdf_text($nameLines[1], 56, $y - 12, 8, 'F1');
        }
        $stream .= pdf_color(0.392, 0.455, 0.545) . "\n" . pdf_text($item['sku'] ?: 'Manual', 56, $y - 24, 8, 'F1');
        $stream .= pdf_color(0.067, 0.094, 0.153) . "\n";
        $stream .= pdf_text((string)(int)$item['quantity'], 354, $y, 9, 'F1');
        $stream .= pdf_text(clp((int)$item['unitPrice']), 400, $y, 9, 'F1');
        $stream .= pdf_text(clp((int)$item['total']), 493, $y, 9, 'F2');
        $y -= 44;
        if ($y < 260) {
            break;
        }
    }

    if (count($quote['items']) > 12 || $y < 260) {
        $stream .= pdf_color(0.392, 0.455, 0.545) . "\n" . pdf_text('Detalle completo disponible en la version HTML guardada de la cotizacion.', 56, $y, 8, 'F1');
        $y -= 24;
    }

    $boxY = max(176, $y - 120);
    $stream .= pdf_color(0.945, 0.961, 1.000) . "\n330 {$boxY} 218 96 re f\n";
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n330 " . ($boxY + 94) . " 218 2 re f\n";
    $stream .= pdf_color(0.275, 0.333, 0.424) . "\n";
    $stream .= pdf_text('Subtotal neto', 348, $boxY + 68, 10, 'F1');
    $stream .= pdf_text(clp((int)$totals['subtotal']), 462, $boxY + 68, 10, 'F2');
    $stream .= pdf_text($taxLabel, 348, $boxY + 45, 10, 'F1');
    $stream .= pdf_text(clp((int)$totals['tax']), 462, $boxY + 45, 10, 'F2');
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n";
    $stream .= pdf_text('TOTAL A PAGAR', 348, $boxY + 18, 11, 'F2');
    $stream .= pdf_text(clp((int)$totals['total']), 454, $boxY + 16, 14, 'F2');

    $conditionsY = $boxY + 70;
    $stream .= pdf_color(0.067, 0.094, 0.153) . "\n" . pdf_text('CONDICIONES COMERCIALES', 46, $conditionsY, 10, 'F2');
    $conditionsY -= 18;
    $stream .= pdf_color(0.275, 0.333, 0.424) . "\n";
    foreach (array_slice($quote['conditions'], 0, 5) as $condition) {
        foreach (pdf_wrap('- ' . $condition, 48) as $line) {
            $stream .= pdf_text($line, 46, $conditionsY, 8, 'F1');
            $conditionsY -= 12;
            if ($conditionsY < 92) {
                break 2;
            }
        }
    }

    $stream .= pdf_color(0.898, 0.906, 0.922) . "\n46 68 502 1 re f\n";
    $stream .= pdf_color(0.392, 0.455, 0.545) . "\n" . pdf_text('Cotizacion generada automaticamente por Mizo. Valores en pesos chilenos. Total incluye IVA 19%.', 46, 48, 8, 'F1');

    $objects = [
        "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
        "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n",
        "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >> endobj\n",
        "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
        "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj\n",
        "6 0 obj << /Length " . strlen($stream) . " >> stream\n" . $stream . "\nendstream endobj\n",
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

function normalize_quote(array $quote): array
{
    if (isset($quote['items']) && is_array($quote['items'])) {
        $quote['totals'] = quote_totals($quote['items']);
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
    file_put_contents($pdfPath, quote_pdf_content($quote), LOCK_EX);

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
