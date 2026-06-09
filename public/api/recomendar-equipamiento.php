<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_data(): array
{
    $data = $_GET;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = array_merge($data, $json);
        } else {
            $data = array_merge($data, $_POST);
        }
    }
    return $data;
}

function normalize_text($value): string
{
    $value = strtolower(trim((string) $value));
    $from = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
    $to = ['a', 'e', 'i', 'o', 'u', 'u', 'n'];
    return str_replace($from, $to, $value);
}

function clean_string($value, int $max = 900): string
{
    $value = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?: '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function current_origin(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'www.mizo.cl';
    if (strpos($host, 'mizo.cl') !== false) {
        return 'https://' . $host;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    return $scheme . '://' . $host;
}

function fetch_catalog(): array
{
    $url = current_origin() . '/api-productos-publicados.php?ts=' . time();
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => "Accept: application/json\r\nUser-Agent: Mizo-Configurator-AI/1.0\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        respond(502, [
            'ok' => false,
            'error' => 'No se pudo leer el catálogo maestro publicado.',
            'source' => $url,
        ]);
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload) || empty($payload['ok']) || !isset($payload['products']) || !is_array($payload['products'])) {
        respond(502, [
            'ok' => false,
            'error' => 'El catálogo maestro no respondió con un formato válido.',
            'source' => $url,
        ]);
    }
    return [$payload['products'], $url];
}

function product_text(array $product): string
{
    return normalize_text(
        ($product['name'] ?? '') . ' ' .
        ($product['brand'] ?? '') . ' ' .
        ($product['category'] ?? '') . ' ' .
        ($product['categoryLabel'] ?? '') . ' ' .
        ($product['description'] ?? '')
    );
}

function is_available(array $product): bool
{
    if (isset($product['published']) && $product['published'] === false) return false;
    if (isset($product['available']) && $product['available'] === false) return false;
    if (isset($product['stock']) && is_numeric($product['stock']) && (int) $product['stock'] <= 0) return false;
    return true;
}

function has_any(string $text, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && strpos($text, normalize_text($needle)) !== false) {
            return true;
        }
    }
    return false;
}

function score_product(array $product, array $rule): int
{
    if (!is_available($product)) return -1000;

    $text = product_text($product);
    $category = normalize_text($product['category'] ?? '');
    $score = 0;

    foreach ($rule['categories'] as $wanted) {
        if ($category === normalize_text($wanted)) $score += 55;
    }
    foreach ($rule['strong'] as $word) {
        if (strpos($text, normalize_text($word)) !== false) $score += 28;
    }
    foreach ($rule['medium'] as $word) {
        if (strpos($text, normalize_text($word)) !== false) $score += 12;
    }
    foreach ($rule['brands'] as $brand) {
        if (strpos($text, normalize_text($brand)) !== false) $score += 18;
    }
    foreach ($rule['avoid'] as $word) {
        if (strpos($text, normalize_text($word)) !== false) $score -= 35;
    }
    if (isset($product['stock']) && is_numeric($product['stock'])) {
        $score += min(10, max(0, (int) $product['stock']));
    }

    return $score;
}

