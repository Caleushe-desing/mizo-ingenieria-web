<?php
declare(strict_types=1);

namespace MizoCrm\Mail;

final class Mime
{
	public static function parse(string $raw): array
	{
		$raw = str_replace("\r\n", "\n", $raw);
		$parts = explode("\n\n", $raw, 2);
		$headers = self::headers($parts[0] ?? '');
		$body = $parts[1] ?? '';
		$from = self::mailbox((string) ($headers['from'] ?? ''));
		$toList = self::mailboxList((string) ($headers['to'] ?? ''));
		$ccList = self::mailboxList((string) ($headers['cc'] ?? ''));
		$decoded = self::decodePart($headers, $body);
		$text = $decoded['text'];
		$html = $decoded['html'];
		$attachments = $decoded['attachments'];
		if ($text === '' && $html !== '') {
			$text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		}
		$date = (string) ($headers['date'] ?? '');
		$stamp = $date !== '' ? strtotime($date) : false;

		return [
			'message_id' => self::angle((string) ($headers['message-id'] ?? '')),
			'in_reply_to' => self::angle((string) ($headers['in-reply-to'] ?? '')),
			'from_email' => $from['email'],
			'from_name' => $from['name'],
			'to_email' => implode(', ', array_column($toList, 'email')),
			'to_list' => $toList,
			'cc_email' => implode(', ', array_column($ccList, 'email')),
			'cc_list' => $ccList,
			'subject' => self::decodeHeader((string) ($headers['subject'] ?? '(sin asunto)')),
			'body_text' => $text,
			'body_html' => $html,
			'attachments' => $attachments,
			'sent_at' => $stamp ? date('c', $stamp) : date('c'),
		];
	}

	/** @param list<array{filename:string,mime:string,content:string}> $attachments */
	public static function build(
		string $fromName,
		string $fromEmail,
		string $to,
		string $subject,
		string $html,
		string $replyToMessageId = '',
		string $cc = '',
		array $attachments = [],
	): string {
		$plain = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		$alt = 'mizoalt' . bin2hex(random_bytes(8));
		$messageId = '<crm-' . bin2hex(random_bytes(12)) . '@mizo.cl>';
		$alternative = '--' . $alt . "\r\n"
			. "Content-Type: text/plain; charset=UTF-8\r\n"
			. "Content-Transfer-Encoding: base64\r\n\r\n"
			. chunk_split(base64_encode($plain !== '' ? $plain : strip_tags($html)))
			. '--' . $alt . "\r\n"
			. "Content-Type: text/html; charset=UTF-8\r\n"
			. "Content-Transfer-Encoding: base64\r\n\r\n"
			. chunk_split(base64_encode($html))
			. '--' . $alt . "--\r\n";
		$mixed = 'mizomix' . bin2hex(random_bytes(8));
		$contentType = $attachments === []
			? 'multipart/alternative; boundary="' . $alt . '"'
			: 'multipart/mixed; boundary="' . $mixed . '"';
		$headers = [
			'From: ' . self::encodeMailbox($fromName, $fromEmail),
			'To: ' . $to,
			'Subject: ' . self::encodeHeader($subject),
			'Date: ' . date('r'),
			'Message-ID: ' . $messageId,
			'MIME-Version: 1.0',
			'Content-Type: ' . $contentType,
		];
		if (trim($cc) !== '') {
			array_splice($headers, 2, 0, ['Cc: ' . $cc]);
		}
		if ($replyToMessageId !== '') {
			$headers[] = 'In-Reply-To: <' . trim($replyToMessageId, '<>') . '>';
			$headers[] = 'References: <' . trim($replyToMessageId, '<>') . '>';
		}
		if ($attachments === []) {
			$body = $alternative;
		} else {
			$body = '--' . $mixed . "\r\n"
				. 'Content-Type: multipart/alternative; boundary="' . $alt . "\"\r\n\r\n"
				. $alternative;
			foreach ($attachments as $file) {
				$filename = str_replace(["\r", "\n", '"'], '', (string) ($file['filename'] ?? 'archivo'));
				$mime = (string) ($file['mime'] ?? 'application/octet-stream');
				$body .= '--' . $mixed . "\r\n"
					. 'Content-Type: ' . $mime . '; name="' . $filename . "\"\r\n"
					. "Content-Transfer-Encoding: base64\r\n"
					. 'Content-Disposition: attachment; filename="' . $filename . "\"\r\n\r\n"
					. chunk_split(base64_encode((string) ($file['content'] ?? '')));
			}
			$body .= '--' . $mixed . "--\r\n";
		}

		return implode("\r\n", $headers) . "\r\n\r\n" . $body;
	}

	public static function messageIdOf(string $rfc822): string
	{
		if (preg_match('/^Message-ID:\s*(.+)$/im', $rfc822, $matches)) {
			return self::angle(trim($matches[1]));
		}
		return '';
	}

