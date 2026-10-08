<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Database;
use PDO;

final class VisitReport
{
	public static function summary(int $days): array
	{
		$pdo = self::pdo();
		$from = self::from($days);
		$views = self::count($pdo, 'SELECT COUNT(*) FROM site_visits WHERE visited_at >= ?', [$from]);
		$people = self::count($pdo, 'SELECT COUNT(DISTINCT visitor_id) FROM site_visits WHERE visited_at >= ?', [$from]);
		$sessions = self::count($pdo, 'SELECT COUNT(DISTINCT session_id) FROM site_visits WHERE visited_at >= ?', [$from]);
		$bounces = self::count(
			$pdo,
			'SELECT COUNT(*) FROM (SELECT session_id FROM site_visits WHERE visited_at >= ? GROUP BY session_id HAVING COUNT(*) = 1)',
			[$from]
		);
		return [
			'views' => $views,
			'people' => $people,
			'sessions' => $sessions,
			'bounce' => $sessions > 0 ? (int) round($bounces * 100 / $sessions) : 0,
			'pages' => $sessions > 0 ? round($views / $sessions, 1) : 0,
			'days' => self::byDay($pdo, $from, $days),
			'hours' => self::byHour($pdo, $from),
			'pages_top' => self::grouped($pdo, 'path', $from),
			'sources' => self::grouped($pdo, 'source', $from),
			'campaigns' => self::grouped($pdo, 'utm_campaign', $from, "utm_campaign <> ''"),
			'devices' => self::grouped($pdo, 'device', $from),
			'browsers' => self::grouped($pdo, 'browser', $from),
			'systems' => self::grouped($pdo, 'os', $from),
			'languages' => self::grouped($pdo, 'language', $from, "language <> ''"),
			'countries' => self::grouped($pdo, 'country', $from, "country <> ''"),
			'landings' => self::landings($pdo, $from),
			'recent' => self::recent($pdo, $from),
		];
	}

	private static function pdo(): PDO
	{
		$pdo = Database::pdo();
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS site_visits (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				visited_at TEXT NOT NULL,
				visitor_id TEXT NOT NULL,
				session_id TEXT NOT NULL,
				path TEXT NOT NULL,
				title TEXT NOT NULL DEFAULT \'\',
				referrer TEXT NOT NULL DEFAULT \'\',
				source TEXT NOT NULL DEFAULT \'\',
				utm_source TEXT NOT NULL DEFAULT \'\',
				utm_medium TEXT NOT NULL DEFAULT \'\',
				utm_campaign TEXT NOT NULL DEFAULT \'\',
				utm_term TEXT NOT NULL DEFAULT \'\',
				utm_content TEXT NOT NULL DEFAULT \'\',
				gclid INTEGER NOT NULL DEFAULT 0,
				fbclid INTEGER NOT NULL DEFAULT 0,
				device TEXT NOT NULL DEFAULT \'\',
				browser TEXT NOT NULL DEFAULT \'\',
				os TEXT NOT NULL DEFAULT \'\',
				language TEXT NOT NULL DEFAULT \'\',
				screen TEXT NOT NULL DEFAULT \'\',
				country TEXT NOT NULL DEFAULT \'\',
				ip TEXT NOT NULL DEFAULT \'\'
			)'
		);
		return $pdo;
	}

	private static function from(int $days): string
	{
		if ($days <= 1) {
			return date('Y-m-d 00:00:00');
		}
		return date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
	}

	private static function count(PDO $pdo, string $sql, array $params): int
	{
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		return (int) $stmt->fetchColumn();
	}

	/** @return list<array{label:string,count:int}> */
	private static function byDay(PDO $pdo, string $from, int $days): array
	{
		$stmt = $pdo->prepare("SELECT substr(visited_at, 1, 10) AS day, COUNT(*) AS total FROM site_visits WHERE visited_at >= ? GROUP BY day");
		$stmt->execute([$from]);
		$map = [];
		foreach ($stmt->fetchAll() as $row) {
			$map[(string) $row['day']] = (int) $row['total'];
		}
		$out = [];
		for ($i = $days - 1; $i >= 0; $i--) {
			$day = date('Y-m-d', strtotime('-' . $i . ' days'));
			$out[] = ['label' => date('d/m', strtotime($day)), 'count' => $map[$day] ?? 0];
		}
		return $out;
	}

	/** @return list<array{label:string,count:int}> */
	private static function byHour(PDO $pdo, string $from): array
	{
		$stmt = $pdo->prepare("SELECT substr(visited_at, 12, 2) AS hour, COUNT(*) AS total FROM site_visits WHERE visited_at >= ? GROUP BY hour");
		$stmt->execute([$from]);
		$map = [];
		foreach ($stmt->fetchAll() as $row) {
			$map[(string) $row['hour']] = (int) $row['total'];
		}
		$out = [];
		for ($hour = 0; $hour < 24; $hour++) {
			$key = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
			$out[] = ['label' => $key, 'count' => $map[$key] ?? 0];
		}
		return $out;
	}

	/** @return list<array{label:string,count:int}> */
	private static function grouped(PDO $pdo, string $column, string $from, string $extra = '1=1'): array
	{
		$allowed = ['path', 'source', 'utm_campaign', 'device', 'browser', 'os', 'language', 'country'];
		if (!in_array($column, $allowed, true)) {
			return [];
		}
		$stmt = $pdo->prepare("SELECT {$column} AS label, COUNT(*) AS total FROM site_visits WHERE visited_at >= ? AND {$extra} GROUP BY {$column} ORDER BY total DESC LIMIT 12");
		$stmt->execute([$from]);
		$out = [];
		foreach ($stmt->fetchAll() as $row) {
			$label = trim((string) $row['label']);
			if ($label === '') {
				continue;
			}
			$out[] = ['label' => $label, 'count' => (int) $row['total']];
		}
		return $out;
	}

	/** @return list<array{label:string,count:int}> */
	private static function landings(PDO $pdo, string $from): array
	{
		$stmt = $pdo->prepare(
			'SELECT path AS label, COUNT(*) AS total
			 FROM site_visits
			 WHERE visited_at >= ?
			 AND id IN (SELECT MIN(id) FROM site_visits WHERE visited_at >= ? GROUP BY session_id)
			 GROUP BY path
			 ORDER BY total DESC
			 LIMIT 10'
		);
		$stmt->execute([$from, $from]);
		$out = [];
		foreach ($stmt->fetchAll() as $row) {
			$out[] = ['label' => (string) $row['label'], 'count' => (int) $row['total']];
		}
		return $out;
	}

	/** @return list<array<string, string>> */
	private static function recent(PDO $pdo, string $from): array
	{
		$stmt = $pdo->prepare(
			'SELECT visited_at, path, title, source, referrer, utm_campaign, utm_medium, device, browser, os, language, screen, country, ip
			 FROM site_visits
			 WHERE visited_at >= ?
			 ORDER BY id DESC
			 LIMIT 40'
		);
		$stmt->execute([$from]);
		return $stmt->fetchAll() ?: [];
	}
}
