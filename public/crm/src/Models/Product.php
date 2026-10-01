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
			'SELECT id, sku, nombre, descripcion, categoria, proveedor_link, imagenes
			 FROM products
			 WHERE activo = 1
			 ORDER BY categoria COLLATE NOCASE, nombre COLLATE NOCASE, sku COLLATE NOCASE'
		);
		return $stmt->fetchAll();
	}

	/** Catálogo para el cotizador: id, sku, nombre, descripción y precio de compra c/IVA. */
	public static function forQuoting(string $query = ''): array
	{
		$sql = 'SELECT id, sku, nombre, descripcion, categoria, precio_compra_iva FROM products';
		$params = [];
		$term = trim($query);
		if ($term !== '') {
			$sql .= ' WHERE sku LIKE ? OR nombre LIKE ? OR categoria LIKE ? OR descripcion LIKE ?';
			$like = '%' . $term . '%';
			$params = [$like, $like, $like, $like];
		}
		$sql .= ' ORDER BY categoria COLLATE NOCASE, nombre COLLATE NOCASE, sku COLLATE NOCASE';
		$stmt = static::pdo()->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll();
		foreach ($rows as &$row) {
			$row['id'] = (int) $row['id'];
			$row['precio_compra_iva'] = (int) ($row['precio_compra_iva'] ?? 0);
		}
		unset($row);
		return $rows;
	}
}
