<?php
declare(strict_types=1);

namespace MizoCrm;

use RuntimeException;

/** Lee una ficha pública de producto y devuelve los campos para el alta del catálogo. */
final class ProductImporter
{
	private const MAX_BYTES = 1500000;
	private const MAX_REDIRECTS = 4;

	/** @return array{nombre: string, descripcion: string, proveedor_empresa: string, proveedor_link: string} */
	public static function fromUrl(string $url): array
	{
		$url = self::publicUrl($url);
		$html = self::fetch($url);
		$parsed = self::parse($html, $url);
		if ($parsed['nombre'] === '' && $parsed['descripcion'] === '') {
			throw new RuntimeException('No se pudo leer el nombre ni la descripción. Prueba con la URL directa de la ficha.');
		}
		return $parsed;
	}

	/** @return array{nombre: string, descripcion: string, proveedor_empresa: string, proveedor_link: string} */
	public static function parse(string $html, string $url): array
	{
		$host = self::hostLabel((string) parse_url($url, PHP_URL_HOST));
		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		$xpath = new \DOMXPath($dom);

		$json = self::productFromJsonLd($xpath);
		$name = $json['name'] ?? '';
		$description = $json['description'] ?? '';
		if ($name === '') {
			$name = self::meta($xpath, 'og:title') ?: self::meta($xpath, 'twitter:title');
		}
		if ($name === '') {
			$h1 = $xpath->query('//h1');
			if ($h1 instanceof \DOMNodeList && $h1->length > 0) {
				$name = self::plain($h1->item(0)?->textContent ?? '');
			}
		}
		if ($name === '') {
			$title = $xpath->query('//title');
			if ($title instanceof \DOMNodeList && $title->length > 0) {
				$name = self::plain($title->item(0)?->textContent ?? '');
			}
		}
		$name = self::cleanTitle($name, $host);

		if ($description === '') {
			$description = self::meta($xpath, 'og:description')
				?: self::meta($xpath, 'description')
				?: self::meta($xpath, 'twitter:description');
		}
		if ($description === '') {
			$block = $xpath->query('//*[@itemprop="description"]');
			if ($block instanceof \DOMNodeList && $block->length > 0) {
				$description = self::plain($block->item(0)?->textContent ?? '');
			}
		}
		$description = self::clip($description, 4000);

		return [
			'nombre' => self::clip($name, 180),
			'descripcion' => $description,
			'proveedor_empresa' => self::clip($host, 160),
			'proveedor_link' => $url,
		];
	}

