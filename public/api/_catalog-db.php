<?php
declare(strict_types=1);

function mizo_env(array $keys, ?string $default = null): ?string
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }
        if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
            return trim((string) $_ENV[$key]);
        }
    }
    return $default;
}

function mizo_products_table(): string
{
    $table = mizo_env(['MIZO_PRODUCTS_TABLE', 'DB_PRODUCTS_TABLE'], 'mizo_market_trend_products') ?? 'mizo_market_trend_products';
    return preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: 'mizo_market_trend_products';
}

function mizo_category_label(?string $category): string
{
    $labels = [
        'captacion-mezcla' => 'Sistemas de Captacion y Mezcla',
        'rack-potencia' => 'Procesamiento en Rack y Potencia',
        'proyeccion-video' => 'Sistemas de Proyeccion y Video',
        'sonido' => 'Audio Profesional',
        'proyector' => 'Proyectores',
        'camara' => 'Camaras',
        'microfonos' => 'Microfonos',
        'consolas-mixers' => 'Consolas y Mixers',
        'parlantes' => 'Parlantes',
        'cajas-acusticas' => 'Cajas Acusticas',
        'amplificadores' => 'Amplificadores',
        'procesadores' => 'Procesadores',
        'matrices-video' => 'Matrices de Video',
    ];
    $key = trim((string) $category);
    return $labels[$key] ?? ($key !== '' ? ucwords(str_replace(['-', '_'], ' ', $key)) : 'Producto audiovisual');
}