	public static function mailbox(string $value): array
	{
		$value = self::decodeHeader($value);
		$email = '';
		if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
			$email = mb_strtolower($matches[0]);
		}
		$name = trim(str_replace(['<', '>', '"'], '', preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '', $value) ?? ''));
		return ['email' => $email, 'name' => $name];
	}

	/** @return list<array{email:string,name:string}> */
	public static function mailboxList(string $value): array
	{
		$value = self::decodeHeader($value);
		if ($value === '') {
			return [];
		}
		$out = [];
		foreach (preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) ?: [] as $part) {
			$box = self::mailbox(trim($part));
			if ($box['email'] !== '') {
				$out[] = $box;
			}
		}
		return $out;
	}

	/** @return list<string> */
	public static function emailsFromString(string $value): array
	{
		$emails = [];
		if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
			foreach ($matches[0] as $email) {
				$email = mb_strtolower(trim($email));
				if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
					$emails[] = $email;
				}
			}
		}
		return array_values(array_unique($emails));
	}

	public static function safeHtml(string $html): string
	{
		$html = preg_replace('#<(script|iframe|object|embed|form|link|meta|style)[^>]*>.*?</\1>#is', '', $html) ?? $html;
		$html = strip_tags($html, '<p><br><b><strong><em><i><u><ul><ol><li><a><table><thead><tbody><tr><td><th><span><div><h1><h2><h3><blockquote><hr><img><font>');
		$html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
		$html = preg_replace('/javascript:/i', '', $html) ?? $html;
		return $html;
	}

	private static function headers(string $raw): array
	{
		$raw = preg_replace("/\n[ \t]+/", ' ', $raw) ?? $raw;
		$out = [];
		foreach (preg_split("/\n/", $raw) ?: [] as $line) {
			if (!str_contains($line, ':')) {
				continue;
			}
			[$name, $value] = explode(':', $line, 2);
			$key = strtolower(trim($name));
			$val = trim($value);
			if (isset($out[$key])) {
				$out[$key] .= ', ' . $val;
			} else {
				$out[$key] = $val;
			}
		}
		return $out;
	}

	/** @return array{text:string,html:string,attachments:list<array{filename:string,mime:string,content:string}>} */
	private static function decodePart(array $headers, string $body): array
	{
		$typeRaw = (string) ($headers['content-type'] ?? 'text/plain');
		$type = strtolower($typeRaw);
		$encoding = strtolower(trim((string) ($headers['content-transfer-encoding'] ?? '')));
		$disposition = strtolower((string) ($headers['content-disposition'] ?? ''));
		$charset = 'UTF-8';
		if (preg_match('/charset="?([^";\s]+)"?/i', $typeRaw, $matches)) {
			$charset = $matches[1];
		}
		// El boundary es case-sensitive: no usar $type en minúsculas para extraerlo.
		if (str_starts_with($type, 'multipart/') && preg_match('/boundary=("([^"]+)"|([^;\s]+))/i', $typeRaw, $matches)) {
			$boundary = ($matches[2] ?? '') !== '' ? $matches[2] : (string) ($matches[3] ?? '');
			if ($boundary !== '') {
				return self::multipart($body, $boundary);
			}
		}

		$filename = self::filenameFrom($headers);
		$mime = strtolower(trim(explode(';', $type)[0]));
		if ($mime === '') {
			$mime = 'application/octet-stream';
		}

		$isAttach = str_contains($disposition, 'attachment')
			|| (str_contains($disposition, 'inline') && $filename !== '')
			|| ($filename !== '' && !str_starts_with($mime, 'text/'))
			|| str_starts_with($mime, 'application/')
			|| str_starts_with($mime, 'image/')
			|| str_starts_with($mime, 'audio/')
			|| str_starts_with($mime, 'video/');

		// El cuerpo del mensaje (text/html o text/plain) no es adjunto.
		if (str_starts_with($mime, 'text/') && !str_contains($disposition, 'attachment')) {
			$isAttach = false;
		}

		$rawBody = self::decodeBodyBinary($body, $encoding);
		if ($isAttach && $rawBody !== '') {
			if ($filename === '') {
				$filename = self::defaultFilename($mime);
			}
			return [
				'text' => '',
				'html' => '',
				'attachments' => [[
					'filename' => $filename,
					'mime' => $mime,
					'content' => $rawBody,
				]],
			];
		}

		$decoded = self::toUtf8($rawBody, $charset);
		if (str_starts_with($mime, 'text/html')) {
			return ['text' => '', 'html' => $decoded, 'attachments' => []];
		}
		return ['text' => $decoded, 'html' => '', 'attachments' => []];
	}

	/** @return array{text:string,html:string,attachments:list<array{filename:string,mime:string,content:string}>} */
	private static function multipart(string $body, string $boundary): array
	{
		$text = '';
		$html = '';
		$attachments = [];
		$pattern = '/\r?\n--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*\r?\n/';
		// Prefijo por si el cuerpo empieza con el boundary.
		$normalized = str_starts_with(ltrim($body), '--' . $boundary)
			? "\n" . ltrim($body)
			: $body;
		$chunks = preg_split($pattern, $normalized) ?: [];
		foreach ($chunks as $chunk) {
			$chunk = trim($chunk);
			if ($chunk === '' || $chunk === '--') {
				continue;
			}
			// Quita el cierre final suelto que dejan algunos servidores.
			if ($chunk === '--') {
				continue;
			}
			$parts = explode("\n\n", str_replace("\r\n", "\n", $chunk), 2);
			if (count($parts) < 2) {
				$parts = explode("\r\n\r\n", $chunk, 2);
			}
			$headers = self::headers($parts[0] ?? '');
			$partBody = $parts[1] ?? '';
			$decoded = self::decodePart($headers, $partBody);
			if ($decoded['text'] !== '') {
				$text = $decoded['text'];
			}
			if ($decoded['html'] !== '') {
				$html = $decoded['html'];
			}
			foreach ($decoded['attachments'] as $att) {
				$attachments[] = $att;
			}
		}
		return ['text' => $text, 'html' => $html, 'attachments' => $attachments];
	}

	private static function filenameFrom(array $headers): string
	{
		$disp = (string) ($headers['content-disposition'] ?? '');
		$type = (string) ($headers['content-type'] ?? '');
		foreach ([$disp, $type] as $src) {
			if (preg_match('/filename\*\s*=\s*(?:UTF-8\'\')?([^;]+)/i', $src, $m)) {
				return self::decodeHeader(trim($m[1], " \t\"'"));
			}
			if (preg_match('/filename\s*=\s*"([^"]+)"/i', $src, $m)) {
				return self::decodeHeader(trim($m[1]));
			}
			if (preg_match('/filename\s*=\s*([^;\s]+)/i', $src, $m)) {
				return self::decodeHeader(trim($m[1], " \t\"'"));
			}
			if (preg_match('/\bname\s*=\s*"([^"]+)"/i', $src, $m)) {
				return self::decodeHeader(trim($m[1]));
			}
			if (preg_match('/\bname\s*=\s*([^;\s]+)/i', $src, $m)) {
				return self::decodeHeader(trim($m[1], " \t\"'"));
			}
		}
		return '';
	}

	private static function defaultFilename(string $mime): string
	{
		return match (true) {
			str_contains($mime, 'pdf') => 'documento.pdf',
			str_contains($mime, 'word') || str_contains($mime, 'msword') => 'documento.docx',
			str_contains($mime, 'sheet') || str_contains($mime, 'excel') => 'planilla.xlsx',
			str_starts_with($mime, 'image/') => 'imagen.' . (explode('/', $mime)[1] ?? 'bin'),
			default => 'adjunto.bin',
		};
	}

	private static function decodeBodyBinary(string $body, string $encoding): string
	{
		$encoding = strtolower(trim($encoding));
		$clean = preg_replace('/\s+/', '', $body) ?? '';
		return match ($encoding) {
			'base64' => (string) (base64_decode($clean, true) !== false
				? base64_decode($clean, true)
				: base64_decode($clean)),
			'quoted-printable' => quoted_printable_decode(str_replace("\r\n", "\n", $body)),
			'7bit', '8bit', 'binary', '' => str_replace("\r\n", "\n", $body),
			default => str_replace("\r\n", "\n", $body),
		};
	}

	private static function toUtf8(string $decoded, string $charset): string
	{
		$charset = strtoupper($charset);
		if ($charset !== '' && $charset !== 'UTF-8' && $charset !== 'UTF8' && function_exists('mb_convert_encoding')) {
			$converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);
			if (is_string($converted)) {
				$decoded = $converted;
			}
		}
		return trim(str_replace("\r\n", "\n", $decoded));
	}

	private static function decodeHeader(string $value): string
	{
		if (function_exists('mb_decode_mimeheader')) {
			$value = mb_decode_mimeheader($value);
		}
		return trim(rawurldecode($value));
	}

	private static function encodeHeader(string $value): string
	{
		if ($value === '' || preg_match('/^[\x20-\x7E]+$/', $value)) {
			return $value;
		}
		return '=?UTF-8?B?' . base64_encode($value) . '?=';
	}

	private static function encodeMailbox(string $name, string $email): string
	{
		$name = trim($name);
		if ($name === '') {
			return $email;
		}
		return self::encodeHeader($name) . ' <' . $email . '>';
	}

	private static function angle(string $value): string
	{
		if (preg_match('/<([^>]+)>/', $value, $matches)) {
			return $matches[1];
		}
		return trim($value);
	}
}
