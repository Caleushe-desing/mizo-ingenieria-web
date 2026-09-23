<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class Pipeline extends Record
{
	protected static function table(): string
	{
		return 'deals';
	}

	public static function onQuoteSent(int $dealId): void
	{
		$deal = Deal::find($dealId);
		if (!$deal || !empty($deal['archived'])) {
			return;
		}
		if (self::rank(self::roleOf((string) $deal['stage'])) >= self::rank('accepted')) {
			return;
		}
		Deal::update($dealId, ['job_status' => 'cotizado', 'updated_at' => date('c')]);
		self::move($deal, 'sent', 'El presupuesto quedó en Presupuesto enviado.');
	}

	public static function onQuoteAccepted(int $dealId, int $total): void
	{
		$deal = Deal::find($dealId);
		if (!$deal || !empty($deal['archived'])) {
			return;
		}
		$fields = ['amount' => max($total, (int) $deal['amount']), 'updated_at' => date('c')];
		$slug = self::slug('accepted');
		if ($slug && self::rank(self::roleOf((string) $deal['stage'])) < self::rank('invoiced')) {
			$fields['stage'] = $slug;
		}
		Deal::update($dealId, $fields);
		if (isset($fields['stage'])) {
			Activity::log('stage', 'El cliente aceptó el presupuesto. La tarjeta pasó a «' . self::label('accepted') . '».', null, (int) $deal['client_id'], $dealId);
		}
	}

	public static function onInvoicesChanged(int $dealId): void
	{
		$deal = Deal::find($dealId);
		if (!$deal || !empty($deal['archived'])) {
			return;
		}
		$sales = self::pdo()->prepare('SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS total, SUM(CASE WHEN status = \'paid\' THEN 1 ELSE 0 END) AS paid FROM sales_invoices WHERE deal_id = ?');
		$sales->execute([$dealId]);
		$row = $sales->fetch() ?: ['n' => 0, 'total' => 0, 'paid' => 0];
		$count = (int) $row['n'];
		if ($count < 1) {
			return;
		}
		$target = self::target($dealId, $deal);
		$covered = $target > 0 && (int) $row['total'] >= $target;
		$allPaid = (int) $row['paid'] === $count;
		if ($covered && $allPaid) {
			$fields = ['archived' => 1, 'updated_at' => date('c')];
			$paid = self::slug('paid');
			if ($paid !== null) {
				$fields['stage'] = $paid;
			}
			Deal::update($dealId, $fields);
			Activity::log('stage', 'Las facturas cubren el proyecto y están pagadas. Salió del tablero activo.', null, (int) $deal['client_id'], $dealId);
			return;
		}
		self::move($deal, $allPaid ? 'paid' : 'invoiced', $allPaid
			? 'La factura quedó pagada. La tarjeta pasó a «' . self::label('paid') . '».'
			: 'Se registró una factura. La tarjeta pasó a «' . self::label('invoiced') . '».');
	}

	/** @param array<string,mixed> $deal */
	private static function move(array $deal, string $role, string $message): void
	{
		$slug = self::slug($role);
		if ($slug === null || (string) $deal['stage'] === $slug) {
			return;
		}
		Deal::update((int) $deal['id'], ['stage' => $slug, 'updated_at' => date('c')]);
		Activity::log('stage', $message, null, (int) $deal['client_id'], (int) $deal['id']);
	}

	public static function slug(string $role): ?string
	{
		$stmt = self::pdo()->prepare('SELECT slug FROM board_stages WHERE role = ? ORDER BY position ASC, id ASC LIMIT 1');
		$stmt->execute([$role]);
		$slug = $stmt->fetchColumn();
		return $slug ? (string) $slug : null;
	}

	public static function label(string $role): string
	{
		$slug = self::slug($role);
		if ($slug === null) {
			return $role;
		}
		$labels = Stage::labels();
		return $labels[$slug] ?? $role;
	}

	private static function roleOf(string $slug): string
	{
		$stmt = self::pdo()->prepare('SELECT role FROM board_stages WHERE slug = ?');
		$stmt->execute([$slug]);
		$role = $stmt->fetchColumn();
		return $role ? (string) $role : '';
	}

	private static function rank(string $role): int
	{
		return match ($role) {
			'sent' => 1,
			'accepted' => 2,
			'invoiced' => 3,
			'paid' => 4,
			default => 0,
		};
	}

	/** @param array<string,mixed> $deal */
	private static function target(int $dealId, array $deal): int
	{
		$stmt = self::pdo()->prepare("SELECT COALESCE(MAX(total), 0) FROM quotes WHERE deal_id = ? AND status = 'aceptada'");
		$stmt->execute([$dealId]);
		$accepted = (int) $stmt->fetchColumn();
		$latest = 0;
		if ($accepted < 1) {
			$latestStmt = self::pdo()->prepare('SELECT COALESCE(total, 0) FROM quotes WHERE deal_id = ? ORDER BY id DESC LIMIT 1');
			$latestStmt->execute([$dealId]);
			$latest = (int) $latestStmt->fetchColumn();
		}
		return max((int) $deal['amount'], $accepted, $latest);
	}
}
