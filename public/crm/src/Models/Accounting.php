<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;
use RuntimeException;

final class Accounting extends Record
{
	protected static function table(): string
	{
		return 'company_obligations';
	}

	public static function taxRate(): float
	{
		$rate = self::pdo()->query('SELECT income_tax_rate FROM accounting_settings WHERE id = 1')->fetchColumn();
		if ($rate === false) {
			self::pdo()->exec('INSERT OR IGNORE INTO accounting_settings (id, income_tax_rate) VALUES (1, 27)');
			return 27.0;
		}
		return max(0.0, min(100.0, (float) $rate));
	}

	public static function saveTaxRate(float $rate): void
	{
		$rate = max(0.0, min(100.0, round($rate, 2)));
		self::taxRate();
		self::pdo()->prepare('UPDATE accounting_settings SET income_tax_rate = ? WHERE id = 1')->execute([$rate]);
	}

	/** @param array<int,float> $rates */
	public static function saveCommissions(array $rates): void
	{
		$stmt = self::pdo()->prepare('UPDATE users SET commission_rate = ? WHERE id = ? AND active = 1');
		foreach ($rates as $id => $rate) {
			$id = (int) $id;
			if ($id < 1) {
				continue;
			}
			$stmt->execute([max(0.0, min(100.0, round((float) $rate, 2))), $id]);
		}
	}

	public static function addObligation(array $data): int
	{
		$now = date('c');
		return self::insert([
			'kind' => $data['kind'],
			'concept' => $data['concept'],
			'notes' => $data['notes'],
			'net' => (int) $data['net'],
			'tax' => (int) $data['tax'],
			'total' => (int) $data['total'],
			'status' => $data['status'],
			'issued_on' => $data['issued_on'],
			'due_on' => $data['due_on'],
			'paid_at' => $data['status'] === 'paid' ? $now : null,
			'created_by' => $data['created_by'],
			'created_at' => $now,
			'updated_at' => $now,
		]);
	}

	public static function updateObligation(int $id, array $data): void
	{
		$row = self::find($id);
		if (!$row) {
			throw new RuntimeException('Ese registro no existe.');
		}
		$paidAt = null;
		if ($data['status'] === 'paid') {
			$paidAt = (string) ($row['paid_at'] ?? '') !== '' ? $row['paid_at'] : date('c');
		}
		self::update($id, [
			'kind' => $data['kind'],
			'concept' => $data['concept'],
			'notes' => $data['notes'],
			'net' => (int) $data['net'],
			'tax' => (int) $data['tax'],
			'total' => (int) $data['total'],
			'status' => $data['status'],
			'issued_on' => $data['issued_on'],
			'due_on' => $data['due_on'],
			'paid_at' => $paidAt,
			'updated_at' => date('c'),
		]);
	}

