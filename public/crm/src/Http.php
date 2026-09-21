<?php
declare(strict_types=1);

namespace MizoCrm;

final class Http
{
	public static function method(): string
	{
		return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
	}

	public static function path(): string
	{
		$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
		if (preg_match('#^/crm(?:/(.*))?$#', $uri, $matches)) {
			$rest = trim((string) ($matches[1] ?? ''), '/');
			return $rest === '' ? '/' : '/' . $rest;
		}
		$script = $_SERVER['SCRIPT_NAME'] ?? '/crm/index.php';
		$base = rtrim(str_replace('\\', '/', dirname($script)), '/');
		if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
			$uri = substr($uri, strlen($base)) ?: '/';
		}
		$path = '/' . trim($uri, '/');
		return $path === '/' ? '/' : rtrim($path, '/');
	}

	public static function base(): string
	{
		$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
		if (preg_match('#^(/crm)(?:/|$)#', $uri, $matches)) {
			return $matches[1];
		}
		$script = $_SERVER['SCRIPT_NAME'] ?? '/crm/index.php';
		$base = rtrim(str_replace('\\', '/', dirname($script)), '/');
		if ($base === '' || $base === '/' || $base === '\\') {
			return '/crm';
		}
		return $base;
	}

	public static function url(string $path = '/'): string
	{
		$path = '/' . ltrim($path, '/');
		if ($path === '/') {
			return self::base() ?: '/crm';
		}
		return self::base() . $path;
	}

	public static function redirect(string $path): never
	{
		header('Location: ' . (str_starts_with($path, 'http') ? $path : self::url($path)), true, 302);
		exit;
	}

	public static function string(string $key, int $max = 500): string
	{
		$value = trim((string) ($_POST[$key] ?? $_GET[$key] ?? ''));
		$value = preg_replace('/\s+/u', ' ', $value) ?: '';
		return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
	}

	public static function int(string $key, int $default = 0): int
	{
		$raw = preg_replace('/[^\d\-]/', '', (string) ($_POST[$key] ?? $_GET[$key] ?? '')) ?: '';
		return $raw === '' ? $default : (int) $raw;
	}

	public static function money(string $key): int
	{
		$raw = str_replace(['.', ' ', '$'], '', (string) ($_POST[$key] ?? '0'));
		$raw = str_replace(',', '.', $raw);
		return (int) round((float) $raw);
	}

	public static function isPost(): bool
	{
		return self::method() === 'POST';
	}

	public static function text(string $key, int $max = 20000): string
	{
		$value = str_replace("\0", '', (string) ($_POST[$key] ?? ''));
		$value = trim($value);
		return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
	}
}