	private static function fetch(string $url): string
	{
		if (!function_exists('curl_init')) {
			throw new RuntimeException('El servidor no puede leer páginas externas en este momento.');
		}
		$current = $url;
		for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
			$current = self::publicUrl($current);
			$parts = parse_url($current);
			$host = (string) ($parts['host'] ?? '');
			$scheme = strtolower((string) ($parts['scheme'] ?? ''));
			$port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
			$ip = self::publicIp($host);
			$ch = curl_init($current);
			if ($ch === false) {
				throw new RuntimeException('No se pudo leer esa página.');
			}
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => 12,
				CURLOPT_USERAGENT => 'MizoCRM/1.0 (+https://mizo.cl)',
				CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
				CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
			]);
			$body = curl_exec($ch);
			$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			$next = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
			$type = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
			curl_close($ch);
			if (!is_string($body)) {
				throw new RuntimeException('No se pudo leer esa página. Revisa que la URL sea la ficha pública del producto.');
			}
			if (strlen($body) > self::MAX_BYTES) {
				$body = substr($body, 0, self::MAX_BYTES);
			}
			if ($code >= 300 && $code < 400 && $next !== '') {
				$current = $next;
				continue;
			}
			if ($code < 200 || $code >= 300) {
				throw new RuntimeException('La página del proveedor no respondió. Prueba de nuevo con la URL de la ficha.');
			}
			if ($type !== '' && !str_contains($type, 'html') && !str_contains($type, 'xml') && !str_contains($type, 'text/plain')) {
				throw new RuntimeException('Esa URL no es una ficha de producto en HTML.');
			}
			return $body;
		}
		throw new RuntimeException('La página redirige demasiadas veces. Pega la URL final de la ficha.');
	}

	private static function publicUrl(string $url): string
	{
		$url = trim($url);
		if ($url === '' || strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) {
			throw new RuntimeException('Pega una URL http o https de la ficha del producto.');
		}
		$parts = parse_url($url);
		$scheme = strtolower((string) ($parts['scheme'] ?? ''));
		$host = strtolower((string) ($parts['host'] ?? ''));
		if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
			throw new RuntimeException('Pega una URL http o https de la ficha del producto.');
		}
		$port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
		if (!in_array($port, [80, 443], true)) {
			throw new RuntimeException('Solo se pueden leer fichas publicadas en la web.');
		}
		self::publicIp($host);
		$rebuilt = $scheme . '://' . $host;
		if (isset($parts['port'])) {
			$rebuilt .= ':' . $port;
		}
		$rebuilt .= ($parts['path'] ?? '/');
		if (isset($parts['query'])) {
			$rebuilt .= '?' . $parts['query'];
		}
		return $rebuilt;
	}

	private static function publicIp(string $host): string
	{
		$host = strtolower(trim($host, '[]'));
		if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
			throw new RuntimeException('Esa dirección no es una ficha pública.');
		}
		if (filter_var($host, FILTER_VALIDATE_IP)) {
			if (!self::isPublicAddress($host)) {
				throw new RuntimeException('Esa dirección no es una ficha pública.');
			}
			return $host;
		}
		$ips = [];
		if (function_exists('dns_get_record')) {
			$records = @dns_get_record($host, DNS_A + DNS_AAAA);
			if (is_array($records)) {
				foreach ($records as $record) {
					if (!empty($record['ip'])) {
						$ips[] = (string) $record['ip'];
					}
					if (!empty($record['ipv6'])) {
						$ips[] = (string) $record['ipv6'];
					}
				}
			}
		}
		if ($ips === []) {
			$v4 = @gethostbynamel($host);
			if (is_array($v4)) {
				$ips = $v4;
			}
		}
		foreach ($ips as $ip) {
			if (self::isPublicAddress($ip)) {
				return $ip;
			}
		}
		throw new RuntimeException('Esa dirección no es una ficha pública.');
	}

	private static function isPublicAddress(string $ip): bool
	{
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
	}

	/** @return array{name?: string, description?: string} */
	private static function productFromJsonLd(\DOMXPath $xpath): array
	{
		$found = [];
		$scripts = $xpath->query('//script[@type="application/ld+json"]');
		if (!$scripts instanceof \DOMNodeList) {
			return $found;
		}
		foreach ($scripts as $script) {
			$data = json_decode(trim($script->textContent), true);
			if (!is_array($data)) {
				continue;
			}
			self::walkJsonLd($data, $found);
			if (!empty($found['name']) && !empty($found['description'])) {
				break;
			}
		}
		return $found;
	}

	private static function walkJsonLd(mixed $node, array &$found): void
	{
		if (!is_array($node) || (!empty($found['name']) && !empty($found['description']))) {
			return;
		}
		$type = $node['@type'] ?? '';
		$types = is_array($type) ? $type : [$type];
		$isProduct = false;
		foreach ($types as $item) {
			if (is_string($item) && strcasecmp($item, 'Product') === 0) {
				$isProduct = true;
			}
		}
		if ($isProduct) {
			if (empty($found['name']) && !empty($node['name']) && is_string($node['name'])) {
				$found['name'] = self::plain($node['name']);
			}
			if (empty($found['description']) && !empty($node['description']) && is_string($node['description'])) {
				$found['description'] = self::plain($node['description']);
			}
		}
		foreach ($node as $child) {
			if (is_array($child)) {
				self::walkJsonLd($child, $found);
			}
		}
	}

	private static function meta(\DOMXPath $xpath, string $key): string
	{
		$query = '//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . $key . '" or translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . $key . '"]/@content';
		$nodes = $xpath->query($query);
		if (!$nodes instanceof \DOMNodeList || $nodes->length < 1) {
			return '';
		}
		return self::plain($nodes->item(0)?->nodeValue ?? '');
	}

	private static function cleanTitle(string $title, string $host): string
	{
		$title = self::plain($title);
		$brand = preg_replace('/\..*$/', '', $host) ?? '';
		$parts = preg_split('/\s+[|\x{2013}\x{2014}]\s+|\s+-\s+/u', $title) ?: [$title];
		if (count($parts) > 1 && $brand !== '') {
			$last = (string) end($parts);
			if (mb_stripos($last, $brand) !== false) {
				array_pop($parts);
				$title = trim(implode(' - ', $parts));
			}
		}
		return $title;
	}

	private static function hostLabel(string $host): string
	{
		$host = strtolower($host);
		if (str_starts_with($host, 'www.')) {
			$host = substr($host, 4);
		}
		if (function_exists('idn_to_utf8')) {
			$unicode = idn_to_utf8($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
			if (is_string($unicode) && $unicode !== '') {
				$host = $unicode;
			}
		}
		return $host;
	}

	private static function plain(string $value): string
	{
		$value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
		return $value;
	}

	private static function clip(string $value, int $max): string
	{
		return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
	}
}
