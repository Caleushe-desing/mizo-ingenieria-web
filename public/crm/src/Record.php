<?php
declare(strict_types=1);

namespace MizoCrm;

use PDO;

abstract class Record
{
	abstract protected static function table(): string;

	protected static function pdo(): PDO
	{
		return Database::pdo();
	}

	public static function find(int $id): ?array
	{
		$stmt = static::pdo()->prepare('SELECT * FROM ' . static::table() . ' WHERE id = ?');
		$stmt->execute([$id]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function all(string $order = 'id DESC'): array
	{
		return static::pdo()->query('SELECT * FROM ' . static::table() . ' ORDER BY ' . $order)->fetchAll();
	}

	public static function count(): int
	{
		return (int) static::pdo()->query('SELECT COUNT(*) FROM ' . static::table())->fetchColumn();
	}

	public static function insert(array $data): int
	{
		$keys = array_keys($data);
		$fields = implode(', ', $keys);
		$placeholders = implode(', ', array_fill(0, count($keys), '?'));
		$stmt = static::pdo()->prepare('INSERT INTO ' . static::table() . " ({$fields}) VALUES ({$placeholders})");
		$stmt->execute(array_values($data));
		return (int) static::pdo()->lastInsertId();
	}

	public static function update(int $id, array $data): void
	{
		$sets = implode(', ', array_map(static fn($key) => "{$key} = ?", array_keys($data)));
		$stmt = static::pdo()->prepare('UPDATE ' . static::table() . " SET {$sets} WHERE id = ?");
		$stmt->execute([...array_values($data), $id]);
	}

	public static function delete(int $id): void
	{
		$stmt = static::pdo()->prepare('DELETE FROM ' . static::table() . ' WHERE id = ?');
		$stmt->execute([$id]);
	}
}
