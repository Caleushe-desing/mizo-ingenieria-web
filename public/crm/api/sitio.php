<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=15');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'active' => false], JSON_UNESCAPED_UNICODE);
	exit;
}

$path = '/' . trim((string) ($_GET['path'] ?? '/'), '/');
if ($path !== '/') {
	$path = rtrim($path, '/');
}

try {
	$crmRoot = dirname(__DIR__);
	$dbPath = dirname($crmRoot) . '/crm-data/crm.sqlite';
	if (!is_file($dbPath)) {
		echo json_encode(['ok' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
		exit;
	}
	$pdo = new PDO('sqlite:' . $dbPath, null, null, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
	$pdo->exec('PRAGMA query_only = ON');
	$exists = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'site_pages'")->fetch();
	if (!$exists) {
		echo json_encode(['ok' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
		exit;
	}
	$cols = array_column($pdo->query('PRAGMA table_info(site_pages)')->fetchAll(), 'name');
	$hasHtml = in_array('html', $cols, true);
	$chrome = null;
	if ($hasHtml) {
		$chromeStmt = $pdo->prepare('SELECT html, active FROM site_pages WHERE path = ?');
		$chromeStmt->execute(['#chrome']);
		$chromeRow = $chromeStmt->fetch();
		if ($chromeRow && (int) $chromeRow['active'] === 1) {
			$decoded = json_decode((string) $chromeRow['html'], true);
			if (is_array($decoded) && !empty($decoded['header']) && !empty($decoded['footer'])) {
				$chrome = [
					'header' => (string) $decoded['header'],
					'footer' => (string) $decoded['footer'],
				];
			}
		}
	}
	$stmt = $pdo->prepare($hasHtml
		? 'SELECT title, description, blocks, html, active FROM site_pages WHERE path = ?'
		: 'SELECT title, description, blocks, active FROM site_pages WHERE path = ?');
	$stmt->execute([$path]);
	$row = $stmt->fetch();
	$active = $row && (int) $row['active'] === 1;
	$html = $active && $hasHtml ? trim((string) ($row['html'] ?? '')) : '';
	$blocks = [];
	if ($active && $html === '') {
		$decodedBlocks = json_decode((string) ($row['blocks'] ?? '[]'), true);
		$blocks = is_array($decodedBlocks) ? $decodedBlocks : [];
	}
	if (!$active && $chrome === null) {
		echo json_encode(['ok' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
		exit;
	}
	if ($active && $html === '' && $blocks === [] && $chrome === null) {
		echo json_encode(['ok' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
		exit;
	}
	echo json_encode([
		'ok' => true,
		'active' => $active && ($html !== '' || $blocks !== []),
		'mode' => $html !== '' ? 'html' : 'blocks',
		'title' => $active ? (string) ($row['title'] ?? '') : '',
		'description' => $active ? (string) ($row['description'] ?? '') : '',
		'html' => $html,
		'blocks' => $blocks,
		'chrome' => $chrome,
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'active' => false], JSON_UNESCAPED_UNICODE);
}
