<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Auth;
use MizoCrm\Record;

final class MailMessage extends Record
{
	protected static function table(): string
	{
		return 'mail_messages';
	}

	public static function list(int $userId, string $folder, ?int $clientId = null): array
	{
		$sql = 'SELECT m.*, c.name AS client_name
			FROM mail_messages m
			LEFT JOIN clients c ON c.id = m.client_id
			WHERE m.user_id = ? AND m.folder = ?';
		$params = [$userId, $folder];
		if ($clientId) {
			$sql .= ' AND m.client_id = ?';
			$params[] = $clientId;
		}
		$sql .= ' ORDER BY m.sent_at DESC, m.id DESC LIMIT 120';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public static function forClient(int $userId, int $clientId): array
	{
		$stmt = self::pdo()->prepare(
			'SELECT * FROM mail_messages
			 WHERE user_id = ? AND client_id = ?
			 ORDER BY sent_at DESC, id DESC LIMIT 20'
		);
		$stmt->execute([$userId, $clientId]);
		return $stmt->fetchAll();
	}

	public static function owned(int $userId, int $id): ?array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM mail_messages WHERE id = ? AND user_id = ?');
		$stmt->execute([$id, $userId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function unreadCount(int $userId): int
	{
		$stmt = self::pdo()->prepare("SELECT COUNT(*) FROM mail_messages WHERE user_id = ? AND folder = 'inbox' AND seen = 0");
		$stmt->execute([$userId]);
		return (int) $stmt->fetchColumn();
	}

	public static function unreadPeek(int $userId, int $limit = 8): array
	{
		$limit = max(1, min(20, $limit));
		$stmt = self::pdo()->prepare(
			"SELECT id, from_name, from_email, subject, client_id, sent_at
			 FROM mail_messages
			 WHERE user_id = ? AND folder = 'inbox' AND seen = 0
			 ORDER BY id DESC LIMIT {$limit}"
		);
		$stmt->execute([$userId]);
		return $stmt->fetchAll();
	}

	public static function uidsFor(int $userId, string $folder): array
	{
		$stmt = self::pdo()->prepare('SELECT uid, id FROM mail_messages WHERE user_id = ? AND folder = ? AND uid IS NOT NULL');
		$stmt->execute([$userId, $folder]);
		$out = [];
		foreach ($stmt->fetchAll() as $row) {
			$out[(int) $row['uid']] = (int) $row['id'];
		}
		return $out;
	}

	public static function store(int $userId, string $folder, array $data): int
	{
		$now = date('c');
		if (!empty($data['uid'])) {
			$existing = self::pdo()->prepare('SELECT id FROM mail_messages WHERE user_id = ? AND folder = ? AND uid = ?');
			$existing->execute([$userId, $folder, $data['uid']]);
			$id = $existing->fetchColumn();
			if ($id) {
				self::update((int) $id, [
					'seen' => (int) $data['seen'],
					'subject' => $data['subject'],
					'client_id' => $data['client_id'],
				]);
				return (int) $id;
			}
		}
		$messageId = trim((string) ($data['message_id'] ?? ''));
		if ($messageId !== '') {
			$existing = self::pdo()->prepare('SELECT id FROM mail_messages WHERE user_id = ? AND folder = ? AND message_id = ?');
			$existing->execute([$userId, $folder, $messageId]);
			$id = $existing->fetchColumn();
			if ($id) {
				self::update((int) $id, [
					'uid' => $data['uid'] ?: null,
					'seen' => (int) $data['seen'],
					'client_id' => $data['client_id'],
				]);
				return (int) $id;
			}
		}
		return self::insert([
			'user_id' => $userId,
			'folder' => $folder,
			'uid' => $data['uid'] ?: null,
			'message_id' => $data['message_id'] ?? '',
			'in_reply_to' => $data['in_reply_to'] ?? '',
			'from_email' => $data['from_email'] ?? '',
			'from_name' => $data['from_name'] ?? '',
			'to_email' => $data['to_email'] ?? '',
			'subject' => $data['subject'] ?? '',
			'body_text' => $data['body_text'] ?? '',
			'body_html' => $data['body_html'] ?? '',
			'sent_at' => $data['sent_at'] ?? $now,
			'seen' => (int) ($data['seen'] ?? 0),
			'client_id' => $data['client_id'] ?: null,
			'created_at' => $now,
		]);
	}

	public static function markRead(array $row): void
	{
		if ((int) $row['seen'] === 1) {
			return;
		}
		self::update((int) $row['id'], ['seen' => 1]);
		if (empty($row['uid']) || $row['folder'] !== 'inbox') {
			return;
		}
		try {
			$box = Mailbox::open((int) $row['user_id']);
			$imap = new \MizoCrm\Mail\Imap($box);
			try {
				$imap->markSeen('INBOX', (int) $row['uid']);
			} finally {
				$imap->close();
			}
		} catch (\Throwable) {
		}
	}

	public static function clientIdFor(int $userId, string $email): ?int
	{
		$email = mb_strtolower(trim($email));
		if ($email === '') {
			return null;
		}
		$client = Client::findByEmail($email);
		if (!$client) {
			return null;
		}
		if (!Auth::canAccessClient($client) && (int) $client['owner_id'] !== $userId) {
			return null;
		}
		return (int) $client['id'];
	}
}
