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

function quote_html(array $quote): string
{
    $company = $quote['company'];
    $client = $quote['client'];
    $totals = $quote['totals'];
    $rows = '';
    foreach ($quote['items'] as $item) {
        $reference = item_reference($item);
        $rows .= '<tr>'
            . '<td><strong>' . esc($item['name']) . '</strong>' . ($reference !== '' ? '<br><span>' . esc($reference) . '</span>' : '') . '</td>'
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
            body{font-family:Arial,Helvetica,sans-serif;color:#1e293b;margin:0;background:#f1f5f9}
            .page{max-width:920px;margin:0 auto;background:#fff;min-height:100vh;padding:44px}
            .top{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;border-bottom:1px solid #e2e8f0;padding-bottom:24px}
            img{max-width:165px;max-height:50px;object-fit:contain}.logo-fallback{font-size:30px;font-weight:900;color:#1877f2}
            h1{margin:0;color:#1e293b;font-size:28px}.doc-label{color:#1877f2;font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
            .muted{color:#64748b;font-size:13px;line-height:1.55}.box{border:1px solid #e2e8f0;border-radius:18px;padding:18px;background:#fff}
            .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:26px 0}.box-title{font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#1877f2}
            table{width:100%;border-collapse:collapse;margin-top:16px;font-size:13px}th{background:#f8fafc;color:#475569;text-align:left;padding:13px;border-bottom:1px solid #e2e8f0}
            td{border-bottom:1px solid #e2e8f0;padding:16px 13px;vertical-align:top}td span{color:#94a3b8;font-size:11px}.right{text-align:right;white-space:nowrap}
            .totals{margin-left:auto;margin-top:22px;width:330px;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;background:#fff}.totals-row{display:flex;justify-content:space-between;gap:18px;padding:13px 16px;border-bottom:1px solid #e2e8f0;font-size:13px}
            .totals-row span:last-child{font-variant-numeric:tabular-nums;text-align:right}.totals-row strong{font-size:21px;color:#1e293b;font-variant-numeric:tabular-nums}.grand{background:#f8fafc;font-weight:900}
            ul{margin:10px 0 0;padding-left:18px;color:#475569;font-size:13px;line-height:1.55}.footer{margin-top:30px;border-top:1px solid #e2e8f0;padding-top:18px;font-size:12px;color:#64748b;text-align:center}
        </style></head><body><main class="page">'
        . '<section class="top"><div>' . $logoHtml . '<p class="muted" style="margin:14px 0 0">' . esc($company['address']) . '<br>' . esc($company['phone']) . ' · ' . esc($company['email']) . '<br>' . esc($company['website']) . '</p></div>'
        . '<div style="text-align:right"><p class="doc-label">Cotización profesional</p><h1>Presupuesto ' . esc($quote['number']) . '</h1><p class="muted" style="margin:10px 0 0">Fecha: ' . esc($quote['createdAt']) . '<br>Emitido por ventas@mizo.cl</p></div></section>'
        . '<section class="grid"><div class="box"><p class="box-title">Cliente</p><p class="muted"><strong style="color:#1e293b">' . esc($client['name']) . '</strong><br>' . esc($client['email']) . '<br>' . esc($client['phone']) . '<br>' . esc($client['address']) . '</p></div>'
        . '<div class="box"><p class="box-title">Resumen</p><p class="muted">Productos, servicios e integración audiovisual profesional.</p><p style="margin:12px 0 0;font-size:24px;font-weight:900;color:#1e293b">' . clp((int)$totals['total']) . '</p><p class="muted">Total con IVA incluido</p></div></section>'
        . '<section class="box"><p class="box-title">Detalle de productos y servicios</p><table><thead><tr><th>Ítem</th><th class="right">Cant.</th><th class="right">Precio unit.</th><th class="right">Neto</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<div class="totals"><div class="totals-row"><span>Subtotal neto</span><span>' . clp((int)$totals['subtotal']) . '</span></div><div class="totals-row"><span>' . esc($taxLabel) . '</span><span>' . clp((int)$totals['tax']) . '</span></div><div class="totals-row grand"><span>Total a pagar</span><strong>' . clp((int)$totals['total']) . '</strong></div></div></section>'
        . '<section class="box" style="margin-top:22px"><p class="box-title">Condiciones comerciales</p><ul>' . $conditions . '</ul></section>'
        . '<p class="footer">Cotización generada automáticamente por Mizo. Valores expresados en pesos chilenos e incluyen IVA cuando se indica. ' . esc($company['website']) . '</p>'
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

function pdf_text_width(string $text, int $size, string $font = 'F1'): int
{
    $plain = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    $plain = $plain === false ? $text : $plain;
    $factor = in_array($font, ['F3', 'F4'], true) ? 0.60 : ($font === 'F2' ? 0.58 : 0.52);
    return (int)ceil(strlen($plain) * $size * $factor);
}

function pdf_text_right(string $text, int $rightX, int $y, int $size = 10, string $font = 'F3'): string
{
    return pdf_text($text, $rightX - pdf_text_width($text, $size, $font), $y, $size, $font);
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
    $stream .= pdf_color(0.973, 0.976, 0.984) . "\n0 0 595 842 re f\n";
    $stream .= "1 1 1 rg\n36 38 523 766 re f\n";
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n36 794 523 6 re f\n";

    // Wordmark Mizo basado en el logo de la web, dibujado nativamente en PDF.
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n52 742 46 46 re f\n";
    $stream .= pdf_color(1, 1, 1) . "\n" . pdf_text('M', 65, 756, 22, 'F2');
    $stream .= pdf_color(0.118, 0.161, 0.231) . "\n" . pdf_text('MIZO', 112, 765, 28, 'F2');
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n" . pdf_text('Ingenieria audiovisual e instalaciones profesionales', 114, 748, 8, 'F1');

    $stream .= pdf_color(0.973, 0.976, 0.984) . "\n376 728 154 58 re f\n";
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n376 728 3 58 re f\n";
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n" . pdf_text('COTIZACION', 394, 767, 8, 'F2');
    $stream .= pdf_color(0.118, 0.161, 0.231) . "\n" . pdf_text('Presupuesto ' . $quote['number'], 394, 750, 14, 'F2');
    $stream .= pdf_color(0.392, 0.455, 0.545) . "\n" . pdf_text('Fecha: ' . $quote['createdAt'], 394, 735, 8, 'F1');

    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n52 708 228 2 re f\n";
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n316 708 214 2 re f\n";
    $stream .= pdf_color(0.067, 0.094, 0.153) . "\n" . pdf_text('DATOS DE MIZO', 52, 720, 10, 'F2');
    $stream .= pdf_text('DATOS DEL CLIENTE', 316, 720, 10, 'F2');

    $stream .= pdf_color(0.275, 0.333, 0.424) . "\n";
    $stream .= pdf_text($company['companyName'] ?? 'Mizo', 52, 688, 11, 'F2');
    $stream .= pdf_text($company['address'] ?? '', 52, 673, 9, 'F1');
    $stream .= pdf_text(($company['phone'] ?? '') . ' | ' . ($company['email'] ?? DEFAULT_FROM), 52, 659, 9, 'F1');
    $stream .= pdf_text($company['website'] ?? 'https://mizo.cl', 52, 645, 9, 'F1');

    $stream .= pdf_text($client['name'] ?? 'Cliente sin nombre', 316, 688, 11, 'F2');
    $stream .= pdf_text($client['email'] ?? '', 316, 673, 9, 'F1');
    $stream .= pdf_text(($client['phone'] ?? '') . (($client['rut'] ?? '') !== '' ? ' | RUT: ' . $client['rut'] : ''), 316, 659, 9, 'F1');
    $stream .= pdf_text($client['address'] ?? '', 316, 645, 9, 'F1');

    $stream .= pdf_color(0.118, 0.161, 0.231) . "\n" . pdf_text('DETALLE DE PRODUCTOS Y SERVICIOS', 52, 610, 11, 'F2');
    $stream .= pdf_color(0.973, 0.976, 0.984) . "\n52 584 478 26 re f\n";
    $stream .= pdf_color(0.392, 0.455, 0.545) . "\n";
    $stream .= pdf_text('ITEM', 64, 593, 8, 'F2');
    $stream .= pdf_text_right('CANT.', 358, 593, 8, 'F2');
    $stream .= pdf_text_right('UNITARIO', 442, 593, 8, 'F2');
    $stream .= pdf_text_right('NETO', 518, 593, 8, 'F2');

    $y = 558;
    $rowIndex = 0;
    foreach (array_slice($quote['items'], 0, 10) as $item) {
        if ($rowIndex % 2 === 0) {
            $stream .= pdf_color(0.973, 0.976, 0.984) . "\n52 " . ($y - 29) . " 478 39 re f\n";
        }
        $stream .= pdf_color(0.898, 0.906, 0.922) . "\n52 " . ($y - 31) . " 478 1 re f\n";
        $stream .= pdf_color(0.118, 0.161, 0.231) . "\n";
        $nameLines = pdf_wrap((string)$item['name'], 43);
        $stream .= pdf_text($nameLines[0], 64, $y, 9, 'F2');
        if (isset($nameLines[1])) {
            $stream .= pdf_color(0.275, 0.333, 0.424) . "\n" . pdf_text($nameLines[1], 64, $y - 11, 8, 'F1');
        }
        $reference = item_reference($item);
        if ($reference !== '') {
            $stream .= pdf_color(0.392, 0.455, 0.545) . "\n" . pdf_text($reference, 64, $y - 23, 7, 'F1');
        }
        $stream .= pdf_color(0.118, 0.161, 0.231) . "\n";
        $stream .= pdf_text_right((string)(int)$item['quantity'], 358, $y - 4, 9, 'F3');
        $stream .= pdf_text_right(clp((int)$item['unitPrice']), 442, $y - 4, 9, 'F3');
        $stream .= pdf_text_right(clp((int)$item['total']), 518, $y - 4, 9, 'F4');
        $y -= 40;
        $rowIndex++;
        if ($y < 258) {
            break;
        }
    }

    if (count($quote['items']) > 10 || $y < 258) {
        $stream .= pdf_color(0.392, 0.455, 0.545) . "\n" . pdf_text('Detalle completo disponible en la vista HTML guardada de la cotizacion.', 64, $y, 8, 'F1');
        $y -= 20;
    }

    $boxY = max(172, $y - 118);
    $stream .= pdf_color(0.973, 0.976, 0.984) . "\n316 {$boxY} 214 108 re f\n";
    $stream .= pdf_color(0.094, 0.467, 0.949) . "\n316 " . ($boxY + 104) . " 214 3 re f\n";
    $stream .= pdf_color(0.275, 0.333, 0.424) . "\n";
    $stream .= pdf_text('Subtotal neto', 334, $boxY + 78, 10, 'F1');
    $stream .= pdf_text_right(clp((int)$totals['subtotal']), 512, $boxY + 78, 10, 'F3');
    $stream .= pdf_text($taxLabel, 334, $boxY + 54, 10, 'F1');
    $stream .= pdf_text_right(clp((int)$totals['tax']), 512, $boxY + 54, 10, 'F3');
    $stream .= pdf_color(0.898, 0.906, 0.922) . "\n334 " . ($boxY + 39) . " 178 1 re f\n";
    $stream .= pdf_color(0.118, 0.161, 0.231) . "\n";
    $stream .= pdf_text('TOTAL A PAGAR', 334, $boxY + 20, 10, 'F2');
    $stream .= pdf_text_right(clp((int)$totals['total']), 512, $boxY + 18, 15, 'F4');

    $conditionsY = $boxY + 84;
    $stream .= pdf_color(0.067, 0.094, 0.153) . "\n" . pdf_text('CONDICIONES COMERCIALES', 52, $conditionsY, 10, 'F2');
    $conditionsY -= 18;
    $stream .= pdf_color(0.275, 0.333, 0.424) . "\n";
    foreach (array_slice($quote['conditions'], 0, 5) as $condition) {
        foreach (pdf_wrap('- ' . $condition, 47) as $line) {
            $stream .= pdf_text($line, 52, $conditionsY, 8, 'F1');
            $conditionsY -= 12;
            if ($conditionsY < 94) {
                break 2;
            }
        }
    }

    $stream .= pdf_color(0.898, 0.906, 0.922) . "\n52 76 478 1 re f\n";
    $stream .= pdf_color(0.392, 0.455, 0.545) . "\n" . pdf_text('Cotizacion generada automaticamente por Mizo. Valores en pesos chilenos. Total incluye IVA 19%.', 52, 56, 8, 'F1');

    $objects = [
        "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
        "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n",
        "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R /F4 7 0 R >> >> /Contents 8 0 R >> endobj\n",
        "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
        "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj\n",
        "6 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Courier >> endobj\n",
        "7 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >> endobj\n",
        "8 0 obj << /Length " . strlen($stream) . " >> stream\n" . $stream . "\nendstream endobj\n",
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
