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
			'SELECT stage, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS money FROM deals WHERE COALESCE(archived, 0) = 0 GROUP BY stage'
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
				(SELECT COUNT(*) FROM deals d WHERE d.owner_id = u.id AND d.stage IN ($openIn) AND COALESCE(d.archived, 0) = 0) AS open_deals,
				(SELECT COALESCE(SUM(d.amount), 0) FROM deals d WHERE d.owner_id = u.id AND d.stage IN ($openIn) AND COALESCE(d.archived, 0) = 0) AS open_amount,
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

	/** Actividad comercial, sin montos. @return array<string,mixed> */
	public static function desk(): array
	{
		$stages = [];
		$open = [];
		foreach (Stage::rows() as $row) {
			$stages[] = ['slug' => $row['slug'], 'label' => $row['label'], 'kind' => $row['kind']];
			if ($row['kind'] === 'open') {
				$open[$row['slug']] = true;
			}
		}
		$people = [];
		foreach (self::pdo()->query('SELECT id, name FROM users WHERE active = 1 ORDER BY name ASC') as $user) {
			$people[(int) $user['id']] = self::blankPerson((int) $user['id'], (string) $user['name'], $stages);
		}
		$bucket = static function (int $id) use (&$people, $stages): int {
			if ($id > 0 && isset($people[$id])) {
				return $id;
			}
			if (!isset($people[0])) {
				$people[0] = self::blankPerson(0, 'Sin asignar', $stages);
			}
			return 0;
		};

		$deals = self::pdo()->query(
			'SELECT d.stage, d.owner_id, d.updated_at, c.owner_id AS client_owner
			 FROM deals d JOIN clients c ON c.id = d.client_id
			 WHERE d.archived IS NULL OR d.archived = 0'
		)->fetchAll();
		$now = time();
		foreach ($deals as $deal) {
			$owner = (int) ($deal['owner_id'] ?? 0);
			if ($owner < 1) {
				$owner = (int) ($deal['client_owner'] ?? 0);
			}
			$owner = $bucket($owner);
			$slug = (string) $deal['stage'];
			if (!isset($people[$owner]['stages'][$slug])) {
				$people[$owner]['stages'][$slug] = 0;
			}
			$people[$owner]['stages'][$slug]++;
			if (!isset($open[$slug])) {
				continue;
			}
			$people[$owner]['prospects']++;
			$stamp = strtotime((string) $deal['updated_at']);
			if ($stamp) {
				$people[$owner]['idle_sum'] += max(0, (int) floor(($now - $stamp) / 86400));
				$people[$owner]['idle_n']++;
			}
		}

		$quotes = self::pdo()->query(
			"SELECT created_by, status, sent_at, responded_at FROM quotes WHERE status != 'borrador'"
		)->fetchAll();
		foreach ($quotes as $quote) {
			$owner = $bucket((int) ($quote['created_by'] ?? 0));
			$people[$owner]['quotes']++;
			$status = (string) $quote['status'];
			if ($status === 'aceptada') {
				$people[$owner]['accepted']++;
			} elseif ($status === 'rechazada') {
				$people[$owner]['rejected']++;
			} else {
				$people[$owner]['silent']++;
			}
			$sent = strtotime((string) ($quote['sent_at'] ?? ''));
			$answered = strtotime((string) ($quote['responded_at'] ?? ''));
			if ($sent && $answered && $answered >= $sent) {
				$people[$owner]['reply_sum'] += (int) floor(($answered - $sent) / 86400);
				$people[$owner]['reply_n']++;
			}
		}

		foreach (self::pdo()->query("SELECT user_id, COUNT(*) AS n FROM mail_messages WHERE folder = 'sent' GROUP BY user_id") as $row) {
			$owner = $bucket((int) $row['user_id']);
			$people[$owner]['mails'] += (int) $row['n'];
		}
		foreach (self::pdo()->query("SELECT user_id, COUNT(*) AS n FROM activities WHERE type = 'llamada' OR (type = 'stage' AND message LIKE '%Llamada realizada%') GROUP BY user_id") as $row) {
			$owner = $bucket((int) $row['user_id']);
			$people[$owner]['calls'] += (int) $row['n'];
		}
		foreach (self::pdo()->query("SELECT user_id, COUNT(*) AS n FROM activities WHERE type IN ('comentario','nota','note','recordatorio') GROUP BY user_id") as $row) {
			$owner = $bucket((int) $row['user_id']);
			$people[$owner]['notes'] += (int) $row['n'];
		}

		$list = [];
		$totals = ['prospects' => 0, 'mails' => 0, 'quotes' => 0, 'accepted' => 0, 'rejected' => 0, 'silent' => 0, 'reply_sum' => 0, 'reply_n' => 0, 'idle_sum' => 0, 'idle_n' => 0];
		foreach ($people as $person) {
			$busy = $person['prospects'] + $person['quotes'] + $person['mails'] + $person['calls'] + $person['notes'] + array_sum($person['stages']);
			if ($person['id'] === 0 && $busy === 0) {
				continue;
			}
			$person['response_rate'] = $person['quotes'] > 0 ? (int) round(100 * ($person['accepted'] + $person['rejected']) / $person['quotes']) : null;
			$person['accept_rate'] = $person['quotes'] > 0 ? (int) round(100 * $person['accepted'] / $person['quotes']) : null;
			$person['avg_reply_days'] = $person['reply_n'] > 0 ? (int) round($person['reply_sum'] / $person['reply_n']) : null;
			$person['avg_idle_days'] = $person['idle_n'] > 0 ? (int) round($person['idle_sum'] / $person['idle_n']) : null;
			foreach ($totals as $key => $value) {
				$totals[$key] += $person[$key];
			}
			unset($person['reply_sum'], $person['reply_n'], $person['idle_sum'], $person['idle_n']);
			$list[] = $person;
		}

		return [
			'stages' => $stages,
			'people' => $list,
			'prospects' => $totals['prospects'],
			'mails' => $totals['mails'],
			'quotes' => $totals['quotes'],
			'accepted' => $totals['accepted'],
			'rejected' => $totals['rejected'],
			'silent' => $totals['silent'],
			'response_rate' => $totals['quotes'] > 0 ? (int) round(100 * ($totals['accepted'] + $totals['rejected']) / $totals['quotes']) : null,
			'accept_rate' => $totals['quotes'] > 0 ? (int) round(100 * $totals['accepted'] / $totals['quotes']) : null,
			'avg_reply_days' => $totals['reply_n'] > 0 ? (int) round($totals['reply_sum'] / $totals['reply_n']) : null,
			'avg_idle_days' => $totals['idle_n'] > 0 ? (int) round($totals['idle_sum'] / $totals['idle_n']) : null,
		];
	}

	/** @param list<array{slug:string,label:string,kind:string}> $stages */
	private static function blankPerson(int $id, string $name, array $stages): array
	{
		$counts = [];
		foreach ($stages as $stage) {
			$counts[$stage['slug']] = 0;
		}
		return [
			'id' => $id,
			'name' => $name,
			'prospects' => 0,
			'mails' => 0,
			'calls' => 0,
			'notes' => 0,
			'quotes' => 0,
			'accepted' => 0,
			'rejected' => 0,
			'silent' => 0,
			'reply_sum' => 0,
			'reply_n' => 0,
			'idle_sum' => 0,
			'idle_n' => 0,
			'stages' => $counts,
		];
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
