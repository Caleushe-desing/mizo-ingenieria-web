<?php
declare(strict_types=1);

namespace MizoCrm;

$fpdfBoot = dirname(__DIR__) . '/lib/fpdf/fpdf.php';
if (is_file($fpdfBoot)) {
	if (!defined('FPDF_FONTPATH')) {
		define('FPDF_FONTPATH', dirname(__DIR__) . '/lib/fpdf/font/');
	}
	require_once $fpdfBoot;
}

/**
 * Genera el PDF de cotización (2 páginas) para adjuntar al correo.
 * Usa FPDF embebido en lib/fpdf (sin Composer / sin GD).
 */
final class QuotePdf
{
	/**
	 * @param array<string,mixed> $quote
	 * @param list<array<string,mixed>> $items
	 * @return array{filename:string,mime:string,content:string}|null
	 */
	public static function attachment(array $quote, array $items): ?array
	{
		try {
			$content = self::render($quote, $items);
		} catch (\Throwable) {
			return null;
		}
		if ($content === '') {
			return null;
		}
		$number = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) ($quote['number'] ?? 'cotizacion')) ?: 'cotizacion';
		$rev = trim((string) ($quote['revision'] ?? ''));
		$filename = 'Cotizacion-' . $number . ($rev !== '' ? '-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', $rev) : '') . '.pdf';
		return [
			'filename' => $filename,
			'mime' => 'application/pdf',
			'content' => $content,
		];
	}

	/** @param array<string,mixed> $quote @param list<array<string,mixed>> $items */
	public static function render(array $quote, array $items): string
	{
		if (!class_exists(\FPDF::class)) {
			throw new \RuntimeException('FPDF no está instalado.');
		}
		$pdf = new QuotePdfDocument('P', 'mm', 'A4');
		$pdf->build($quote, $items);
		return $pdf->Output('S');
	}
}

/** @internal */
final class QuotePdfDocument extends \FPDF
{
	/** @var array<string,mixed> */
	private array $quote = [];

	/** @param array<string,mixed> $quote @param list<array<string,mixed>> $items */
	public function build(array $quote, array $items): void
	{
		$this->quote = $quote;
		$this->SetAutoPageBreak(true, 18);
		$this->SetMargins(12, 12, 12);
		$this->AddPage();
		$this->drawPage1($quote, $items);
		$this->AddPage();
		$this->drawPage2($quote);
	}

	public function Header(): void
	{
		// Letterhead dibujado en cada página vía drawLetterhead.
	}

