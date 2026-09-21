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
		return self::insert([
			'name' => $name,
			'email' => mb_strtolower(trim($email)),
			'password_hash' => password_hash($password, PASSWORD_DEFAULT),
			'role' => $role,
			'active' => 1,
			'created_at' => date('c'),
		]);
	}

	public static function team(): array
	{
		return self::pdo()->query('SELECT id, name, email, role FROM users WHERE active = 1 ORDER BY name ASC')->fetchAll();
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
