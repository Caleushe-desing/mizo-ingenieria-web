<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class User extends Record
{
	protected static function table(): string
	{
		return 'users';
	}

	public static function findByEmail(string $email): ?array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM users WHERE email = ?');
		$stmt->execute([mb_strtolower(trim($email))]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function create(string $name, string $email, string $password, string $role = 'vendedor'): int
	{
		$email = mb_strtolower(trim($email));
		return self::insert([
			'name' => $name,
			'email' => $email,
			'password_hash' => password_hash($password, PASSWORD_DEFAULT),
			'role' => $role,
			'active' => 1,
			'signature' => self::defaultSignature($name, $email),
			'created_at' => date('c'),
		]);
	}

	public static function team(): array
	{
		return self::pdo()->query('SELECT id, name, email, role, signature FROM users WHERE active = 1 ORDER BY name ASC')->fetchAll();
	}

	public static function defaultSignature(string $name, string $email): string
	{
		$lines = array_filter([
			trim($name),
			'Ejecutivo comercial',
			\MizoCrm\Config::COMPANY,
			\MizoCrm\Config::PHONE,
			mb_strtolower(trim($email)),
		], static fn($line) => $line !== '');
		return implode("\n", $lines);
	}

	public static function signatureText(?array $user): string
	{
		if (!$user) {
			return '';
		}
		$text = trim((string) ($user['signature'] ?? ''));
		if ($text !== '') {
			return $text;
		}
		return self::defaultSignature((string) ($user['name'] ?? ''), (string) ($user['email'] ?? ''));
	}

	public static function mailFromName(?array $user): string
	{
		$name = trim((string) ($user['name'] ?? ''));
		$company = \MizoCrm\Config::COMPANY;
		if ($name === '') {
			return $company;
		}
		if (str_starts_with(mb_strtolower($name), mb_strtolower($company))) {
			return $name;
		}
		return $company . ' - ' . $name;
	}

	public static function signatureHtml(?array $user): string
	{
		$text = self::signatureText($user);
		if ($text === '') {
			return '';
		}
		$inner = self::looksLikeHtml($text)
			? \MizoCrm\Mail\Mime::safeHtml($text)
			: nl2br(h($text), false);
		return self::wrapResponsiveSignature(self::responsiveSignature($inner));
	}

	public static function signatureEditorHtml(?array $user): string
	{
		$text = self::signatureText($user);
		if ($text === '') {
			return '';
		}
		if (self::looksLikeHtml($text)) {
			return \MizoCrm\Mail\Mime::safeHtml($text);
		}
		return nl2br(h($text), false);
	}

	public static function sanitizeSignature(string $html): string
	{
		$html = self::persistInlineImages($html);
		$html = \MizoCrm\Mail\Mime::safeHtml($html);
		$origin = \MizoCrm\App::origin();
		$html = self::responsiveSignature($html);
		$html = preg_replace_callback(
			'/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'])/i',
			static function (array $match) use ($origin): string {
				$src = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				if (str_starts_with($src, 'data:image/')) {
					return $match[0];
				}
				if (preg_match('#^https?://#i', $src)) {
					return $match[1] . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . $match[3];
				}
				if (str_starts_with($src, '/crm/uploads/firmas/')) {
					return $match[1] . htmlspecialchars($origin . $src, ENT_QUOTES, 'UTF-8') . $match[3];
				}
				return '';
			},
			$html
		) ?? $html;
		return trim($html);
	}

	public static function storeSignatureImage(string $binary, string $mime): ?string
	{
		$mime = strtolower(trim($mime));
		$ext = match ($mime) {
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			default => null,
		};
		if ($ext === null || strlen($binary) < 24 || strlen($binary) > 1_500_000) {
			return null;
		}
		if (@getimagesizefromstring($binary) === false) {
			return null;
		}
		$dir = dirname(__DIR__, 2) . '/uploads/firmas';
		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
			return null;
		}
		$name = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
		if (file_put_contents($dir . '/' . $name, $binary) === false) {
			return null;
		}
		return \MizoCrm\App::absolute('/uploads/firmas/' . $name);
	}

	private static function wrapResponsiveSignature(string $inner): string
	{
		return '<div style="max-width:100%;width:100%;box-sizing:border-box;overflow:hidden;margin:24px 0 0;font-size:13px;line-height:1.45;color:#444;word-break:break-word;">'
			. '<hr style="width:100%;max-width:100%;border:0;border-top:1px solid #e6e6e6;margin:0 0 16px;height:0;">'
			. $inner
			. '</div>';
	}

	/** Ajusta imágenes y tablas de la firma para que no se salgan en el celular. */
	private static function responsiveSignature(string $html): string
	{
		$html = preg_replace_callback('/<img\b([^>]*)>/i', static function (array $match): string {
			$attrs = preg_replace('/\s(?:width|height)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $match[1]) ?? $match[1];
			$attrs = self::setInlineStyle($attrs, [
				'max-width' => '100%',
				'height' => 'auto',
				'display' => 'block',
				'border' => '0',
			], ['width', 'height', 'max-height', 'min-width']);
			return '<img' . $attrs . '>';
		}, $html) ?? $html;

		$html = preg_replace_callback('/<table\b([^>]*)>/i', static function (array $match): string {
			$attrs = preg_replace('/\swidth\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $match[1]) ?? $match[1];
			$attrs = self::setInlineStyle($attrs . ' width="100%"', [
				'width' => '100%',
				'max-width' => '100%',
			], ['min-width']);
			return '<table' . $attrs . '>';
		}, $html) ?? $html;

		$html = preg_replace_callback('/<(td|th)\b([^>]*)>/i', static function (array $match): string {
			$attrs = self::setInlineStyle($match[2], [
				'word-break' => 'break-word',
				'overflow-wrap' => 'break-word',
			], []);
			return '<' . strtolower($match[1]) . $attrs . '>';
		}, $html) ?? $html;

		return $html;
	}

	/** @param array<string, string> $set @param list<string> $drop */
	private static function setInlineStyle(string $attrs, array $set, array $drop): string
	{
		$style = '';
		if (preg_match('/\sstyle\s*=\s*("|\')(.*?)\1/i', $attrs, $match)) {
			$style = $match[2];
			$attrs = preg_replace('/\sstyle\s*=\s*("|\')(.*?)\1/i', '', $attrs, 1) ?? $attrs;
		}
		$props = [];
		foreach (explode(';', $style) as $part) {
			$part = trim($part);
			if ($part === '' || !str_contains($part, ':')) {
				continue;
			}
			[$name, $value] = explode(':', $part, 2);
			$name = strtolower(trim($name));
			if ($name === '' || in_array($name, $drop, true) || array_key_exists($name, $set)) {
				continue;
			}
			$props[$name] = trim($value);
		}
		foreach ($set as $name => $value) {
			$props[$name] = $value;
		}
		$css = [];
		foreach ($props as $name => $value) {
			$css[] = $name . ':' . $value;
		}
		return $attrs . ' style="' . htmlspecialchars(implode(';', $css), ENT_QUOTES, 'UTF-8') . '"';
	}

	private static function looksLikeHtml(string $text): bool
	{
		return (bool) preg_match('/<[a-z][\s\S]*>/i', $text);
	}

	private static function persistInlineImages(string $html): string
	{
		return preg_replace_callback(
			'/<img\b[^>]*src=["\'](data:image\/(png|jpe?g|gif|webp);base64,([A-Za-z0-9+\/=]+))["\'][^>]*>/i',
			static function (array $match): string {
				$binary = base64_decode($match[3], true);
				if ($binary === false) {
					return '';
				}
				$kind = strtolower($match[2]);
				$mime = ($kind === 'jpg' || $kind === 'jpeg') ? 'image/jpeg' : 'image/' . $kind;
				$url = self::storeSignatureImage($binary, $mime);
				return $url ? '<img src="' . h($url) . '" alt="" style="max-width:100%;height:auto;display:block;border:0">' : '';
			},
			$html
		) ?? $html;
	}

	public static function adminCount(): int
	{
		return (int) self::pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn();
	}

	public static function deactivate(int $id): void
	{
		self::update($id, ['active' => 0]);
	}
}