	public function Footer(): void
	{
		$this->SetY(-14);
		$this->SetFont('Helvetica', '', 8);
		$this->SetTextColor(100, 100, 100);
		$number = (string) ($this->quote['number'] ?? '');
		$this->Cell(0, 5, $this->t('Mizo · ' . $number . ' · Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'C');
	}

	/** @param array<string,mixed> $quote @param list<array<string,mixed>> $items */
	private function drawPage1(array $quote, array $items): void
	{
		$this->AliasNbPages();
		$this->drawLetterhead($quote, '1 / 2');

		$companyRut = trim((string) Config::RUT) !== '' ? (string) Config::RUT : '77.589.163-7';
		$clientName = (string) ($quote['client_name'] ?? 'Cliente');
		$clientRut = trim((string) ($quote['client_rut'] ?? ''));
		$contactName = trim((string) ($quote['contact_name'] ?? ''));
		$contactTitle = trim((string) ($quote['contact_title'] ?? ''));
		$contactEmail = trim((string) ($quote['contact_email'] ?? $quote['client_email'] ?? ''));
		$contactPhone = trim((string) ($quote['client_phone'] ?? ''));
		$contactCity = trim((string) ($quote['client_city'] ?? ''));

		$y = $this->GetY();
		$this->SetFont('Helvetica', 'B', 9);
		$this->SetTextColor(28, 155, 216);
		$this->SetXY(12, $y);
		$this->Cell(90, 5, $this->t('De'), 0, 0);
		$this->SetXY(108, $y);
		$this->Cell(90, 5, $this->t('Para'), 0, 1);
		$this->SetTextColor(30, 30, 30);
		$this->SetFont('Helvetica', 'B', 10);
		$y = $this->GetY();
		$this->SetXY(12, $y);
		$this->MultiCell(90, 4.5, $this->t(Config::COMPANY . ' · RUT ' . $companyRut), 0, 'L');
		$yLeft = $this->GetY();
		$this->SetXY(108, $y);
		$para = $clientName . ($clientRut !== '' ? ' · RUT ' . $clientRut : '');
		$this->MultiCell(90, 4.5, $this->t($para), 0, 'L');
		$this->SetFont('Helvetica', '', 9);
		$this->SetTextColor(70, 70, 70);
		$y = $yLeft;
		$this->SetXY(12, $y);
		$this->MultiCell(90, 4, $this->t(Config::EMAIL . ' · ' . Config::PHONE . "\n" . Config::ADDRESS), 0, 'L');
		$yLeft = $this->GetY();
		$bits = array_filter([
			$contactName !== '' ? ('Contacto: ' . $contactName . ($contactTitle !== '' ? ' · ' . $contactTitle : '')) : '',
			$contactEmail,
			$contactPhone,
			$contactCity,
		]);
		$this->SetXY(108, $y);
		$this->MultiCell(90, 4, $this->t(implode("\n", $bits)), 0, 'L');
		$this->SetY(max($yLeft, $this->GetY()) + 3);

		$reference = trim((string) ($quote['intro'] ?? $quote['deal_title'] ?? ''));
		if ($reference !== '') {
			$this->SetFont('Helvetica', 'B', 9);
			$this->SetTextColor(28, 155, 216);
			$this->Cell(0, 5, $this->t('Referencia'), 0, 1);
			$this->SetFont('Helvetica', '', 10);
			$this->SetTextColor(30, 30, 30);
			$this->MultiCell(0, 4.5, $this->t($reference), 0, 'L');
			$this->Ln(2);
		}

		$this->SetFont('Helvetica', 'B', 9);
		$this->SetTextColor(28, 155, 216);
		$this->Cell(0, 5, $this->t('Detalle de la propuesta'), 0, 1);
		$this->Ln(1);

		// Header tabla
		$this->SetFillColor(255, 255, 255);
		$this->SetDrawColor(28, 155, 216);
		$this->SetTextColor(28, 155, 216);
		$this->SetFont('Helvetica', 'B', 8);
		$this->Cell(10, 6, '#', 'B', 0, 'C');
		$this->Cell(95, 6, $this->t('Descripción'), 'B', 0, 'L');
		$this->Cell(22, 6, 'Cant.', 'B', 0, 'R');
		$this->Cell(30, 6, $this->t('P. unit. neto'), 'B', 0, 'R');
		$this->Cell(29, 6, $this->t('Total neto'), 'B', 1, 'R');

		$this->SetTextColor(30, 30, 30);
		$this->SetDrawColor(220, 220, 220);
		$n = 1;
		foreach ($items as $item) {
			$label = Models\Quote::itemLabel($item);
			if ($label === '') {
				continue;
			}
			$detail = trim((string) ($item['description'] ?? ''));
			if ($detail !== '' && strcasecmp($detail, $label) === 0) {
				$detail = '';
			}
			$qty = rtrim(rtrim(number_format((float) ($item['quantity'] ?? 1), 2, ',', '.'), '0'), ',');
			$unit = trim((string) ($item['unit'] ?? 'un'));
			$qtyLabel = $qty . ($unit !== '' ? ' ' . $unit : '');
			$price = $this->money((int) ($item['unit_price'] ?? 0));
			$total = $this->money((int) ($item['total'] ?? 0));
			$desc = $label . ($detail !== '' ? "\n" . $detail : '');

			if ($this->GetY() > 248) {
				$this->AddPage();
				$this->drawLetterhead($quote, '1 / 2');
			}

			$this->SetFont('Helvetica', 'B', 8);
			$descW = 95;
			$lineH = 3.8;
			$descH = $this->calcMultiHeight($descW, $lineH, $desc);
			$rowH = max(7.0, $descH + 1);
			$x0 = $this->GetX();
			$y0 = $this->GetY();

			$this->Rect($x0, $y0, 186, $rowH);
			$this->SetXY($x0, $y0 + 1);
			$this->Cell(10, $rowH - 1, (string) $n, 0, 0, 'C');
			$this->SetXY($x0 + 10, $y0 + 1);
			$this->MultiCell($descW, $lineH, $this->t($desc), 0, 'L');
			$this->SetFont('Helvetica', '', 8);
			$this->SetXY($x0 + 105, $y0 + 1);
			$this->Cell(22, $rowH - 1, $this->t($qtyLabel), 0, 0, 'R');
			$this->Cell(30, $rowH - 1, $this->t($price), 0, 0, 'R');
			$this->Cell(29, $rowH - 1, $this->t($total), 0, 0, 'R');
			$this->SetY($y0 + $rowH);
			$n++;
		}

		$this->Ln(3);
		$this->SetFont('Helvetica', '', 9);
		$this->SetTextColor(80, 80, 80);
		$x = 120;
		$this->SetX($x);
		$this->Cell(40, 5, 'Neto', 0, 0, 'L');
		$this->Cell(34, 5, $this->t($this->money((int) ($quote['subtotal'] ?? 0))), 0, 1, 'R');
		$this->SetX($x);
		$this->Cell(40, 5, $this->t('IVA ' . (int) ($quote['tax_rate'] ?? 19) . '%'), 0, 0, 'L');
		$this->Cell(34, 5, $this->t($this->money((int) ($quote['tax'] ?? 0))), 0, 1, 'R');
		$this->SetFont('Helvetica', 'B', 11);
		$this->SetTextColor(244, 123, 32);
		$this->SetDrawColor(30, 30, 30);
		$this->SetX($x);
		$this->Cell(40, 7, 'Total', 'T', 0, 'L');
		$this->Cell(34, 7, $this->t($this->money((int) ($quote['total'] ?? 0))), 'T', 1, 'R');
		$this->SetTextColor(100, 100, 100);
		$this->SetFont('Helvetica', '', 8);
		$this->Cell(0, 4, $this->t('Montos en CLP. IVA incluido en el total.'), 0, 1, 'R');

		$notes = trim((string) ($quote['notes'] ?? ''));
		if ($notes !== '') {
			$this->Ln(2);
			$this->SetFont('Helvetica', '', 9);
			$this->SetTextColor(60, 60, 60);
			$this->MultiCell(0, 4, $this->t($notes), 0, 'L');
		}
	}

	/** @param array<string,mixed> $quote */
	private function drawPage2(array $quote): void
	{
		$this->drawLetterhead($quote, '2 / 2');

		$companyRut = trim((string) Config::RUT) !== '' ? (string) Config::RUT : '77.589.163-7';
		$clientName = (string) ($quote['client_name'] ?? 'Cliente');
		$clientRut = trim((string) ($quote['client_rut'] ?? ''));

		$this->SetFont('Helvetica', 'B', 9);
		$this->SetTextColor(28, 155, 216);
		$y = $this->GetY();
		$this->SetXY(12, $y);
		$this->Cell(90, 5, $this->t('De'), 0, 0);
		$this->SetXY(108, $y);
		$this->Cell(90, 5, $this->t('Para'), 0, 1);
		$this->SetFont('Helvetica', 'B', 10);
		$this->SetTextColor(30, 30, 30);
		$y = $this->GetY();
		$this->SetXY(12, $y);
		$this->MultiCell(90, 4.5, $this->t(Config::COMPANY . ' · RUT ' . $companyRut), 0, 'L');
		$this->SetXY(108, $y);
		$this->MultiCell(90, 4.5, $this->t($clientName . ($clientRut !== '' ? ' · RUT ' . $clientRut : '')), 0, 'L');
		$this->Ln(4);

		$terms = Models\Quote::termsText($quote);
		$about = Models\Quote::aboutText($quote);

		$this->SetFont('Helvetica', 'B', 11);
		$this->SetTextColor(244, 123, 32);
		$this->Cell(0, 6, $this->t('Condiciones y modo de pago'), 0, 1);
		$this->SetFont('Helvetica', '', 9);
		$this->SetTextColor(40, 40, 40);
		$this->MultiCell(0, 4.2, $this->t($terms), 0, 'L');
		$this->Ln(5);

		$this->SetFont('Helvetica', 'B', 11);
		$this->SetTextColor(244, 123, 32);
		$this->Cell(0, 6, $this->t('Mizo · Ingeniería e integración'), 0, 1);
		$this->SetFont('Helvetica', '', 9);
		$this->SetTextColor(40, 40, 40);
		$this->MultiCell(0, 4.2, $this->t($about), 0, 'L');
	}

	/** @param array<string,mixed> $quote */
	private function drawLetterhead(array $quote, string $pageLabel): void
	{
		$this->SetY(12);
		$this->SetFont('Helvetica', 'B', 18);
		$this->SetTextColor(244, 123, 32);
		$this->Cell(70, 8, 'MIZO', 0, 0, 'L');
		$this->SetFont('Helvetica', 'B', 9);
		$this->SetTextColor(28, 155, 216);
		$this->Cell(50, 5, $this->t('COTIZACIÓN'), 0, 0, 'R');
		$this->SetFont('Helvetica', 'B', 14);
		$this->SetTextColor(20, 20, 20);
		$this->Cell(66, 5, $this->t((string) ($quote['number'] ?? '')), 0, 1, 'R');
		$this->SetFont('Helvetica', '', 8);
		$this->SetTextColor(100, 100, 100);
		$meta = 'Emisión ' . when($quote['sent_at'] ?? $quote['created_at'] ?? null, 'd-m-Y');
		$valid = trim((string) ($quote['valid_until'] ?? ''));
		if ($valid !== '') {
			$meta .= ' · Válida hasta ' . when($valid, 'd-m-Y');
		}
		$rev = trim((string) ($quote['revision'] ?? ''));
		if ($rev !== '') {
			$meta .= ' · ' . $rev;
		}
		$this->Cell(120, 4, $this->t($meta), 0, 0, 'L');
		$this->Cell(66, 4, $this->t($pageLabel), 0, 1, 'R');
		$this->SetDrawColor(244, 123, 32);
		$this->SetLineWidth(0.6);
		$this->Line(12, $this->GetY() + 1, 198, $this->GetY() + 1);
		$this->Ln(5);
		$this->SetTextColor(30, 30, 30);
		$this->SetLineWidth(0.2);
	}

	private function money(int $amount): string
	{
		return '$' . number_format($amount, 0, ',', '.');
	}

	private function t(string $text): string
	{
		$converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
		return is_string($converted) ? $converted : $text;
	}

	private function calcMultiHeight(float $width, float $lineHeight, string $text): float
	{
		$cw = $this->GetStringWidth('a');
		if ($cw <= 0) {
			return $lineHeight;
		}
		$lines = 0;
		foreach (explode("\n", $text) as $chunk) {
			$chunk = $this->t($chunk);
			$w = $this->GetStringWidth($chunk);
			$lines += max(1, (int) ceil($w / max(1, $width - 1)));
		}
		return max($lineHeight, $lines * $lineHeight);
	}
}
