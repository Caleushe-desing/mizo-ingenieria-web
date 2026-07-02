<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog-db.php';

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
        $data = is_array($json) ? array_merge($data, $json) : array_merge($data, $_POST);
    }
    return $data;
}

function normalize_text($value): string
{
    $value = strtolower(trim((string) $value));
    return str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $value);
}

function clean_string($value, int $max = 900): string
{
    $value = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?: '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function project_metrics(string $entorno, string $especialidad, string $tamano): array
{
    $size = [
        'pequeno' => ['area' => 45, 'listeners' => 25, 'screen' => 3.2, 'label' => 'Pequeno'],
        'mediano' => ['area' => 120, 'listeners' => 90, 'screen' => 7.5, 'label' => 'Mediano'],
        'grande' => ['area' => 280, 'listeners' => 230, 'screen' => 15.5, 'label' => 'Grande'],
    ][$tamano];

    $environment = [
        'auditorio' => ['height' => 6.2, 'spl' => 94, 'lux' => 420, 'ambient' => 1.45, 'label' => 'Auditorio / Teatro'],
        'gimnasio' => ['height' => 8.0, 'spl' => 98, 'lux' => 520, 'ambient' => 1.65, 'label' => 'Gimnasio / Espacio Deportivo'],
        'sala' => ['height' => 3.2, 'spl' => 82, 'lux' => 360, 'ambient' => 1.25, 'label' => 'Sala Corporativa'],
        'planta' => ['height' => 7.0, 'spl' => 92, 'lux' => 560, 'ambient' => 1.7, 'label' => 'Planta Industrial / Logistica'],
    ][$entorno];

    $sizeSplAdd = ['pequeno' => 0, 'mediano' => 3, 'grande' => 6][$tamano];
    $specialtySplAdd = $especialidad === 'video' ? -6 : ($especialidad === 'ambos' ? 2 : 0);
    $volume = (int) round($size['area'] * $environment['height']);
    $targetSpl = max(76, (int) round($environment['spl'] + $sizeSplAdd + $specialtySplAdd));
    $lumens = (int) ceil(($size['screen'] * $environment['lux'] * $environment['ambient']) / 100) * 100;
    if ($especialidad === 'audio') {
        $lumens = (int) round($lumens * 0.55 / 100) * 100;
    }

    $rackPoints = 2;
    if ($especialidad === 'audio') $rackPoints += 3;
    if ($especialidad === 'video') $rackPoints += 2;
    if ($especialidad === 'ambos') $rackPoints += 5;
    if ($tamano === 'mediano') $rackPoints += 2;
    if ($tamano === 'grande') $rackPoints += 5;
    if (in_array($entorno, ['auditorio', 'gimnasio', 'planta'], true)) $rackPoints += 2;
    $rack = $rackPoints >= 12 ? '24U' : ($rackPoints >= 7 ? '12U' : '6U');

    return [
        'volumen_estimado_m3' => [
            'label' => 'Volumen estimado',
            'value' => $volume,
            'unit' => 'm3',
            'icon' => 'cube',
            'formula' => sprintf('%d m2 x %.1f m de altura promedio', $size['area'], $environment['height']),
        ],
        'presion_sonora_objetivo_db_spl' => [
            'label' => 'Presion sonora objetivo',
            'value' => $targetSpl,
            'unit' => 'dB SPL',
            'icon' => 'wave',
            'formula' => 'SPL base por entorno + correccion por escala + especialidad',
        ],
        'flujo_luminico_ansi_lumenes' => [
            'label' => 'Flujo luminico',
            'value' => $lumens,
            'unit' => 'ANSI lumenes',
            'icon' => 'beam',
            'formula' => 'Area de pantalla x lux objetivo x factor de luz ambiente',
        ],
        'rack_requerido' => [
            'label' => 'Rack requerido',
            'value' => $rack,
            'unit' => '',
            'icon' => 'rack',
            'formula' => 'Unidades estimadas por DSP, potencia, matriz, red y reserva tecnica',
        ],
        'base_calculo' => [
            'area_m2' => $size['area'],
            'altura_promedio_m' => $environment['height'],
            'aforo_referencial' => $size['listeners'],
            'pantalla_referencial_m2' => $size['screen'],
            'entorno_label' => $environment['label'],
            'tamano_label' => $size['label'],
        ],
    ];
}

function desired_groups(string $especialidad): array
{
    $groups = [];
    if ($especialidad !== 'video') {
        $groups['captacion-mezcla'] = [
            'title' => 'Sistemas de Captacion y Mezcla',
            'categories' => ['microfonos', 'consolas-mixers'],
            'strong' => ['microfono', 'inalambrico', 'consola', 'mixer', 'mezcla', 'usb', 'digital'],
            'medium' => ['shure', 'behringer', 'yamaha', 'samson', 'audio-technica', 'voz'],
            'target' => 2,
        ];
        $groups['rack-potencia'] = [
            'title' => 'Procesamiento en Rack y Potencia',
            'categories' => ['procesadores', 'amplificadores', 'parlantes', 'cajas-acusticas', 'sonido'],
            'strong' => ['dsp', 'driverack', 'amplificador', 'potencia', 'parlante', 'caja activa', 'subwoofer', 'line array'],
            'medium' => ['rack', 'spl', '1000w', 'activo', 'pasivo', 'cobertura', 'qsc', 'jbl', 'crown'],
            'target' => 3,
        ];
    }
    if ($especialidad !== 'audio') {
        $groups['proyeccion-video'] = [
            'title' => 'Sistemas de Proyeccion y Video',
            'categories' => ['proyector', 'camara', 'matrices-video', 'tv-streaming'],
            'strong' => ['proyector', 'ansi', 'lumen', 'full hd', '4k', 'camara', 'ptz', 'hdmi'],
            'medium' => ['epson', 'benq', 'hikvision', 'sony', 'streaming', 'ip', 'matriz'],
            'target' => $especialidad === 'video' ? 3 : 2,
        ];
    }
    return $groups;
}

function product_search_text(array $product): string
{
    return normalize_text(
        ($product['name'] ?? '') . ' ' .
        ($product['brand'] ?? '') . ' ' .
        ($product['category'] ?? '') . ' ' .
        ($product['categoryLabel'] ?? '') . ' ' .
        ($product['engineeringCategory'] ?? '') . ' ' .
        ($product['technicalChain'] ?? '') . ' ' .
        ($product['description'] ?? '')
    );
}

function score_product(array $product, array $group, array $metrics): int
{
    if (($product['published'] ?? true) === false || ($product['available'] ?? true) === false) {
        return -1000;
    }

    $score = (int) ($product['trendScore'] ?? 0);
    $text = product_search_text($product);
    $category = normalize_text($product['category'] ?? '');
    $chainStage = normalize_text($product['chainStage'] ?? '');

    if ($chainStage === normalize_text((string) ($group['stage'] ?? ''))) $score += 70;
    foreach ($group['categories'] as $wanted) {
        if ($category === normalize_text($wanted)) $score += 60;
    }
    foreach ($group['strong'] as $word) {
        if (strpos($text, normalize_text($word)) !== false) $score += 26;
    }
    foreach ($group['medium'] as $word) {
        if (strpos($text, normalize_text($word)) !== false) $score += 10;
    }
    if (isset($product['stock']) && is_numeric($product['stock'])) {
        $score += min(14, max(0, (int) $product['stock']));
    }

    $spl = (int) ($metrics['presion_sonora_objetivo_db_spl']['value'] ?? 0);
    if ($spl >= 98 && strpos($text, '1000w') !== false) $score += 18;
    if ($spl >= 98 && (strpos($text, '12') !== false || strpos($text, '15') !== false)) $score += 8;
    if (($metrics['flujo_luminico_ansi_lumenes']['value'] ?? 0) >= 5000 && strpos($text, '4000') !== false) $score += 10;

    return $score;
}

function quantity_for(array $product, string $stage, array $metrics, string $tamano): int
{
    $category = normalize_text($product['category'] ?? '');
    $volume = (int) ($metrics['volumen_estimado_m3']['value'] ?? 0);

    if ($stage === 'rack-potencia' && in_array($category, ['parlantes', 'cajas-acusticas', 'sonido'], true)) {
        return max(2, min(8, (int) ceil($volume / 280)));
    }
    if ($stage === 'captacion-mezcla' && $category === 'microfonos') {
        return $tamano === 'grande' ? 4 : ($tamano === 'mediano' ? 2 : 1);
    }
    if ($stage === 'proyeccion-video' && $category === 'camara') {
        return $tamano === 'grande' ? 2 : 1;
    }
    return 1;
}

function pick_group_products(array $products, string $stage, array $group, array $metrics, string $tamano, array &$used): array
{
    $group['stage'] = $stage;
    $ranked = [];
    foreach ($products as $product) {
        $id = (string) ($product['id'] ?? $product['sku'] ?? '');
        if ($id === '' || isset($used[$id])) continue;
        $score = score_product($product, $group, $metrics);
        if ($score > 0) {
            $ranked[] = ['score' => $score, 'product' => $product];
        }
    }
    usort($ranked, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $selected = [];
    foreach ($ranked as $entry) {
        if (count($selected) >= (int) $group['target']) break;
        $product = $entry['product'];
        $id = (string) ($product['id'] ?? $product['sku'] ?? '');
        $used[$id] = true;
        $selected[] = [
            'qty' => quantity_for($product, $stage, $metrics, $tamano),
            'unit' => 'unidades',
            'role' => $group['title'],
            'chainStage' => $stage,
            'score' => $entry['score'],
            'id' => $product['id'] ?? '',
            'sku' => $product['sku'] ?? '',
            'name' => clean_string($product['name'] ?? '', 180),
            'brand' => clean_string($product['brand'] ?? '', 80),
            'category' => clean_string($product['categoryLabel'] ?? ($product['category'] ?? ''), 80),
            'description' => clean_string($product['technicalChain'] ?? $product['description'] ?? '', 680),
            'image' => trim((string) ($product['image'] ?? '')) ?: '/mizo-logo.png',
            'stock' => isset($product['stock']) ? (int) $product['stock'] : null,
            'price' => isset($product['price']) ? (int) $product['price'] : 0,
            'source' => $product['source'] ?? null,
            'available' => !isset($product['available']) || $product['available'] !== false,
        ];
    }
    return $selected;
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
        'error' => 'Variables invalidas. Debes enviar entorno, especialidad y tamano validos.',
    ]);
}

