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
		$parsed['imagenes'] = self::downloadImages(self::extractImageUrls($html, $url), 'imp-' . substr(hash('sha256', $url), 0, 16));
		return $parsed;
	}

	/** @return list<string> Rutas públicas /crm/uploads/productos/... */
	public static function saveImagesFromPage(string $pageUrl, string $folder): array
	{
		try {
			$pageUrl = self::publicUrl($pageUrl);
			$html = self::fetch($pageUrl);
			return self::downloadImages(self::extractImageUrls($html, $pageUrl), $folder);
		} catch (RuntimeException) {
			return [];
		}
	}

	/** @return list<string> */
	public static function extractImageUrls(string $html, string $pageUrl): array
	{
		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		$xpath = new \DOMXPath($dom);
		$found = self::productFromJsonLd($xpath);
		$candidates = [];
		foreach ($found['images'] ?? [] as $image) {
			if (is_string($image)) {
				$candidates[] = $image;
			}
		}
		if ($candidates === []) {
			foreach (['og:image', 'twitter:image'] as $key) {
				$value = self::meta($xpath, $key);
				if ($value !== '') {
					$candidates[] = $value;
				}
			}
		}
		$absolute = [];
		foreach ($candidates as $candidate) {
			$resolved = self::absoluteUrl($candidate, $pageUrl);
			if ($resolved !== null && self::looksLikePhoto($resolved)) {
				$absolute[] = $resolved;
			}
		}
		return self::preferLarge(array_values(array_unique($absolute)));
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
		}
		return $found;
	}

	private static function walkJsonLd(mixed $node, array &$found): void
	{
		if (!is_array($node)) {
			return;
		}
		$type = $node['@type'] ?? '';
		$types = is_array($type) ? $type : [$type];
		$isProduct = false;
		$isOffer = false;
		foreach ($types as $item) {
			if (!is_string($item)) {
				continue;
			}
			if (strcasecmp($item, 'Product') === 0) {
				$isProduct = true;
			}
			if (strcasecmp($item, 'Offer') === 0) {
				$isOffer = true;
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
		if (($isProduct || $isOffer) && !empty($node['image'])) {
			$images = self::jsonImages($node['image']);
			if (count($images) > count($found['images'] ?? [])) {
				$found['images'] = $images;
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

	/** @return list<string> */
	private static function jsonImages(mixed $image): array
	{
		$urls = [];
		$walk = static function (mixed $node) use (&$urls, &$walk): void {
			if (is_string($node)) {
				$urls[] = $node;
				return;
			}
			if (!is_array($node)) {
				return;
			}
			foreach (['url', 'contentUrl'] as $key) {
				if (!empty($node[$key]) && is_string($node[$key])) {
					$urls[] = $node[$key];
					return;
				}
			}
			foreach ($node as $child) {
				$walk($child);
			}
		};
		$walk($image);
		return $urls;
	}

	private static function absoluteUrl(string $value, string $pageUrl): ?string
	{
		$value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		if ($value === '' || str_starts_with($value, 'data:')) {
			return null;
		}
		if (str_starts_with($value, '//')) {
			$value = 'https:' . $value;
		} elseif (str_starts_with($value, '/')) {
			$parts = parse_url($pageUrl);
			$value = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $value;
		} elseif (!preg_match('#^https?://#i', $value)) {
			$base = preg_replace('#/[^/]*$#', '/', $pageUrl) ?? $pageUrl;
			$value = $base . $value;
		}
		$parts = parse_url($value);
		if (!is_array($parts) || empty($parts['host'])) {
			return null;
		}
		$scheme = strtolower((string) ($parts['scheme'] ?? ''));
		if (!in_array($scheme, ['http', 'https'], true)) {
			return null;
		}
		return $scheme . '://' . strtolower((string) $parts['host']) . ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
	}

	private static function looksLikePhoto(string $url): bool
	{
		$path = strtolower((string) parse_url($url, PHP_URL_PATH));
		if ($path === '' || str_contains($path, 'logo') || str_contains($path, 'icon') || str_contains($path, 'sprite') || str_contains($path, 'placeholder') || str_contains($path, 'cl-default') || str_ends_with($path, '.svg')) {
			return false;
		}
		return (bool) preg_match('/\.(jpe?g|png|webp|gif)(\?|$)/', $path . (parse_url($url, PHP_URL_QUERY) ? '' : ''));
	}

	/** @param list<string> $urls @return list<string> */
	private static function preferLarge(array $urls): array
	{
		$best = [];
		foreach ($urls as $url) {
			$key = preg_replace('/-(?:large|home|cart|small|medium|thickbox)_default(?=\.)/', '', (string) parse_url($url, PHP_URL_PATH)) ?? $url;
			$key = preg_replace('/-\d+x\d+(?=\.)/', '', $key) ?? $key;
			$score = 500000;
			if (preg_match('/-(\d+)x(\d+)\./', $url, $match)) {
				$score = (int) $match[1] * (int) $match[2];
			} elseif (str_contains($url, 'large_default') || str_contains($url, 'thickbox')) {
				$score = 1000000;
			} elseif (str_contains($url, 'small_default') || str_contains($url, 'cart_default')) {
				$score = 10000;
			}
			if (!isset($best[$key]) || $score > $best[$key]['score']) {
				$best[$key] = ['url' => $url, 'score' => $score];
			}
		}
		$picked = [];
		foreach ($best as $item) {
			$picked[] = $item['url'];
			if (count($picked) >= 8) {
				break;
			}
		}
		return $picked;
	}

	/** @param list<string> $urls @return list<string> */
	private static function downloadImages(array $urls, string $folder): array
	{
		if (!preg_match('/^[a-z0-9-]{1,40}$/', $folder)) {
			return [];
		}
		$dir = dirname(__DIR__) . '/uploads/productos/' . $folder;
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			return [];
		}
		$saved = [];
		$index = 1;
		foreach ($urls as $url) {
			if ($index > 8) {
				break;
			}
			$file = self::downloadImage($url, $dir, $index);
			if ($file === null) {
				continue;
			}
			$saved[] = '/crm/uploads/productos/' . $folder . '/' . basename($file);
			$index++;
		}
		return $saved;
	}

	private static function downloadImage(string $url, string $dir, int $index): ?string
	{
		if (!function_exists('curl_init')) {
			return null;
		}
		try {
			$url = self::publicUrl($url);
		} catch (RuntimeException) {
			return null;
		}
		$parts = parse_url($url);
		$host = (string) ($parts['host'] ?? '');
		$scheme = strtolower((string) ($parts['scheme'] ?? ''));
		$port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
		try {
			$ip = self::publicIp($host);
		} catch (RuntimeException) {
			return null;
		}
		$ch = curl_init($url);
		if ($ch === false) {
			return null;
		}
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 12,
			CURLOPT_USERAGENT => 'MizoCRM/1.0 (+https://mizo.cl)',
			CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/*,*/*'],
			CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
		]);
		$body = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);
		if (!is_string($body) || $code < 200 || $code >= 300 || strlen($body) < 800 || strlen($body) > 5000000) {
			return null;
		}
		$mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
		$ext = match ($mime) {
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/webp' => 'webp',
			'image/gif' => 'gif',
			default => '',
		};
		if ($ext === '') {
			return null;
		}
		$path = $dir . '/' . $index . '.' . $ext;
		if (file_put_contents($path, $body) === false) {
			return null;
		}
		return $path;
	}
}