function rules_for(string $entorno, string $especialidad, string $tamano): array
{
    if ($entorno === 'gimnasio' && $especialidad === 'audio' && $tamano === 'grande') {
        return [
            [
                'role' => 'Audio de alta potencia',
                'qty' => 2,
                'unit' => 'unidades',
                'categories' => ['parlantes', 'cajas-acusticas'],
                'brands' => ['JBL', 'Behringer', 'Samson', 'QSC', 'Electrovoice', 'FBT', 'Turbosound'],
                'strong' => ['line array', '1000w', '800w', '1500w', 'caja activa', 'subwoofer', '15'],
                'medium' => ['activo', 'alta potencia', 'pa system', 'portatil', 'bluetooth'],
                'avoid' => ['audifono', 'camara', 'guitarra', 'teclado'],
            ],
            [
                'role' => 'Amplificación para recinto deportivo',
                'qty' => 1,
                'unit' => 'unidad',
                'categories' => ['amplificadores'],
                'brands' => ['Dynacord', 'Behringer', 'Samson', 'Crown', 'QSC'],
                'strong' => ['amplificador', 'anuncios', 'potencia', '3200w'],
                'medium' => ['audio', 'profesional', 'rack'],
                'avoid' => ['audifono', 'camara'],
            ],
            [
                'role' => 'Microfonía inalámbrica para locución',
                'qty' => 1,
                'unit' => 'unidad',
                'categories' => ['microfonos'],
                'brands' => ['Shure', 'Behringer', 'Samson', 'Audio-Technica'],
                'strong' => ['inalambrico', 'uhf', 'microfono'],
                'medium' => ['vocal', 'mano', 'presentacion'],
                'avoid' => ['atril', 'cable'],
            ],
        ];
    }

    if ($entorno === 'auditorio' && $especialidad === 'ambos' && $tamano === 'mediano') {
        return [
            [
                'role' => 'Parlantes de instalación para auditorio',
                'qty' => 4,
                'unit' => 'unidades',
                'categories' => ['parlantes', 'cajas-acusticas'],
                'brands' => ['HH Audio', 'Electrovoice', 'JBL', 'Wharfedale', 'FBT'],
                'strong' => ['instalacion', '70/100', 'tni-w4', 'cobertura', 'pasiva'],
                'medium' => ['muro', 'gabinete', 'auditorio', 'eventos', 'educacionales'],
                'avoid' => ['bluetooth', 'audifono', 'camara'],
            ],
            [
                'role' => 'Proyección profesional',
                'qty' => 1,
                'unit' => 'unidad',
                'categories' => ['proyectores'],
                'brands' => ['Wanbo', 'Philco', 'Epson'],
                'strong' => ['proyector', 'vali', '900 ansi', 'lumenes', 'android'],
                'medium' => ['wifi', 'bt', '4k', 'full hd'],
                'avoid' => ['camara', 'audifono'],
            ],
            [
                'role' => 'Mezcla y control de audio',
                'qty' => 1,
                'unit' => 'unidad',
                'categories' => ['consolas-mixers'],
                'brands' => ['Behringer', 'Yamaha', 'Studiomaster', 'Wharfedale'],
                'strong' => ['mixer', 'consola', 'mezcla'],
                'medium' => ['usb', 'digital', 'audio'],
                'avoid' => ['tarjeta de expansion'],
            ],
        ];
    }

    if ($entorno === 'sala' && $especialidad === 'ambos' && $tamano === 'pequeno') {
        return [
            [
                'role' => 'Video, streaming o visualización corporativa',
                'qty' => 1,
                'unit' => 'unidad',
                'categories' => ['tv-streaming', 'camaras', 'proyectores'],
                'brands' => ['Sony', 'Samsung', 'Wanbo', 'Hikvision', 'Reolink', 'EZVIZ'],
                'strong' => ['camara', 'proyector', 'streaming', '4k', 'wifi', 'ptz'],
                'medium' => ['full hd', 'tracking', 'smart', 'android', 'pantalla'],
                'avoid' => ['guitarra', 'teclado'],
            ],
            [
                'role' => 'Audio personal y referencia de sala',
                'qty' => 2,
                'unit' => 'unidades',
                'categories' => ['audifonos', 'audio-hogar'],
                'brands' => ['JBL', 'Bose', 'Sony', 'Audio-Technica', 'Pioneer'],
                'strong' => ['audifono', 'bluetooth', 'over ear', 'true wireless'],
                'medium' => ['microfono', 'inalambrico', 'corporativo'],
                'avoid' => ['guitarra', 'teclado'],
            ],
            [
                'role' => 'Micrófono para videoconferencia',
                'qty' => 1,
                'unit' => 'unidad',
                'categories' => ['microfonos'],
                'brands' => ['Shure', 'Samson', 'Audio-Technica', 'Behringer'],
                'strong' => ['microfono', 'podcast', 'usb', 'inalambrico'],
                'medium' => ['voz', 'conferencia', 'streaming'],
                'avoid' => ['atril'],
            ],
        ];
    }

    if ($especialidad === 'video') {
        return [
            [
                'role' => 'Visualización profesional',
                'qty' => 1,
                'unit' => 'unidad',
                'categories' => ['proyectores', 'tv-streaming', 'camaras'],
                'brands' => ['Wanbo', 'Sony', 'Samsung', 'Hikvision', 'Reolink'],
                'strong' => ['proyector', 'camara', 'streaming', '4k', 'full hd'],
                'medium' => ['wifi', 'smart', 'android', 'ptz'],
                'avoid' => ['audifono', 'guitarra'],
            ],
        ];
    }

    return [
        [
            'role' => 'Parlantes profesionales',
            'qty' => $tamano === 'grande' ? 6 : 4,
            'unit' => 'unidades',
            'categories' => ['parlantes', 'cajas-acusticas'],
            'brands' => ['JBL', 'Behringer', 'Samson', 'Electrovoice', 'FBT'],
            'strong' => ['parlante', 'caja activa', 'instalacion', 'line array'],
            'medium' => ['activo', 'pasivo', 'subwoofer', '1000w'],
            'avoid' => ['audifono', 'camara'],
        ],
        [
            'role' => 'Amplificación o mezcla',
            'qty' => 1,
            'unit' => 'unidad',
            'categories' => ['amplificadores', 'consolas-mixers', 'microfonos'],
            'brands' => ['Behringer', 'Samson', 'Shure', 'Yamaha'],
            'strong' => ['amplificador', 'mixer', 'consola', 'microfono'],
            'medium' => ['audio', 'profesional', 'usb'],
            'avoid' => ['audifono', 'camara'],
        ],
    ];
}

