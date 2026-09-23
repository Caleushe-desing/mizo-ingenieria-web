<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;
use RuntimeException;

final class Invoice extends Record
{
	protected static function table(): string
	{
		return 'sales_invoices';
	}

	/** @return array{clients:list<array<string,mixed>>,deals:list<array<string,mixed>>,sales:list<array<string,mixed>>,purchases:list<array<string,mixed>>} */
	public static function board(): array
	{
		$clients = self::pdo()->query('SELECT id, name FROM clients ORDER BY name ASC')->fetchAll();
		$deals = self::pdo()->query(
			'SELECT d.id, d.title, d.amount, d.client_id, c.name AS client_name
			 FROM deals d JOIN clients c ON c.id = d.client_id
			 WHERE COALESCE(d.archived, 0) = 0
			 ORDER BY c.name ASC, d.title ASC'
		)->fetchAll();
		$sales = self::pdo()->query(
			'SELECT s.*, c.name AS client_name, d.title AS deal_title
			 FROM sales_invoices s
			 JOIN clients c ON c.id = s.client_id
			 JOIN deals d ON d.id = s.deal_id
			 ORDER BY s.issued_on DESC, s.id DESC'
		)->fetchAll();
		$purchases = self::pdo()->query(
			'SELECT p.*, d.title AS deal_title, c.name AS client_name
			 FROM purchase_invoices p
			 LEFT JOIN deals d ON d.id = p.deal_id
			 LEFT JOIN clients c ON c.id = d.client_id
			 ORDER BY p.issued_on DESC, p.id DESC'
		)->fetchAll();
		return [
			'clients' => $clients,
			'deals' => $deals,
			'sales' => $sales,
			'purchases' => $purchases,
		];
	}

	public static function addSale(array $data): int
	{
		$deal = Deal::find((int) $data['deal_id']);
		if (!$deal || (int) $deal['client_id'] !== (int) $data['client_id']) {
			throw new RuntimeException('Elige un proyecto de ese cliente.');
		}
		$quoteId = (int) ($data['quote_id'] ?? 0);
		if ($quoteId > 0) {
			$quote = Quote::find($quoteId);
			if (!$quote || (int) $quote['deal_id'] !== (int) $deal['id']) {
				throw new RuntimeException('Ese presupuesto no pertenece al proyecto.');
			}
		} else {
			$quoteId = 0;
		}
		$now = date('c');
		$id = self::insert([
			'number' => $data['number'],
			'client_id' => (int) $data['client_id'],
			'deal_id' => (int) $deal['id'],
			'quote_id' => $quoteId > 0 ? $quoteId : null,
			'net' => (int) $data['net'],
			'tax' => (int) $data['tax'],
			'total' => (int) $data['total'],
			'status' => 'pending',
			'issued_on' => $data['issued_on'],
			'paid_at' => null,
			'created_by' => $data['created_by'],
			'created_at' => $now,
			'updated_at' => $now,
		]);
		Pipeline::onInvoicesChanged((int) $deal['id']);
		Activity::log('stage', 'Factura de venta ' . $data['number'] . ' por ' . $data['total'] . '.', (int) $data['created_by'], (int) $deal['client_id'], (int) $deal['id'], $quoteId > 0 ? $quoteId : null);
		return $id;
	}

	public static function markPaid(int $id, int $userId): void
	{
		$stmt = self::pdo()->prepare('SELECT * FROM sales_invoices WHERE id = ?');
		$stmt->execute([$id]);
		$row = $stmt->fetch();
		if (!$row) {
			throw new RuntimeException('Esa factura no existe.');
		}
		if ((string) $row['status'] === 'paid') {
			return;
		}
		$now = date('c');
		self::update($id, ['status' => 'paid', 'paid_at' => $now, 'updated_at' => $now]);
		Pipeline::onInvoicesChanged((int) $row['deal_id']);
		Activity::log('stage', 'Factura ' . $row['number'] . ' marcada como pagada.', $userId, (int) $row['client_id'], (int) $row['deal_id'], $row['quote_id'] ? (int) $row['quote_id'] : null);
	}

