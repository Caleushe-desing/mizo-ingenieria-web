<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class Pipeline extends Record
{
	/** @var list<array<string,mixed>>|null */
	private static ?array $stageRowsCache = null;

	protected static function table(): string
	{
		return 'deals';
	}

	/** Repara roles de columnas del tablero (idempotente, seguro en cada boot). */
	public static function ensureRoles(): void
	{
		$pdo = self::pdo();
		try {
			$cols = array_column($pdo->query('PRAGMA table_info(board_stages)')->fetchAll(), 'name');
			if ($cols === []) {
				return;
			}
			if (!in_array('role', $cols, true)) {
				$pdo->exec("ALTER TABLE board_stages ADD COLUMN role TEXT NOT NULL DEFAULT ''");
			}
			$dealCols = array_column($pdo->query('PRAGMA table_info(deals)')->fetchAll(), 'name');
			if ($dealCols !== [] && !in_array('archived', $dealCols, true)) {
				$pdo->exec('ALTER TABLE deals ADD COLUMN archived INTEGER NOT NULL DEFAULT 0');
			}
		} catch (\Throwable) {
			return;
		}

		$defaults = [
			'sent' => ['propuesta', 'Presupuesto enviado', '#f47b20', ['presupuesto enviado', 'propuesta', 'cotizacion enviada']],
			'accepted' => ['presupuesto-aceptado', 'Presupuesto aceptado', '#1c9bd8', ['presupuesto aceptado', 'aceptado']],
			'invoiced' => ['proyecto-facturado', 'Proyecto facturado', '#0b6ea8', ['proyecto facturado', 'facturado']],
			'paid' => ['factura-pagada', 'Factura pagada', '#1f8a4c', ['factura pagada', 'pagada']],
		];

		foreach ($defaults as $role => [$slug, $label, $color, $hints]) {
			$hasRole = $pdo->prepare('SELECT slug FROM board_stages WHERE role = ? ORDER BY position ASC, id ASC LIMIT 1');
			$hasRole->execute([$role]);
			if ($hasRole->fetchColumn()) {
				continue;
			}

			$bySlug = $pdo->prepare('SELECT slug FROM board_stages WHERE slug = ? LIMIT 1');
			$bySlug->execute([$slug]);
			if ($bySlug->fetchColumn()) {
				$pdo->prepare("UPDATE board_stages SET role = ? WHERE slug = ? AND (role IS NULL OR role = '')")->execute([$role, $slug]);
				continue;
			}

			$matched = null;
			foreach ($pdo->query('SELECT slug, label, role FROM board_stages') as $row) {
				if (trim((string) ($row['role'] ?? '')) !== '') {
					continue;
				}
				$folded = self::fold((string) ($row['label'] ?? ''));
				foreach ($hints as $hint) {
					if (str_contains($folded, $hint)) {
						$matched = (string) $row['slug'];
						break 2;
					}
				}
			}
			if ($matched !== null) {
				$pdo->prepare("UPDATE board_stages SET role = ? WHERE slug = ?")->execute([$role, $matched]);
				continue;
			}

			$base = (int) $pdo->query("SELECT position FROM board_stages WHERE role = 'sent' OR slug = 'propuesta' ORDER BY position ASC LIMIT 1")->fetchColumn();
			if ($base < 1) {
				$base = (int) $pdo->query('SELECT COALESCE(MAX(position), 0) FROM board_stages')->fetchColumn();
			}
			$shift = match ($role) {
				'sent' => 0,
				'accepted' => 1,
				'invoiced' => 2,
				'paid' => 3,
				default => 1,
			};
			$pdo->prepare('UPDATE board_stages SET position = position + 1 WHERE position > ?')->execute([$base + $shift - 1]);
			$pdo->prepare(
				'INSERT INTO board_stages (slug, label, color, position, kind, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
			)->execute([$slug, $label, $color, $base + $shift, 'open', $role, date('c')]);
		}

		$pdo->exec("UPDATE board_stages SET role = 'sent' WHERE slug = 'propuesta' AND (role IS NULL OR role = '')");
		$pdo->exec("UPDATE board_stages SET label = 'Presupuesto enviado' WHERE slug = 'propuesta' AND label LIKE 'Propuesta / Cotiz%'");
		self::$stageRowsCache = null;
	}

	public static function onQuoteSent(int $dealId): void
	{
		self::ensureRoles();
		$deal = Deal::find($dealId);
		if (!$deal || !empty($deal['archived'])) {
			return;
		}
		if (self::rank(self::roleOf((string) $deal['stage'])) >= self::rank('accepted')) {
			return;
		}
		$slug = self::slug('sent');
		$fields = [
			'job_status' => 'cotizado',
			'updated_at' => date('c'),
		];
		$moved = false;
		if ($slug !== null && (string) $deal['stage'] !== $slug) {
			$fields['stage'] = $slug;
			$moved = true;
		}
		Deal::update($dealId, $fields);
		if ($moved) {
			Activity::log('stage', 'El presupuesto quedó en «' . self::label('sent') . '».', null, (int) $deal['client_id'], $dealId);
		}
	}

	public static function onQuoteAccepted(int $dealId, int $total): void
	{
		self::ensureRoles();
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
		self::ensureRoles();
		$deal = Deal::find($dealId);
		if (!$deal || !empty($deal['archived'])) {
			return;
		}
		if (self::rank(self::roleOf((string) $deal['stage'])) >= self::rank('accepted')) {
			return;
		}
		self::move($deal, 'sent', 'El cliente no aceptó el presupuesto. La tarjeta sigue en «' . self::label('sent') . '».');
	}

	/**
	 * Únicas columnas con arrastre manual: Prospecto ↔ Llamada.
	 * Todo lo demás (diagnóstico, presupuesto, facturas…) es automático.
	 * @return list<string>
	 */
	public static function manualSlugs(): array
	{
		self::ensureRoles();
		$wanted = [
			'nuevo' => ['prospecto', 'lead nuevo'],
			'contactado' => ['llamada', 'contacto /', 'contacto realizado'],
		];
		$out = [];
		$rows = self::stageRows();
		foreach ($wanted as $slug => $hints) {
			foreach ($rows as $row) {
				if ((string) ($row['slug'] ?? '') === $slug) {
					$out[] = $slug;
					continue 2;
				}
			}
			foreach ($rows as $row) {
				$role = trim((string) ($row['role'] ?? ''));
				if ($role !== '' || in_array((string) ($row['kind'] ?? 'open'), ['won', 'lost'], true)) {
					continue;
				}
				$folded = self::fold((string) ($row['label'] ?? ''));
				foreach ($hints as $hint) {
					if (str_contains($folded, $hint)) {
						$out[] = (string) $row['slug'];
						continue 3;
					}
				}
			}
		}
		return array_values(array_unique($out));
	}

	/** @return list<string> */
	public static function executiveSlugs(): array
	{
		return self::manualSlugs();
	}

	public static function executiveLimitSlug(): string
	{
		$manual = self::manualSlugs();
		return $manual !== [] ? (string) $manual[count($manual) - 1] : '';
	}

	/** @return list<string> */
	public static function executiveBlockSlugs(): array
	{
		$manual = self::manualSlugs();
		$out = [];
		foreach (self::stageRows() as $row) {
			$slug = (string) ($row['slug'] ?? '');
			if ($slug !== '' && !in_array($slug, $manual, true)) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	public static function withinExecutiveReach(string $from, string $to): bool
	{
		return self::canManualMove($from, $to);
	}

	public static function canManualMove(string $from, string $to): bool
	{
		$allowed = self::manualSlugs();
		return in_array($from, $allowed, true) && in_array($to, $allowed, true);
	}

	public static function onInvoicesChanged(int $dealId): void
	{
		self::reconcile($dealId);
	}

	/**
	 * Repara el tablero: cotizaciones enviadas/aceptadas y facturas.
	 * @return int cantidad de tarjetas movidas por cotización
	 */
	public static function reconcileOpen(): int
	{
		self::ensureRoles();
		$moved = self::reconcileQuotes();
		$ids = self::pdo()->query(
			'SELECT id FROM deals WHERE COALESCE(archived, 0) = 1
			 OR stage IN (SELECT slug FROM board_stages WHERE role IN (\'invoiced\', \'paid\'))'
		)->fetchAll(\PDO::FETCH_COLUMN);
		foreach ($ids as $id) {
			self::reconcile((int) $id);
		}
		return $moved;
	}

	/**
	 * Alinea tarjetas abiertas con el estado real de sus cotizaciones.
	 * Corrige proyectos enviados que no se movieron a Presupuesto enviado.
	 * @return int
	 */
	public static function reconcileQuotes(): int
	{
		self::ensureRoles();
		$moved = 0;
		$stmt = self::pdo()->query(
			"SELECT d.id, d.client_id, d.stage, d.archived,
				(SELECT q.status FROM quotes q
					WHERE q.deal_id = d.id
					  AND (q.status IN ('enviada','vista','aceptada','rechazada') OR q.sent_at IS NOT NULL AND q.sent_at != '')
					ORDER BY
						CASE q.status
							WHEN 'aceptada' THEN 4
							WHEN 'enviada' THEN 3
							WHEN 'vista' THEN 3
							WHEN 'rechazada' THEN 2
							ELSE 1
						END DESC,
						q.id DESC
					LIMIT 1) AS quote_status,
				(SELECT q.sent_at FROM quotes q
					WHERE q.deal_id = d.id AND q.sent_at IS NOT NULL AND q.sent_at != ''
					ORDER BY q.id DESC LIMIT 1) AS quote_sent_at
			 FROM deals d
			 WHERE COALESCE(d.archived, 0) = 0"
		);
		foreach ($stmt->fetchAll() as $deal) {
			$status = (string) ($deal['quote_status'] ?? '');
			$sentAt = trim((string) ($deal['quote_sent_at'] ?? ''));
			if ($status === '' && $sentAt === '') {
				continue;
			}
			$before = (string) ($deal['stage'] ?? '');
			$current = self::rank(self::roleOf($before));
			if ($status === 'aceptada') {
				if ($current < self::rank('invoiced') && self::move($deal, 'accepted', 'La tarjeta se alineó con el presupuesto aceptado.')) {
					$moved++;
				}
				continue;
			}
			$wasSent = in_array($status, ['enviada', 'vista', 'rechazada'], true) || $sentAt !== '';
			if ($wasSent && $current < self::rank('accepted')) {
				if (self::move($deal, 'sent', 'La tarjeta se alineó con el presupuesto enviado.')) {
					$moved++;
				} elseif ($before !== (string) (self::slug('sent') ?? '')) {
					// Forzar si move no pudo por caché vieja
					self::$stageRowsCache = null;
					if (self::move($deal, 'sent', 'La tarjeta se alineó con el presupuesto enviado.')) {
						$moved++;
					}
				}
			}
		}
		return $moved;
	}

	public static function reconcile(int $dealId): void
	{
		self::ensureRoles();
		$deal = Deal::find($dealId);
		if (!$deal) {
			return;
		}
		$sales = self::pdo()->prepare(
			"SELECT COUNT(*) AS n,
				COALESCE(SUM(total), 0) AS invoice_total,
				COALESCE(SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END), 0) AS paid_total,
				SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count
			 FROM sales_invoices WHERE deal_id = ?"
		);
		$sales->execute([$dealId]);
		$row = $sales->fetch() ?: ['n' => 0, 'invoice_total' => 0, 'paid_total' => 0, 'paid_count' => 0];
		$count = (int) $row['n'];
		$paidTotal = (int) $row['paid_total'];
		$target = self::target($dealId, $deal);
		$covered = $target > 0 && $paidTotal >= $target;
		$allPaid = $count > 0 && (int) $row['paid_count'] === $count;

		if ($covered && $allPaid) {
			if (!empty($deal['archived']) && self::roleOf((string) $deal['stage']) === 'paid') {
				return;
			}
			$fields = ['archived' => 1, 'updated_at' => date('c')];
			$paid = self::slug('paid');
			if ($paid !== null) {
				$fields['stage'] = $paid;
			}
			Deal::update($dealId, $fields);
			Activity::log('stage', 'Las facturas cubren el proyecto y están pagadas. Salió del tablero activo.', null, (int) $deal['client_id'], $dealId);
			return;
		}

		$fields = ['updated_at' => date('c')];
		$bringBack = false;
		if (!empty($deal['archived'])) {
			// Solo reaparece si se cerró solo por pago completo y el cobro dejó de cubrir.
			// Un Finalizado manual (cualquier otra etapa) se queda fuera del tablero.
			if (self::roleOf((string) $deal['stage']) !== 'paid') {
				return;
			}
			$fields['archived'] = 0;
			$bringBack = true;
		}

		if ($count > 0) {
			$wantRole = $allPaid ? 'paid' : 'invoiced';
			$slug = self::slug($wantRole);
			if ($slug !== null && (string) $deal['stage'] !== $slug
				&& self::rank(self::roleOf((string) $deal['stage'])) <= self::rank($wantRole)) {
				$fields['stage'] = $slug;
				$bringBack = true;
				Deal::update($dealId, $fields);
				Activity::log(
					'stage',
					$allPaid
						? 'La factura quedó pagada. La tarjeta pasó a «' . self::label('paid') . '».'
						: 'Se registró una factura. La tarjeta pasó a «' . self::label('invoiced') . '».',
					null,
					(int) $deal['client_id'],
					$dealId
				);
				return;
			}
		}

		$role = self::roleOf((string) $deal['stage']);
		$accepted = self::slug('accepted');
		if ($accepted && in_array($role, ['invoiced', 'paid'], true) && $count < 1 && (string) $deal['stage'] !== $accepted) {
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
	private static function move(array $deal, string $role, string $message): bool
	{
		$slug = self::slug($role);
		if ($slug === null || (string) ($deal['stage'] ?? '') === $slug) {
			return false;
		}
		Deal::update((int) $deal['id'], ['stage' => $slug, 'updated_at' => date('c')]);
		Activity::log('stage', $message, null, (int) ($deal['client_id'] ?? 0), (int) $deal['id']);
		return true;
	}

	public static function slug(string $role): ?string
	{
		foreach (self::stageRows() as $row) {
			if ((string) ($row['role'] ?? '') === $role) {
				return (string) $row['slug'];
			}
		}
		$fallbacks = [
			'sent' => ['propuesta'],
			'accepted' => ['presupuesto-aceptado'],
			'invoiced' => ['proyecto-facturado'],
			'paid' => ['factura-pagada'],
		];
		foreach ($fallbacks[$role] ?? [] as $slug) {
			foreach (self::stageRows() as $row) {
				if ((string) ($row['slug'] ?? '') === $slug) {
					return $slug;
				}
			}
		}
		$hints = [
			'sent' => ['presupuesto enviado', 'propuesta', 'cotizacion enviada'],
			'accepted' => ['presupuesto aceptado'],
			'invoiced' => ['proyecto facturado', 'facturado'],
			'paid' => ['factura pagada', 'pagada'],
		];
		foreach (self::stageRows() as $row) {
			$folded = self::fold((string) ($row['label'] ?? ''));
			foreach ($hints[$role] ?? [] as $hint) {
				if (str_contains($folded, $hint)) {
					return (string) $row['slug'];
				}
			}
		}
		return null;
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
		foreach (self::stageRows() as $row) {
			if ((string) ($row['slug'] ?? '') === $slug) {
				$role = trim((string) ($row['role'] ?? ''));
				if ($role !== '') {
					return $role;
				}
				break;
			}
		}
		$map = [
			'propuesta' => 'sent',
			'presupuesto-aceptado' => 'accepted',
			'proyecto-facturado' => 'invoiced',
			'factura-pagada' => 'paid',
		];
		return $map[$slug] ?? '';
	}

	/** @return list<array<string,mixed>> */
	private static function stageRows(): array
	{
		if (self::$stageRowsCache === null) {
			try {
				$fetched = self::pdo()->query(
					'SELECT slug, label, position, role, kind FROM board_stages ORDER BY position ASC, id ASC'
				)->fetchAll();
				self::$stageRowsCache = $fetched ?: [];
			} catch (\Throwable) {
				self::$stageRowsCache = [];
			}
		}
		return self::$stageRowsCache;
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
