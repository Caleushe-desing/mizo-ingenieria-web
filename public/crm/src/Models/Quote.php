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

	public static function withRelations(?string $status = null, ?int $ownerId = null): array
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
		if ($ownerId) {
			$sql .= ' AND c.owner_id = ?';
			$params[] = $ownerId;
		}
		$sql .= ' ORDER BY q.created_at DESC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return self::attachProfitMetrics($stmt->fetchAll());
	}

	/**
	 * Métricas internas de rentabilidad (no van al documento del cliente).
	 * Utilidad = venta neta − costo neto (costo c/IVA ÷ 1.19).
	 * Margen real % = utilidad ÷ costo neto (sobre el costo indicado).
	 *
	 * @param list<array<string, mixed>> $items
	 * @return array{
	 *   cost_total_iva: int,
	 *   cost_total_net: int,
	 *   sale_net: int,
	 *   profit: int,
	 *   margin_real: ?float,
	 *   margin_on_sale: ?float,
	 *   has_cost: bool
	 * }
	 */
	public static function profitFromItems(array $items, ?float $taxRate = null): array
	{
		$rate = $taxRate ?? Config::TAX_RATE;
		$factor = 1 + ($rate / 100);
		$costIva = 0.0;
		$costNet = 0.0;
		$saleNet = 0.0;
		foreach ($items as $item) {
			$qty = (float) str_replace(',', '.', (string) ($item['quantity'] ?? 0));
			if ($qty <= 0) {
				continue;
			}
			$cost = (int) ($item['cost_price'] ?? 0);
			$price = (int) ($item['unit_price'] ?? 0);
			if ($cost > 0) {
				$price = self::netSaleFromCost($cost, (float) ($item['margin_percent'] ?? 0));
			}
			$costIva += $qty * $cost;
			$costNet += $qty * ($cost / $factor);
			$saleNet += $qty * $price;
		}
		$costIvaInt = (int) round($costIva);
		$costNetInt = (int) round($costNet);
		$saleNetInt = (int) round($saleNet);
		$profit = $saleNetInt - $costNetInt;
		$hasCost = $costNetInt > 0;
		return [
			'cost_total_iva' => $costIvaInt,
			'cost_total_net' => $costNetInt,
			'sale_net' => $saleNetInt,
			'profit' => $profit,
			'margin_real' => $hasCost ? round(($profit / $costNetInt) * 100, 1) : null,
			'margin_on_sale' => $saleNetInt > 0 ? round(($profit / $saleNetInt) * 100, 1) : null,
			'has_cost' => $hasCost,
		];
	}

	/** @param list<array<string, mixed>> $quotes @return list<array<string, mixed>> */
	public static function attachProfitMetrics(array $quotes): array
	{
		if ($quotes === []) {
			return [];
		}
		$ids = [];
		foreach ($quotes as $quote) {
			$id = (int) ($quote['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		$byQuote = self::itemsByQuoteIds($ids);
		foreach ($quotes as &$quote) {
			$id = (int) ($quote['id'] ?? 0);
			$metrics = self::profitFromItems($byQuote[$id] ?? []);
			$quote['cost_total_iva'] = $metrics['cost_total_iva'];
			$quote['cost_total_net'] = $metrics['cost_total_net'];
			$quote['sale_net'] = $metrics['sale_net'] > 0 ? $metrics['sale_net'] : (int) ($quote['subtotal'] ?? 0);
			$quote['profit'] = $metrics['profit'];
			$quote['margin_real'] = $metrics['margin_real'];
			$quote['margin_on_sale'] = $metrics['margin_on_sale'];
			$quote['has_cost'] = $metrics['has_cost'];
		}
		unset($quote);
		return $quotes;
	}

	/**
	 * @param list<int> $quoteIds
	 * @return array<int, list<array<string, mixed>>>
	 */
	public static function itemsByQuoteIds(array $quoteIds): array
	{
		$quoteIds = array_values(array_unique(array_filter(array_map('intval', $quoteIds))));
		if ($quoteIds === []) {
			return [];
		}
		$placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
		$stmt = self::pdo()->prepare(
			"SELECT quote_id, quantity, cost_price, margin_percent, unit_price, total
			 FROM quote_items
			 WHERE quote_id IN ({$placeholders})
			 ORDER BY position ASC, id ASC"
		);
		$stmt->execute($quoteIds);
		$grouped = [];
		foreach ($stmt->fetchAll() as $row) {
			$qid = (int) $row['quote_id'];
			$grouped[$qid][] = $row;
		}
		return $grouped;
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
		$stmt = self::pdo()->prepare('SELECT number FROM quotes WHERE number LIKE ?');
		$stmt->execute(['MZ-' . $year . '-%']);
		$seq = 0;
		foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $number) {
			if (is_string($number) && preg_match('/^MZ-\d{4}-(\d+)/', $number, $m)) {
				$seq = max($seq, (int) $m[1]);
			}
		}
		return sprintf('MZ-%s-%03d', $year, $seq + 1);
	}

	/** Siguiente letra de revisión: MZ-2026-001 → MZ-2026-001-A → MZ-2026-001-B. */
	public static function nextRevision(string $number): string
	{
		$base = preg_match('/^(.*)-([A-Z])$/', $number, $cut) ? $cut[1] : $number;
		$stmt = self::pdo()->prepare('SELECT number FROM quotes WHERE number = ? OR number LIKE ?');
		$stmt->execute([$base, $base . '-%']);
		$max = '@';
		foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $existing) {
			if (!is_string($existing)) {
				continue;
			}
			if (preg_match('/^' . preg_quote($base, '/') . '-([A-Z])$/', $existing, $m) && $m[1] > $max) {
				$max = $m[1];
			}
		}
		$next = $max === '@' ? 'A' : chr(ord($max) + 1);
		if ($next > 'Z') {
			$next = 'Z';
		}
		return $base . '-' . $next;
	}

	/** Venta neta unitaria desde costo con IVA y margen %. */
	public static function netSaleFromCost(int $costWithIva, float $marginPercent): int
	{
		if ($costWithIva <= 0) {
			return 0;
		}
		$factor = 1 + (Config::TAX_RATE / 100);
		$costNet = $costWithIva / $factor;
		return (int) round($costNet * (1 + ($marginPercent / 100)));
	}

	private static function parseMoneyValue(mixed $raw): int
	{
		$value = (string) $raw;
		$value = str_replace(['$', ' '], '', $value);
		$value = str_replace('.', '', $value);
		$value = str_replace(',', '.', $value);
		return (int) round((float) $value);
	}

	private static function parsePercentValue(mixed $raw): float
	{
		$value = str_replace(['%', ' '], '', (string) $raw);
		$value = str_replace(',', '.', $value);
		return max(0.0, (float) $value);
	}

	public static function saveItems(int $quoteId, array $items): array
	{
		self::pdo()->prepare('DELETE FROM quote_items WHERE quote_id = ?')->execute([$quoteId]);
		$subtotal = 0;
		$position = 0;
		$insert = self::pdo()->prepare(
			'INSERT INTO quote_items (quote_id, position, product_id, name, description, quantity, unit, cost_price, margin_percent, unit_price, total)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
		);
		foreach ($items as $item) {
			$name = trim((string) ($item['name'] ?? ''));
			$description = trim((string) ($item['description'] ?? ''));
			if ($name === '' && $description === '') {
				continue;
			}
			if ($name === '') {
				$name = $description;
				$description = '';
			}
			$qty = (float) str_replace(',', '.', (string) ($item['quantity'] ?? 1));
			if ($qty <= 0) {
				$qty = 1;
			}
			$unit = trim((string) ($item['unit'] ?? 'un')) ?: 'un';
			$productId = (int) ($item['product_id'] ?? 0);
			$productId = $productId > 0 ? $productId : null;
			$cost = self::parseMoneyValue($item['cost_price'] ?? 0);
			$margin = self::parsePercentValue($item['margin_percent'] ?? 0);
			$price = self::parseMoneyValue($item['unit_price'] ?? 0);
			if ($cost > 0) {
				$price = self::netSaleFromCost($cost, $margin);
			}
			$total = (int) round($qty * $price);
			$insert->execute([$quoteId, $position, $productId, $name, $description, $qty, $unit, $cost, $margin, $price, $total]);
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

	public static function recentChanges(int $viewerId, ?int $ownerId, int $hours = 24): array
	{
		$sql = "SELECT q.id, q.number, q.status, q.updated_at, q.updated_by, q.client_id,
				c.name AS client_name, u.name AS editor_name
			FROM quotes q
			JOIN clients c ON c.id = q.client_id
			LEFT JOIN users u ON u.id = q.updated_by
			WHERE q.updated_by IS NOT NULL
			  AND q.updated_by != ?
			  AND q.updated_at >= ?";
		$params = [$viewerId, date('c', time() - ($hours * 3600))];
		if ($ownerId) {
			$sql .= ' AND c.owner_id = ?';
			$params[] = $ownerId;
		}
		$sql .= ' ORDER BY q.updated_at DESC LIMIT 8';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public static function itemsFromPost(): array
	{
		$names = $_POST['item_name'] ?? [];
		$descriptions = $_POST['item_description'] ?? [];
		$quantities = $_POST['item_quantity'] ?? [];
		$units = $_POST['item_unit'] ?? [];
		$costs = $_POST['item_cost'] ?? [];
		$margins = $_POST['item_margin'] ?? [];
		$prices = $_POST['item_price'] ?? [];
		$productIds = $_POST['item_product_id'] ?? [];
		$items = [];
		$keys = is_array($names) && $names !== [] ? $names : $descriptions;
		if (!is_array($keys)) {
			return $items;
		}
		foreach ($keys as $i => $_) {
			$items[] = [
				'product_id' => is_array($productIds) ? (int) ($productIds[$i] ?? 0) : 0,
				'name' => is_array($names) ? ($names[$i] ?? '') : '',
				'description' => is_array($descriptions) ? ($descriptions[$i] ?? '') : '',
				'quantity' => $quantities[$i] ?? 1,
				'unit' => $units[$i] ?? 'un',
				'cost_price' => $costs[$i] ?? 0,
				'margin_percent' => $margins[$i] ?? 0,
				'unit_price' => $prices[$i] ?? 0,
			];
		}
		return $items;
	}

	public static function itemLabel(array $item): string
	{
		$name = trim((string) ($item['name'] ?? ''));
		if ($name !== '') {
			return $name;
		}
		return trim((string) ($item['description'] ?? ''));
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