	public static function addPurchase(array $data): int
	{
		$dealId = (int) ($data['deal_id'] ?? 0);
		if ($dealId > 0 && !Deal::find($dealId)) {
			throw new RuntimeException('Ese proyecto no existe.');
		}
		$now = date('c');
		$stmt = self::pdo()->prepare(
			'INSERT INTO purchase_invoices (supplier, number, deal_id, net, tax, travel, operations, other_costs, issued_on, created_by, created_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
		);
		$stmt->execute([
			$data['supplier'],
			$data['number'],
			$dealId > 0 ? $dealId : null,
			(int) $data['net'],
			(int) $data['tax'],
			(int) $data['travel'],
			(int) $data['operations'],
			(int) $data['other_costs'],
			$data['issued_on'],
			$data['created_by'],
			$now,
		]);
		return (int) self::pdo()->lastInsertId();
	}

	/** @return list<array<string,mixed>> */
	public static function kanban(?int $ownerId): array
	{
		$pendingStage = Pipeline::slug('invoiced') ?? 'proyecto-facturado';
		$paidStage = Pipeline::slug('paid') ?? 'factura-pagada';
		$sql = 'SELECT s.id AS invoice_id, s.number, s.total, s.net, s.status, s.issued_on, s.created_at,
			s.deal_id, s.client_id, c.name, c.rut, c.city,
			d.title AS deal_title, d.service, d.owner_id, u.name AS owner_name
			FROM sales_invoices s
			JOIN clients c ON c.id = s.client_id
			JOIN deals d ON d.id = s.deal_id
			LEFT JOIN users u ON u.id = COALESCE(d.owner_id, c.owner_id)
			WHERE 1=1';
		$params = [];
		if ($ownerId) {
			$sql .= ' AND ((d.owner_id IS NOT NULL AND d.owner_id = ?) OR (d.owner_id IS NULL AND c.owner_id = ?))';
			$params[] = $ownerId;
			$params[] = $ownerId;
		}
		$sql .= ' ORDER BY s.issued_on DESC, s.id DESC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll();
		foreach ($rows as &$row) {
			$row['stage'] = ((string) $row['status'] === 'paid') ? $paidStage : $pendingStage;
		}
		unset($row);
		return $rows;
	}

	/** @return array<string,int> */
	public static function summary(): array
	{
		$sales = self::pdo()->query('SELECT COALESCE(SUM(net),0) AS net, COALESCE(SUM(tax),0) AS tax, COALESCE(SUM(total),0) AS total FROM sales_invoices')->fetch() ?: [];
		$buy = self::pdo()->query('SELECT COALESCE(SUM(net),0) AS net, COALESCE(SUM(tax),0) AS tax, COALESCE(SUM(travel),0) AS travel, COALESCE(SUM(operations),0) AS operations, COALESCE(SUM(other_costs),0) AS other_costs FROM purchase_invoices')->fetch() ?: [];
		$revenue = (int) ($sales['net'] ?? 0);
		$cost = (int) ($buy['net'] ?? 0) + (int) ($buy['travel'] ?? 0) + (int) ($buy['operations'] ?? 0) + (int) ($buy['other_costs'] ?? 0);
		return [
			'sales_net' => $revenue,
			'sales_tax' => (int) ($sales['tax'] ?? 0),
			'sales_total' => (int) ($sales['total'] ?? 0),
			'purchase_net' => (int) ($buy['net'] ?? 0),
			'purchase_tax' => (int) ($buy['tax'] ?? 0),
			'travel' => (int) ($buy['travel'] ?? 0),
			'operations' => (int) ($buy['operations'] ?? 0),
			'other_costs' => (int) ($buy['other_costs'] ?? 0),
			'cost' => $cost,
			'margin' => $revenue - $cost,
			'vat_net' => (int) ($sales['tax'] ?? 0) - (int) ($buy['tax'] ?? 0),
		];
	}

	/** @return list<array<string,mixed>> */
	public static function margins(): array
	{
		$rows = self::pdo()->query(
			'SELECT d.id, d.title, d.archived, c.name AS client_name,
				COALESCE((SELECT SUM(net) FROM sales_invoices s WHERE s.deal_id = d.id), 0) AS revenue,
				COALESCE((SELECT SUM(net + travel + operations + other_costs) FROM purchase_invoices p WHERE p.deal_id = d.id), 0) AS cost
			 FROM deals d
			 JOIN clients c ON c.id = d.client_id
			 WHERE EXISTS (SELECT 1 FROM sales_invoices s WHERE s.deal_id = d.id)
			    OR EXISTS (SELECT 1 FROM purchase_invoices p WHERE p.deal_id = d.id)
			 ORDER BY c.name ASC, d.title ASC'
		)->fetchAll();
		foreach ($rows as &$row) {
			$row['margin'] = (int) $row['revenue'] - (int) $row['cost'];
		}
		unset($row);
		return $rows;
	}
}
