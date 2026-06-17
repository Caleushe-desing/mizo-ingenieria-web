<?php
declare(strict_types=1);

/**
 * Motor de informes PDF profesionales Mizo.
 *
 * Expone mizo_generar_pdf_informe(array $lead): array que construye el informe
 * de Ingenieria con membrete Mizo (logo + cabecera azul #0284c7), metricas de
 * ingenieria, grupos de equipamiento (captacion, rack, proyeccion) y el mensaje
 * de cierre estrategico.
 *
 * El motor usa dompdf/dompdf si esta disponible (composer install). Si no lo
 * encuentra, entrega un fallback HTML autocontenido para que el flujo de correo
 * siga adjuntando el documento del diagnostico.
 */

const MIZO_PDF_BRAND_BLUE = '#0284c7';
const MIZO_PDF_CLOSING_MESSAGE = 'Hemos recibido tu solicitud de diagnóstico. Un especialista de Mizo revisará los detalles de tu proyecto y te contactará para optimizar tu propuesta de ingeniería y entregarte una solución personalizada ajustada a tus necesidades operativas y presupuestarias.';

if (!function_exists('mizo_pdf_e')) {
    function mizo_pdf_e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('mizo_pdf_load_autoloader')) {
    function mizo_pdf_load_autoloader(): bool
    {
        if (class_exists('Dompdf\\Dompdf')) {
            return true;
        }
        $candidates = [
            __DIR__ . '/../../vendor/autoload.php',
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/vendor/autoload.php',
            dirname(__DIR__, 2) . '/vendor/autoload.php',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                if (class_exists('Dompdf\\Dompdf')) {
                    return true;
                }
            }
        }
        return class_exists('Dompdf\\Dompdf');
    }
}

if (!function_exists('mizo_pdf_logo_data_uri')) {
    function mizo_pdf_logo_data_uri(): string
    {
        $candidates = [
            __DIR__ . '/../mizo-logo-pdf.png',
            __DIR__ . '/../mizo-logo.png',
            dirname(__DIR__) . '/mizo-logo-pdf.png',
            dirname(__DIR__) . '/mizo-logo.png',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $data = @file_get_contents($path);
                if ($data !== false && $data !== '') {
                    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                    $mime = $ext === 'svg' ? 'image/svg+xml' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png');
                    return 'data:' . $mime . ';base64,' . base64_encode($data);
                }
            }
        }
        return '';
    }
}

if (!function_exists('mizo_pdf_number')) {
    function mizo_pdf_number($value): string
    {
        if (is_numeric($value)) {
            $number = (float) $value;
            if (floor($number) === $number) {
                return number_format($number, 0, ',', '.');
            }
            return number_format($number, 1, ',', '.');
        }
        return (string) ($value ?? '');
    }
}

