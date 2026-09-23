<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Config;
use MizoCrm\Record;

final class AdminReport extends Record
{
	protected static function table(): string
	{
		return 'activities';
	}

	public static function typeLabel(string $type): string
	{
		return match ($type) {
			'stage' => 'Movimiento',
			'mail_sent' => 'Correo enviado',
			'mail_received' => 'Correo recibido',
			'quote_created', 'quote_updated' => 'Cotización',
			'quote_sent' => 'Cotización enviada',
			'quote_accepted' => 'Cotización aceptada',
			'quote_rejected' => 'Cotización rechazada',
			'quote_viewed' => 'Cotización vista',
			'comentario', 'nota', 'note' => 'Nota',
			'recordatorio' => 'Recordatorio',
			'llamada' => 'Llamada',
			'lead' => 'Formulario web',
			'assigned' => 'Asignación',
			'won' => 'Cierre ganado',
			'lost' => 'Descartado',
			default => 'Actividad',
		};
	}

	/** @return list<array<string,mixed>> */
	public static function feed(int $ownerId, string $from, string $to, string $stage, int $limit = 200): array
	{
		$limit = max(1, min(300, $limit));
		$sql = 'SELECT a.id, a.type, a.message, a.created_at, a.client_id, a.deal_id, a.quote_id,
				u.name AS user_name, c.name AS client_name, o.name AS owner_name, d.title AS deal_title
			FROM activities a
			LEFT JOIN users u ON u.id = a.user_id
			LEFT JOIN clients c ON c.id = a.client_id
			LEFT JOIN users o ON o.id = c.owner_id
			LEFT JOIN deals d ON d.id = a.deal_id
			WHERE 1 = 1';
		$params = [];
		if ($ownerId > 0) {
			$sql .= ' AND c.owner_id = ?';
			$params[] = $ownerId;
		}
		if ($from !== '') {
			$sql .= ' AND a.created_at >= ?';
			$params[] = $from;
		}
		if ($to !== '') {
			$sql .= ' AND a.created_at < ?';
			$params[] = date('Y-m-d', strtotime($to . ' +1 day'));
		}
		if ($stage !== '') {
			$sql .= ' AND EXISTS (SELECT 1 FROM deals dx WHERE dx.client_id = a.client_id AND dx.stage = ?)';
			$params[] = $stage;
		}
		$sql .= ' ORDER BY a.id DESC LIMIT ' . $limit;
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	/** @return array{moves:list<array<string,mixed>>,mails:list<array<string,mixed>>,quotes:list<array<string,mixed>>,notes:list<array<string,mixed>>} */
	public static function client(int $clientId): array
	{
		$moves = self::pdo()->prepare(
			'SELECT a.id, a.message, a.created_at, u.name AS user_name, d.title AS deal_title
			 FROM activities a
			 LEFT JOIN users u ON u.id = a.user_id
			 LEFT JOIN deals d ON d.id = a.deal_id
			 WHERE a.client_id = ? AND a.type = \'stage\'
			 ORDER BY a.id DESC LIMIT 80'
		);
		$moves->execute([$clientId]);

		$mails = self::pdo()->prepare(
			'SELECT m.id, m.folder, m.subject, m.to_email, m.from_name, m.from_email, m.body_text, m.body_html, m.sent_at,
				u.name AS user_name
			 FROM mail_messages m
			 LEFT JOIN users u ON u.id = m.user_id
			 WHERE m.client_id = ?
			 ORDER BY m.sent_at DESC, m.id DESC LIMIT 40'
		);
		$mails->execute([$clientId]);

		$quotes = self::pdo()->prepare(
			'SELECT q.id, q.number, q.status, q.total, q.sent_at, q.sent_to, q.created_at,
				u.name AS author_name, d.title AS deal_title
			 FROM quotes q
			 LEFT JOIN users u ON u.id = q.created_by
			 LEFT JOIN deals d ON d.id = q.deal_id
			 WHERE q.client_id = ?
			 ORDER BY q.id DESC LIMIT 40'
		);
		$quotes->execute([$clientId]);

		$notes = self::pdo()->prepare(
			"SELECT a.id, a.type, a.message, a.created_at, u.name AS user_name, d.title AS deal_title
			 FROM activities a
			 LEFT JOIN users u ON u.id = a.user_id
			 LEFT JOIN deals d ON d.id = a.deal_id
			 WHERE a.client_id = ? AND a.type IN ('comentario','nota','note','recordatorio','llamada')
			 ORDER BY a.id DESC LIMIT 80"
		);
		$notes->execute([$clientId]);

		return [
			'moves' => $moves->fetchAll(),
			'mails' => $mails->fetchAll(),
			'quotes' => $quotes->fetchAll(),
			'notes' => $notes->fetchAll(),
		];
	}

	/** @return list<array{stage:string,label:string,count:int,amount:int}> */
	public static function pipeline(): array
	{
		$rows = self::pdo()->query(
			'SELECT stage, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS money FROM deals GROUP BY stage'
		)->fetchAll();
		$by = [];
		foreach ($rows as $row) {
			$by[(string) $row['stage']] = $row;
		}
		$kinds = [];
		foreach (Stage::rows() as $stage) {
			$kinds[$stage['slug']] = $stage['kind'];
		}
		$out = [];
		foreach (Config::stages() as $key => $label) {
			$out[] = [
				'stage' => $key,
				'label' => $label,
				'kind' => $kinds[$key] ?? 'open',
				'count' => (int) ($by[$key]['n'] ?? 0),
				'amount' => (int) ($by[$key]['money'] ?? 0),
			];
		}
		return $out;
	}

	/** @return list<array<string,mixed>> */
	public static function executives(): array
	{
		$wonIn = self::slugIn(Stage::slugs('won'));
		$lostIn = self::slugIn(Stage::slugs('lost'));
		$openIn = self::slugIn(Stage::slugs('open'));
		$rows = self::pdo()->query(
			"SELECT u.id, u.name,
				(SELECT COUNT(*) FROM quotes q WHERE q.created_by = u.id AND q.sent_at IS NOT NULL AND q.sent_at != '') AS quotes_sent,
				(SELECT COUNT(*) FROM deals d WHERE d.owner_id = u.id AND d.stage IN ($wonIn)) AS won,
				(SELECT COUNT(*) FROM deals d WHERE d.owner_id = u.id AND d.stage IN ($lostIn)) AS lost,
				(SELECT COUNT(*) FROM deals d WHERE d.owner_id = u.id AND d.stage IN ($openIn)) AS open_deals,
				(SELECT COALESCE(SUM(d.amount), 0) FROM deals d WHERE d.owner_id = u.id AND d.stage IN ($openIn)) AS open_amount,
				(SELECT COUNT(*) FROM mail_messages m WHERE m.user_id = u.id AND m.folder = 'sent') AS mails,
				(SELECT COUNT(*) FROM activities a WHERE a.user_id = u.id AND a.type = 'llamada') AS calls,
				(SELECT COUNT(*) FROM activities a WHERE a.user_id = u.id AND a.type = 'stage' AND a.message LIKE '%Llamada realizada%') AS call_moves,
				(SELECT COUNT(*) FROM activities a WHERE a.user_id = u.id AND a.type IN ('comentario','nota','note','recordatorio')) AS notes
			 FROM users u
			 WHERE u.active = 1
			 ORDER BY u.name ASC"
		)->fetchAll();
		foreach ($rows as &$row) {
			$won = (int) $row['won'];
			$lost = (int) $row['lost'];
			$closed = $won + $lost;
			$row['conversion'] = $closed > 0 ? (int) round(100 * $won / $closed) : null;
			$row['calls_total'] = (int) $row['calls'] + (int) $row['call_moves'];
		}
		unset($row);
		return $rows;
	}

	/** @return array{forms:int,web_clients:int,services:list<array{key:string,label:string,count:int}>,projects:list<array{key:string,label:string,count:int}>} */
	public static function web(): array
	{
		$forms = (int) self::pdo()->query("SELECT COUNT(*) FROM activities WHERE type = 'lead'")->fetchColumn();
		$webClients = (int) self::pdo()->query("SELECT COUNT(*) FROM clients WHERE source = 'web'")->fetchColumn();
		$messages = self::pdo()->query("SELECT message FROM activities WHERE type = 'lead'")->fetchAll();
		$counts = [];
		foreach (Config::services() as $key => $label) {
			$counts[$key] = 0;
		}
		$rules = [
			'sonido' => ['sonido'],
			'video' => ['videoproy', 'audiovisual'],
			'cctv' => ['cámara', 'camara', 'cctv'],
			'ti' => ['soporte ti', 'infraestructura'],
		];
		foreach ($messages as $row) {
			$msg = mb_strtolower((string) $row['message']);
			$hit = false;
			foreach ($rules as $key => $needles) {
				foreach ($needles as $needle) {
					if (mb_strpos($msg, $needle) !== false) {
						$counts[$key]++;
						$hit = true;
						break 2;
					}
				}
			}
			if (!$hit) {
				$counts['otro']++;
			}
		}
		$services = [];
		foreach (Config::services() as $key => $label) {
			$services[] = ['key' => $key, 'label' => $label, 'count' => $counts[$key]];
		}
		$projectRows = self::pdo()->query('SELECT service, COUNT(*) AS n FROM deals GROUP BY service')->fetchAll();
		$projectCounts = [];
		foreach ($projectRows as $row) {
			$projectCounts[(string) $row['service']] = (int) $row['n'];
		}
		$projects = [];
		foreach (Config::services() as $key => $label) {
			$projects[] = ['key' => $key, 'label' => $label, 'count' => $projectCounts[$key] ?? 0];
		}
		return [
			'forms' => $forms,
			'web_clients' => $webClients,
			'services' => $services,
			'projects' => $projects,
		];
	}

	/** @return array{labels:list<string>,quotes:list<int>,won:list<int>} */
	public static function trend(int $weeks = 12): array
	{
		$weeks = max(4, min(26, $weeks));
		$monday = strtotime('monday this week');
		$start = strtotime('-' . ($weeks - 1) . ' weeks', $monday ?: time());
		$from = date('Y-m-d', $start);
		$labels = [];
		$quotes = array_fill(0, $weeks, 0);
		$won = array_fill(0, $weeks, 0);
		for ($i = 0; $i < $weeks; $i++) {
			$labels[] = date('d/m', strtotime('+' . $i . ' weeks', $start));
		}
		$sent = self::pdo()->prepare('SELECT sent_at FROM quotes WHERE sent_at IS NOT NULL AND sent_at != \'\' AND sent_at >= ?');
		$sent->execute([$from]);
		foreach ($sent->fetchAll() as $row) {
			$idx = self::weekIndex($start, (string) $row['sent_at'], $weeks);
			if ($idx !== null) {
				$quotes[$idx]++;
			}
		}
		$wins = self::pdo()->prepare(
			"SELECT created_at FROM activities
			 WHERE created_at >= ?
			   AND (type = 'won' OR (type = 'stage' AND message LIKE '%Cierre ganado%'))"
		);
		$wins->execute([$from]);
		foreach ($wins->fetchAll() as $row) {
			$idx = self::weekIndex($start, (string) $row['created_at'], $weeks);
			if ($idx !== null) {
				$won[$idx]++;
			}
		}
		return ['labels' => $labels, 'quotes' => $quotes, 'won' => $won];
	}

	public static function preview(string $text, int $max = 280): string
	{
		$text = trim((string) (preg_replace('/\s+/u', ' ', $text) ?? ''));
		if (mb_strlen($text) <= $max) {
			return $text;
		}
		return mb_substr($text, 0, $max) . '…';
	}

	private static function slugIn(array $slugs): string
	{
		$safe = [];
		foreach ($slugs as $slug) {
			if (preg_match('/^[a-z0-9-]+$/', (string) $slug)) {
				$safe[] = "'" . $slug . "'";
			}
		}
		return $safe ? implode(',', $safe) : "''";
	}

	private static function weekIndex(int $start, string $stamp, int $weeks): ?int
	{
		$time = strtotime($stamp);
		if (!$time) {
			return null;
		}
		$idx = (int) floor(($time - $start) / (7 * 86400));
		if ($idx < 0 || $idx >= $weeks) {
			return null;
		}
		return $idx;
	}
}
