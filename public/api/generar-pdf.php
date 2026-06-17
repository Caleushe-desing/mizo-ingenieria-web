<?php
declare(strict_types=1);

/**
 * Motor de informes PDF profesionales Mizo (100% standalone).
 *
 * Genera el informe de Ingenieria con membrete Mizo (logo + cabecera azul
 * #0284c7), Metricas de Ingenieria, Grupos de Equipamiento (captacion, rack,
 * proyeccion) y el mensaje de cierre estrategico.
 *
 * NO requiere composer, vendors ni librerias externas: usa un escritor PDF
 * nativo en PHP puro. Basta con subir este archivo (y assets/mizo-logo-pdf.jpg)
 * al servidor para que funcione.
 */

const MIZO_PDF_BRAND_BLUE = '#0284c7';
const MIZO_PDF_CLOSING_MESSAGE = 'Hemos recibido tu solicitud de diagnóstico. Un especialista de Mizo revisará los detalles de tu proyecto y te contactará para optimizar tu propuesta de ingeniería y entregarte una solución personalizada ajustada a tus necesidades operativas y presupuestarias.';

if (!class_exists('MizoPdfWriter')) {
    /**
     * Escritor PDF minimalista en PHP puro.
     * Soporta texto (Helvetica / Helvetica-Bold con WinAnsiEncoding),
     * rectangulos rellenos, bordes, lineas e imagenes JPEG (DCTDecode).
     * El sistema de coordenadas expuesto es "top-down" (origen arriba-izquierda).
     */
    final class MizoPdfWriter
    {
        private float $w;
        private float $h;
        /** @var string[] */
        private array $pages = [];
        private string $buf = '';
        /** @var array<int, array{data:string,w:int,h:int,bpc:int,cs:string}> */
        private array $images = [];

        public function __construct(float $w = 595.28, float $h = 841.89)
        {
            $this->w = $w;
            $this->h = $h;
        }

        public function pageWidth(): float
        {
            return $this->w;
        }

        public function pageHeight(): float
        {
            return $this->h;
        }

        public function addPage(): void
        {
            if ($this->buf !== '') {
                $this->pages[] = $this->buf;
            }
            $this->buf = '';
        }

        private function col(array $c): string
        {
            return sprintf('%.3F %.3F %.3F', ($c[0] ?? 0) / 255, ($c[1] ?? 0) / 255, ($c[2] ?? 0) / 255);
        }

        private function ty(float $top): float
        {
            return $this->h - $top;
        }

        public function rect(float $x, float $top, float $w, float $h, array $fill): void
        {
            $this->buf .= sprintf("%s rg\n%.2F %.2F %.2F %.2F re f\n", $this->col($fill), $x, $this->ty($top + $h), $w, $h);
        }

        public function box(float $x, float $top, float $w, float $h, ?array $fill, ?array $border, float $bw = 0.6): void
        {
            $y = $this->ty($top + $h);
            if ($fill !== null) {
                $this->buf .= sprintf("%s rg\n%.2F %.2F %.2F %.2F re f\n", $this->col($fill), $x, $y, $w, $h);
            }
            if ($border !== null) {
                $this->buf .= sprintf("%.2F w %s RG\n%.2F %.2F %.2F %.2F re S\n", $bw, $this->col($border), $x, $y, $w, $h);
            }
        }

        public function line(float $x1, float $top1, float $x2, float $top2, array $color, float $width = 0.8): void
        {
            $this->buf .= sprintf("%.2F w %s RG\n%.2F %.2F m %.2F %.2F l S\n", $width, $this->col($color), $x1, $this->ty($top1), $x2, $this->ty($top2));
        }

        public function text(float $x, float $baselineTop, string $utf8, bool $bold, float $size, array $color): void
        {
            $font = $bold ? '/F2' : '/F1';
            $this->buf .= sprintf(
                "BT %s rg %s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                $this->col($color),
                $font,
                $size,
                $x,
                $this->ty($baselineTop),
                $this->encode($utf8)
            );
        }

        private function encode(string $s): string
        {
            if (function_exists('iconv')) {
                $cp = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
                if ($cp !== false) {
                    $s = $cp;
                }
            }
            return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
        }

        public function addJpeg(string $data): ?int
        {
            $info = $this->jpegInfo($data);
            if ($info === null) {
                return null;
            }
            $this->images[] = ['data' => $data] + $info;
            return count($this->images) - 1;
        }

        private function jpegInfo(string $d): ?array
        {
            $len = strlen($d);
            if ($len < 4 || substr($d, 0, 2) !== "\xFF\xD8") {
                return null;
            }
            $i = 2;
            while ($i + 1 < $len) {
                if ($d[$i] !== "\xFF") {
                    $i++;
                    continue;
                }
                $marker = ord($d[$i + 1]);
                $i += 2;
                if ($marker === 0xD8 || $marker === 0xD9 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                    continue;
                }
                if ($i + 1 >= $len) {
                    break;
                }
                $seglen = (ord($d[$i]) << 8) + ord($d[$i + 1]);
                $sof = [0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF];
                if (in_array($marker, $sof, true) && $i + 7 < $len) {
                    $bpc = ord($d[$i + 2]);
                    $hh = (ord($d[$i + 3]) << 8) + ord($d[$i + 4]);
                    $ww = (ord($d[$i + 5]) << 8) + ord($d[$i + 6]);
                    $comp = ord($d[$i + 7]);
                    $cs = $comp === 4 ? 'DeviceCMYK' : ($comp === 1 ? 'DeviceGray' : 'DeviceRGB');
                    return ['w' => $ww, 'h' => $hh, 'bpc' => $bpc, 'cs' => $cs];
                }
                $i += $seglen;
            }
            return null;
        }

        public function image(int $handle, float $x, float $top, float $w, float $h): void
        {
            if (!isset($this->images[$handle])) {
                return;
            }
            $this->buf .= sprintf(
                "q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q\n",
                $w,
                $h,
                $x,
                $this->ty($top + $h),
                $handle
            );
        }

        public function stringWidth(string $utf8, bool $bold, float $size): float
        {
            $s = $utf8;
            if (function_exists('iconv')) {
                $cp = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $utf8);
                if ($cp !== false) {
                    $s = $cp;
                }
            }
            $map = self::widthMap($bold);
            $total = 0;
            $len = strlen($s);
            for ($i = 0; $i < $len; $i++) {
                $total += $map[ord($s[$i])] ?? ($bold ? 600 : 556);
            }
            return $total * $size / 1000;
        }

        private static function widthMap(bool $bold): array
        {
            static $cache = [];
            $key = $bold ? 'b' : 'r';
            if (isset($cache[$key])) {
                return $cache[$key];
            }

            $regular = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
            $boldArr = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

            $src = $bold ? $boldArr : $regular;
            $map = array_fill(0, 256, $bold ? 600 : 556);
            foreach ($src as $idx => $val) {
                $map[32 + $idx] = $val;
            }

            $map[0x85] = 1000;
            $map[0x91] = $bold ? 278 : 222;
            $map[0x92] = $bold ? 278 : 222;
            $map[0x93] = $bold ? 500 : 333;
            $map[0x94] = $bold ? 500 : 333;
            $map[0x96] = 556;
            $map[0x97] = 1000;
            $map[0xA0] = 278;
            $map[0xB0] = 400;
            $map[0xB2] = 333;
            $map[0xB3] = 333;
            $map[0xB7] = 278;
            for ($c = 0xC0; $c <= 0xDF; $c++) {
                $map[$c] = $bold ? 722 : 667;
            }
            for ($c = 0xE0; $c <= 0xFF; $c++) {
                $map[$c] = $bold ? 611 : 556;
            }

            $cache[$key] = $map;
            return $map;
        }

        public function output(): string
        {
            $this->addPage();
            $nImg = count($this->images);
            $imgObjStart = 5;
            $pageObjStart = $imgObjStart + $nImg;
            $nPages = max(1, count($this->pages));

            $kids = [];
            for ($i = 0; $i < $nPages; $i++) {
                $kids[] = ($pageObjStart + $i * 2) . ' 0 R';
            }

            $objs = [];
            $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
            $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $nPages
                . ' /MediaBox [0 0 ' . sprintf('%.2F %.2F', $this->w, $this->h) . '] >>';
            $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
            $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

            for ($k = 0; $k < $nImg; $k++) {
                $img = $this->images[$k];
                $dict = '<< /Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h']
                    . ' /ColorSpace /' . $img['cs'] . ' /BitsPerComponent ' . $img['bpc']
                    . ' /Filter /DCTDecode /Length ' . strlen($img['data']) . ' >>';
                $objs[$imgObjStart + $k] = $dict . "\nstream\n" . $img['data'] . "\nendstream";
            }

            $xobj = '';
            for ($k = 0; $k < $nImg; $k++) {
                $xobj .= '/I' . $k . ' ' . ($imgObjStart + $k) . ' 0 R ';
            }
            $xobjRes = $nImg > 0 ? '/XObject << ' . $xobj . '>>' : '';

            for ($i = 0; $i < $nPages; $i++) {
                $pon = $pageObjStart + $i * 2;
                $con = $pon + 1;
                $content = $this->pages[$i] ?? '';
                $objs[$pon] = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> '
                    . $xobjRes . ' >> /Contents ' . $con . ' 0 R >>';
                $objs[$con] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            }

            $maxObj = $pageObjStart + $nPages * 2 - 1;
            $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
            $offsets = [];
            for ($n = 1; $n <= $maxObj; $n++) {
                $offsets[$n] = strlen($pdf);
                $pdf .= $n . " 0 obj\n" . ($objs[$n] ?? '<< >>') . "\nendobj\n";
            }
            $xrefPos = strlen($pdf);
            $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
            $pdf .= "0000000000 65535 f \n";
            for ($n = 1; $n <= $maxObj; $n++) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
            }
            $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";
            return $pdf;
        }
    }
}

