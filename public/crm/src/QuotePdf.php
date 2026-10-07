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
	private static string $lastError = '';

	public static function lastError(): string
	{
		return self::$lastError;
	}

	public static function attachment(array $quote, array $items): ?array
	{
		self::$lastError = '';
		try {
			$content = self::render($quote, $items);
		} catch (\Throwable $e) {
			self::$lastError = $e->getMessage();
			return null;
		}
		if ($content === '') {
			self::$lastError = 'Dompdf devolvió un PDF vacío.';
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
		@ini_set('memory_limit', '256M');
		@set_time_limit(90);

		$autoload = dirname(__DIR__) . '/lib/dompdf-src/dompdf/autoload.inc.php';
		if (!is_file($autoload)) {
			throw new \RuntimeException('Dompdf no está instalado.');
		}
		require_once $autoload;

		$publicRoot = realpath(dirname(__DIR__, 2));
		if ($publicRoot === false) {
			throw new \RuntimeException('No se encontró la carpeta public.');
		}
		$logo = self::logoSrc($publicRoot);
		$token = trim((string) ($quote['token'] ?? ''));
		$status = (string) ($quote['status'] ?? '');
		// Incluye borrador: el adjunto del correo se genera antes de marcar "enviada".
		$canDecide = $token !== '' && !in_array($status, ['aceptada', 'rechazada'], true);
		$acceptUrl = $canDecide ? App::absolute('/q/' . $token . '/aceptar') : '';
		$rejectUrl = $canDecide ? App::absolute('/q/' . $token . '/rechazar') : '';
		$html = self::html($quote, $items, $logo, $acceptUrl, $rejectUrl);

		$workDir = self::workDir($publicRoot);
		$options = new \Dompdf\Options();
		$options->set('isRemoteEnabled', false);
		$options->set('isHtml5ParserEnabled', true);
		$options->setChroot($publicRoot);
		$options->setTempDir($workDir);
		$options->setFontCache($workDir);
		$options->set('defaultFont', 'DejaVu Sans');

		$dompdf = new \Dompdf\Dompdf($options);
		$dompdf->setBasePath($publicRoot . DIRECTORY_SEPARATOR);
		$dompdf->loadHtml($html, 'UTF-8');
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();
		$out = $dompdf->output();
		return is_string($out) ? $out : '';
	}

	/** Ruta relativa al chroot (public/) para que Dompdf encuentre el logo en hosting. */
	private static function logoSrc(string $publicRoot): string
	{
		foreach (['mizo-logo-pdf.jpg', 'mizo-logo.png'] as $name) {
			if (is_file($publicRoot . DIRECTORY_SEPARATOR . $name)) {
				return $name;
			}
		}
		return '';
	}

	/** Temp/font-cache escribible (vendor/ del FTP suele ser de solo lectura). */
	private static function workDir(string $publicRoot): string
	{
		$candidates = [
			$publicRoot . DIRECTORY_SEPARATOR . 'crm-data' . DIRECTORY_SEPARATOR . 'dompdf',
			sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mizo-dompdf',
		];
		foreach ($candidates as $dir) {
			if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
				continue;
			}
			if (is_writable($dir)) {
				return $dir;
			}
		}
		return sys_get_temp_dir();
	}

	/**
	 * @param array<string,mixed> $quote
	 * @param list<array<string,mixed>> $items
	 */
	private static function html(
		array $quote,
		array $items,
		string $logoSrc,
		string $acceptUrl = '',
		string $rejectUrl = '',
	): string {
		ob_start();
		require dirname(__DIR__) . '/views/quotes/pdf.php';
		return (string) ob_get_clean();
	}
}