function pick_for_rule(array $products, array $rule, array $used): ?array
{
    $ranked = [];
    foreach ($products as $product) {
        if (!is_array($product)) continue;
        $id = (string) ($product['id'] ?? $product['sku'] ?? '');
        if ($id === '' || isset($used[$id])) continue;
        $score = score_product($product, $rule);
        if ($score > 0) {
            $ranked[] = ['score' => $score, 'product' => $product];
        }
    }
    usort($ranked, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    return $ranked[0]['product'] ?? null;
}

function public_image(array $product): string
{
    $image = trim((string) ($product['image'] ?? ''));
    if ($image === '') return '/mizo-logo.png';
    return $image;
}

$input = input_data();
$entorno = normalize_text($input['entorno'] ?? '');
$especialidad = normalize_text($input['especialidad'] ?? '');
$tamano = normalize_text($input['tamano'] ?? '');

$validEntornos = ['auditorio', 'gimnasio', 'sala', 'planta'];
$validEspecialidades = ['audio', 'video', 'ambos'];
$validTamanos = ['pequeno', 'mediano', 'grande'];

if (!in_array($entorno, $validEntornos, true) || !in_array($especialidad, $validEspecialidades, true) || !in_array($tamano, $validTamanos, true)) {
    respond(422, [
        'ok' => false,
        'error' => 'Variables inválidas. Debes enviar entorno, especialidad y tamano válidos.',
    ]);
}

[$products, $source] = fetch_catalog();
$rules = rules_for($entorno, $especialidad, $tamano);
$used = [];
$recommendations = [];

foreach ($rules as $rule) {
    $product = pick_for_rule($products, $rule, $used);
    if (!$product) continue;
    $id = (string) ($product['id'] ?? $product['sku'] ?? '');
    $used[$id] = true;
    $recommendations[] = [
        'qty' => $rule['qty'],
        'unit' => $rule['unit'],
        'role' => $rule['role'],
        'id' => $product['id'] ?? '',
        'sku' => $product['sku'] ?? '',
        'name' => clean_string($product['name'] ?? '', 180),
        'brand' => clean_string($product['brand'] ?? '', 80),
        'category' => clean_string($product['categoryLabel'] ?? ($product['category'] ?? ''), 80),
        'description' => clean_string($product['description'] ?? '', 520),
        'image' => public_image($product),
        'stock' => isset($product['stock']) ? (int) $product['stock'] : null,
        'available' => !isset($product['available']) || $product['available'] !== false,
    ];
}

if (!$recommendations) {
    respond(404, [
        'ok' => false,
        'error' => 'No se encontraron productos publicados con stock para esta combinación.',
        'source' => $source,
    ]);
}

respond(200, [
    'ok' => true,
    'source' => 'MySQL vía /api-productos-publicados.php',
    'catalogUrl' => $source,
    'criteria' => [
        'entorno' => $entorno,
        'especialidad' => $especialidad,
        'tamano' => $tamano,
    ],
    'title' => 'Pre-diseño IA con productos reales del catálogo Mizo',
    'summary' => 'El motor cruzó las variables del recinto con productos publicados, stock disponible, categorías técnicas, marcas y descripciones reales del catálogo maestro MySQL.',
    'products' => $recommendations,
]);
