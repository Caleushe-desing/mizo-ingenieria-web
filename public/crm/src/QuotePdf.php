<?php
declare(strict_types=1);

namespace MizoCrm;

/**
 * PDF de cotización idéntico a la vista pública (Dompdf + logo Mizo).
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
		$autoload = dirname(__DIR__) . '/lib/dompdf-src/dompdf/autoload.inc.php';
		if (!is_file($autoload)) {
			throw new \RuntimeException('Dompdf no está instalado.');
		}
		require_once $autoload;

		$publicRoot = realpath(dirname(__DIR__, 2));
		if ($publicRoot === false) {
			throw new \RuntimeException('No se encontró la carpeta public.');
		}
		$logo = self::logoPath($publicRoot);
		$html = self::html($quote, $items, $logo);

		$options = new \Dompdf\Options();
		$options->set('isRemoteEnabled', false);
		$options->set('isHtml5ParserEnabled', true);
		$options->setChroot($publicRoot);
		$options->set('defaultFont', 'DejaVu Sans');

		$dompdf = new \Dompdf\Dompdf($options);
		$dompdf->loadHtml($html, 'UTF-8');
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();
		$out = $dompdf->output();
		return is_string($out) ? $out : '';
	}

	private static function logoPath(string $publicRoot): string
	{
		$jpg = $publicRoot . DIRECTORY_SEPARATOR . 'mizo-logo-pdf.jpg';
		if (is_file($jpg)) {
			return $jpg;
		}
		$png = $publicRoot . DIRECTORY_SEPARATOR . 'mizo-logo.png';
		return is_file($png) ? $png : '';
	}

	/** @param array<string,mixed> $quote @param list<array<string,mixed>> $items */
	private static function html(array $quote, array $items, string $logoSrc): string
	{
		$quote = $quote;
		$items = $items;
		$logoSrc = $logoSrc;
		ob_start();
		require dirname(__DIR__) . '/views/quotes/pdf.php';
		return (string) ob_get_clean();
	}
}
