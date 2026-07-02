<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/api/_catalog-db.php';

/**
 * Cron diario sugerido:
 * 15 3 * * * /usr/bin/php /home/USUARIO/public_html/scripts/cron-trends-sync.php >> /home/USUARIO/logs/mizo-trends-sync.log 2>&1
 *
 * Variables requeridas:
 * MIZO_DB_HOST, MIZO_DB_NAME, MIZO_DB_USER, MIZO_DB_PASS
 */

function trend_product(
    string $store,
    string $sku,
    string $name,
    string $brand,
    string $category,
    string $chainStage,
    string $engineeringCategory,
    string $description,
    string $image,
    string $sourceUrl,
    int $basePrice,
    ?int $stock,
    int $trendScore
): array {
    return [
        'id' => mizo_slug($store . '-' . $sku . '-' . $name),
        'sku' => $sku,
        'name' => $name,
        'brand' => $brand,
        'category' => $category,
        'categoryLabel' => mizo_category_label($category),
        'engineeringCategory' => $engineeringCategory,
        'chainStage' => $chainStage,
        'description' => $description,
        'descriptionLong' => $description,
        'technicalChain' => sprintf(
            'Fuente/tendencia: %s. Cadena electroacustica recomendada: captacion o reproduccion, mezcla/DSP, amplificacion, distribucion y transduccion final segun el recinto.',
            $store
        ),
        'image' => $image,
        'sourceStore' => $store,
        'sourceUrl' => $sourceUrl,
        'sourceImage' => $image,
        'basePrice' => $basePrice,
        'sellingPrice' => (int) round($basePrice * 1.2 / 10) * 10,
        'stock' => $stock,
        'available' => true,
        'published' => true,
        'trendScore' => $trendScore,
        'capturedAt' => date('Y-m-d'),
    ];
}