	/** @return array<string,mixed> */
	public static function report(int $year): array
	{
		$from = sprintf('%04d-01-01', $year);
		$to = sprintf('%04d-01-01', $year + 1);
		$rate = self::taxRate();
		$people = self::pdo()->query(
			'SELECT id, name, role, commission_rate FROM users WHERE active = 1 ORDER BY name ASC'
		)->fetchAll();
		$byUser = [];
		foreach ($people as $person) {
			$byUser[(int) $person['id']] = [
				'id' => (int) $person['id'],
				'name' => (string) $person['name'],
				'role' => (string) $person['role'],
				'rate' => (float) $person['commission_rate'],
				'net' => 0,
				'paid_net' => 0,
				'commission' => 0,
			];
		}

		$salesStmt = self::pdo()->prepare(
			'SELECT s.net, s.tax, s.total, s.status, s.issued_on, s.deal_id,
				d.title AS deal_title, d.owner_id AS deal_owner,
				c.name AS client_name, c.owner_id AS client_owner
			 FROM sales_invoices s
			 JOIN deals d ON d.id = s.deal_id
			 JOIN clients c ON c.id = s.client_id
			 WHERE s.issued_on >= ? AND s.issued_on < ?'
		);
		$salesStmt->execute([$from, $to]);
		$sales = ['net' => 0, 'tax' => 0, 'total' => 0, 'paid_total' => 0];
		$months = [];
		for ($month = 1; $month <= 12; $month++) {
			$months[$month] = ['debit' => 0, 'credit' => 0];
		}
		$projects = [];
		foreach ($salesStmt->fetchAll() as $row) {
			$net = (int) $row['net'];
			$tax = (int) $row['tax'];
			$total = (int) $row['total'];
			$paid = (string) $row['status'] === 'paid';
			$sales['net'] += $net;
			$sales['tax'] += $tax;
			$sales['total'] += $total;
			if ($paid) {
				$sales['paid_total'] += $total;
			}
			$month = self::monthOf((string) $row['issued_on']);
			if ($month) {
				$months[$month]['debit'] += $tax;
			}
			$dealId = (int) $row['deal_id'];
			if (!isset($projects[$dealId])) {
				$owner = self::assignee($row);
				$projects[$dealId] = [
					'id' => $dealId,
					'title' => (string) $row['deal_title'],
					'client' => (string) $row['client_name'],
					'owner_id' => $owner,
					'owner' => $byUser[$owner]['name'] ?? 'Sin asignar',
					'rate' => $byUser[$owner]['rate'] ?? 0.0,
					'net' => 0,
					'tax' => 0,
					'total' => 0,
					'paid_total' => 0,
					'cost' => 0,
				];
			}
			$projects[$dealId]['net'] += $net;
			$projects[$dealId]['tax'] += $tax;
			$projects[$dealId]['total'] += $total;
			if ($paid) {
				$projects[$dealId]['paid_total'] += $total;
			}
		}

		$buyStmt = self::pdo()->prepare(
			'SELECT net, tax, travel, operations, other_costs, issued_on, deal_id
			 FROM purchase_invoices
			 WHERE issued_on >= ? AND issued_on < ?'
		);
		$buyStmt->execute([$from, $to]);
		$purchases = ['net' => 0, 'tax' => 0, 'extras' => 0, 'cost' => 0, 'cash' => 0];
		foreach ($buyStmt->fetchAll() as $row) {
			$net = (int) $row['net'];
			$tax = (int) $row['tax'];
			$extras = (int) $row['travel'] + (int) $row['operations'] + (int) $row['other_costs'];
			$cost = $net + $extras;
			$purchases['net'] += $net;
			$purchases['tax'] += $tax;
			$purchases['extras'] += $extras;
			$purchases['cost'] += $cost;
			$purchases['cash'] += $cost + $tax;
			$month = self::monthOf((string) $row['issued_on']);
			if ($month) {
				$months[$month]['credit'] += $tax;
			}
			$dealId = (int) ($row['deal_id'] ?? 0);
			if ($dealId > 0) {
				if (!isset($projects[$dealId])) {
					$deal = Deal::find($dealId);
					$clientName = '';
					$owner = 0;
					$title = 'Proyecto';
					if ($deal) {
						$title = (string) $deal['title'];
						$owner = (int) ($deal['owner_id'] ?? 0);
						$client = Client::find((int) $deal['client_id']);
						$clientName = $client ? (string) $client['name'] : '';
						if ($owner < 1 && $client) {
							$owner = (int) ($client['owner_id'] ?? 0);
						}
					}
					$projects[$dealId] = [
						'id' => $dealId,
						'title' => $title,
						'client' => $clientName,
						'owner_id' => $owner,
						'owner' => $byUser[$owner]['name'] ?? 'Sin asignar',
						'rate' => $byUser[$owner]['rate'] ?? 0.0,
						'net' => 0,
						'tax' => 0,
						'total' => 0,
						'paid_total' => 0,
						'cost' => 0,
					];
				}
				$projects[$dealId]['cost'] += $cost;
			}
		}

		$obStmt = self::pdo()->prepare(
			'SELECT * FROM company_obligations WHERE issued_on >= ? AND issued_on < ? ORDER BY issued_on DESC, id DESC'
		);
		$obStmt->execute([$from, $to]);
		$obligations = [
			'expense_net' => 0,
			'expense_tax' => 0,
			'expense_paid' => 0,
			'expense_pending' => 0,
			'debt_pending' => 0,
			'debt_paid' => 0,
			'commitment_pending' => 0,
			'commitment_paid' => 0,
		];
		$rows = $obStmt->fetchAll();
		foreach ($rows as $row) {
			$kind = (string) $row['kind'];
			$paid = (string) $row['status'] === 'paid';
			$net = (int) $row['net'];
			$tax = (int) $row['tax'];
			$total = (int) $row['total'];
			if ($kind === 'gasto') {
				$obligations['expense_net'] += $net;
				$obligations['expense_tax'] += $tax;
				if ($paid) {
					$obligations['expense_paid'] += $total;
				} else {
					$obligations['expense_pending'] += $total;
				}
				$month = self::monthOf((string) $row['issued_on']);
				if ($month) {
					$months[$month]['credit'] += $tax;
				}
			} elseif ($kind === 'deuda') {
				if ($paid) {
					$obligations['debt_paid'] += $total;
				} else {
					$obligations['debt_pending'] += $total;
				}
			} elseif ($paid) {
				$obligations['commitment_paid'] += $total;
			} else {
				$obligations['commitment_pending'] += $total;
			}
		}

		$otherDebt = self::pdo()->prepare(
			"SELECT COALESCE(SUM(total), 0) FROM company_obligations
			 WHERE kind = 'deuda' AND status = 'pending' AND (issued_on < ? OR issued_on >= ?)"
		);
		$otherDebt->execute([$from, $to]);
		$openDebtOtherYears = (int) $otherDebt->fetchColumn();

		$commissionTotal = 0;
		foreach ($projects as &$project) {
			$commission = (int) round((int) $project['net'] * (float) $project['rate'] / 100);
			$project['commission'] = $commission;
			$project['margin'] = (int) $project['net'] - (int) $project['cost'] - $commission;
			$commissionTotal += $commission;
			$owner = (int) $project['owner_id'];
			if (isset($byUser[$owner])) {
				$byUser[$owner]['net'] += (int) $project['net'];
				$byUser[$owner]['commission'] += $commission;
			}
		}
		unset($project);
		usort($projects, static function (array $a, array $b): int {
			return [$a['client'], $a['title']] <=> [$b['client'], $b['title']];
		});

		$gross = $sales['net'] - $purchases['cost'];
		$beforeTax = $gross - $obligations['expense_net'] - $commissionTotal;
		$incomeTax = $beforeTax > 0 ? (int) round($beforeTax * $rate / 100) : 0;
		$afterTax = $beforeTax - $incomeTax;
		$vatDebit = $sales['tax'];
		$vatCredit = $purchases['tax'] + $obligations['expense_tax'];
		$cashOut = $purchases['cash'] + $obligations['expense_paid'] + $obligations['debt_paid'] + $obligations['commitment_paid'];
		$cashBalance = $sales['paid_total'] - $cashOut;
		$pendingOut = $obligations['expense_pending'] + $obligations['debt_pending'] + $obligations['commitment_pending'] + $commissionTotal + $openDebtOtherYears;

		$monthRows = [];
		foreach ($months as $number => $bucket) {
			$monthRows[] = [
				'month' => $number,
				'debit' => $bucket['debit'],
				'credit' => $bucket['credit'],
				'due' => $bucket['debit'] - $bucket['credit'],
			];
		}

		return [
			'year' => $year,
			'tax_rate' => $rate,
			'sales' => $sales,
			'purchases' => $purchases,
			'obligations' => $obligations,
			'rows' => $rows,
			'open_debt_other_years' => $openDebtOtherYears,
			'gross_profit' => $gross,
			'commissions_total' => $commissionTotal,
			'profit_before_tax' => $beforeTax,
			'income_tax' => $incomeTax,
			'profit_after_tax' => $afterTax,
			'vat_debit' => $vatDebit,
			'vat_credit' => $vatCredit,
			'vat_due' => $vatDebit - $vatCredit,
			'cash_in' => $sales['paid_total'],
			'cash_out' => $cashOut,
			'cash_balance' => $cashBalance,
			'cash_forecast' => $cashBalance - $pendingOut,
			'months' => $monthRows,
			'executives' => array_values($byUser),
			'projects' => $projects,
		];
	}

	/** @param array<string,mixed> $row */
	private static function assignee(array $row): int
	{
		$owner = (int) ($row['deal_owner'] ?? 0);
		if ($owner < 1) {
			$owner = (int) ($row['client_owner'] ?? 0);
		}
		return $owner;
	}

	private static function monthOf(string $date): int
	{
		if (!preg_match('/^\d{4}-(\d{2})-\d{2}/', $date, $matches)) {
			return 0;
		}
		$month = (int) $matches[1];
		return $month >= 1 && $month <= 12 ? $month : 0;
	}
}
