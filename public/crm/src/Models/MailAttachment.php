<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;
use RuntimeException;

final class MailAttachment extends Record
{
	protected static function table(): string
	{
		return 'mail_attachments';
	}

	public static function root(): string
	{
		return dirname(__DIR__, 3) . '/crm-data/mail-files';
	}

	public static function forMessage(int $messageId): array
	{
		$stmt = self::pdo()->prepare(
			'SELECT id, message_id, user_id, filename, mime, size, storage_path, created_at
			 FROM mail_attachments WHERE message_id = ? ORDER BY id ASC'
		);
		$stmt->execute([$messageId]);
		return $stmt->fetchAll();
	}

	/**
	 * Si el correo ya estaba en el CRM sin adjuntos guardados, los baja de IMAP al abrirlo.
	 * @return list<array<string,mixed>>
	 */
	public static function ensureForMessage(int $userId, array $message): array
	{
		$messageId = (int) ($message['id'] ?? 0);
		if ($messageId <= 0) {
			return [];
		}
		$existing = self::forMessage($messageId);
		$missingFiles = false;
		foreach ($existing as $row) {
			if (!is_file(self::absolutePath($row))) {
				$missingFiles = true;
				break;
			}
		}
		if ($existing !== [] && !$missingFiles) {
			return $existing;
		}
		$uid = (int) ($message['uid'] ?? 0);
		if ($uid <= 0) {
			return $existing;
		}
		try {
			$box = Mailbox::open($userId);
			$folder = (($message['folder'] ?? '') === 'sent')
				? (string) ($box['sent_folder'] ?? 'Sent')
				: 'INBOX';
			$imap = new \MizoCrm\Mail\Imap($box);
			try {
				$parsed = $imap->fetch($folder, $uid);
			} finally {
				$imap->close();
			}
			$atts = $parsed['attachments'] ?? [];
			if ($atts === []) {
				return $existing;
			}
			if ($missingFiles && $existing !== []) {
				foreach ($existing as $row) {
					$path = self::absolutePath($row);
					if (is_file($path)) {
						@unlink($path);
					}
					self::delete((int) $row['id']);
				}
			}
			self::saveForMessage($userId, $messageId, $atts);
			if (!empty($atts)) {
				MailMessage::update($messageId, ['has_attachments' => 1]);
			}
			return self::forMessage($messageId);
		} catch (\Throwable) {
			return $existing;
		}
	}

	public static function owned(int $userId, int $id): ?array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM mail_attachments WHERE id = ? AND user_id = ?');
		$stmt->execute([$id, $userId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	/** @param list<array{filename:string,mime:string,content:string}> $attachments */
	public static function saveForMessage(int $userId, int $messageId, array $attachments): int
	{
		$count = 0;
		$dir = self::root() . '/' . $userId . '/' . $messageId;
		if ($attachments && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new RuntimeException('No se pudo guardar adjuntos.');
		}
		$root = self::root();
		if (!is_dir($root)) {
			@mkdir($root, 0775, true);
		}
		$deny = $root . '/.htaccess';
		if (!is_file($deny)) {
			@file_put_contents($deny, "Require all denied\n");
		}
		foreach (array_slice($attachments, 0, 12) as $att) {
			$bin = (string) ($att['content'] ?? '');
			if ($bin === '' || strlen($bin) > 12 * 1024 * 1024) {
				continue;
			}
			$filename = self::safeName((string) ($att['filename'] ?? 'archivo'));
			$mime = (string) ($att['mime'] ?? 'application/octet-stream');
			$rel = $userId . '/' . $messageId . '/' . bin2hex(random_bytes(4)) . '_' . $filename;
			$full = self::root() . '/' . $rel;
			if (file_put_contents($full, $bin) === false) {
				continue;
			}
			self::insert([
				'message_id' => $messageId,
				'user_id' => $userId,
				'filename' => $filename,
				'mime' => $mime,
				'size' => strlen($bin),
				'storage_path' => $rel,
				'created_at' => date('c'),
			]);
			$count++;
		}
		if ($count > 0) {
			MailMessage::update($messageId, ['has_attachments' => 1]);
		}
		return $count;
	}

	public static function absolutePath(array $row): string
	{
		return self::root() . '/' . ltrim((string) $row['storage_path'], '/');
	}

	public static function isPreviewable(string $mime, string $filename): bool
	{
		$mime = strtolower($mime);
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if (str_contains($mime, 'pdf') || $ext === 'pdf') {
			return true;
		}
		if (str_starts_with($mime, 'image/')) {
			return true;
		}
		if (str_starts_with($mime, 'text/')) {
			return true;
		}
		return false;
	}

	public static function kind(string $mime, string $filename): string
	{
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$mime = strtolower($mime);
		if (str_contains($mime, 'pdf') || $ext === 'pdf') {
			return 'pdf';
		}
		if (in_array($ext, ['doc', 'docx'], true) || str_contains($mime, 'word')) {
			return 'word';
		}
		if (in_array($ext, ['xls', 'xlsx', 'csv'], true) || str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) {
			return 'excel';
		}
		if (str_starts_with($mime, 'image/')) {
			return 'image';
		}
		return 'file';
	}

	private static function safeName(string $name): string
	{
		$name = preg_replace('/[^\w.\- áéíóúÁÉÍÓÚñÑ]+/u', '_', $name) ?? 'archivo';
		$name = trim($name, '._ ');
		if ($name === '') {
			$name = 'archivo';
		}
		return function_exists('mb_substr') ? mb_substr($name, 0, 120, 'UTF-8') : substr($name, 0, 120);
	}
}
