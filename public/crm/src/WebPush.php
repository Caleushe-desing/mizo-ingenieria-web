<?php
declare(strict_types=1);

namespace MizoCrm;

use PDO;

/**
 * Web Push (VAPID ES256 + RFC 8291 aes128gcm) sin dependencias externas.
 * Las suscripciones viven en SQLite, ligadas al usuario del CRM.
 */
final class WebPush
{
	private const SUBJECT = 'mailto:ventas@mizo.cl';

	public static function publicKey(): string
	{
		return self::keys()['public'];
	}

	/** @param array<string, mixed> $subscription */
	public static function save(int $userId, array $subscription): void
	{
		$endpoint = trim((string) ($subscription['endpoint'] ?? ''));
		$keys = $subscription['keys'] ?? null;
		if (!is_array($keys)) {
			return;
		}
		$p256dh = trim((string) ($keys['p256dh'] ?? ''));
		$auth = trim((string) ($keys['auth'] ?? ''));
		if ($endpoint === '' || $p256dh === '' || $auth === '' || !str_starts_with($endpoint, 'https://')) {
			return;
		}
		$now = date('c');
		$pdo = Database::pdo();
		$stmt = $pdo->prepare(
			'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, created_at, updated_at)
			 VALUES (?, ?, ?, ?, ?, ?)
			 ON CONFLICT(endpoint) DO UPDATE SET
			 	user_id = excluded.user_id,
			 	p256dh = excluded.p256dh,
			 	auth = excluded.auth,
			 	updated_at = excluded.updated_at'
		);
		$stmt->execute([$userId, $endpoint, $p256dh, $auth, $now, $now]);
	}

	/** Avisa a administradores y ejecutivos activos. */
	public static function notifyStaff(string $title, string $body, string $url, string $tag): void
	{
		try {
			if (!Database::ready()) {
				return;
			}
			$ids = Database::pdo()->query(
				"SELECT id FROM users WHERE active = 1 AND role IN ('admin', 'vendedor')"
			)->fetchAll(PDO::FETCH_COLUMN);
			$users = [];
			foreach ($ids as $id) {
				$users[] = (int) $id;
			}
			self::notifyUsers($users, $title, $body, $url, $tag);
		} catch (\Throwable $e) {
		}
	}

	/** @param list<int> $userIds */
	public static function notifyUsers(array $userIds, string $title, string $body, string $url, string $tag): void
	{
		try {
			$userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
			if ($userIds === [] || !Database::ready()) {
				return;
			}
			$marks = implode(',', array_fill(0, count($userIds), '?'));
			$stmt = Database::pdo()->prepare(
				"SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id IN ($marks)"
			);
			$stmt->execute($userIds);
			$rows = $stmt->fetchAll();
			if (!$rows) {
				return;
			}
			$payload = json_encode([
				'title' => $title,
				'body' => mb_substr($body, 0, 180),
				'url' => $url,
				'tag' => $tag,
			], JSON_UNESCAPED_UNICODE);
			if (!is_string($payload)) {
				return;
			}
			$keys = self::keys();
			foreach ($rows as $row) {
				self::deliver($keys, $row, $payload, $tag);
			}
		} catch (\Throwable $e) {
		}
	}

