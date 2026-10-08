<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false]);
	exit;
}

$purpose = strtolower((string) ($_SERVER['HTTP_PURPOSE'] ?? $_SERVER['HTTP_X_PURPOSE'] ?? ''));
if ($purpose === 'prefetch' || $purpose === 'preview') {
	http_response_code(204);
	exit;
}

$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|spider|crawler|slurp|preview|lighthouse|pagespeed|headless|facebookexternalhit|petalbot|ahrefs|semrush|dotbot|bingbot|googlebot|yandex|duckduckbot|curl|wget|python-requests/i', $ua)) {
	http_response_code(204);
	exit;
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 8000) {
	http_response_code(204);
	exit;
}
$data = json_decode($raw, true);
if (!is_array($data)) {
	http_response_code(204);
	exit;
}

$path = (string) ($data['path'] ?? '/');
$path = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '', '/');
if ($path !== '/') {
	$path = rtrim($path, '/');
}
if ($path === '/crm' || str_starts_with($path, '/crm/')) {
	http_response_code(204);
	exit;
}
if (!preg_match('#^/[a-z0-9/_-]*$#i', $path)) {
	http_response_code(204);
	exit;
}

$visitor = token((string) ($data['visitor'] ?? ''));
$session = token((string) ($data['session'] ?? ''));
if ($visitor === '' || $session === '') {
	http_response_code(204);
	exit;
}

try {
	date_default_timezone_set('America/Santiago');
	$crmRoot = dirname(__DIR__);
	$dbPath = dirname($crmRoot) . '/crm-data/crm.sqlite';
	if (!is_file($dbPath)) {
		http_response_code(204);
		exit;
	}
	$pdo = new PDO('sqlite:' . $dbPath, null, null, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
	$pdo->exec('PRAGMA busy_timeout = 1500');
	ensureVisits($pdo);

	$since = date('Y-m-d H:i:s', time() - 25);
	$dup = $pdo->prepare('SELECT 1 FROM site_visits WHERE session_id = ? AND path = ? AND visited_at >= ? LIMIT 1');
	$dup->execute([$session, $path, $since]);
	if ($dup->fetch()) {
		http_response_code(204);
		exit;
	}

	$referrer = mb_substr(trim((string) ($data['referrer'] ?? '')), 0, 500);
	$utmSource = clip((string) ($data['utm_source'] ?? ''), 80);
	$utmMedium = clip((string) ($data['utm_medium'] ?? ''), 80);
	$utmCampaign = clip((string) ($data['utm_campaign'] ?? ''), 120);
	$utmTerm = clip((string) ($data['utm_term'] ?? ''), 120);
	$utmContent = clip((string) ($data['utm_content'] ?? ''), 120);
	$gclid = !empty($data['gclid']) ? 1 : 0;
	$fbclid = !empty($data['fbclid']) ? 1 : 0;
	$agent = clientAgent($ua);
	$country = strtoupper(clip((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? $_SERVER['HTTP_X_COUNTRY_CODE'] ?? ''), 8));
	$ip = filter_var((string) ($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: '';

	$pdo->prepare(
		'INSERT INTO site_visits (
			visited_at, visitor_id, session_id, path, title, referrer, source,
			utm_source, utm_medium, utm_campaign, utm_term, utm_content,
			gclid, fbclid, device, browser, os, language, screen, country, ip
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
	)->execute([
		date('Y-m-d H:i:s'),
		$visitor,
		$session,
		$path,
		clip(strip_tags((string) ($data['title'] ?? '')), 180),
		$referrer,
		classify($referrer, $utmSource, $gclid, $fbclid),
		$utmSource,
		$utmMedium,
		$utmCampaign,
		$utmTerm,
		$utmContent,
		$gclid,
		$fbclid,
		$agent['device'],
		$agent['browser'],
		$agent['os'],
		clip((string) ($data['lang'] ?? ''), 16),
		clip((string) ($data['screen'] ?? ''), 20),
		$country,
		$ip,
	]);

	if (random_int(1, 40) === 1) {
		$pdo->prepare('DELETE FROM site_visits WHERE visited_at < ?')->execute([date('Y-m-d H:i:s', strtotime('-400 days'))]);
	}
} catch (Throwable $e) {
	// El sitio público no debe fallar si el registro no se puede escribir.
}

http_response_code(204);

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

function ensureVisits(PDO $pdo): void
{
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
	$pdo->exec('CREATE INDEX IF NOT EXISTS idx_site_visits_at ON site_visits(visited_at)');
	$pdo->exec('CREATE INDEX IF NOT EXISTS idx_site_visits_visitor ON site_visits(visitor_id, visited_at)');
}

function classify(string $referrer, string $utmSource, int $gclid, int $fbclid): string
{
	if ($gclid === 1) {
		return 'Google Ads';
	}
	if ($fbclid === 1) {
		return 'Facebook / Instagram';
	}
	$utm = strtolower($utmSource);
	if ($utm !== '') {
		if (str_contains($utm, 'google')) {
			return 'Google';
		}
		if (str_contains($utm, 'facebook') || str_contains($utm, 'instagram') || $utm === 'ig' || $utm === 'fb') {
			return 'Facebook / Instagram';
		}
		if (str_contains($utm, 'whatsapp')) {
			return 'WhatsApp';
		}
		return 'Campaña: ' . $utmSource;
	}
	$host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
	$host = preg_replace('/^www\./', '', $host) ?? $host;
	if ($host === '' || str_ends_with($host, 'mizo.cl')) {
		return 'Directo';
	}
	if (str_contains($host, 'google.')) {
		return 'Google';
	}
	if (str_contains($host, 'bing.') || str_contains($host, 'yahoo.')) {
		return 'Bing / Yahoo';
	}
	if (str_contains($host, 'facebook.') || str_contains($host, 'instagram.')) {
		return 'Facebook / Instagram';
	}
	if (str_contains($host, 'whatsapp') || $host === 'wa.me') {
		return 'WhatsApp';
	}
	if (str_contains($host, 'linkedin.')) {
		return 'LinkedIn';
	}
	if (str_contains($host, 'youtube.') || str_contains($host, 'youtu.be')) {
		return 'YouTube';
	}
	if (str_contains($host, 'tiktok.')) {
		return 'TikTok';
	}
	return $host;
}

/** @return array{device:string,browser:string,os:string} */
function clientAgent(string $ua): array
{
	$device = 'Escritorio';
	if (preg_match('/ipad|tablet|playbook|silk/i', $ua)) {
		$device = 'Tablet';
	} elseif (preg_match('/mobile|iphone|android/i', $ua)) {
		$device = 'Celular';
	}
	$browser = 'Otro';
	if (preg_match('/edg\//i', $ua)) {
		$browser = 'Edge';
	} elseif (preg_match('/chrome|crios/i', $ua) && !preg_match('/edg\//i', $ua)) {
		$browser = 'Chrome';
	} elseif (preg_match('/firefox|fxios/i', $ua)) {
		$browser = 'Firefox';
	} elseif (preg_match('/safari/i', $ua)) {
		$browser = 'Safari';
	}
	$os = 'Otro';
	if (preg_match('/windows/i', $ua)) {
		$os = 'Windows';
	} elseif (preg_match('/android/i', $ua)) {
		$os = 'Android';
	} elseif (preg_match('/iphone|ipad|ios/i', $ua)) {
		$os = 'iPhone / iPad';
	} elseif (preg_match('/mac os/i', $ua)) {
		$os = 'Mac';
	} elseif (preg_match('/linux/i', $ua)) {
		$os = 'Linux';
	}
	return ['device' => $device, 'browser' => $browser, 'os' => $os];
}
