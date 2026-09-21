<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class Activity extends Record
{
	protected static function table(): string
	{
		return 'activities';
	}

	public static function log(
		string $type,
		string $message,
		?int $userId = null,
		?int $clientId = null,
		?int $dealId = null,
		?int $quoteId = null,
	): void {
		self::insert([
			'type' => $type,
			'message' => $message,
			'user_id' => $userId,
			'client_id' => $clientId,
			'deal_id' => $dealId,
			'quote_id' => $quoteId,
			'created_at' => date('c'),
		]);
	}

	public static function recent(int $limit = 12): array
	{
		$limit = max(1, min(50, $limit));
		return self::pdo()->query(
			'SELECT a.*, u.name AS user_name, c.name AS client_name, d.title AS deal_title
			 FROM activities a
			 LEFT JOIN users u ON u.id = a.user_id
			 LEFT JOIN clients c ON c.id = a.client_id
			 LEFT JOIN deals d ON d.id = a.deal_id
			 ORDER BY a.id DESC LIMIT ' . $limit
		)->fetchAll();
	}

	public static function forDeal(int $dealId): array
	{
		$stmt = self::pdo()->prepare(
			'SELECT a.*, u.name AS user_name
			 FROM activities a
			 LEFT JOIN users u ON u.id = a.user_id
			 WHERE a.deal_id = ?
			 ORDER BY a.id DESC'
		);
		$stmt->execute([$dealId]);
		return $stmt->fetchAll();
	}

	public static function commentsForClient(int $clientId): array
	{
		$stmt = self::pdo()->prepare(
			"SELECT a.*, u.name AS user_name
			 FROM activities a
			 LEFT JOIN users u ON u.id = a.user_id
			 WHERE a.client_id = ?
			   AND a.type IN ('comentario','nota','lead','quote_sent','quote_accepted','quote_rejected')
			 ORDER BY a.id DESC LIMIT 80"
		);
		$stmt->execute([$clientId]);
		return $stmt->fetchAll();
	}
}
