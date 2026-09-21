<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class ChatMessage extends Record
{
	protected static function table(): string
	{
		return 'chat_messages';
	}

	public static function peersFor(int $userId, bool $isAdmin): array
	{
		if ($isAdmin) {
			$sql = "SELECT u.id, u.name, u.email, u.role,
				(SELECT body FROM chat_messages m
				 WHERE (m.from_user_id = u.id AND m.to_user_id = ?)
				    OR (m.from_user_id = ? AND m.to_user_id = u.id)
				 ORDER BY m.id DESC LIMIT 1) AS last_body,
				(SELECT created_at FROM chat_messages m
				 WHERE (m.from_user_id = u.id AND m.to_user_id = ?)
				    OR (m.from_user_id = ? AND m.to_user_id = u.id)
				 ORDER BY m.id DESC LIMIT 1) AS last_at,
				(SELECT COUNT(*) FROM chat_messages m
				 WHERE m.from_user_id = u.id AND m.to_user_id = ? AND m.seen = 0) AS unread
				FROM users u
				WHERE u.active = 1 AND u.id != ? AND u.role = 'vendedor'
				ORDER BY CASE WHEN last_at IS NULL THEN 1 ELSE 0 END, last_at DESC, u.name ASC";
			$stmt = self::pdo()->prepare($sql);
			$stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
			return $stmt->fetchAll();
		}

		$sql = "SELECT u.id, u.name, u.email, u.role,
			(SELECT body FROM chat_messages m
			 WHERE (m.from_user_id = u.id AND m.to_user_id = ?)
			    OR (m.from_user_id = ? AND m.to_user_id = u.id)
			 ORDER BY m.id DESC LIMIT 1) AS last_body,
			(SELECT created_at FROM chat_messages m
			 WHERE (m.from_user_id = u.id AND m.to_user_id = ?)
			    OR (m.from_user_id = ? AND m.to_user_id = u.id)
			 ORDER BY m.id DESC LIMIT 1) AS last_at,
			(SELECT COUNT(*) FROM chat_messages m
			 WHERE m.from_user_id = u.id AND m.to_user_id = ? AND m.seen = 0) AS unread
			FROM users u
			WHERE u.active = 1 AND u.role = 'admin'
			ORDER BY u.name ASC";
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute([$userId, $userId, $userId, $userId, $userId]);
		return $stmt->fetchAll();
	}

	public static function thread(int $userId, int $peerId, int $afterId = 0): array
	{
		$sql = 'SELECT m.*, f.name AS from_name
			FROM chat_messages m
			JOIN users f ON f.id = m.from_user_id
			WHERE ((m.from_user_id = ? AND m.to_user_id = ?)
			   OR (m.from_user_id = ? AND m.to_user_id = ?))';
		$params = [$userId, $peerId, $peerId, $userId];
		if ($afterId > 0) {
			$sql .= ' AND m.id > ?';
			$params[] = $afterId;
		}
		$sql .= ' ORDER BY m.id ASC LIMIT 300';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public static function send(int $fromId, int $toId, string $body): int
	{
		return self::insert([
			'from_user_id' => $fromId,
			'to_user_id' => $toId,
			'body' => $body,
			'seen' => 0,
			'created_at' => date('c'),
		]);
	}

	public static function markSeen(int $userId, int $peerId): void
	{
		$stmt = self::pdo()->prepare(
			'UPDATE chat_messages SET seen = 1
			 WHERE to_user_id = ? AND from_user_id = ? AND seen = 0'
		);
		$stmt->execute([$userId, $peerId]);
	}

	public static function unreadCount(int $userId): int
	{
		$stmt = self::pdo()->prepare('SELECT COUNT(*) FROM chat_messages WHERE to_user_id = ? AND seen = 0');
		$stmt->execute([$userId]);
		return (int) $stmt->fetchColumn();
	}

	public static function unreadPeek(int $userId, int $limit = 6): array
	{
		$stmt = self::pdo()->prepare(
			'SELECT m.id, m.from_user_id, m.body, m.created_at, u.name AS from_name
			 FROM chat_messages m
			 JOIN users u ON u.id = m.from_user_id
			 WHERE m.to_user_id = ? AND m.seen = 0
			 ORDER BY m.id DESC LIMIT ?'
		);
		$stmt->bindValue(1, $userId, \PDO::PARAM_INT);
		$stmt->bindValue(2, $limit, \PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public static function canTalk(array $me, array $peer): bool
	{
		if (!(int) ($peer['active'] ?? 0)) {
			return false;
		}
		$meAdmin = ($me['role'] ?? '') === 'admin';
		$peerAdmin = ($peer['role'] ?? '') === 'admin';
		if ($meAdmin) {
			return ($peer['role'] ?? '') === 'vendedor';
		}
		return $peerAdmin;
	}
}
