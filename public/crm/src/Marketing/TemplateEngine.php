<?php
declare(strict_types=1);

namespace MizoCrm\Marketing;

/**
 * Sustituye etiquetas {variable} en asuntos y cuerpos de correos comerciales.
 */
final class TemplateEngine
{
	/** @return array<string, string> */
	public static function catalog(): array
	{
		return [
			'nombre_cliente' => 'Nombre del contacto',
			'nombre_empresa' => 'Empresa / cliente',
			'numero_cotizacion' => 'Número de cotización',
			'total_cotizacion' => 'Total de la cotización',
			'enlace_cotizacion' => 'Enlace público de la cotización',
			'nombre_vendedor' => 'Tu nombre',
			'ciudad' => 'Ciudad del cliente',
			'proyecto' => 'Nombre del proyecto',
		];
	}

	/**
	 * @param array<string, mixed>|null $client
	 * @param array<string, mixed>|null $contact
	 * @param array<string, mixed>|null $quote
	 * @param array<string, mixed>|null $user
	 * @param array<string, mixed>|null $deal
	 */
	public static function context(
		?array $client,
		?array $contact,
		?array $quote,
		?array $user,
		?array $deal = null,
		string $publicUrl = '',
	): array {
		$contactName = trim((string) ($contact['name'] ?? ''));
		if ($contactName === '') {
			$contactName = trim((string) ($client['contact_name'] ?? ''));
		}
		if ($contactName === '') {
			$contactName = trim((string) ($client['name'] ?? ''));
		}

		$number = trim((string) ($quote['number'] ?? ''));
		$rev = trim((string) ($quote['revision'] ?? ''));
		if ($number !== '' && $rev !== '') {
			$number .= ' ' . $rev;
		}

		$total = '';
		if ($quote !== null && isset($quote['total'])) {
			$total = '$' . number_format((int) $quote['total'], 0, ',', '.');
		}

		return [
			'nombre_cliente' => $contactName,
			'nombre_empresa' => trim((string) ($client['name'] ?? '')),
			'numero_cotizacion' => $number,
			'total_cotizacion' => $total,
			'enlace_cotizacion' => $publicUrl,
			'nombre_vendedor' => trim((string) ($user['name'] ?? '')),
			'ciudad' => trim((string) ($client['city'] ?? '')),
			'proyecto' => trim((string) ($deal['title'] ?? ($quote['deal_title'] ?? ''))),
		];
	}

	/** @param array<string, string> $vars */
	public static function render(string $text, array $vars): string
	{
		return (string) preg_replace_callback(
			'/\{([a-z0-9_]+)\}/i',
			static function (array $m) use ($vars): string {
				$key = strtolower($m[1]);
				return array_key_exists($key, $vars) ? (string) $vars[$key] : $m[0];
			},
			$text
		);
	}
}
