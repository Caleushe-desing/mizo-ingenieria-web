<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Config;
use MizoCrm\Record;

final class Quote extends Record
{
	protected static function table(): string
	{
		return 'quotes';
	}

	public static function withRelations(?string $status = null): array
	{
		$sql = 'SELECT q.*, c.name AS client_name, d.title AS deal_title, u.name AS author_name
			FROM quotes q
			JOIN clients c ON c.id = q.client_id
			JOIN deals d ON d.id = q.deal_id
			LEFT JOIN users u ON u.id = q.created_by
			WHERE 1=1';
		$params = [];
		if ($status) {
			$sql .= ' AND q.status = ?';
			$params[] = $status;
		}
		$sql .= ' ORDER BY q.created_at DESC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public static function findByToken(string $token): ?array
	{
		$stmt = self::pdo()->prepare('SELECT q.*, c.name AS client_name, c.contact_name, c.email AS client_email,
			c.phone AS client_phone, c.rut AS client_rut, c.city AS client_city, d.title AS deal_title
			FROM quotes q
			JOIN clients c ON c.id = q.client_id
			JOIN deals d ON d.id = q.deal_id
			WHERE q.token = ?');
		$stmt->execute([$token]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function items(int $quoteId): array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY position ASC, id ASC');
		$stmt->execute([$quoteId]);
		return $stmt->fetchAll();
	}

	public static function nextNumber(): string
	{
		$year = date('Y');
		$stmt = self::pdo()->prepare("SELECT number FROM quotes WHERE number LIKE ? ORDER BY id DESC LIMIT 1");
		$stmt->execute(['MZ-' . $year . '-%']);
		$last = $stmt->fetchColumn();
		$seq = 1;
		if (is_string($last) && preg_match('/MZ-\d{4}-(\d+)/', $last, $m)) {
			$seq = (int) $m[1] + 1;
		}
		return sprintf('MZ-%s-%03d', $year, $seq);
	}

	public static function saveItems(int $quoteId, array $items): array
	{
		self::pdo()->prepare('DELETE FROM quote_items WHERE quote_id = ?')->execute([$quoteId]);
		$subtotal = 0;
		$position = 0;
		$insert = self::pdo()->prepare(
			'INSERT INTO quote_items (quote_id, position, description, quantity, unit, unit_price, total)
			 VALUES (?, ?, ?, ?, ?, ?, ?)'
		);
		foreach ($items as $item) {
			$description = trim((string) ($item['description'] ?? ''));
			if ($description === '') {
				continue;
			}
			$qty = (float) str_replace(',', '.', (string) ($item['quantity'] ?? 1));
			if ($qty <= 0) {
				$qty = 1;
			}
			$unit = trim((string) ($item['unit'] ?? 'un')) ?: 'un';
			$price = (int) round((float) str_replace(['.', ' '], ['', ''], (string) ($item['unit_price'] ?? 0)));
			$total = (int) round($qty * $price);
			$insert->execute([$quoteId, $position, $description, $qty, $unit, $price, $total]);
			$subtotal += $total;
			$position++;
		}
		$rate = Config::TAX_RATE;
		$tax = (int) round($subtotal * ($rate / 100));
		$total = $subtotal + $tax;
		return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $total, 'tax_rate' => $rate];
	}

	public static function sentCount(): int
	{
		return (int) self::pdo()->query("SELECT COUNT(*) FROM quotes WHERE status IN ('enviada','vista','aceptada','rechazada')")->fetchColumn();
	}

	public static function recentResponses(?int $ownerId, int $hours = 72): array
	{
		$sql = "SELECT q.id, q.number, q.status, q.responded_at, q.client_id, c.name AS client_name
			FROM quotes q
			JOIN clients c ON c.id = q.client_id
			WHERE q.status IN ('aceptada', 'rechazada')
			  AND q.responded_at IS NOT NULL
			  AND q.responded_at >= ?";
		$params = [date('c', time() - ($hours * 3600))];
		if ($ownerId) {
			$sql .= ' AND c.owner_id = ?';
			$params[] = $ownerId;
		}
		$sql .= ' ORDER BY q.responded_at DESC LIMIT 8';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public static function itemsFromPost(): array
	{
		$descriptions = $_POST['item_description'] ?? [];
		$quantities = $_POST['item_quantity'] ?? [];
		$units = $_POST['item_unit'] ?? [];
		$prices = $_POST['item_price'] ?? [];
		$items = [];
		if (!is_array($descriptions)) {
			return $items;
		}
		foreach ($descriptions as $i => $description) {
			$rawPrice = (string) ($prices[$i] ?? '0');
			$rawPrice = str_replace(['$', ' ', '.'], '', $rawPrice);
			$rawPrice = str_replace(',', '.', $rawPrice);
			$items[] = [
				'description' => $description,
				'quantity' => $quantities[$i] ?? 1,
				'unit' => $units[$i] ?? 'un',
				'unit_price' => $rawPrice,
			];
		}
		return $items;
	}

	public static function purge(int $quoteId): int
	{
		$quote = self::find($quoteId);
		if (!$quote) {
			return 0;
		}
		$clientId = (int) $quote['client_id'];
		$dealId = (int) $quote['deal_id'];
		$pdo = self::pdo();
		$pdo->beginTransaction();
		try {
			$pdo->prepare('DELETE FROM quote_items WHERE quote_id = ?')->execute([$quoteId]);
			$pdo->prepare('DELETE FROM activities WHERE quote_id = ?')->execute([$quoteId]);
			$pdo->prepare('DELETE FROM quotes WHERE id = ?')->execute([$quoteId]);
			$countStmt = $pdo->prepare('SELECT COUNT(*) FROM quotes WHERE deal_id = ?');
			$countStmt->execute([$dealId]);
			if ((int) $countStmt->fetchColumn() === 0) {
				$pdo->prepare('DELETE FROM deals WHERE id = ?')->execute([$dealId]);
			}
			$pdo->commit();
		} catch (\Throwable $e) {
			$pdo->rollBack();
			throw $e;
		}
		return $clientId;
	}
}