if (!function_exists('mizo_pdf_decode_report')) {
    function mizo_pdf_decode_report(array $lead): array
    {
        $report = $lead['reporte'] ?? $lead['report'] ?? null;
        if (is_string($report) && $report !== '') {
            $decoded = json_decode($report, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (is_array($report)) {
            return $report;
        }
        return [];
    }
}

if (!function_exists('mizo_pdf_metric_cards')) {
    function mizo_pdf_metric_cards(array $metricas): string
    {
        $order = [
            'volumen_estimado_m3',
            'presion_sonora_objetivo_db_spl',
            'flujo_luminico_ansi_lumenes',
            'rack_requerido',
        ];

        $cards = '';
        $seen = [];
        foreach ($order as $key) {
            if (!isset($metricas[$key]) || !is_array($metricas[$key])) {
                continue;
            }
            $seen[$key] = true;
            $metric = $metricas[$key];
            $label = $metric['label'] ?? ucwords(str_replace('_', ' ', $key));
            $value = mizo_pdf_number($metric['value'] ?? '');
            $unit = trim((string) ($metric['unit'] ?? ''));
            $cards .= '<td class="metric-card">'
                . '<span class="metric-label">' . mizo_pdf_e($label) . '</span>'
                . '<span class="metric-value">' . mizo_pdf_e($value !== '' ? $value : '—')
                . ($unit !== '' ? ' <span class="metric-unit">' . mizo_pdf_e($unit) . '</span>' : '')
                . '</span>'
                . '</td>';
        }

        if ($cards === '') {
            return '';
        }

        return '<table class="metric-grid"><tr>' . $cards . '</tr></table>';
    }
}

if (!function_exists('mizo_pdf_base_calculo')) {
    function mizo_pdf_base_calculo(array $metricas): string
    {
        $base = $metricas['base_calculo'] ?? null;
        if (!is_array($base) || $base === []) {
            return '';
        }
        $labels = [
            'area_m2' => 'Superficie (m²)',
            'altura_m' => 'Altura (m)',
            'personas' => 'Aforo',
            'entorno_label' => 'Entorno',
            'tamano_label' => 'Tamaño derivado',
        ];
        $rows = '';
        foreach ($base as $key => $value) {
            if ($value === '' || $value === null || is_array($value)) {
                continue;
            }
            $label = $labels[$key] ?? ucwords(str_replace('_', ' ', (string) $key));
            $rows .= '<tr><th>' . mizo_pdf_e($label) . '</th><td>' . mizo_pdf_e(mizo_pdf_number($value)) . '</td></tr>';
        }
        if ($rows === '') {
            return '';
        }
        return '<table class="base-table">' . $rows . '</table>';
    }
}

if (!function_exists('mizo_pdf_group_sections')) {
    function mizo_pdf_group_sections(array $grupos): string
    {
        if ($grupos === []) {
            return '';
        }

        $stageMeta = [
            'captacion-mezcla' => ['tag' => 'CAPTACIÓN', 'fallback' => 'Sistemas de Captación y Mezcla'],
            'rack-potencia' => ['tag' => 'RACK', 'fallback' => 'Procesamiento en Rack y Potencia'],
            'proyeccion-video' => ['tag' => 'PROYECCIÓN', 'fallback' => 'Sistemas de Proyección y Video'],
        ];

        $sections = '';
        foreach ($grupos as $stage => $group) {
            if (!is_array($group)) {
                continue;
            }
            $meta = $stageMeta[$stage] ?? ['tag' => 'EQUIPAMIENTO', 'fallback' => 'Grupo de equipamiento'];
            $title = trim((string) ($group['title'] ?? $meta['fallback']));
            $products = is_array($group['products'] ?? null) ? $group['products'] : [];

            $rows = '';
            foreach ($products as $product) {
                if (!is_array($product)) {
                    continue;
                }
                $qty = (int) ($product['qty'] ?? 1);
                $unit = strtoupper((string) ($product['unit'] ?? 'UNIDADES'));
                $name = trim((string) ($product['name'] ?? ''));
                $brand = trim((string) ($product['brand'] ?? ''));
                $category = trim((string) ($product['category'] ?? ''));
                $sku = trim((string) ($product['sku'] ?? ''));
                $stock = $product['stock'] ?? null;

                $meta_line = array_filter([
                    $brand !== '' ? 'Marca: ' . $brand : '',
                    $category !== '' ? 'Categoría: ' . $category : '',
                    $sku !== '' ? 'SKU: ' . $sku : '',
                    ($stock !== null && $stock !== '') ? 'Stock: ' . mizo_pdf_number($stock) : '',
                ]);

                $rows .= '<tr>'
                    . '<td class="qty">' . mizo_pdf_e($qty) . '<span class="qty-unit">' . mizo_pdf_e($unit) . '</span></td>'
                    . '<td class="prod">'
                    . '<span class="prod-name">' . mizo_pdf_e($name !== '' ? $name : 'Equipo audiovisual') . '</span>'
                    . ($meta_line ? '<span class="prod-meta">' . mizo_pdf_e(implode('  ·  ', $meta_line)) . '</span>' : '')
                    . '</td>'
                    . '</tr>';
            }

            if ($rows === '') {
                $rows = '<tr><td class="qty">—</td><td class="prod"><span class="prod-name">Selección final reservada para validación de ingeniería.</span></td></tr>';
            }

            $sections .= '<div class="group-block">'
                . '<div class="group-head"><span class="group-tag">' . mizo_pdf_e($meta['tag']) . '</span>'
                . '<span class="group-title">' . mizo_pdf_e($title) . '</span></div>'
                . '<table class="group-table"><thead><tr><th class="qty">Cant.</th><th>Equipamiento precalificado</th></tr></thead>'
                . '<tbody>' . $rows . '</tbody></table>'
                . '</div>';
        }

        return $sections;
    }
}

if (!function_exists('mizo_pdf_build_html')) {
    function mizo_pdf_build_html(array $lead): string
    {
        $report = mizo_pdf_decode_report($lead);
        $metricas = is_array($report['metricas'] ?? null) ? $report['metricas'] : (is_array($report['metricas_proyecto'] ?? null) ? $report['metricas_proyecto'] : []);
        $grupos = is_array($report['grupos'] ?? null) ? $report['grupos'] : (is_array($report['groups'] ?? null) ? $report['groups'] : []);

        $logo = mizo_pdf_logo_data_uri();
        $folio = (string) ($lead['id'] ?? ('lead_' . date('Ymd_His')));
        $fecha = date('d-m-Y H:i');

        $metricCards = mizo_pdf_metric_cards($metricas);
        $baseTable = mizo_pdf_base_calculo($metricas);
        $groupSections = mizo_pdf_group_sections($grupos);

        $reportTitle = trim((string) ($report['title'] ?? 'Informe de Diagnóstico Técnico'));
        $reportSummary = trim((string) ($report['summary'] ?? ''));

        $infoRows = [
            'Contacto' => $lead['nombre'] ?? '',
            'Empresa' => $lead['empresa'] ?? '',
            'Teléfono' => $lead['telefono'] ?? '',
            'Correo' => $lead['correo'] ?? '',
            'Entorno' => $lead['espacio'] ?? '',
            'Especialidad' => $lead['especialidad'] ?? '',
            'Dimensión' => $lead['dimension'] ?? '',
        ];
        $infoHtml = '';
        foreach ($infoRows as $label => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $infoHtml .= '<tr><th>' . mizo_pdf_e($label) . '</th><td>' . mizo_pdf_e($value) . '</td></tr>';
        }

        $fallbackDetails = '';
        if ($metricCards === '' && $groupSections === '') {
            $detalles = trim((string) ($lead['detalles'] ?? $lead['resumenTecnico'] ?? ''));
            if ($detalles !== '') {
                $fallbackDetails = '<div class="section"><h2 class="section-title">Resumen técnico del diagnóstico</h2>'
                    . '<pre class="raw-block">' . mizo_pdf_e($detalles) . '</pre></div>';
            }
        }

        $logoHtml = $logo !== ''
            ? '<img class="brand-logo" src="' . $logo . '" alt="Mizo">'
            : '<span class="brand-word">MIZO</span>';

        $blue = MIZO_PDF_BRAND_BLUE;
        $closing = mizo_pdf_e(MIZO_PDF_CLOSING_MESSAGE);

        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<style>'
            . '@page { margin: 0; }'
            . '* { box-sizing: border-box; }'
            . 'body { margin: 0; padding: 0; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #0f172a; font-size: 11px; line-height: 1.5; }'
            . '.page { padding: 0 0 120px 0; }'
            . '.header { background: ' . $blue . '; color: #ffffff; padding: 26px 40px 22px 40px; }'
            . '.header-top { width: 100%; }'
            . '.brand-logo { height: 46px; width: auto; }'
            . '.brand-word { font-size: 30px; font-weight: 800; letter-spacing: 4px; color: #ffffff; }'
            . '.header .doc-tag { float: right; text-align: right; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #e0f2fe; }'
            . '.header h1 { margin: 16px 0 4px 0; font-size: 21px; font-weight: 800; }'
            . '.header p { margin: 0; font-size: 10px; color: #e0f2fe; max-width: 78%; }'
            . '.meta-bar { background: #0f172a; color: #cbd5e1; padding: 8px 40px; font-size: 9px; letter-spacing: 1px; }'
            . '.meta-bar span { margin-right: 22px; }'
            . '.meta-bar b { color: #ffffff; }'
            . '.content { padding: 24px 40px 0 40px; }'
            . '.section { margin-bottom: 22px; }'
            . '.section-title { font-size: 13px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0; padding-bottom: 6px; border-bottom: 2px solid ' . $blue . '; text-transform: uppercase; letter-spacing: 0.6px; }'
            . '.info-table { width: 100%; border-collapse: collapse; }'
            . '.info-table th { text-align: left; width: 130px; padding: 5px 10px; background: #f1f5f9; color: #475569; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #e2e8f0; font-weight: 700; }'
            . '.info-table td { padding: 5px 12px; border: 1px solid #e2e8f0; font-size: 11px; }'
            . '.metric-grid { width: 100%; border-collapse: separate; border-spacing: 8px 0; }'
            . '.metric-card { width: 25%; background: #f0f9ff; border: 1px solid #bae6fd; border-top: 3px solid ' . $blue . '; border-radius: 6px; padding: 12px 12px; vertical-align: top; }'
            . '.metric-label { display: block; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.6px; color: ' . $blue . '; font-weight: 700; }'
            . '.metric-value { display: block; margin-top: 6px; font-size: 17px; font-weight: 800; color: #0f172a; }'
            . '.metric-unit { font-size: 9px; font-weight: 600; color: #64748b; }'
            . '.base-table { width: 100%; border-collapse: collapse; margin-top: 12px; }'
            . '.base-table th { text-align: left; padding: 5px 10px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 9px; text-transform: uppercase; color: #64748b; width: 150px; }'
            . '.base-table td { padding: 5px 10px; border: 1px solid #e2e8f0; font-weight: 700; }'
            . '.group-block { margin-bottom: 14px; }'
            . '.group-head { background: #0f172a; color: #ffffff; padding: 7px 12px; border-radius: 5px 5px 0 0; }'
            . '.group-tag { display: inline-block; background: ' . $blue . '; color: #ffffff; font-size: 8px; font-weight: 800; letter-spacing: 1px; padding: 2px 8px; border-radius: 10px; margin-right: 10px; }'
            . '.group-title { font-size: 11px; font-weight: 700; }'
            . '.group-table { width: 100%; border-collapse: collapse; }'
            . '.group-table thead th { background: #f1f5f9; color: #475569; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; padding: 6px 12px; border: 1px solid #e2e8f0; }'
            . '.group-table td { padding: 8px 12px; border: 1px solid #e2e8f0; vertical-align: top; }'
            . '.group-table .qty { width: 58px; text-align: center; font-weight: 800; color: ' . $blue . '; font-size: 14px; }'
            . '.group-table .qty-unit { display: block; font-size: 7px; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; }'
            . '.prod-name { display: block; font-weight: 700; font-size: 11px; color: #0f172a; }'
            . '.prod-meta { display: block; margin-top: 3px; font-size: 9px; color: #64748b; }'
            . '.raw-block { white-space: pre-wrap; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; color: #334155; }'
            . '.closing { margin: 8px 40px 0 40px; background: #f0f9ff; border: 1px solid #bae6fd; border-left: 5px solid ' . $blue . '; border-radius: 8px; padding: 16px 18px; }'
            . '.closing h3 { margin: 0 0 6px 0; color: ' . $blue . '; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; }'
            . '.closing p { margin: 0; font-size: 11px; line-height: 1.65; color: #0f172a; }'
            . '.footer { position: fixed; bottom: 0; left: 0; right: 0; height: 70px; background: ' . $blue . '; color: #e0f2fe; padding: 14px 40px; font-size: 9px; }'
            . '.footer b { color: #ffffff; }'
            . '.footer .right { float: right; text-align: right; }'
            . '</style></head><body>'
            . '<div class="page">'
            . '<div class="header"><div class="header-top">' . $logoHtml
            . '<span class="doc-tag">Informe de Ingeniería Audiovisual<br>Folio ' . mizo_pdf_e($folio) . '</span></div>'
            . '<h1>' . mizo_pdf_e($reportTitle) . '</h1>'
            . ($reportSummary !== '' ? '<p>' . mizo_pdf_e($reportSummary) . '</p>' : '<p>Diagnóstico técnico generado por el motor de dimensionamiento Mizo.</p>')
            . '</div>'
            . '<div class="meta-bar"><span>FECHA: <b>' . mizo_pdf_e($fecha) . '</b></span><span>ORIGEN: <b>' . mizo_pdf_e($lead['source'] ?? 'Asistente de Diagnóstico Técnico Mizo') . '</b></span></div>'
            . '<div class="content">';

        if ($infoHtml !== '') {
            $html .= '<div class="section"><h2 class="section-title">Datos del proyecto</h2>'
                . '<table class="info-table">' . $infoHtml . '</table></div>';
        }

        if ($metricCards !== '') {
            $html .= '<div class="section"><h2 class="section-title">Métricas de Ingeniería</h2>'
                . $metricCards . $baseTable . '</div>';
        }

        if ($groupSections !== '') {
            $html .= '<div class="section"><h2 class="section-title">Grupos de Equipamiento</h2>'
                . $groupSections . '</div>';
        }

        $html .= $fallbackDetails;

        $html .= '</div>'
            . '<div class="closing"><h3>Próximo paso con Mizo</h3><p>' . $closing . '</p></div>'
            . '</div>'
            . '<div class="footer"><span><b>MIZO Ingeniería Audiovisual</b><br>Sonido · Video · CCTV · Instalaciones</span>'
            . '<span class="right">ventas@mizo.cl<br>www.mizo.cl</span></div>'
            . '</body></html>';

        return $html;
    }
}

if (!function_exists('mizo_generar_pdf_informe')) {
    /**
     * Genera el informe PDF del lead.
     *
     * @return array{ok:bool, mode:string, filename:string, mime:string, content:string, error?:string}
     */
    function mizo_generar_pdf_informe(array $lead): array
    {
        $html = mizo_pdf_build_html($lead);
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($lead['id'] ?? 'mizo')) ?: 'mizo';
        $baseName = 'Informe-Mizo-' . $slug;

        if (mizo_pdf_load_autoloader()) {
            try {
                $optionsClass = 'Dompdf\\Options';
                $dompdfClass = 'Dompdf\\Dompdf';
                $options = new $optionsClass();
                $options->set('isRemoteEnabled', false);
                $options->set('isHtml5ParserEnabled', true);
                $options->set('defaultFont', 'DejaVu Sans');
                $dompdf = new $dompdfClass($options);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $output = $dompdf->output();
                if (is_string($output) && $output !== '') {
                    return [
                        'ok' => true,
                        'mode' => 'dompdf',
                        'filename' => $baseName . '.pdf',
                        'mime' => 'application/pdf',
                        'content' => $output,
                    ];
                }
            } catch (Throwable $error) {
                return [
                    'ok' => false,
                    'mode' => 'html-fallback',
                    'filename' => $baseName . '.html',
                    'mime' => 'text/html; charset=UTF-8',
                    'content' => $html,
                    'error' => $error->getMessage(),
                ];
            }
        }

        return [
            'ok' => false,
            'mode' => 'html-fallback',
            'filename' => $baseName . '.html',
            'mime' => 'text/html; charset=UTF-8',
            'content' => $html,
            'error' => 'dompdf no disponible. Ejecuta composer install para habilitar el PDF nativo.',
        ];
    }
}

// Permite previsualizar el informe directamente (solo cuando se invoca el script).
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $sample = [
        'id' => 'preview_' . date('Ymd_His'),
        'nombre' => 'Vista previa',
        'empresa' => 'Mizo Ingeniería',
        'telefono' => '+56 9 0000 0000',
        'correo' => 'ventas@mizo.cl',
        'espacio' => 'Auditorio / Teatro',
        'especialidad' => 'Audio + Video',
        'dimension' => '120 m² · 6 m altura · 90 personas · Cielo acústico',
        'source' => 'Asistente de Diagnóstico Técnico Mizo',
        'detalles' => 'Vista previa del informe de diagnóstico técnico Mizo.',
        'reporte' => json_encode([
            'title' => 'Informe de Ingeniería Mizo con radar diario de tendencias',
            'summary' => 'El motor calculó volumen, SPL, luminancia y rack requerido y los cruzó con productos tendencia disponibles.',
            'metricas' => [
                'volumen_estimado_m3' => ['label' => 'Volumen estimado', 'value' => 720, 'unit' => 'm³'],
                'presion_sonora_objetivo_db_spl' => ['label' => 'Presión sonora objetivo', 'value' => 94, 'unit' => 'dB SPL'],
                'flujo_luminico_ansi_lumenes' => ['label' => 'Flujo lumínico', 'value' => 4200, 'unit' => 'ANSI lúmenes'],
                'rack_requerido' => ['label' => 'Rack requerido', 'value' => '12U', 'unit' => ''],
                'base_calculo' => ['area_m2' => 120, 'altura_m' => 6, 'personas' => 90, 'entorno_label' => 'Auditorio / Teatro', 'tamano_label' => 'Mediano'],
            ],
            'grupos' => [
                'captacion-mezcla' => ['title' => 'Sistemas de Captación y Mezcla', 'products' => [
                    ['qty' => 2, 'unit' => 'unidades', 'name' => 'Shure SLXD24/SM58 Sistema Inalámbrico', 'brand' => 'Shure', 'category' => 'Micrófonos', 'sku' => 'AM-SHU-SLXD24', 'stock' => 6],
                ]],
                'rack-potencia' => ['title' => 'Procesamiento en Rack y Potencia', 'products' => [
                    ['qty' => 1, 'unit' => 'unidades', 'name' => 'dbx DriveRack PA2', 'brand' => 'dbx', 'category' => 'Procesadores', 'sku' => 'PM-DBX-PA2', 'stock' => 5],
                    ['qty' => 4, 'unit' => 'unidades', 'name' => 'QSC CP12 Parlante Activo', 'brand' => 'QSC', 'category' => 'Parlantes', 'sku' => 'PM-QSC-CP12', 'stock' => 8],
                ]],
                'proyeccion-video' => ['title' => 'Sistemas de Proyección y Video', 'products' => [
                    ['qty' => 1, 'unit' => 'unidades', 'name' => 'Epson EB-FH52 Full HD 4000lm', 'brand' => 'Epson', 'category' => 'Proyectores', 'sku' => 'CR-EPSON-EB-FH52', 'stock' => 7],
                ]],
            ],
        ], JSON_UNESCAPED_UNICODE),
    ];

    $result = mizo_generar_pdf_informe($sample);
    header('Content-Type: ' . $result['mime']);
    header('Content-Disposition: inline; filename="' . $result['filename'] . '"');
    echo $result['content'];
    exit;
}