	/** @param array{private:string,public:string} $keys @param array<string, mixed> $row */
	private static function deliver(array $keys, array $row, string $payload, string $tag): void
	{
		$endpoint = (string) $row['endpoint'];
		$parts = parse_url($endpoint);
		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
			return;
		}
		$body = self::encrypt($payload, (string) $row['p256dh'], (string) $row['auth']);
		if ($body === null) {
			return;
		}
		$jwt = self::jwt($keys['private'], $parts['scheme'] . '://' . $parts['host']);
		if ($jwt === null) {
			return;
		}
		$topic = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $tag) ?: 'mizo', 0, 32);
		$headers = [
			'TTL: 86400',
			'Urgency: high',
			'Topic: ' . $topic,
			'Content-Type: application/octet-stream',
			'Content-Encoding: aes128gcm',
			'Content-Length: ' . strlen($body),
			'Authorization: vapid t=' . $jwt . ', k=' . $keys['public'],
		];
		$code = self::post($endpoint, $headers, $body);
		if ($code === 404 || $code === 410) {
			$drop = Database::pdo()->prepare('DELETE FROM push_subscriptions WHERE id = ?');
			$drop->execute([(int) $row['id']]);
		}
	}

	/** @param list<string> $headers */
	private static function post(string $endpoint, array $headers, string $body): int
	{
		if (function_exists('curl_init')) {
			$ch = curl_init($endpoint);
			if ($ch === false) {
				return 0;
			}
			curl_setopt_array($ch, [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 8,
				CURLOPT_HTTPHEADER => $headers,
			]);
			curl_exec($ch);
			$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			return $code;
		}
		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $body,
				'timeout' => 8,
				'ignore_errors' => true,
			],
		]);
		@file_get_contents($endpoint, false, $context);
		$status = $http_response_header[0] ?? '';
		if (preg_match('/\s(\d{3})\s/', $status, $match)) {
			return (int) $match[1];
		}
		return 0;
	}

	private static function encrypt(string $payload, string $p256dh, string $auth): ?string
	{
		$uaPublic = self::b64urlDecode($p256dh);
		$authSecret = self::b64urlDecode($auth);
		if ($uaPublic === null || $authSecret === null || strlen($uaPublic) !== 65 || strlen($authSecret) !== 16) {
			return null;
		}
		$local = openssl_pkey_new([
			'curve_name' => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		]);
		if ($local === false) {
			return null;
		}
		$details = openssl_pkey_get_details($local);
		if (!is_array($details) || empty($details['ec']['x']) || empty($details['ec']['y'])) {
			return null;
		}
		$asPublic = "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
		$shared = openssl_pkey_derive(self::publicPem($uaPublic), $local, 32);
		if (!is_string($shared) || $shared === '') {
			return null;
		}
		$ikm = self::hkdfExpand(
			self::hkdfExtract($authSecret, $shared),
			"WebPush: info\x00" . $uaPublic . $asPublic,
			32
		);
		$salt = random_bytes(16);
		$prk = self::hkdfExtract($salt, $ikm);
		$cek = self::hkdfExpand($prk, "Content-Encoding: aes128gcm\x00", 16);
		$nonce = self::hkdfExpand($prk, "Content-Encoding: nonce\x00", 12);
		$tag = '';
		$cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
		if (!is_string($cipher) || strlen($tag) !== 16) {
			return null;
		}
		return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
	}

	private static function jwt(string $privatePem, string $audience): ?string
	{
		$header = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
		$claims = self::b64url(json_encode([
			'aud' => $audience,
			'exp' => time() + 12 * 3600,
			'sub' => self::SUBJECT,
		], JSON_THROW_ON_ERROR));
		$input = $header . '.' . $claims;
		$der = '';
		if (!openssl_sign($input, $der, $privatePem, OPENSSL_ALGO_SHA256)) {
			return null;
		}
		$raw = self::derToRaw($der);
		if ($raw === null) {
			return null;
		}
		return $input . '.' . self::b64url($raw);
	}

	/** @return array{private:string,public:string} */
	private static function keys(): array
	{
		$path = dirname(__DIR__, 2) . '/crm-data/vapid.json';
		if (is_file($path)) {
			$stored = json_decode((string) file_get_contents($path), true);
			if (is_array($stored) && !empty($stored['private']) && !empty($stored['public'])) {
				return ['private' => (string) $stored['private'], 'public' => (string) $stored['public']];
			}
		}
		$key = openssl_pkey_new([
			'curve_name' => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		]);
		if ($key === false) {
			throw new \RuntimeException('No se pudo crear la clave VAPID.');
		}
		$pem = '';
		if (!openssl_pkey_export($key, $pem)) {
			throw new \RuntimeException('No se pudo exportar la clave VAPID.');
		}
		$details = openssl_pkey_get_details($key);
		if (!is_array($details) || empty($details['ec']['x']) || empty($details['ec']['y'])) {
			throw new \RuntimeException('Clave VAPID incompleta.');
		}
		$public = self::b64url(
			"\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)
		);
		$dir = dirname($path);
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new \RuntimeException('No existe la carpeta de datos del CRM.');
		}
		file_put_contents($path, json_encode(['private' => $pem, 'public' => $public], JSON_UNESCAPED_SLASHES));
		return ['private' => $pem, 'public' => $public];
	}

	private static function publicPem(string $uncompressed): string
	{
		$der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $uncompressed;
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
	}

	private static function hkdfExtract(string $salt, string $ikm): string
	{
		return hash_hmac('sha256', $ikm, $salt, true);
	}

	private static function hkdfExpand(string $prk, string $info, int $length): string
	{
		$block = '';
		$out = '';
		$counter = 1;
		while (strlen($out) < $length) {
			$block = hash_hmac('sha256', $block . $info . chr($counter), $prk, true);
			$out .= $block;
			$counter++;
		}
		return substr($out, 0, $length);
	}

	private static function derToRaw(string $der): ?string
	{
		$pos = 0;
		if (!isset($der[$pos]) || ord($der[$pos]) !== 0x30) {
			return null;
		}
		$pos++;
		$len = ord($der[$pos]);
		$pos++;
		if ($len & 0x80) {
			$pos += ($len & 0x7f);
		}
		$read = static function (string $der, int &$pos): ?string {
			if (!isset($der[$pos]) || ord($der[$pos]) !== 0x02) {
				return null;
			}
			$pos++;
			$n = ord($der[$pos]);
			$pos++;
			$int = substr($der, $pos, $n);
			$pos += $n;
			return $int;
		};
		$r = $read($der, $pos);
		$s = $read($der, $pos);
		if ($r === null || $s === null) {
			return null;
		}
		return str_pad(ltrim($r, "\x00"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\x00"), 32, "\0", STR_PAD_LEFT);
	}

	private static function b64url(string $raw): string
	{
		return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
	}

	private static function b64urlDecode(string $value): ?string
	{
		$value = strtr($value, '-_', '+/');
		$pad = strlen($value) % 4;
		if ($pad > 0) {
			$value .= str_repeat('=', 4 - $pad);
		}
		$decoded = base64_decode($value, true);
		return is_string($decoded) ? $decoded : null;
	}
}
