<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class Client extends Record
{
	protected static function table(): string
	{
		return 'clients';
	}

	public static function search(?string $q = null, ?int $ownerId = null): array
	{
		$sql = "SELECT c.*, u.name AS owner_name,
			(SELECT COUNT(*) FROM quotes q WHERE q.client_id = c.id) AS quotes_count,
			(SELECT a.message FROM activities a WHERE a.client_id = c.id AND a.type IN ('comentario','nota','lead','quote_sent','mail_sent','mail_received') ORDER BY a.id DESC LIMIT 1) AS last_note
			FROM clients c
			LEFT JOIN users u ON u.id = c.owner_id
			WHERE 1=1";
		$params = [];
		if ($ownerId) {
			$sql .= ' AND c.owner_id = ?';
			$params[] = $ownerId;
		}
		if ($q) {
			$sql .= ' AND (c.name LIKE ? OR c.contact_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.rut LIKE ?
				OR EXISTS (SELECT 1 FROM client_contacts ct WHERE ct.client_id = c.id AND (ct.name LIKE ? OR ct.email LIKE ? OR ct.phone LIKE ?)))';
			$like = '%' . $q . '%';
			$params = [...$params, $like, $like, $like, $like, $like, $like, $like, $like];
		}
		$sql .= ' ORDER BY c.updated_at DESC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	/** Lista liviana para el panel lateral (con nombres de contactos para buscar). */
	public static function forRail(?int $ownerId, int $limit = 250): array
	{
		$limit = max(1, min(500, $limit));
		$sql = "SELECT c.id, c.name, c.rut, c.contact_name, c.email, c.phone, c.city,
			(SELECT GROUP_CONCAT(ct.name, ' ') FROM client_contacts ct WHERE ct.client_id = c.id) AS contact_names
			FROM clients c
			WHERE 1=1";
		$params = [];
		if ($ownerId) {
			$sql .= ' AND c.owner_id = ?';
			$params[] = $ownerId;
		}
		$sql .= " ORDER BY c.name ASC LIMIT {$limit}";
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public static function findByEmail(string $email): ?array
	{
		$email = mb_strtolower(trim($email));
		if ($email === '') {
			return null;
		}
		$stmt = self::pdo()->prepare('SELECT * FROM clients WHERE email = ? ORDER BY id DESC');
		$stmt->execute([$email]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function findByPhone(string $phone): ?array
	{
		$phone = trim($phone);
		if ($phone === '') {
			return null;
		}
		$stmt = self::pdo()->prepare('SELECT * FROM clients WHERE phone = ? ORDER BY id DESC');
		$stmt->execute([$phone]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function findOrCreate(string $name, string $email, string $phone, string $source = 'otro', string $notes = '', ?int $ownerId = null): int
	{
		$email = mb_strtolower(trim($email));
		$phone = trim($phone);
		$existing = self::findByEmail($email) ?? self::findByPhone($phone);
		$now = date('c');
		if ($existing) {
			$payload = [
				'name' => $name !== '' ? $name : $existing['name'],
				'contact_name' => $name !== '' ? $name : $existing['contact_name'],
				'email' => $email !== '' ? $email : $existing['email'],
				'phone' => $phone !== '' ? $phone : $existing['phone'],
				'updated_at' => $now,
			];
			if (!$existing['owner_id'] && $ownerId) {
				$payload['owner_id'] = $ownerId;
			}
			self::update((int) $existing['id'], $payload);
			return (int) $existing['id'];
		}

		return self::insert([
			'name' => $name,
			'contact_name' => $name,
			'email' => $email,
			'phone' => $phone,
			'rut' => '',
			'city' => '',
			'source' => $source,
			'notes' => $notes,
			'owner_id' => $ownerId,
			'created_at' => $now,
			'updated_at' => $now,
		]);
	}

	public static function conflictForUser(string $email, string $phone, int $userId): ?array
	{
		$existing = self::findByEmail($email) ?? self::findByPhone($phone);
		if (!$existing && $email !== '') {
			$contact = ClientContact::findByEmail($email);
			if ($contact) {
				$existing = self::find((int) $contact['client_id']);
			}
		}
		if (!$existing) {
			return null;
		}
		$owner = (int) ($existing['owner_id'] ?? 0);
		if ($owner !== 0 && $owner !== $userId) {
			return $existing;
		}
		return null;
	}

	public static function deals(int $clientId): array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM deals WHERE client_id = ? ORDER BY updated_at DESC');
		$stmt->execute([$clientId]);
		return $stmt->fetchAll();
	}

	public static function quotes(int $clientId): array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM quotes WHERE client_id = ? ORDER BY created_at DESC');
		$stmt->execute([$clientId]);
		return $stmt->fetchAll();
	}

	public static function purge(int $clientId): void
	{
		$pdo = self::pdo();
		$pdo->beginTransaction();
		try {
			$quoteIds = $pdo->prepare('SELECT id FROM quotes WHERE client_id = ?');
			$quoteIds->execute([$clientId]);
			$ids = $quoteIds->fetchAll(\PDO::FETCH_COLUMN);
			if ($ids) {
				$placeholders = implode(',', array_fill(0, count($ids), '?'));
				$pdo->prepare("DELETE FROM quote_items WHERE quote_id IN ({$placeholders})")->execute($ids);
			}
			$pdo->prepare('DELETE FROM client_contacts WHERE client_id = ?')->execute([$clientId]);
			$pdo->prepare('DELETE FROM activities WHERE client_id = ?')->execute([$clientId]);
			$pdo->prepare('DELETE FROM quotes WHERE client_id = ?')->execute([$clientId]);
			$pdo->prepare('DELETE FROM deals WHERE client_id = ?')->execute([$clientId]);
			$pdo->prepare('DELETE FROM clients WHERE id = ?')->execute([$clientId]);
			$pdo->commit();
		} catch (\Throwable $e) {
			$pdo->rollBack();
			throw $e;
		}
	}
}
