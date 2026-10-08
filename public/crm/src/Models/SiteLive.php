<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Database;
use PDO;

final class SiteLive
{
	public static function unread(): int
	{
		$pdo = self::pdo();
		return (int) $pdo->query(
			"SELECT COUNT(*) FROM site_chat_messages WHERE author = 'visitor' AND seen = 0"
		)->fetchColumn();
	}

	public static function snapshot(int $chatId = 0): array
	{
		$pdo = self::pdo();
		$since = date('Y-m-d H:i:s', time() - 40);
		$onlineStmt = $pdo->prepare(
			'SELECT visitor_id, path, title, referrer, device, last_seen
			 FROM site_presence
			 WHERE last_seen >= ?
			 ORDER BY last_seen DESC
			 LIMIT 40'
		);
		$onlineStmt->execute([$since]);
		$online = [];
		foreach ($onlineStmt->fetchAll() as $row) {
			$online[] = [
				'path' => (string) $row['path'],
				'title' => (string) $row['title'],
				'referrer' => (string) $row['referrer'],
				'device' => (string) $row['device'],
				'last_seen' => (string) $row['last_seen'],
			];
		}
		$chats = $pdo->query(
			"SELECT c.id, c.visitor_name, c.page, c.updated_at,
				(SELECT body FROM site_chat_messages m WHERE m.chat_id = c.id ORDER BY m.id DESC LIMIT 1) AS preview,
				(SELECT COUNT(*) FROM site_chat_messages m WHERE m.chat_id = c.id AND m.author = 'visitor' AND m.seen = 0) AS unread
			 FROM site_chats c
			 ORDER BY c.updated_at DESC
			 LIMIT 30"
		)->fetchAll();
		$thread = [];
		if ($chatId > 0) {
			$pdo->prepare("UPDATE site_chat_messages SET seen = 1 WHERE chat_id = ? AND author = 'visitor' AND seen = 0")->execute([$chatId]);
			$stmt = $pdo->prepare('SELECT id, author, body, created_at FROM site_chat_messages WHERE chat_id = ? ORDER BY id ASC LIMIT 200');
			$stmt->execute([$chatId]);
			$thread = $stmt->fetchAll() ?: [];
		}
		return [
			'online' => $online,
			'chats' => $chats,
			'thread' => $thread,
			'unread' => self::unread(),
		];
	}

	public static function reply(int $chatId, string $body): bool
	{
		$body = trim($body);
		if ($chatId < 1 || $body === '') {
			return false;
		}
		$pdo = self::pdo();
		$chat = $pdo->prepare('SELECT id FROM site_chats WHERE id = ?');
		$chat->execute([$chatId]);
		if (!$chat->fetch()) {
			return false;
		}
		$now = date('Y-m-d H:i:s');
		$pdo->prepare('INSERT INTO site_chat_messages (chat_id, author, body, created_at, seen) VALUES (?, \'mizo\', ?, ?, 1)')
			->execute([$chatId, $body, $now]);
		$pdo->prepare('UPDATE site_chats SET updated_at = ?, status = \'abierto\' WHERE id = ?')->execute([$now, $chatId]);
		return true;
	}

	private static function pdo(): PDO
	{
		$pdo = Database::pdo();
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS site_presence (
				visitor_id TEXT PRIMARY KEY,
				session_id TEXT NOT NULL DEFAULT \'\',
				path TEXT NOT NULL DEFAULT \'/\',
				title TEXT NOT NULL DEFAULT \'\',
				referrer TEXT NOT NULL DEFAULT \'\',
				device TEXT NOT NULL DEFAULT \'\',
				ip TEXT NOT NULL DEFAULT \'\',
				last_seen TEXT NOT NULL
			)'
		);
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS site_chats (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				visitor_id TEXT NOT NULL,
				visitor_name TEXT NOT NULL DEFAULT \'\',
				page TEXT NOT NULL DEFAULT \'/\',
				status TEXT NOT NULL DEFAULT \'abierto\',
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)'
		);
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS site_chat_messages (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				chat_id INTEGER NOT NULL,
				author TEXT NOT NULL,
				body TEXT NOT NULL,
				created_at TEXT NOT NULL,
				seen INTEGER NOT NULL DEFAULT 0
			)'
		);
		return $pdo;
	}
}
