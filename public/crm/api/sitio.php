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
	$stmt = $pdo->prepare('SELECT title, description, blocks, active FROM site_pages WHERE path = ?');
	$stmt->execute([$path]);
	$row = $stmt->fetch();
	if (!$row || (int) $row['active'] !== 1) {
		echo json_encode(['ok' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
		exit;
	}
	$blocks = json_decode((string) $row['blocks'], true);
	echo json_encode([
		'ok' => true,
		'active' => true,
		'title' => (string) $row['title'],
		'description' => (string) $row['description'],
		'blocks' => is_array($blocks) ? $blocks : [],
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'active' => false], JSON_UNESCAPED_UNICODE);
}