if (!class_exists('MizoPdfReport')) {
    /**
     * Renderiza el informe tecnico Mizo sobre el escritor PDF nativo.
     */
    final class MizoPdfReport
    {
        private const C_BLUE = [2, 132, 199];
        private const C_DARK = [15, 23, 42];
        private const C_SLATE = [71, 85, 105];
        private const C_MUTED = [100, 116, 139];
        private const C_BORDER = [226, 232, 240];
        private const C_CARDBG = [240, 249, 255];
        private const C_CARDBR = [186, 230, 253];
        private const C_WHITE = [255, 255, 255];
        private const C_HEADBG = [241, 245, 249];
        private const C_LIGHT = [224, 242, 254];

        private MizoPdfWriter $pdf;
        private array $lead;
        private array $report;
        private array $metricas;
        private array $grupos;
        private float $W;
        private float $H;
        private float $ml = 40.0;
        private float $mr = 40.0;
        private float $y = 0.0;
        private int $pageNo = 0;
        private ?int $logo = null;
        private string $title;
        private string $summary;
        private string $folio;
        private string $fecha;
        private string $source;

        public function __construct(array $lead)
        {
            $this->pdf = new MizoPdfWriter();
            $this->W = $this->pdf->pageWidth();
            $this->H = $this->pdf->pageHeight();
            $this->lead = $lead;

            $report = $this->decodeReport($lead);
            $this->report = $report;
            $this->metricas = is_array($report['metricas'] ?? null)
                ? $report['metricas']
                : (is_array($report['metricas_proyecto'] ?? null) ? $report['metricas_proyecto'] : []);
            $this->grupos = is_array($report['grupos'] ?? null)
                ? $report['grupos']
                : (is_array($report['groups'] ?? null) ? $report['groups'] : []);

            $this->title = trim((string) ($report['title'] ?? 'Informe de Diagnóstico Técnico'));
            if ($this->title === '') {
                $this->title = 'Informe de Diagnóstico Técnico';
            }
            $this->summary = trim((string) ($report['summary'] ?? 'Diagnóstico técnico generado por el motor de dimensionamiento Mizo.'));
            $this->folio = (string) ($lead['id'] ?? ('lead_' . date('Ymd_His')));
            $this->fecha = date('d-m-Y H:i');
            $this->source = trim((string) ($lead['source'] ?? 'Asistente de Diagnóstico Técnico Mizo'));

            $this->loadLogo();
        }

        private function decodeReport(array $lead): array
        {
            $report = $lead['reporte'] ?? $lead['report'] ?? null;
            if (is_string($report) && $report !== '') {
                $decoded = json_decode($report, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            return is_array($report) ? $report : [];
        }

        private function loadLogo(): void
        {
            $candidates = [
                __DIR__ . '/assets/mizo-logo-pdf.jpg',
                __DIR__ . '/assets/mizo-logo.jpg',
            ];
            foreach ($candidates as $path) {
                if (is_file($path)) {
                    $data = @file_get_contents($path);
                    if ($data !== false && $data !== '') {
                        $handle = $this->pdf->addJpeg($data);
                        if ($handle !== null) {
                            $this->logo = $handle;
                            return;
                        }
                    }
                }
            }
        }

        private function contentW(): float
        {
            return $this->W - $this->ml - $this->mr;
        }

        private function put(float $x, float $top, string $s, bool $bold, float $size, array $color): void
        {
            $this->pdf->text($x, $top + $size * 0.80, $s, $bold, $size, $color);
        }

        private function putRight(float $xRight, float $top, string $s, bool $bold, float $size, array $color): void
        {
            $w = $this->pdf->stringWidth($s, $bold, $size);
            $this->put($xRight - $w, $top, $s, $bold, $size, $color);
        }

        private function putCenter(float $cx, float $top, string $s, bool $bold, float $size, array $color): void
        {
            $w = $this->pdf->stringWidth($s, $bold, $size);
            $this->put($cx - $w / 2, $top, $s, $bold, $size, $color);
        }

        /** @return string[] */
        private function wrap(string $text, bool $bold, float $size, float $maxW, int $maxLines = 0): array
        {
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if ($text === '') {
                return [];
            }
            $words = explode(' ', $text);
            $lines = [];
            $cur = '';
            foreach ($words as $word) {
                $try = $cur === '' ? $word : $cur . ' ' . $word;
                if ($cur === '' || $this->pdf->stringWidth($try, $bold, $size) <= $maxW) {
                    $cur = $try;
                } else {
                    $lines[] = $cur;
                    $cur = $word;
                }
            }
            if ($cur !== '') {
                $lines[] = $cur;
            }
            if ($maxLines > 0 && count($lines) > $maxLines) {
                $lines = array_slice($lines, 0, $maxLines);
                $last = $this->fit($lines[$maxLines - 1] . ' …', $bold, $size, $maxW);
                $lines[$maxLines - 1] = $last;
            }
            return $lines;
        }

        private function fit(string $s, bool $bold, float $size, float $maxW): string
        {
            if ($this->pdf->stringWidth($s, $bold, $size) <= $maxW) {
                return $s;
            }
            $useMb = function_exists('mb_substr');
            while ($s !== '' && $this->pdf->stringWidth($s . '…', $bold, $size) > $maxW) {
                $s = $useMb ? mb_substr($s, 0, mb_strlen($s, 'UTF-8') - 1, 'UTF-8') : substr($s, 0, -1);
            }
            return $s . '…';
        }

        private function ensure(float $need): void
        {
            if ($this->y + $need > $this->H - 58) {
                $this->newPage();
            }
        }

        private function newPage(): void
        {
            $this->pdf->addPage();
            $this->pageNo++;
            $this->drawHeader();
            $this->drawFooter();
            $this->y = 162.0;
        }

        private function drawHeader(): void
        {
            $this->pdf->rect(0, 0, $this->W, 120, self::C_BLUE);

            $cardX = $this->ml;
            $cardY = 30.0;
            $cardW = 150.0;
            $cardH = 54.0;
            $this->pdf->box($cardX, $cardY, $cardW, $cardH, self::C_WHITE, null);

            if ($this->logo !== null) {
                $lw = 126.0;
                $lh = $lw * 140.0 / 420.0;
                $lx = $cardX + ($cardW - $lw) / 2;
                $ly = $cardY + ($cardH - $lh) / 2;
                $this->pdf->image($this->logo, $lx, $ly, $lw, $lh);
            } else {
                $this->put($cardX + 24, $cardY + 16, 'MIZO', true, 22, self::C_BLUE);
            }

            $rx = $this->ml + 170;
            $rRight = $this->W - $this->mr;
            $rw = $rRight - $rx;

            $this->put($rx, 30, 'INFORME DE INGENIERÍA AUDIOVISUAL', true, 8, self::C_LIGHT);

            $titleLines = $this->wrap($this->title, true, 14, $rw, 2);
            $ty = 44.0;
            foreach ($titleLines as $line) {
                $this->put($rx, $ty, $line, true, 14, self::C_WHITE);
                $ty += 17;
            }

            $summaryLines = $this->wrap($this->summary, false, 8.5, $rw, 2);
            $ty += 1;
            foreach ($summaryLines as $line) {
                $this->put($rx, $ty, $line, false, 8.5, self::C_LIGHT);
                $ty += 11;
            }

            $folioStr = 'Folio ' . $this->folio;
            $this->putRight($rRight, 14, $folioStr, false, 8, self::C_LIGHT);

            $this->pdf->rect(0, 120, $this->W, 20, self::C_DARK);
            $metaLeft = 'FECHA: ' . $this->fecha;
            $metaRight = 'ORIGEN: ' . $this->fit($this->source, false, 8.5, 280);
            $this->put($this->ml, 124, $metaLeft, true, 8.5, self::C_WHITE);
            $this->put($this->ml + 150, 124, $metaRight, false, 8.5, self::C_LIGHT);
        }

        private function drawFooter(): void
        {
            $fy = $this->H - 46;
            $this->pdf->rect(0, $fy, $this->W, 46, self::C_BLUE);
            $this->put($this->ml, $fy + 9, 'MIZO Ingeniería Audiovisual', true, 9, self::C_WHITE);
            $this->put($this->ml, $fy + 23, 'Sonido · Video · CCTV · Instalaciones', false, 8, self::C_LIGHT);
            $this->putRight($this->W - $this->mr, $fy + 9, 'ventas@mizo.cl', true, 9, self::C_WHITE);
            $this->putRight($this->W - $this->mr, $fy + 23, 'www.mizo.cl', false, 8, self::C_LIGHT);
        }

        private function sectionTitle(string $title): void
        {
            $this->ensure(34);
            $this->put($this->ml, $this->y, $title, true, 12, self::C_DARK);
            $this->pdf->line($this->ml, $this->y + 18, $this->W - $this->mr, $this->y + 18, self::C_BLUE, 1.2);
            $this->y += 30;
        }

        private function infoSection(): void
        {
            $rows = [
                'Contacto' => $this->lead['nombre'] ?? '',
                'Empresa' => $this->lead['empresa'] ?? '',
                'Teléfono' => $this->lead['telefono'] ?? '',
                'Correo' => $this->lead['correo'] ?? '',
                'Entorno' => $this->lead['espacio'] ?? '',
                'Especialidad' => $this->lead['especialidad'] ?? '',
                'Dimensión' => $this->lead['dimension'] ?? '',
            ];
            $rows = array_filter($rows, static fn($v) => trim((string) $v) !== '');
            if ($rows === []) {
                return;
            }

            $this->sectionTitle('Datos del proyecto');
            $labelW = 120.0;
            $rowH = 18.0;
            $valW = $this->contentW() - $labelW;
            foreach ($rows as $label => $value) {
                $this->ensure($rowH);
                $this->pdf->box($this->ml, $this->y, $labelW, $rowH, self::C_HEADBG, self::C_BORDER);
                $this->pdf->box($this->ml + $labelW, $this->y, $valW, $rowH, self::C_WHITE, self::C_BORDER);
                $this->put($this->ml + 8, $this->y + 5, $this->upper((string) $label), true, 8, self::C_SLATE);
                $valueText = $this->fit(trim((string) $value), false, 10, $valW - 16);
                $this->put($this->ml + $labelW + 8, $this->y + 5, $valueText, false, 10, self::C_DARK);
                $this->y += $rowH;
            }
            $this->y += 12;
        }

        private function metricsSection(): bool
        {
            $order = [
                'volumen_estimado_m3',
                'presion_sonora_objetivo_db_spl',
                'flujo_luminico_ansi_lumenes',
                'rack_requerido',
            ];
            $cards = [];
            foreach ($order as $key) {
                if (isset($this->metricas[$key]) && is_array($this->metricas[$key])) {
                    $m = $this->metricas[$key];
                    $cards[] = [
                        'label' => (string) ($m['label'] ?? ucwords(str_replace('_', ' ', $key))),
                        'value' => $this->formatNumber($m['value'] ?? ''),
                        'unit' => trim((string) ($m['unit'] ?? '')),
                    ];
                }
            }
            if ($cards === []) {
                return false;
            }

            $this->sectionTitle('Métricas de Ingeniería');

            $n = count($cards);
            $gap = 8.0;
            $cardW = ($this->contentW() - $gap * ($n - 1)) / $n;
            $cardH = 56.0;
            $this->ensure($cardH + 4);
            $top = $this->y;
            foreach ($cards as $i => $card) {
                $x = $this->ml + $i * ($cardW + $gap);
                $this->pdf->box($x, $top, $cardW, $cardH, self::C_CARDBG, self::C_CARDBR);
                $this->pdf->rect($x, $top, $cardW, 3, self::C_BLUE);

                $labelLines = $this->wrap($this->upper($card['label']), true, 7.5, $cardW - 16, 2);
                $ly = $top + 9;
                foreach ($labelLines as $line) {
                    $this->put($x + 9, $ly, $line, true, 7.5, self::C_BLUE);
                    $ly += 9;
                }
                $valueText = $this->fit($card['value'] !== '' ? $card['value'] : '—', true, 15, $cardW - 16);
                $this->put($x + 9, $top + 30, $valueText, true, 15, self::C_DARK);
                if ($card['unit'] !== '') {
                    $this->put($x + 9, $top + 45, $this->fit($card['unit'], false, 8, $cardW - 16), false, 8, self::C_MUTED);
                }
            }
            $this->y = $top + $cardH;

            $base = $this->metricas['base_calculo'] ?? null;
            if (is_array($base)) {
                $labels = [
                    'area_m2' => 'Superficie',
                    'altura_m' => 'Altura',
                    'personas' => 'Aforo',
                    'entorno_label' => 'Entorno',
                    'tamano_label' => 'Tamaño',
                ];
                $parts = [];
                foreach ($labels as $k => $lbl) {
                    if (isset($base[$k]) && $base[$k] !== '' && !is_array($base[$k])) {
                        $parts[] = $lbl . ': ' . $this->formatNumber($base[$k]);
                    }
                }
                if ($parts !== []) {
                    $this->y += 8;
                    $this->ensure(14);
                    $this->put($this->ml, $this->y, 'Base de cálculo  ·  ' . implode('   ·   ', $parts), false, 8.5, self::C_MUTED);
                    $this->y += 12;
                }
            }
            $this->y += 14;
            return true;
        }

        private function groupsSection(): bool
        {
            if ($this->grupos === []) {
                return false;
            }

            $stageMeta = [
                'captacion-mezcla' => ['tag' => 'CAPTACIÓN', 'fallback' => 'Sistemas de Captación y Mezcla'],
                'rack-potencia' => ['tag' => 'RACK', 'fallback' => 'Procesamiento en Rack y Potencia'],
                'proyeccion-video' => ['tag' => 'PROYECCIÓN', 'fallback' => 'Sistemas de Proyección y Video'],
            ];

            $this->sectionTitle('Grupos de Equipamiento');
            $qtyW = 56.0;
            $prodW = $this->contentW() - $qtyW;

            foreach ($this->grupos as $stage => $group) {
                if (!is_array($group)) {
                    continue;
                }
                $meta = $stageMeta[$stage] ?? ['tag' => 'EQUIPAMIENTO', 'fallback' => 'Grupo de equipamiento'];
                $title = trim((string) ($group['title'] ?? $meta['fallback']));
                $products = is_array($group['products'] ?? null) ? $group['products'] : [];

                $this->ensure(58);
                $this->drawGroupHead($meta['tag'], $title);
                $this->drawGroupThead($qtyW);

                if ($products === []) {
                    $this->drawProductRow('—', 'Selección final reservada para validación de ingeniería.', '', $qtyW, $prodW);
                } else {
                    foreach ($products as $product) {
                        if (!is_array($product)) {
                            continue;
                        }
                        $qty = (int) ($product['qty'] ?? 1);
                        $unit = strtoupper((string) ($product['unit'] ?? 'UNIDADES'));
                        $name = trim((string) ($product['name'] ?? 'Equipo audiovisual'));
                        $metaParts = array_filter([
                            trim((string) ($product['brand'] ?? '')) !== '' ? 'Marca: ' . trim((string) $product['brand']) : '',
                            trim((string) ($product['category'] ?? '')) !== '' ? 'Categoría: ' . trim((string) $product['category']) : '',
                            trim((string) ($product['sku'] ?? '')) !== '' ? 'SKU: ' . trim((string) $product['sku']) : '',
                            (($product['stock'] ?? null) !== null && $product['stock'] !== '') ? 'Stock: ' . $this->formatNumber($product['stock']) : '',
                        ]);
                        $this->drawProductRow(
                            $qty . "\n" . $unit,
                            $name,
                            implode('   ·   ', $metaParts),
                            $qtyW,
                            $prodW,
                            $meta['tag'],
                            $title
                        );
                    }
                }
                $this->y += 8;
            }
            return true;
        }

        private function drawGroupHead(string $tag, string $title): void
        {
            $barH = 20.0;
            $this->pdf->rect($this->ml, $this->y, $this->contentW(), $barH, self::C_DARK);
            $pillW = $this->pdf->stringWidth($tag, true, 7) + 14;
            $this->pdf->box($this->ml + 8, $this->y + 5, $pillW, 11, self::C_BLUE, null);
            $this->putCenter($this->ml + 8 + $pillW / 2, $this->y + 5.5, $tag, true, 7, self::C_WHITE);
            $titleMax = $this->contentW() - $pillW - 30;
            $this->put($this->ml + 8 + $pillW + 8, $this->y + 5.5, $this->fit($title, true, 10, $titleMax), true, 10, self::C_WHITE);
            $this->y += $barH;
        }

        private function drawGroupThead(float $qtyW): void
        {
            $thH = 16.0;
            $this->pdf->box($this->ml, $this->y, $qtyW, $thH, self::C_HEADBG, self::C_BORDER);
            $this->pdf->box($this->ml + $qtyW, $this->y, $this->contentW() - $qtyW, $thH, self::C_HEADBG, self::C_BORDER);
            $this->putCenter($this->ml + $qtyW / 2, $this->y + 4, 'CANT.', true, 8, self::C_SLATE);
            $this->put($this->ml + $qtyW + 8, $this->y + 4, 'EQUIPAMIENTO PRECALIFICADO', true, 8, self::C_SLATE);
            $this->y += $thH;
        }

        private function drawProductRow(string $qtyText, string $name, string $metaLine, float $qtyW, float $prodW, ?string $tag = null, ?string $title = null): void
        {
            $nameLines = $this->wrap($name, true, 10.5, $prodW - 16, 2);
            if ($nameLines === []) {
                $nameLines = [''];
            }
            $metaLines = $metaLine !== '' ? $this->wrap($metaLine, false, 8.5, $prodW - 16, 2) : [];
            $rowH = 10 + count($nameLines) * 13 + (count($metaLines) * 11);
            $rowH = max(30.0, (float) $rowH);

            if ($this->y + $rowH > $this->H - 58) {
                $this->newPage();
                if ($tag !== null && $title !== null) {
                    $this->drawGroupHead($tag, $title);
                    $this->drawGroupThead($qtyW);
                }
            }

            $this->pdf->box($this->ml, $this->y, $qtyW, $rowH, self::C_WHITE, self::C_BORDER);
            $this->pdf->box($this->ml + $qtyW, $this->y, $prodW, $rowH, self::C_WHITE, self::C_BORDER);

            $qtyParts = explode("\n", $qtyText);
            $qtyMain = $qtyParts[0] ?? '';
            $qtyUnit = $qtyParts[1] ?? '';
            $this->putCenter($this->ml + $qtyW / 2, $this->y + $rowH / 2 - 10, $qtyMain, true, 14, self::C_BLUE);
            if ($qtyUnit !== '') {
                $this->putCenter($this->ml + $qtyW / 2, $this->y + $rowH / 2 + 4, $this->fit($qtyUnit, false, 6, $qtyW - 6), false, 6, self::C_MUTED);
            }

            $ty = $this->y + 8;
            foreach ($nameLines as $line) {
                $this->put($this->ml + $qtyW + 8, $ty, $line, true, 10.5, self::C_DARK);
                $ty += 13;
            }
            foreach ($metaLines as $line) {
                $this->put($this->ml + $qtyW + 8, $ty, $line, false, 8.5, self::C_MUTED);
                $ty += 11;
            }

            $this->y += $rowH;
        }

        private function fallbackDetails(): void
        {
            $detalles = trim((string) ($this->lead['detalles'] ?? $this->lead['resumenTecnico'] ?? ''));
            if ($detalles === '') {
                return;
            }
            $this->sectionTitle('Resumen técnico del diagnóstico');
            $lines = explode("\n", $detalles);
            foreach ($lines as $rawLine) {
                $wrapped = $this->wrap($rawLine, false, 9.5, $this->contentW(), 0);
                if ($wrapped === []) {
                    $this->y += 6;
                    continue;
                }
                foreach ($wrapped as $line) {
                    $this->ensure(13);
                    $this->put($this->ml, $this->y, $line, false, 9.5, self::C_SLATE);
                    $this->y += 13;
                }
            }
            $this->y += 10;
        }

        private function closingSection(): void
        {
            $lines = $this->wrap(MIZO_PDF_CLOSING_MESSAGE, false, 10.5, $this->contentW() - 32, 0);
            $boxH = 34 + count($lines) * 14 + 10;
            $this->ensure($boxH + 6);
            $this->pdf->box($this->ml, $this->y, $this->contentW(), $boxH, self::C_CARDBG, self::C_CARDBR);
            $this->pdf->rect($this->ml, $this->y, 5, $boxH, self::C_BLUE);
            $this->put($this->ml + 16, $this->y + 12, 'PRÓXIMO PASO CON MIZO', true, 11, self::C_BLUE);
            $ty = $this->y + 32;
            foreach ($lines as $line) {
                $this->put($this->ml + 16, $ty, $line, false, 10.5, self::C_DARK);
                $ty += 14;
            }
            $this->y += $boxH + 10;
        }

        private function upper(string $s): string
        {
            if (function_exists('mb_strtoupper')) {
                return mb_strtoupper($s, 'UTF-8');
            }
            return strtoupper($s);
        }

        private function formatNumber($value): string
        {
            if (is_numeric($value)) {
                $number = (float) $value;
                if (floor($number) === $number) {
                    return number_format($number, 0, ',', '.');
                }
                return number_format($number, 1, ',', '.');
            }
            return trim((string) ($value ?? ''));
        }

        public function build(): string
        {
            $this->newPage();
            $this->infoSection();
            $hasMetrics = $this->metricsSection();
            $hasGroups = $this->groupsSection();
            if (!$hasMetrics && !$hasGroups) {
                $this->fallbackDetails();
            }
            $this->closingSection();
            return $this->pdf->output();
        }
    }
}

if (!function_exists('mizo_generar_pdf_informe')) {
    /**
     * Genera el informe PDF del lead con el motor nativo (sin dependencias).
     *
     * @return array{ok:bool, mode:string, filename:string, mime:string, content:string, error?:string}
     */
    function mizo_generar_pdf_informe(array $lead): array
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($lead['id'] ?? 'mizo')) ?: 'mizo';
        $baseName = 'Informe-Mizo-' . $slug;

        try {
            $report = new MizoPdfReport($lead);
            $content = $report->build();
            if (is_string($content) && strncmp($content, '%PDF', 4) === 0) {
                return [
                    'ok' => true,
                    'mode' => 'native',
                    'filename' => $baseName . '.pdf',
                    'mime' => 'application/pdf',
                    'content' => $content,
                ];
            }
        } catch (Throwable $error) {
            return [
                'ok' => false,
                'mode' => 'error',
                'filename' => $baseName . '.txt',
                'mime' => 'text/plain; charset=UTF-8',
                'content' => "Informe Mizo\n\n" . (string) ($lead['detalles'] ?? $lead['resumenTecnico'] ?? '') . "\n\n" . MIZO_PDF_CLOSING_MESSAGE,
                'error' => $error->getMessage(),
            ];
        }

        return [
            'ok' => false,
            'mode' => 'error',
            'filename' => $baseName . '.txt',
            'mime' => 'text/plain; charset=UTF-8',
            'content' => "Informe Mizo\n\n" . (string) ($lead['detalles'] ?? $lead['resumenTecnico'] ?? '') . "\n\n" . MIZO_PDF_CLOSING_MESSAGE,
            'error' => 'No se pudo construir el PDF nativo.',
        ];
    }
}

