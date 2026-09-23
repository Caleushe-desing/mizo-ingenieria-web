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

	public static function onQuoteRejected(int $dealId): void
	{
		$deal = Deal::find($dealId);
		if (!$deal || !empty($deal['archived'])) {
			return;
		}
		if (self::rank(self::roleOf((string) $deal['stage'])) >= self::rank('accepted')) {
			return;
		}
		self::move($deal, 'sent', 'El cliente no aceptó el presupuesto. La tarjeta sigue en Presupuesto enviado.');
	}

	/** Columnas que el ejecutivo puede usar, de la primera hasta Presupuesto enviado inclusive. @return list<string> */
	public static function executiveSlugs(): array
	{
		$rows = self::stageRows();
		$boundary = self::boundaryRow($rows);
		$out = [];
		foreach ($rows as $row) {
			if (self::isBlockedRole((string) ($row['role'] ?? ''))) {
				continue;
			}
			if ($boundary !== null && (int) $row['position'] > (int) $boundary['position']) {
				continue;
			}
			if ($boundary === null && in_array((string) ($row['kind'] ?? 'open'), ['won', 'lost'], true)) {
				continue;
			}
			$out[] = (string) $row['slug'];
		}
		if ($boundary !== null) {
			$slug = (string) $boundary['slug'];
			if (!in_array($slug, $out, true) && !self::isBlockedRole((string) ($boundary['role'] ?? ''))) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	public static function executiveLimitSlug(): string
	{
		$boundary = self::boundaryRow(self::stageRows());
		return $boundary ? (string) $boundary['slug'] : '';
	}

	/** @return list<string> */
	public static function executiveBlockSlugs(): array
	{
		$out = [];
		foreach (self::stageRows() as $row) {
			if (self::isBlockedRole((string) ($row['role'] ?? ''))) {
				$out[] = (string) $row['slug'];
			}
		}
		return $out;
	}

	public static function withinExecutiveReach(string $from, string $to): bool
	{
		$allowed = self::executiveSlugs();
		return in_array($from, $allowed, true) && in_array($to, $allowed, true);
	}

	public static function onInvoicesChanged(int $dealId): void
	{
		self::reconcile($dealId);
	}

	/** Devuelve al tablero los proyectos cerrados antes de que lo pagado cubra el monto. */
	public static function reconcileOpen(): void
	{
		$ids = self::pdo()->query(
			'SELECT id FROM deals WHERE COALESCE(archived, 0) = 1
			 OR stage IN (SELECT slug FROM board_stages WHERE role IN (\'invoiced\', \'paid\'))'
		)->fetchAll(\PDO::FETCH_COLUMN);
		foreach ($ids as $id) {
			self::reconcile((int) $id);
		}
	}

	public static function reconcile(int $dealId): void
	{
		$deal = Deal::find($dealId);
		if (!$deal) {
			return;
		}
		$sales = self::pdo()->prepare(
			'SELECT COALESCE(SUM(CASE WHEN status = \'paid\' THEN total ELSE 0 END), 0) AS paid_total
			 FROM sales_invoices WHERE deal_id = ?'
		);
		$sales->execute([$dealId]);
		$paidTotal = (int) $sales->fetchColumn();
		$target = self::target($dealId, $deal);
		$covered = $target > 0 && $paidTotal >= $target;
		if ($covered) {
			if (!empty($deal['archived'])) {
				return;
			}
			Deal::update($dealId, ['archived' => 1, 'updated_at' => date('c')]);
			Activity::log('stage', 'Lo pagado ya cubre el proyecto. Salió del tablero activo.', null, (int) $deal['client_id'], $dealId);
			return;
		}
		$fields = ['updated_at' => date('c')];
		$bringBack = false;
		if (!empty($deal['archived'])) {
			$fields['archived'] = 0;
			$bringBack = true;
		}
		$role = self::roleOf((string) $deal['stage']);
		$accepted = self::slug('accepted');
		if ($accepted && in_array($role, ['invoiced', 'paid'], true) && (string) $deal['stage'] !== $accepted) {
			$fields['stage'] = $accepted;
			$bringBack = true;
		}
		if (!$bringBack) {
			return;
		}
		Deal::update($dealId, $fields);
		Activity::log('stage', 'El proyecto sigue en el tablero: lo pagado todavía no cubre el monto.', null, (int) $deal['client_id'], $dealId);
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

	/** @return list<array<string,mixed>> */
	private static function stageRows(): array
	{
		static $rows = null;
		if ($rows === null) {
			$fetched = self::pdo()->query(
				'SELECT slug, label, position, role, kind FROM board_stages ORDER BY position ASC, id ASC'
			)->fetchAll();
			$rows = $fetched ?: [];
		}
		return $rows;
	}

	/** @param list<array<string,mixed>> $rows */
	private static function boundaryRow(array $rows): ?array
	{
		foreach ($rows as $row) {
			if ((string) ($row['role'] ?? '') === 'sent') {
				return $row;
			}
		}
		foreach ($rows as $row) {
			if ((string) ($row['slug'] ?? '') === 'propuesta') {
				return $row;
			}
		}
		foreach ($rows as $row) {
			if (str_contains(self::fold((string) ($row['label'] ?? '')), 'presupuesto enviado')) {
				return $row;
			}
		}
		return null;
	}

	private static function isBlockedRole(string $role): bool
	{
		return in_array($role, ['accepted', 'invoiced', 'paid'], true);
	}

	private static function fold(string $value): string
	{
		$value = strtr($value, [
			'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
		]);
		return strtolower(trim($value));
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
