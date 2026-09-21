<?php
declare(strict_types=1);

namespace MizoCrm;

use RuntimeException;

final class Secret
{
	public static function seal(string $plain): string
	{
		$iv = random_bytes(12);
		$tag = '';
		$cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
		if ($cipher === false || $tag === '') {
			throw new RuntimeException('No se pudo guardar la clave del correo.');
		}
		return base64_encode($iv . $tag . $cipher);
	}

	public static function open(string $sealed): string
	{
		$raw = base64_decode($sealed, true);
		if ($raw === false || strlen($raw) < 29) {
			throw new RuntimeException('La clave del correo está dañada. Vuelve a conectarla.');
		}
		$iv = substr($raw, 0, 12);
		$tag = substr($raw, 12, 16);
		$cipher = substr($raw, 28);
		$plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
		if ($plain === false) {
			throw new RuntimeException('No se pudo leer la clave del correo. Vuelve a conectarla.');
		}
		return $plain;
	}

	private static function key(): string
	{
		$path = dirname(Database::path()) . '/mail.key';
		if (!is_file($path)) {
			$dir = dirname($path);
			if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
				throw new RuntimeException('No se pudo crear la carpeta de datos del CRM.');
			}
			if (file_put_contents($path, random_bytes(32)) === false) {
				throw new RuntimeException('No se pudo crear la clave de cifrado del correo.');
			}
			@chmod($path, 0600);
		}
		$key = file_get_contents($path);
		if (!is_string($key) || strlen($key) !== 32) {
			throw new RuntimeException('La clave de cifrado del correo no es válida.');
		}
		return $key;
	}
}
