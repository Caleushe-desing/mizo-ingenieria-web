<?php
declare(strict_types=1);

namespace MizoCrm\Mail;

use RuntimeException;

final class Smtp
{
	public static function send(array $mailbox, string $to, string $rfc822): void
	{
		$host = (string) $mailbox['smtp_host'];
		$port = (int) $mailbox['smtp_port'];
		$user = (string) $mailbox['email'];
		$pass = (string) $mailbox['password'];
		$from = $user;
		$recipients = Mime::emailsFromString($to);
		if ($recipients === []) {
			throw new RuntimeException('No hay destinatarios válidos.');
		}

		if ($port === 465) {
			$fp = Socket::open('ssl://' . $host . ':' . $port);
		} else {
			$fp = Socket::open('tcp://' . $host . ':' . $port);
		}

		try {
			self::expect($fp, 220);
			self::ehlo($fp, $host);
			if ($port !== 465) {
				self::cmd($fp, 'STARTTLS', 220);
				Socket::enableTls($fp);
				self::ehlo($fp, $host);
			}
			self::cmd($fp, 'AUTH LOGIN', 334);
			self::cmd($fp, base64_encode($user), 334);
			self::cmd($fp, base64_encode($pass), 235);
			self::cmd($fp, 'MAIL FROM:<' . $from . '>', 250);
			foreach ($recipients as $rcpt) {
				self::cmd($fp, 'RCPT TO:<' . $rcpt . '>', 250);
			}
			self::cmd($fp, 'DATA', 354);
			$payload = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $rfc822))) ?? $rfc822;
			fwrite($fp, $payload . "\r\n.\r\n");
			self::expect($fp, 250);
			fwrite($fp, "QUIT\r\n");
		} finally {
			fclose($fp);
		}
	}

	public static function probe(array $mailbox): void
	{
		$host = (string) $mailbox['smtp_host'];
		$port = (int) $mailbox['smtp_port'];
		if ($port === 465) {
			$fp = Socket::open('ssl://' . $host . ':' . $port);
		} else {
			$fp = Socket::open('tcp://' . $host . ':' . $port);
		}
		try {
			self::expect($fp, 220);
			self::ehlo($fp, $host);
			if ($port !== 465) {
				self::cmd($fp, 'STARTTLS', 220);
				Socket::enableTls($fp);
				self::ehlo($fp, $host);
			}
			self::cmd($fp, 'AUTH LOGIN', 334);
			self::cmd($fp, base64_encode((string) $mailbox['email']), 334);
			self::cmd($fp, base64_encode((string) $mailbox['password']), 235);
			fwrite($fp, "QUIT\r\n");
		} finally {
			fclose($fp);
		}
	}

	private static function ehlo($fp, string $host): void
	{
		self::cmd($fp, 'EHLO mizo.cl', 250);
	}

	private static function cmd($fp, string $line, int $code): void
	{
		fwrite($fp, $line . "\r\n");
		self::expect($fp, $code);
	}

	private static function expect($fp, int $code): void
	{
		$reply = '';
		while (($line = fgets($fp, 8192)) !== false) {
			$reply .= $line;
			if (strlen($line) < 4 || $line[3] !== '-') {
				break;
			}
		}
		$got = (int) substr($reply, 0, 3);
		if ($got !== $code) {
			$hint = trim(preg_replace('/\s+/', ' ', $reply) ?? '');
			if ($code === 235 || $code === 334) {
				throw new RuntimeException('El correo o la clave de la casilla no son correctos.');
			}
			throw new RuntimeException('El servidor de correo respondió: ' . ($hint !== '' ? $hint : 'error ' . $got));
		}
	}
}
