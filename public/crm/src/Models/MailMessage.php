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

	public static function list(
		int $userId,
		string $folder,
		?int $clientId = null,
		string $query = '',
		string $sort = 'fecha',
		string $filter = 'todos'
	): array {
		$sql = 'SELECT m.*, c.name AS client_name
			FROM mail_messages m
			LEFT JOIN clients c ON c.id = m.client_id
			WHERE m.user_id = ? AND m.folder = ?';
		$params = [$userId, $folder];
		if ($clientId) {
			$sql .= ' AND m.client_id = ?';
			$params[] = $clientId;
		}
		$query = trim($query);
		if ($query !== '') {
			$sql .= ' AND (
				m.subject LIKE ? OR m.from_name LIKE ? OR m.from_email LIKE ?
				OR m.to_email LIKE ? OR m.body_text LIKE ? OR IFNULL(c.name, \'\') LIKE ?
			)';
			$like = '%' . $query . '%';
			$params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
		}
		if ($filter === 'no_leidos') {
			$sql .= ' AND m.seen = 0';
		} elseif ($filter === 'importantes') {
			$sql .= ' AND m.important = 1';
		} elseif ($filter === 'adjuntos') {
			$sql .= ' AND m.has_attachments = 1';
		}
		$sql .= ' ORDER BY m.sent_at DESC, m.id DESC LIMIT 200';
		try {
			$stmt = self::pdo()->prepare($sql);
			$stmt->execute($params);
			$rows = $stmt->fetchAll();
		} catch (\Throwable) {
			return [];
		}
		usort($rows, static function (array $a, array $b) use ($sort): int {
			return match ($sort) {
				'fecha_asc' => strcmp((string) ($a['sent_at'] ?? ''), (string) ($b['sent_at'] ?? '')),
				'remitente' => strcasecmp(
					(string) (($a['from_name'] ?: $a['from_email']) ?? ''),
					(string) (($b['from_name'] ?: $b['from_email']) ?? '')
				),
				'asunto' => strcasecmp((string) ($a['subject'] ?? ''), (string) ($b['subject'] ?? '')),
				'no_leidos' => ((int) ($a['seen'] ?? 0) <=> (int) ($b['seen'] ?? 0))
					?: strcmp((string) ($b['sent_at'] ?? ''), (string) ($a['sent_at'] ?? '')),
				default => ((int) ($b['important'] ?? 0) <=> (int) ($a['important'] ?? 0))
					?: strcmp((string) ($b['sent_at'] ?? ''), (string) ($a['sent_at'] ?? '')),
			};
		});
		return $rows;
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
			'cc_email' => $data['cc_email'] ?? '',
			'subject' => $data['subject'] ?? '',
			'body_text' => $data['body_text'] ?? '',
			'body_html' => $data['body_html'] ?? '',
			'sent_at' => $data['sent_at'] ?? $now,
			'seen' => (int) ($data['seen'] ?? 0),
			'important' => (int) ($data['important'] ?? 0),
			'has_attachments' => (int) ($data['has_attachments'] ?? 0),
			'client_id' => $data['client_id'] ?: null,
			'created_at' => $now,
		]);
	}

	/** @param list<int> $ids */
	public static function bulkOwned(int $userId, array $ids): array
	{
		$ids = array_values(array_filter(array_map('intval', $ids), static fn($id) => $id > 0));
		if ($ids === []) {
			return [];
		}
		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$stmt = self::pdo()->prepare(
			"SELECT * FROM mail_messages WHERE user_id = ? AND id IN ({$placeholders})"
		);
		$stmt->execute([$userId, ...$ids]);
		return $stmt->fetchAll();
	}

	public static function markRead(array $row): void
	{
		self::setSeen($row, true);
	}

	public static function setSeen(array $row, bool $seen): void
	{
		$next = $seen ? 1 : 0;
		if ((int) ($row['seen'] ?? 0) === $next) {
			return;
		}
		self::apply([$row], $seen ? 'seen' : 'unseen');
	}

	public static function setImportant(array $row, bool $important): void
	{
		$next = $important ? 1 : 0;
		if ((int) ($row['important'] ?? 0) === $next) {
			return;
		}
		self::apply([$row], $important ? 'flag' : 'unflag');
	}

	/**
	 * Aplica leído, no leído, importante o borrado en el servidor y recién entonces en el CRM.
	 * @param list<array<string, mixed>> $rows
	 */
	public static function apply(array $rows, string $action): int
	{
		if ($rows === []) {
			return 0;
		}
		$userId = (int) ($rows[0]['user_id'] ?? 0);
		$done = array_fill_keys(self::push($userId, $rows, $action), true);
		$n = 0;
		foreach ($rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id <= 0 || !isset($done[$id])) {
				continue;
			}
			if ($action === 'delete') {
				self::forget($id);
			} elseif ($action === 'seen' || $action === 'unseen') {
				self::update($id, ['seen' => $action === 'seen' ? 1 : 0]);
			} elseif ($action === 'flag' || $action === 'unflag') {
				self::update($id, ['important' => $action === 'flag' ? 1 : 0]);
			}
			$n++;
		}
		return $n;
	}

	/** Quita el correo del CRM porque ya no está en el servidor. */
	public static function forget(int $id): void
	{
		if ($id <= 0) {
			return;
		}
		foreach (MailAttachment::forMessage($id) as $att) {
			$path = MailAttachment::absolutePath($att);
			if (is_file($path)) {
				@unlink($path);
			}
		}
		self::delete($id);
	}

	/** @param list<array<string, mixed>> $rows @return list<int> */
	private static function push(int $userId, array $rows, string $action): array
	{
		$done = [];
		$pending = [];
		foreach ($rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			if (empty($row['uid'])) {
				if ($action !== 'delete') {
					$done[] = $id;
				}
				continue;
			}
			$pending[] = $row;
		}
		if ($pending === []) {
			return $done;
		}
		try {
			$box = Mailbox::open($userId);
			$imap = new \MizoCrm\Mail\Imap($box);
			try {
				$groups = [];
				foreach ($pending as $row) {
					$remote = ($row['folder'] ?? '') === 'sent'
						? (string) ($box['sent_folder'] ?? 'Sent')
						: 'INBOX';
					$groups[$remote][] = $row;
				}
				foreach ($groups as $folder => $group) {
					if ($action === 'delete') {
						$imap->remove($folder, array_map(static fn(array $row): int => (int) $row['uid'], $group));
						foreach ($group as $row) {
							$done[] = (int) $row['id'];
						}
						continue;
					}
					foreach ($group as $row) {
						$uid = (int) $row['uid'];
						try {
							match ($action) {
								'seen' => $imap->markSeen($folder, $uid),
								'unseen' => $imap->markUnseen($folder, $uid),
								'flag' => $imap->markFlagged($folder, $uid, true),
								'unflag' => $imap->markFlagged($folder, $uid, false),
								default => null,
							};
							$done[] = (int) $row['id'];
						} catch (\Throwable) {
						}
					}
				}
			} finally {
				$imap->close();
			}
		} catch (\Throwable) {
		}
		return $done;
	}

	public static function clientIdFor(int $userId, string $email): ?int
	{
		$email = mb_strtolower(trim($email));
		if ($email === '') {
			return null;
		}
		$client = Client::findByEmail($email);
		if (!$client) {
			$contact = ClientContact::findByEmail($email);
			if ($contact) {
				$client = Client::find((int) $contact['client_id']);
			}
		}
		if (!$client) {
			return null;
		}
		if (!Auth::canAccessClient($client) && (int) $client['owner_id'] !== $userId) {
			return null;
		}
		return (int) $client['id'];
	}
}