$metrics = project_metrics($entorno, $especialidad, $tamano);
$catalog = mizo_fetch_public_products(['limit' => 220]);
$products = $catalog['products'];

if (!$products) {
    respond(404, [
        'ok' => false,
        'error' => 'No se encontraron productos publicados. Ejecuta scripts/cron-trends-sync.php para nutrir MySQL.',
        'source' => $catalog['source'],
        'metricas_proyecto' => $metrics,
    ]);
}

$used = [];
$groups = [];
foreach (desired_groups($especialidad) as $stage => $group) {
    $items = pick_group_products($products, $stage, $group, $metrics, $tamano, $used);
    $groups[$stage] = [
        'title' => $group['title'],
        'products' => $items,
    ];
}

$recommendations = [];
foreach ($groups as $group) {
    foreach ($group['products'] as $product) {
        $recommendations[] = $product;
    }
}

if (!$recommendations) {
    respond(404, [
        'ok' => false,
        'error' => 'No se encontraron productos publicados con stock para esta combinacion tecnica.',
        'source' => $catalog['source'],
        'metricas_proyecto' => $metrics,
    ]);
}

respond(200, [
    'ok' => true,
    'source' => $catalog['source'] === 'mysql' ? 'MySQL tendencias mercado audiovisual nacional' : $catalog['source'],
    'criteria' => [
        'entorno' => $entorno,
        'especialidad' => $especialidad,
        'tamano' => $tamano,
    ],
    'title' => 'Informe de Ingenieria Mizo con radar diario de tendencias',
    'summary' => 'El motor calculo volumen, SPL, luminancia y rack requerido; luego cruzo esas metricas con productos tendencia disponibles en el catalogo MySQL.',
    'metricas_proyecto' => $metrics,
    'grupos' => $groups,
    'products' => $recommendations,
]);
