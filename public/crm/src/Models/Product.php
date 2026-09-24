<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class Product extends Record
{
	protected static function table(): string
	{
		return 'products';
	}

	public static function catalog(string $query = ''): array
	{
		$sql = 'SELECT * FROM products';
		$params = [];
		$term = trim($query);
		if ($term !== '') {
			$sql .= ' WHERE sku LIKE ? OR nombre LIKE ? OR proveedor_empresa LIKE ? OR categoria LIKE ?';
			$like = '%' . $term . '%';
			$params = [$like, $like, $like, $like];
		}
		$sql .= ' ORDER BY nombre COLLATE NOCASE, sku COLLATE NOCASE';
		$stmt = static::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public static function findBySku(string $sku, ?int $exceptId = null): ?array
	{
		if ($exceptId) {
			$stmt = static::pdo()->prepare('SELECT * FROM products WHERE sku = ? COLLATE NOCASE AND id != ?');
			$stmt->execute([$sku, $exceptId]);
		} else {
			$stmt = static::pdo()->prepare('SELECT * FROM products WHERE sku = ? COLLATE NOCASE');
			$stmt->execute([$sku]);
		}
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function visible(): array
	{
		$stmt = static::pdo()->query(
			'SELECT sku, nombre, descripcion, categoria, proveedor_empresa, proveedor_link
			 FROM products
			 WHERE activo = 1
			 ORDER BY categoria COLLATE NOCASE, nombre COLLATE NOCASE, sku COLLATE NOCASE'
		);
		return $stmt->fetchAll();
	}
}
