<?php
declare(strict_types=1);

namespace MizoCrm\Mail;

use RuntimeException;

final class Socket
{
	public static function open(string $target, int $timeout = 20)
	{
		$attempts = [
			['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
			['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
		];
		$last = 'sin conexión';
		foreach ($attempts as $ssl) {
			$ctx = stream_context_create(['ssl' => $ssl]);
			$fp = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
			if (is_resource($fp)) {
				stream_set_timeout($fp, $timeout);
				stream_set_blocking($fp, true);
				return $fp;
			}
			$last = trim($errstr !== '' ? $errstr : 'error ' . $errno);
		}
		throw new RuntimeException('No se pudo conectar a ' . $target . ' (' . $last . ').');
	}

	public static function enableTls($fp): void
	{
		$ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
		if ($ok !== true) {
			$ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
		}
		if ($ok !== true) {
			throw new RuntimeException('El servidor de correo no aceptó la conexión segura.');
		}
	}
}