// Previsualizacion directa del informe (solo al invocar este script).
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
                    ['qty' => 2, 'unit' => 'unidades', 'name' => 'Shure SLXD24/SM58 Sistema Inalámbrico Digital', 'brand' => 'Shure', 'category' => 'Micrófonos', 'sku' => 'AM-SHU-SLXD24', 'stock' => 6],
                ]],
                'rack-potencia' => ['title' => 'Procesamiento en Rack y Potencia', 'products' => [
                    ['qty' => 1, 'unit' => 'unidades', 'name' => 'dbx DriveRack PA2 Procesador de Altavoces', 'brand' => 'dbx', 'category' => 'Procesadores', 'sku' => 'PM-DBX-PA2', 'stock' => 5],
                    ['qty' => 4, 'unit' => 'unidades', 'name' => 'QSC CP12 Parlante Activo 12 Pulgadas 1000W', 'brand' => 'QSC', 'category' => 'Parlantes', 'sku' => 'PM-QSC-CP12', 'stock' => 8],
                ]],
                'proyeccion-video' => ['title' => 'Sistemas de Proyección y Video', 'products' => [
                    ['qty' => 1, 'unit' => 'unidades', 'name' => 'Epson EB-FH52 Proyector 3LCD Full HD 4000 Lúmenes', 'brand' => 'Epson', 'category' => 'Proyectores', 'sku' => 'CR-EPSON-EB-FH52', 'stock' => 7],
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