function mizo_slug(string $value): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $slug = strtolower((string) ($ascii ?: $value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'producto-' . substr(sha1($value), 0, 10);
}

function mizo_pdo(): ?PDO
{
    $host = mizo_env(['MIZO_DB_HOST', 'DB_HOST', 'MYSQL_HOST']);
    $database = mizo_env(['MIZO_DB_NAME', 'DB_NAME', 'MYSQL_DATABASE']);
    $user = mizo_env(['MIZO_DB_USER', 'DB_USER', 'MYSQL_USER']);
    $password = mizo_env(['MIZO_DB_PASS', 'DB_PASS', 'MYSQL_PASSWORD'], '');
    $port = mizo_env(['MIZO_DB_PORT', 'DB_PORT', 'MYSQL_PORT'], '3306');
    $charset = mizo_env(['MIZO_DB_CHARSET', 'DB_CHARSET'], 'utf8mb4');

    if (!$host || !$database || !$user) {
        return null;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);
    try {
        return new PDO($dsn, $user, (string) $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $error) {
        return null;
    }
}

function mizo_require_pdo(): PDO
{
    $pdo = mizo_pdo();
    if (!$pdo) {
        throw new RuntimeException('No hay conexion MySQL configurada. Define MIZO_DB_HOST, MIZO_DB_NAME, MIZO_DB_USER y MIZO_DB_PASS.');
    }
    return $pdo;
}

function mizo_ensure_products_table(PDO $pdo): void
{
    $table = mizo_products_table();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` VARCHAR(180) NOT NULL,
            `sku` VARCHAR(120) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `brand` VARCHAR(120) NOT NULL DEFAULT '',
            `category` VARCHAR(90) NOT NULL DEFAULT '',
            `category_label` VARCHAR(160) NOT NULL DEFAULT '',
            `engineering_category` VARCHAR(120) NOT NULL DEFAULT '',
            `chain_stage` VARCHAR(80) NOT NULL DEFAULT '',
            `description` TEXT NULL,
            `description_long` MEDIUMTEXT NULL,
            `technical_chain` TEXT NULL,
            `image` VARCHAR(600) NOT NULL DEFAULT '',
            `source_store` VARCHAR(120) NOT NULL DEFAULT '',
            `source_url` VARCHAR(600) NOT NULL DEFAULT '',
            `source_image` VARCHAR(600) NOT NULL DEFAULT '',
            `base_price` INT NOT NULL DEFAULT 0,
            `selling_price` INT NOT NULL DEFAULT 0,
            `stock` INT NULL,
            `available` TINYINT(1) NOT NULL DEFAULT 1,
            `published` TINYINT(1) NOT NULL DEFAULT 1,
            `trend_score` INT NOT NULL DEFAULT 0,
            `captured_at` DATE NULL,
            `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_sku` (`sku`),
            KEY `idx_chain_stage` (`chain_stage`),
            KEY `idx_category` (`category`),
            KEY `idx_public` (`published`, `available`, `trend_score`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function mizo_int_or_null($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (int) $value : null;
}

function mizo_normalize_product(array $product): array
{
    $sku = trim((string) ($product['sku'] ?? ''));
    $name = trim((string) ($product['name'] ?? ''));
    $category = trim((string) ($product['category'] ?? ''));
    $id = trim((string) ($product['id'] ?? ''));
    if ($id === '') {
        $id = mizo_slug(($sku !== '' ? $sku : $name) . '-' . $category);
    }

    return [
        'id' => $id,
        'sku' => $sku,
        'name' => $name,
        'brand' => trim((string) ($product['brand'] ?? 'Generico')),
        'category' => $category,
        'category_label' => trim((string) ($product['categoryLabel'] ?? $product['category_label'] ?? mizo_category_label($category))),
        'engineering_category' => trim((string) ($product['engineeringCategory'] ?? $product['engineering_category'] ?? 'Ingenieria audiovisual')),
        'chain_stage' => trim((string) ($product['chainStage'] ?? $product['chain_stage'] ?? $category)),
        'description' => trim((string) ($product['description'] ?? '')),
        'description_long' => trim((string) ($product['descriptionLong'] ?? $product['description_long'] ?? $product['description'] ?? '')),
        'technical_chain' => trim((string) ($product['technicalChain'] ?? $product['technical_chain'] ?? $product['description'] ?? '')),
        'image' => trim((string) ($product['image'] ?? '')),
        'source_store' => trim((string) ($product['sourceStore'] ?? $product['source_store'] ?? ($product['source']['store'] ?? ''))),
        'source_url' => trim((string) ($product['sourceUrl'] ?? $product['source_url'] ?? ($product['source']['url'] ?? ''))),
        'source_image' => trim((string) ($product['sourceImage'] ?? $product['source_image'] ?? ($product['source']['image'] ?? ''))),
        'base_price' => (int) ($product['basePrice'] ?? $product['base_price'] ?? 0),
        'selling_price' => (int) ($product['sellingPrice'] ?? $product['selling_price'] ?? $product['price'] ?? 0),
        'stock' => mizo_int_or_null($product['stock'] ?? null),
        'available' => !isset($product['available']) || $product['available'] !== false,
        'published' => !isset($product['published']) || $product['published'] !== false,
        'trend_score' => (int) ($product['trendScore'] ?? $product['trend_score'] ?? 0),
        'captured_at' => trim((string) ($product['capturedAt'] ?? $product['captured_at'] ?? date('Y-m-d'))),
    ];
}

function mizo_upsert_trend_products(PDO $pdo, array $products): array
{
    mizo_ensure_products_table($pdo);
    $table = mizo_products_table();
    $sql = "
        INSERT INTO `{$table}` (
            id, sku, name, brand, category, category_label, engineering_category, chain_stage,
            description, description_long, technical_chain, image, source_store, source_url, source_image,
            base_price, selling_price, stock, available, published, trend_score, captured_at, last_seen_at
        ) VALUES (
            :id, :sku, :name, :brand, :category, :category_label, :engineering_category, :chain_stage,
            :description, :description_long, :technical_chain, :image, :source_store, :source_url, :source_image,
            :base_price, :selling_price, :stock, :available, :published, :trend_score, :captured_at, NOW()
        )
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            brand = VALUES(brand),
            category = VALUES(category),
            category_label = VALUES(category_label),
            engineering_category = VALUES(engineering_category),
            chain_stage = VALUES(chain_stage),
            description = VALUES(description),
            description_long = VALUES(description_long),
            technical_chain = VALUES(technical_chain),
            image = VALUES(image),
            source_store = VALUES(source_store),
            source_url = VALUES(source_url),
            source_image = VALUES(source_image),
            base_price = VALUES(base_price),
            selling_price = VALUES(selling_price),
            stock = VALUES(stock),
            available = VALUES(available),
            published = VALUES(published),
            trend_score = VALUES(trend_score),
            captured_at = VALUES(captured_at),
            last_seen_at = NOW()
    ";
    $stmt = $pdo->prepare($sql);
    $summary = ['received' => count($products), 'upserted' => 0, 'skipped' => 0];

    foreach ($products as $product) {
        $row = mizo_normalize_product($product);
        if ($row['sku'] === '' || $row['name'] === '') {
            $summary['skipped']++;
            continue;
        }
        $stmt->execute([
            ':id' => $row['id'],
            ':sku' => $row['sku'],
            ':name' => $row['name'],
            ':brand' => $row['brand'],
            ':category' => $row['category'],
            ':category_label' => $row['category_label'],
            ':engineering_category' => $row['engineering_category'],
            ':chain_stage' => $row['chain_stage'],
            ':description' => $row['description'],
            ':description_long' => $row['description_long'],
            ':technical_chain' => $row['technical_chain'],
            ':image' => $row['image'],
            ':source_store' => $row['source_store'],
            ':source_url' => $row['source_url'],
            ':source_image' => $row['source_image'],
            ':base_price' => $row['base_price'],
            ':selling_price' => $row['selling_price'],
            ':stock' => $row['stock'],
            ':available' => $row['available'] ? 1 : 0,
            ':published' => $row['published'] ? 1 : 0,
            ':trend_score' => $row['trend_score'],
            ':captured_at' => $row['captured_at'] !== '' ? $row['captured_at'] : date('Y-m-d'),
        ]);
        $summary['upserted']++;
    }

    return $summary;
}

function mizo_public_product(array $row): array
{
    $category = (string) ($row['category'] ?? '');
    $price = (int) ($row['selling_price'] ?? $row['sellingPrice'] ?? $row['price'] ?? 0);
    if ($price <= 0 && isset($row['base_price'])) {
        $price = (int) round(((int) $row['base_price']) * 1.2 / 10) * 10;
    }

    return [
        'id' => (string) ($row['id'] ?? ''),
        'sku' => (string) ($row['sku'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'brand' => (string) ($row['brand'] ?? ''),
        'category' => $category,
        'categoryLabel' => (string) ($row['category_label'] ?? $row['categoryLabel'] ?? mizo_category_label($category)),
        'engineeringCategory' => (string) ($row['engineering_category'] ?? $row['engineeringCategory'] ?? ''),
        'chainStage' => (string) ($row['chain_stage'] ?? $row['chainStage'] ?? $category),
        'description' => (string) ($row['description'] ?? ''),
        'descriptionLong' => (string) ($row['description_long'] ?? $row['descriptionLong'] ?? $row['description'] ?? ''),
        'technicalChain' => (string) ($row['technical_chain'] ?? $row['technicalChain'] ?? $row['description'] ?? ''),
        'image' => (string) ($row['image'] ?? '/mizo-logo.png'),
        'price' => $price,
        'basePrice' => (int) ($row['base_price'] ?? $row['basePrice'] ?? 0),
        'stock' => isset($row['stock']) ? (int) $row['stock'] : 0,
        'available' => !isset($row['available']) || (bool) $row['available'],
        'published' => !isset($row['published']) || (bool) $row['published'],
        'trendScore' => (int) ($row['trend_score'] ?? $row['trendScore'] ?? 0),
        'source' => [
            'store' => (string) ($row['source_store'] ?? $row['source']['store'] ?? ''),
            'url' => (string) ($row['source_url'] ?? $row['source']['url'] ?? ''),
            'image' => (string) ($row['source_image'] ?? $row['source']['image'] ?? ''),
            'capturedAt' => (string) ($row['captured_at'] ?? $row['source']['capturedAt'] ?? ''),
        ],
    ];
}

function mizo_fetch_products_from_mysql(array $filters = []): ?array
{
    $pdo = mizo_pdo();
    if (!$pdo) {
        return null;
    }
    mizo_ensure_products_table($pdo);
    $table = mizo_products_table();
    $where = ['published = 1', 'available = 1'];
    $params = [];

    if (!empty($filters['chainStages']) && is_array($filters['chainStages'])) {
        $placeholders = [];
        foreach (array_values($filters['chainStages']) as $index => $stage) {
            $key = ':stage' . $index;
            $placeholders[] = $key;
            $params[$key] = (string) $stage;
        }
        $where[] = 'chain_stage IN (' . implode(',', $placeholders) . ')';
    }

    if (!empty($filters['categories']) && is_array($filters['categories'])) {
        $placeholders = [];
        foreach (array_values($filters['categories']) as $index => $category) {
            $key = ':category' . $index;
            $placeholders[] = $key;
            $params[$key] = (string) $category;
        }
        $where[] = 'category IN (' . implode(',', $placeholders) . ')';
    }

    $limit = max(1, min(1000, (int) ($filters['limit'] ?? 160)));
    $sql = "SELECT * FROM `{$table}` WHERE " . implode(' AND ', $where) . " ORDER BY trend_score DESC, updated_at DESC, name ASC LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('mizo_public_product', $stmt->fetchAll());
}

function mizo_fetch_products_from_json(): array
{
    $paths = [
        __DIR__ . '/../catalogo-productos.json',
        __DIR__ . '/../../src/data/products.json',
        __DIR__ . '/../data/products.json',
        __DIR__ . '/../../data/products.json',
    ];
    foreach ($paths as $path) {
        if (!is_file($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        $products = json_decode((string) $raw, true);
        if (is_array($products)) {
			return array_map(function ($product) {
				return mizo_public_product(mizo_normalize_product((array) $product));
			}, $products);
        }
    }
    return [];
}

function mizo_seed_trend_products(): array
{
	$capturedAt = date('Y-m-d');
	$items = [
		[
			'id' => 'seed-shure-slxd24-sm58',
			'sku' => 'AM-SHU-SLXD24-SM58',
			'name' => 'Shure SLXD24/SM58 Sistema Inalambrico Digital',
			'brand' => 'Shure',
			'category' => 'microfonos',
			'categoryLabel' => 'Microfonos',
			'engineeringCategory' => 'Captacion vocal inalambrica',
			'chainStage' => 'captacion-mezcla',
			'description' => 'Sistema digital UHF para voz principal con receptor diversity, capsula dinamica SM58 y salida balanceada XLR hacia consola o DSP.',
			'technicalChain' => 'Captacion vocal inalambrica -> preamplificacion de consola -> DSP de sala -> amplificacion -> sistema PA.',
			'image' => 'https://www.audiomusica.com/on/demandware.static/-/Sites-master-catalog/default/dw7a14606f/images/large/SHU-SLXD24SM58.jpg',
			'basePrice' => 719990,
			'price' => 863990,
			'stock' => 6,
			'trendScore' => 98,
			'source' => ['store' => 'Audiomusica', 'url' => 'https://www.audiomusica.com/', 'image' => '', 'capturedAt' => $capturedAt],
		],
		[
			'id' => 'seed-behringer-x32-compact',
			'sku' => 'AM-BEH-X32-COMPACT',
			'name' => 'Behringer X32 Compact Consola Digital 40 Canales',
			'brand' => 'Behringer',
			'category' => 'consolas-mixers',
			'categoryLabel' => 'Consolas y Mixers',
			'engineeringCategory' => 'Mezcla digital y control de buses',
			'chainStage' => 'captacion-mezcla',
			'description' => 'Consola digital con preamplificadores, buses de mezcla, escenas, interfaz USB y ruteo para sistemas AV instalados.',
			'technicalChain' => 'Microfonia y fuentes -> mezcla digital -> buses auxiliares -> procesador de sistema -> potencia y parlantes.',
			'image' => 'https://www.audiomusica.com/on/demandware.static/-/Sites-master-catalog/default/dw946275b2/images/large/BEH-X32COMPACT.jpg',
			'basePrice' => 2099990,
			'price' => 2519990,
			'stock' => 3,
			'trendScore' => 95,
			'source' => ['store' => 'Audiomusica', 'url' => 'https://www.audiomusica.com/', 'image' => '', 'capturedAt' => $capturedAt],
		],
		[
			'id' => 'seed-dbx-driverack-pa2',
			'sku' => 'PM-DBX-PA2',
			'name' => 'dbx DriveRack PA2 Procesador de Altavoces',
			'brand' => 'dbx',
			'category' => 'procesadores',
			'categoryLabel' => 'Procesadores',
			'engineeringCategory' => 'DSP de sistema para crossover y proteccion',
			'chainStage' => 'rack-potencia',
			'description' => 'Procesador rack 1U con AutoEQ, limitadores, control de feedback, alineacion temporal y crossover para proteger el sistema.',
			'technicalChain' => 'Salida de consola -> DSP/crossover/limitacion -> amplificadores o parlantes activos -> cobertura del recinto.',
			'image' => 'https://www.promusic.cl/cdn/shop/products/dbx-driverack-pa2.jpg',
			'basePrice' => 459990,
			'price' => 551990,
			'stock' => 5,
			'trendScore' => 91,
			'source' => ['store' => 'Promusic', 'url' => 'https://www.promusic.cl/', 'image' => '', 'capturedAt' => $capturedAt],
		],
		[
			'id' => 'seed-qsc-cp12',
			'sku' => 'PM-QSC-CP12',
			'name' => 'QSC CP12 Parlante Activo 12 Pulgadas 1000W',
			'brand' => 'QSC',
			'category' => 'parlantes',
			'categoryLabel' => 'Parlantes',
			'engineeringCategory' => 'Transduccion final de alta presion sonora',
			'chainStage' => 'rack-potencia',
			'description' => 'Caja activa de 12 pulgadas con amplificacion Clase D, DSP interno, cobertura amplia y entradas balanceadas para recintos medianos.',
			'technicalChain' => 'Senal procesada -> amplificacion Clase D interna -> transductor LF/HF -> SPL uniforme al publico.',
			'image' => 'https://www.promusic.cl/cdn/shop/products/qsc-cp12.jpg',
			'basePrice' => 699990,
			'price' => 839990,
			'stock' => 8,
			'trendScore' => 94,
			'source' => ['store' => 'Promusic', 'url' => 'https://www.promusic.cl/', 'image' => '', 'capturedAt' => $capturedAt],
		],
		[
			'id' => 'seed-epson-eb-fh52',
			'sku' => 'CR-EPSON-EB-FH52',
			'name' => 'Epson EB-FH52 Proyector 3LCD Full HD 4000 Lumenes',
			'brand' => 'Epson',
			'category' => 'proyector',
			'categoryLabel' => 'Proyectores',
			'engineeringCategory' => 'Proyeccion Full HD de alto brillo',
			'chainStage' => 'proyeccion-video',
			'description' => 'Proyector 3LCD Full HD de 4000 lumenes para salas iluminadas, auditorios y aulas con fuente HDMI o matriz AV.',
			'technicalChain' => 'Fuente HDMI -> matriz o extensor -> proyector Full HD -> superficie de proyeccion calibrada por luz ambiente.',
			'image' => 'https://www.casaroyal.cl/arquivos/ids/210483/epson-eb-fh52.jpg',
			'basePrice' => 799990,
			'price' => 959990,
			'stock' => 7,
			'trendScore' => 97,
			'source' => ['store' => 'Casa Royal', 'url' => 'https://www.casaroyal.cl/', 'image' => '', 'capturedAt' => $capturedAt],
		],
		[
			'id' => 'seed-hikvision-ptz-4mp',
			'sku' => 'CR-HIK-DS2DE2A404IW',
			'name' => 'Hikvision DS-2DE2A404IW-DE3 Camara PTZ IP 4MP',
			'brand' => 'Hikvision',
			'category' => 'camara',
			'categoryLabel' => 'Camaras',
			'engineeringCategory' => 'Captura de video IP para streaming y registro',
			'chainStage' => 'proyeccion-video',
			'description' => 'Camara PTZ IP 4MP con movimiento remoto, vision IR y salida por red para monitoreo, streaming o registro de eventos.',
			'technicalChain' => 'Camara IP/PoE -> switch de red -> NVR o plataforma de streaming -> visualizacion y control AV.',
			'image' => 'https://www.casaroyal.cl/arquivos/ids/214810/hikvision-ds-2de2a404iw.jpg',
			'basePrice' => 399990,
			'price' => 479990,
			'stock' => 9,
			'trendScore' => 89,
			'source' => ['store' => 'Casa Royal', 'url' => 'https://www.casaroyal.cl/', 'image' => '', 'capturedAt' => $capturedAt],
		],
	];

	return array_map(function ($product) {
		return mizo_public_product(mizo_normalize_product($product));
	}, $items);
}

function mizo_fetch_public_products(array $filters = []): array
{
    $mysql = mizo_fetch_products_from_mysql($filters);
    if (is_array($mysql) && count($mysql) > 0) {
        return ['products' => $mysql, 'source' => 'mysql'];
    }

    $fallback = mizo_fetch_products_from_json();
	if (count($fallback) > 0) {
		return ['products' => $fallback, 'source' => 'json-fallback'];
	}

	return ['products' => mizo_seed_trend_products(), 'source' => 'seed-trends'];
}

function mizo_runtime_storage_root(): string
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

    throw new RuntimeException('No se pudo preparar el almacenamiento local.');
}

function mizo_showcase_config_path(): string
{
    return mizo_runtime_storage_root() . '/showcase.json';
}

function mizo_public_root(): string
{
    return dirname(__DIR__);
}

function mizo_resolve_product_image(array $product): string
{
    $publicRoot = mizo_public_root();
    $image = trim((string) ($product['image'] ?? ''));
    $sourceImage = trim((string) ($product['source']['image'] ?? $product['source_image'] ?? $product['sourceImage'] ?? ''));
    $id = trim((string) ($product['id'] ?? ''));

    $candidates = [];
    if ($image !== '' && !preg_match('#^https?://#i', $image)) {
        $candidates[] = $image;
    }
    if ($id !== '') {
        $candidates[] = '/images/productos/' . $id . '.jpg';
    }

    foreach ($candidates as $candidate) {
        $localPath = $publicRoot . $candidate;
        if (is_file($localPath) && (int) filesize($localPath) > 1500) {
            return $candidate;
        }
    }

    if ($image !== '' && preg_match('#^https?://#i', $image)) {
        return $image;
    }
    if ($sourceImage !== '' && preg_match('#^https?://#i', $sourceImage)) {
        return $sourceImage;
    }

    return '/mizo-logo.png';
}

function mizo_default_showcase_items(): array
{
    return [];
}

function mizo_read_showcase_config(): array
{
    $path = mizo_showcase_config_path();
    if (is_file($path)) {
        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data)) {
            if (!isset($data['mode'])) {
                $data['mode'] = 'all';
            }
            if (!isset($data['items']) || !is_array($data['items'])) {
                $data['items'] = [];
            }
            return $data;
        }
    }

    return [
        'mode' => 'all',
        'updatedAt' => null,
        'items' => [],
        'source' => 'default-all',
    ];
}

function mizo_write_showcase_config(array $items): array
{
    $normalized = [];
    $sortOrder = 1;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = trim((string) ($item['id'] ?? ''));
        $sku = trim((string) ($item['sku'] ?? ''));
        if ($id === '' && $sku === '') {
            continue;
        }
        $normalized[] = [
            'id' => $id,
            'sku' => $sku,
            'sortOrder' => (int) ($item['sortOrder'] ?? $sortOrder),
            'note' => trim((string) ($item['note'] ?? '')),
        ];
        $sortOrder++;
    }

    usort($normalized, static function (array $a, array $b): int {
        return ($a['sortOrder'] <=> $b['sortOrder']) ?: strcmp($a['id'], $b['id']);
    });

    $payload = [
        'mode' => 'curated',
        'updatedAt' => gmdate('c'),
        'items' => $normalized,
    ];

    $path = mizo_showcase_config_path();
    $tmp = $path . '.tmp';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('No se pudo guardar la vitrina web.');
    }

    return $payload;
}

function mizo_write_showcase_mode(string $mode): array
{
    $payload = [
        'mode' => $mode === 'curated' ? 'curated' : 'all',
        'updatedAt' => gmdate('c'),
        'items' => $mode === 'curated' ? (mizo_read_showcase_config()['items'] ?? []) : [],
    ];

    $path = mizo_showcase_config_path();
    $tmp = $path . '.tmp';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('No se pudo guardar la vitrina web.');
    }

    return $payload;
}

function mizo_showcase_product(array $product, string $note = ''): array
{
    $description = trim((string) ($product['descriptionLong'] ?? $product['description'] ?? ''));
    if ($description === '') {
        $description = trim((string) ($product['description'] ?? ''));
    }
    if (strlen($description) > 260) {
        $description = rtrim(substr($description, 0, 257)) . '...';
    }

    return [
        'id' => (string) ($product['id'] ?? ''),
        'sku' => (string) ($product['sku'] ?? ''),
        'name' => (string) ($product['name'] ?? ''),
        'brand' => (string) ($product['brand'] ?? ''),
        'category' => (string) ($product['category'] ?? ''),
        'categoryLabel' => (string) ($product['categoryLabel'] ?? mizo_category_label($product['category'] ?? '')),
        'engineeringCategory' => (string) ($product['engineeringCategory'] ?? ''),
        'chainStage' => (string) ($product['chainStage'] ?? ''),
        'description' => $description,
		'image' => mizo_resolve_product_image($product),
		'sourceImage' => trim((string) ($product['source']['image'] ?? $product['source_image'] ?? $product['sourceImage'] ?? '')),
		'note' => $note,
    ];
}

function mizo_fetch_showcase_products(): array
{
    $config = mizo_read_showcase_config();
    $mode = (string) ($config['mode'] ?? 'all');
    $items = is_array($config['items'] ?? null) ? $config['items'] : [];
    $catalog = mizo_fetch_public_products(['limit' => 1000])['products'];

    $notesByKey = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = trim((string) ($item['id'] ?? $item['sku'] ?? ''));
        if ($key === '') {
            continue;
        }
        $notesByKey[$key] = trim((string) ($item['note'] ?? ''));
    }

    if ($mode === 'curated' && count($items) > 0) {
        $byId = [];
        $bySku = [];
        foreach ($catalog as $product) {
            $byId[(string) ($product['id'] ?? '')] = $product;
            $sku = trim((string) ($product['sku'] ?? ''));
            if ($sku !== '') {
                $bySku[$sku] = $product;
            }
        }

        $products = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string) ($item['id'] ?? ''));
            $sku = trim((string) ($item['sku'] ?? ''));
            $product = null;
            if ($id !== '' && isset($byId[$id])) {
                $product = $byId[$id];
            } elseif ($sku !== '' && isset($bySku[$sku])) {
                $product = $bySku[$sku];
            } elseif ($id !== '' && isset($bySku[$id])) {
                $product = $bySku[$id];
            }
            if (!$product) {
                continue;
            }
            $shown = mizo_showcase_product($product, trim((string) ($item['note'] ?? '')));
            if ($shown['image'] === '/mizo-logo.png') {
                continue;
            }
            $products[] = $shown;
        }

        return [
            'products' => $products,
            'count' => count($products),
            'updatedAt' => $config['updatedAt'] ?? null,
            'mode' => 'curated',
            'source' => isset($config['source']) ? (string) $config['source'] : 'configured',
        ];
    }

    $products = [];
    foreach ($catalog as $product) {
        $shown = mizo_showcase_product($product, $notesByKey[(string) ($product['id'] ?? '')] ?? $notesByKey[(string) ($product['sku'] ?? '')] ?? '');
        if ($shown['image'] === '/mizo-logo.png') {
            continue;
        }
        $products[] = $shown;
    }

    usort($products, static function (array $a, array $b): int {
        $category = strcmp((string) ($a['categoryLabel'] ?? ''), (string) ($b['categoryLabel'] ?? ''));
        if ($category !== 0) {
            return $category;
        }
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return [
        'products' => $products,
        'count' => count($products),
        'updatedAt' => $config['updatedAt'] ?? null,
        'mode' => 'all',
        'source' => isset($config['source']) ? (string) $config['source'] : 'catalog-all',
    ];
}

function mizo_admin_password_ok(?string $password): bool
{
    $expected = mizo_env(['ADMIN_PASSWORD', 'MIZO_ADMIN_PASSWORD'], 'mizo2026') ?? 'mizo2026';
    return is_string($password) && hash_equals($expected, $password);
}
