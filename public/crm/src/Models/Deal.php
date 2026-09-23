<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Config;
use MizoCrm\Record;

final class Deal extends Record
{
	protected static function table(): string
	{
		return 'deals';
	}

	public static function withClient(?string $stage = null, ?int $ownerId = null): array
	{
		$sql = 'SELECT d.*, c.name AS client_name, c.email AS client_email, u.name AS owner_name
			FROM deals d
			JOIN clients c ON c.id = d.client_id
			LEFT JOIN users u ON u.id = d.owner_id
			WHERE 1=1';
		$params = [];
		if ($stage) {
			$sql .= ' AND d.stage = ?';
			$params[] = $stage;
		}
		if ($ownerId) {
			$sql .= ' AND d.owner_id = ?';
			$params[] = $ownerId;
		}
		$sql .= ' ORDER BY d.updated_at DESC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public static function pipeline(): array
	{
		$grouped = [];
		foreach (array_keys(Config::stages()) as $stage) {
			$grouped[$stage] = [];
		}
		foreach (self::withClient() as $deal) {
			$grouped[$deal['stage']][] = $deal;
		}
		return $grouped;
	}

	/** Una tarjeta por proyecto. Un cliente puede tener varios. */
	public static function board(?int $ownerId): array
	{
		$sql = 'SELECT d.id AS deal_id, d.title AS deal_title, d.stage, d.service, d.amount, d.owner_id, d.updated_at,
			c.id AS client_id, c.name, c.rut, c.city, c.contact_name, c.email,
			u.name AS owner_name,
			(SELECT a.message FROM activities a
				WHERE a.deal_id = d.id AND a.type IN (\'comentario\',\'nota\',\'recordatorio\')
				ORDER BY a.id DESC LIMIT 1) AS last_note,
			(SELECT a.created_at FROM activities a WHERE a.deal_id = d.id ORDER BY a.id DESC LIMIT 1) AS last_at,
			(SELECT q.total FROM quotes q WHERE q.deal_id = d.id ORDER BY q.id DESC LIMIT 1) AS quote_total
			FROM deals d
			JOIN clients c ON c.id = d.client_id
			LEFT JOIN users u ON u.id = COALESCE(d.owner_id, c.owner_id)
			WHERE COALESCE(d.archived, 0) = 0';
		$params = [];
		if ($ownerId) {
			$sql .= ' AND (c.owner_id = ? OR d.owner_id = ?)';
			$params[] = $ownerId;
			$params[] = $ownerId;
		}
		$sql .= ' ORDER BY d.updated_at DESC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public static function purge(int $dealId): void
	{
		$pdo = self::pdo();
		$pdo->beginTransaction();
		try {
			$ids = $pdo->prepare('SELECT id FROM quotes WHERE deal_id = ?');
			$ids->execute([$dealId]);
			$quoteIds = $ids->fetchAll(\PDO::FETCH_COLUMN);
			if ($quoteIds) {
				$placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
				$pdo->prepare("DELETE FROM quote_items WHERE quote_id IN ({$placeholders})")->execute($quoteIds);
				$pdo->prepare("DELETE FROM activities WHERE quote_id IN ({$placeholders})")->execute($quoteIds);
			}
			$pdo->prepare('DELETE FROM quotes WHERE deal_id = ?')->execute([$dealId]);
			$pdo->prepare('DELETE FROM deal_contacts WHERE deal_id = ?')->execute([$dealId]);
			$pdo->prepare('DELETE FROM activities WHERE deal_id = ?')->execute([$dealId]);
			$pdo->prepare('DELETE FROM deals WHERE id = ?')->execute([$dealId]);
			$pdo->commit();
		} catch (\Throwable $e) {
			$pdo->rollBack();
			throw $e;
		}
	}

	/** @param list<int> $dealIds @return array<int, list<array<string, mixed>>> */
	public static function contactsByDeal(array $dealIds): array
	{
		$dealIds = array_values(array_filter(array_map('intval', $dealIds)));
		if ($dealIds === []) {
			return [];
		}
		$placeholders = implode(',', array_fill(0, count($dealIds), '?'));
		$stmt = self::pdo()->prepare(
			"SELECT dc.deal_id, ct.id, ct.name, ct.title, ct.email, ct.phone
			 FROM deal_contacts dc
			 JOIN client_contacts ct ON ct.id = dc.contact_id
			 WHERE dc.deal_id IN ({$placeholders})
			 ORDER BY ct.name ASC"
		);
		$stmt->execute($dealIds);
		$grouped = [];
		foreach ($stmt->fetchAll() as $row) {
			$grouped[(int) $row['deal_id']][] = $row;
		}
		return $grouped;
	}

	/** @param list<int> $contactIds */
	public static function setContacts(int $dealId, int $clientId, array $contactIds): void
	{
		$pdo = self::pdo();
		$pdo->prepare('DELETE FROM deal_contacts WHERE deal_id = ?')->execute([$dealId]);
		$insert = $pdo->prepare(
			'INSERT INTO deal_contacts (deal_id, contact_id)
			 SELECT ?, id FROM client_contacts WHERE id = ? AND client_id = ?'
		);
		foreach (array_unique($contactIds) as $contactId) {
			$contactId = (int) $contactId;
			if ($contactId > 0) {
				$insert->execute([$dealId, $contactId, $clientId]);
			}
		}
	}

	public static function latestForClient(int $clientId): ?array
	{
		$stmt = self::pdo()->prepare(
			'SELECT * FROM deals WHERE client_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1'
		);
		$stmt->execute([$clientId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function openValue(): int
	{
		$stages = Config::openStages();
		$placeholders = implode(',', array_fill(0, count($stages), '?'));
		$stmt = self::pdo()->prepare("SELECT COALESCE(SUM(amount),0) FROM deals WHERE stage IN ({$placeholders})");
		$stmt->execute($stages);
		return (int) $stmt->fetchColumn();
	}

	public static function quotes(int $dealId): array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM quotes WHERE deal_id = ? ORDER BY created_at DESC');
		$stmt->execute([$dealId]);
		return $stmt->fetchAll();
	}

	public static function latestQuote(int $dealId): ?array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM quotes WHERE deal_id = ? ORDER BY id DESC LIMIT 1');
		$stmt->execute([$dealId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function inbox(string $filter = 'pendiente', string $q = '', ?int $ownerId = null): array
	{
		$sql = 'SELECT d.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone, c.contact_name,
			q.id AS quote_id, q.number AS quote_number, q.status AS quote_status, q.total AS quote_total, q.sent_at, q.token,
			u.name AS owner_name
			FROM deals d
			JOIN clients c ON c.id = d.client_id
			LEFT JOIN quotes q ON q.id = (
				SELECT id FROM quotes WHERE deal_id = d.id ORDER BY id DESC LIMIT 1
			)
			LEFT JOIN users u ON u.id = d.owner_id
			WHERE 1=1';
		$params = [];
		if ($ownerId) {
			$sql .= ' AND d.owner_id = ?';
			$params[] = $ownerId;
		}
		if ($filter === 'libre') {
			$sql .= ' AND d.owner_id IS NULL';
		}
		if ($q !== '') {
			$sql .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR d.title LIKE ? OR IFNULL(q.number, \'\') LIKE ?)';
			$like = '%' . $q . '%';
			$params = [...$params, $like, $like, $like, $like, $like];
		}
		$sql .= ' ORDER BY d.updated_at DESC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll();
		if ($filter === '' || $filter === 'todas' || $filter === 'libre') {
			return $rows;
		}
		return array_values(array_filter($rows, static fn(array $row): bool => \work_status($row) === $filter));
	}

	public static function inboxCounts(?int $ownerId = null): array
	{
		$counts = ['pendiente' => 0, 'enviada' => 0, 'ganada' => 0, 'perdida' => 0, 'todas' => 0, 'libre' => 0];
		foreach (self::inbox('todas', '', $ownerId) as $row) {
			$status = \work_status($row);
			$counts[$status]++;
			$counts['todas']++;
			if (empty($row['owner_id'])) {
				$counts['libre']++;
			}
		}
		return $counts;
	}
}
