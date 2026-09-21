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
		return '<p style="margin:24px 0 0;padding-top:16px;border-top:1px solid #e6e6e6;font-size:13px;line-height:1.45;color:#444;">'
			. nl2br(h($text), false)
			. '</p>';
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
