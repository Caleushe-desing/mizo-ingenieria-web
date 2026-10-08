<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
	http_response_code(405);
	echo json_encode(['ok' => false]);
	exit;
}

$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|spider|crawler|slurp|preview|lighthouse|pagespeed|headless|facebookexternalhit|petalbot|ahrefs|semrush|dotbot|bingbot|googlebot|yandex|duckduckbot|curl|wget|python-requests/i', $ua)) {
	http_response_code(204);
	exit;
}

try {
	date_default_timezone_set('America/Santiago');
	$data = $method === 'POST' ? body() : $_GET;
	$visitor = token((string) ($data['visitor'] ?? ''));
	if ($visitor === '') {
		http_response_code(204);
		exit;
	}
	$pdo = db();
	$action = (string) ($data['action'] ?? 'presencia');
	if ($action === 'mensajes') {
		echo json_encode(messages($pdo, $visitor), JSON_UNESCAPED_UNICODE);
		exit;
	}
	if ($action === 'mensaje') {
		echo json_encode(incoming($pdo, $visitor, $data), JSON_UNESCAPED_UNICODE);
		exit;
	}
	presence($pdo, $visitor, $data, $ua);
	http_response_code(204);
} catch (Throwable $e) {
	http_response_code(204);
}

function body(): array
{
	$raw = file_get_contents('php://input');
	if (!is_string($raw) || strlen($raw) > 8000) {
		return [];
	}
	$data = json_decode($raw, true);
	return is_array($data) ? $data : [];
}

function db(): PDO
{
	$crmRoot = dirname(__DIR__);
	$path = dirname($crmRoot) . '/crm-data/crm.sqlite';
	if (!is_file($path)) {
		throw new RuntimeException('sin base');
	}
	$pdo = new PDO('sqlite:' . $path, null, null, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
	$pdo->exec('PRAGMA busy_timeout = 1500');
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS site_presence (
			visitor_id TEXT PRIMARY KEY,
			session_id TEXT NOT NULL DEFAULT \'\',
			path TEXT NOT NULL DEFAULT \'/\',
			title TEXT NOT NULL DEFAULT \'\',
			referrer TEXT NOT NULL DEFAULT \'\',
			device TEXT NOT NULL DEFAULT \'\',
			ip TEXT NOT NULL DEFAULT \'\',
			last_seen TEXT NOT NULL
		)'
	);
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS site_chats (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			visitor_id TEXT NOT NULL,
			visitor_name TEXT NOT NULL DEFAULT \'\',
			page TEXT NOT NULL DEFAULT \'/\',
			status TEXT NOT NULL DEFAULT \'abierto\',
			created_at TEXT NOT NULL,
			updated_at TEXT NOT NULL
		)'
	);
	$pdo->exec('CREATE INDEX IF NOT EXISTS idx_site_chats_visitor ON site_chats(visitor_id, updated_at)');
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS site_chat_messages (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			chat_id INTEGER NOT NULL,
			author TEXT NOT NULL,
			body TEXT NOT NULL,
			created_at TEXT NOT NULL,
			seen INTEGER NOT NULL DEFAULT 0
		)'
	);
	$pdo->exec('CREATE INDEX IF NOT EXISTS idx_site_chat_messages_chat ON site_chat_messages(chat_id, id)');
	return $pdo;
}