function simulated_trends(): array
{
    return [
        trend_product(
            'Audiomusica',
            'AM-SHU-SLXD24-SM58',
            'Shure SLXD24/SM58 Sistema Inalambrico Digital',
            'Shure',
            'microfonos',
            'captacion-mezcla',
            'Captacion vocal inalambrica para cadena electroacustica profesional',
            'Sistema digital UHF para voz principal. Integra capsula dinamica SM58, receptor diversity y salida balanceada XLR para entrar a consola digital o procesador DSP con baja latencia.',
            'https://www.audiomusica.com/on/demandware.static/-/Sites-master-catalog/default/dw7a14606f/images/large/SHU-SLXD24SM58.jpg',
            'https://www.audiomusica.com/',
            719990,
            6,
            98
        ),
        trend_product(
            'Audiomusica',
            'AM-BEH-X32-COMPACT',
            'Behringer X32 Compact Consola Digital 40 Canales',
            'Behringer',
            'consolas-mixers',
            'captacion-mezcla',
            'Mezcla digital y control de buses para sistemas de audio instalados',
            'Consola digital con preamplificadores MIDAS, buses de mezcla, escenas, USB multicanal y salidas balanceadas. Opera como centro de ruteo entre microfonia, DSP, amplificacion y sistemas PA.',
            'https://www.audiomusica.com/on/demandware.static/-/Sites-master-catalog/default/dw946275b2/images/large/BEH-X32COMPACT.jpg',
            'https://www.audiomusica.com/',
            2099990,
            3,
            95
        ),
        trend_product(
            'Promusic',
            'PM-QSC-CP12',
            'QSC CP12 Parlante Activo 12 Pulgadas 1000W',
            'QSC',
            'parlantes',
            'rack-potencia',
            'Transduccion final de alta presion sonora para recintos medianos',
            'Caja activa de 12 pulgadas con amplificacion Clase D, DSP interno, cobertura amplia y entradas balanceadas. Recibe senal desde consola o procesador para entregar SPL uniforme al publico.',
            'https://www.promusic.cl/cdn/shop/products/qsc-cp12.jpg',
            'https://www.promusic.cl/',
            699990,
            8,
            94
        ),
        trend_product(
            'Promusic',
            'PM-DBX-PA2',
            'dbx DriveRack PA2 Procesador de Altavoces',
            'dbx',
            'procesadores',
            'rack-potencia',
            'DSP de sistema para crossover, limitacion y ecualizacion de sala',
            'Procesador rack 1U con AutoEQ, control de feedback, limitadores, alineacion temporal y crossover. Se instala entre mezcla y potencia para proteger parlantes y ajustar la respuesta del recinto.',
            'https://www.promusic.cl/cdn/shop/products/dbx-driverack-pa2.jpg',
            'https://www.promusic.cl/',
            459990,
            5,
            91
        ),
        trend_product(
            'Promusic',
            'PM-CRO-XLI2500',
            'Crown XLi 2500 Amplificador de Potencia',
            'Crown',
            'amplificadores',
            'rack-potencia',
            'Amplificacion rack para cajas pasivas y zonas de audio distribuido',
            'Etapa de potencia estereo para rack con entradas balanceadas, modo bridge y reserva dinamica. Recibe senal procesada desde DSP y alimenta cajas acusticas pasivas o lineas de refuerzo.',
            'https://www.promusic.cl/cdn/shop/products/crown-xli2500.jpg',
            'https://www.promusic.cl/',
            549990,
            4,
            90
        ),
        trend_product(
            'Casa Royal',
            'CR-EPSON-EB-FH52',
            'Epson EB-FH52 Proyector 3LCD Full HD 4000 Lumenes',
            'Epson',
            'proyector',
            'proyeccion-video',
            'Proyeccion Full HD para auditorios, salas corporativas y aulas',
            'Proyector 3LCD Full HD de alto brillo para salas iluminadas. Recibe HDMI desde matriz o computador, proyecta imagen principal y se integra con audio por la cadena de control AV.',
            'https://www.casaroyal.cl/arquivos/ids/210483/epson-eb-fh52.jpg',
            'https://www.casaroyal.cl/',
            799990,
            7,
            97
        ),
        trend_product(
            'Casa Royal',
            'CR-BENQ-MH733',
            'BenQ MH733 Proyector Full HD 4000 ANSI Lumenes',
            'BenQ',
            'proyector',
            'proyeccion-video',
            'Video profesional de alta luminosidad para salas medianas',
            'Proyector DLP Full HD con 4000 ANSI lumenes, correccion keystone y conectividad HDMI/VGA. Adecuado para cadena de video con fuentes, matriz, extensor y pantalla de proyeccion.',
            'https://www.casaroyal.cl/arquivos/ids/221918/benq-mh733.jpg',
            'https://www.casaroyal.cl/',
            739990,
            5,
            93
        ),
        trend_product(
            'Casa Royal',
            'CR-HIK-DS2DE2A404IW',
            'Hikvision DS-2DE2A404IW-DE3 Camara PTZ IP 4MP',
            'Hikvision',
            'camara',
            'proyeccion-video',
            'Captura de video IP para monitoreo, streaming o registro de eventos',
            'Camara PTZ IP 4MP con movimiento remoto, vision IR y salida por red. Se integra a switch PoE, NVR o plataforma de streaming para registro audiovisual y monitoreo tecnico.',
            'https://www.casaroyal.cl/arquivos/ids/214810/hikvision-ds-2de2a404iw.jpg',
            'https://www.casaroyal.cl/',
            399990,
            9,
            89
        ),
        trend_product(
            'Audiomusica',
            'AM-JBL-PRX912',
            'JBL PRX912 Parlante Activo 12 Pulgadas',
            'JBL',
            'parlantes',
            'rack-potencia',
            'Sistema PA activo con DSP para alta cobertura e inteligibilidad',
            'Parlante activo de dos vias con DSP, Bluetooth de control y amplificacion integrada. Funciona como elemento final de la cadena electroacustica para voz, musica y refuerzo general.',
            'https://www.audiomusica.com/on/demandware.static/-/Sites-master-catalog/default/dw16d435a9/images/large/JBL-PRX912.jpg',
            'https://www.audiomusica.com/',
            979990,
            4,
            96
        ),
    ];
}

function main(array $argv): void
{
    $dryRun = in_array('--dry-run', $argv, true);
    $json = in_array('--json', $argv, true);
    $products = simulated_trends();

    if ($dryRun) {
        $payload = ['ok' => true, 'mode' => 'dry-run', 'count' => count($products), 'products' => $products];
        echo $json ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL : sprintf("[%s] Dry run: %d productos tendencia listos.\n", date('c'), count($products));
        return;
    }

    $pdo = mizo_require_pdo();
    $summary = mizo_upsert_trend_products($pdo, $products);
    $payload = ['ok' => true, 'mode' => 'mysql-upsert', 'summary' => $summary, 'capturedAt' => date('c')];

    if ($json) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
        return;
    }

    printf(
        "[%s] Tendencias sincronizadas: recibidos=%d upserted=%d omitidos=%d\n",
        $payload['capturedAt'],
        $summary['received'],
        $summary['upserted'],
        $summary['skipped']
    );
}

try {
    main($argv ?? []);
} catch (Throwable $error) {
    fwrite(STDERR, sprintf("[%s] ERROR cron-trends-sync: %s\n", date('c'), $error->getMessage()));
    exit(1);
}