function presence(PDO $pdo, string $visitor, array $data, string $ua): void
{
	$path = pagePath((string) ($data['path'] ?? '/'));
	$host = strtolower((string) parse_url((string) ($data['referrer'] ?? ''), PHP_URL_HOST));
	$host = preg_replace('/^www\./', '', $host) ?? '';
	if ($host !== '' && str_ends_with($host, 'mizo.cl')) {
		$host = '';
	}
	$device = preg_match('/ipad|tablet/i', $ua) ? 'Tablet' : (preg_match('/mobile|iphone|android/i', $ua) ? 'Celular' : 'Escritorio');
	$ip = filter_var((string) ($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: '';
	$now = date('Y-m-d H:i:s');
	$pdo->prepare(
		'INSERT INTO site_presence (visitor_id, session_id, path, title, referrer, device, ip, last_seen)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
		 ON CONFLICT(visitor_id) DO UPDATE SET
			session_id = excluded.session_id,
			path = excluded.path,
			title = excluded.title,
			referrer = excluded.referrer,
			device = excluded.device,
			ip = excluded.ip,
			last_seen = excluded.last_seen'
	)->execute([
		$visitor,
		token((string) ($data['session'] ?? '')),
		$path,
		clip(strip_tags((string) ($data['title'] ?? '')), 140),
		clip($host, 120),
		$device,
		$ip,
		$now,
	]);
	if (random_int(1, 30) === 1) {
		$pdo->prepare('DELETE FROM site_presence WHERE last_seen < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
	}
}

function incoming(PDO $pdo, string $visitor, array $data): array
{
	$body = clip(strip_tags((string) ($data['body'] ?? '')), 2000);
	if ($body === '') {
		return ['ok' => false, 'error' => 'Escribe un mensaje.'];
	}
	$hour = date('Y-m-d H:i:s', time() - 3600);
	$count = $pdo->prepare(
		"SELECT COUNT(*) FROM site_chat_messages m
		 JOIN site_chats c ON c.id = m.chat_id
		 WHERE c.visitor_id = ? AND m.author = 'visitor' AND m.created_at >= ?"
	);
	$count->execute([$visitor, $hour]);
	if ((int) $count->fetchColumn() >= 30) {
		return ['ok' => false, 'error' => 'Espera un momento antes de enviar otro mensaje.'];
	}
	$now = date('Y-m-d H:i:s');
	$page = pagePath((string) ($data['path'] ?? '/'));
	$name = clip(strip_tags((string) ($data['name'] ?? '')), 80);
	if ($name === '') {
		$name = 'Visitante';
	}
	$find = $pdo->prepare('SELECT id FROM site_chats WHERE visitor_id = ? ORDER BY id DESC LIMIT 1');
	$find->execute([$visitor]);
	$chatId = (int) ($find->fetchColumn() ?: 0);
	if ($chatId < 1) {
		$pdo->prepare('INSERT INTO site_chats (visitor_id, visitor_name, page, status, created_at, updated_at) VALUES (?, ?, ?, \'abierto\', ?, ?)')
			->execute([$visitor, $name, $page, $now, $now]);
		$chatId = (int) $pdo->lastInsertId();
	} else {
		$pdo->prepare('UPDATE site_chats SET visitor_name = ?, page = ?, updated_at = ?, status = \'abierto\' WHERE id = ?')
			->execute([$name, $page, $now, $chatId]);
	}
	$pdo->prepare("INSERT INTO site_chat_messages (chat_id, author, body, created_at, seen) VALUES (?, 'visitor', ?, ?, 0)")
		->execute([$chatId, $body, $now]);
	return messages($pdo, $visitor);
}

function messages(PDO $pdo, string $visitor): array
{
	$find = $pdo->prepare('SELECT id, visitor_name FROM site_chats WHERE visitor_id = ? ORDER BY id DESC LIMIT 1');
	$find->execute([$visitor]);
	$chat = $find->fetch();
	if (!$chat) {
		return ['ok' => true, 'messages' => []];
	}
	$stmt = $pdo->prepare('SELECT author, body, created_at FROM site_chat_messages WHERE chat_id = ? ORDER BY id ASC LIMIT 200');
	$stmt->execute([(int) $chat['id']]);
	return ['ok' => true, 'name' => (string) $chat['visitor_name'], 'messages' => $stmt->fetchAll() ?: []];
}

function token(string $value): string
{
	$value = trim($value);
	return preg_match('/^[a-zA-Z0-9-]{8,64}$/', $value) ? $value : '';
}

function clip(string $value, int $max): string
{
	$value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
	return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function pagePath(string $path): string
{
	$path = '/' . trim((string) (parse_url($path, PHP_URL_PATH) ?: ''), '/');
	if ($path !== '/') {
		$path = rtrim($path, '/');
	}
	return preg_match('#^/[a-z0-9/_-]*$#i', $path) ? $path : '/';
}
