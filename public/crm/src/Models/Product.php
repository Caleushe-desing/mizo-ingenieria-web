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
		$sql = 'SELECT p.*,
				(SELECT COUNT(DISTINCT qi.quote_id)
				 FROM quote_items qi
				 WHERE qi.product_id = p.id) AS quote_count,
				(SELECT COALESCE(SUM(qi.quantity), 0)
				 FROM quote_items qi
				 WHERE qi.product_id = p.id) AS units_quoted
			FROM products p';
		$params = [];
		$term = trim($query);
		if ($term !== '') {
			$sql .= ' WHERE p.sku LIKE ? OR p.nombre LIKE ? OR p.proveedor_empresa LIKE ? OR p.categoria LIKE ?';
			$like = '%' . $term . '%';
			$params = [$like, $like, $like, $like];
		}
		$sql .= ' ORDER BY quote_count DESC, p.nombre COLLATE NOCASE, p.sku COLLATE NOCASE';
		$stmt = static::pdo()->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll();
		foreach ($rows as &$row) {
			$row['quote_count'] = (int) ($row['quote_count'] ?? 0);
			$row['units_quoted'] = (float) ($row['units_quoted'] ?? 0);
		}
		unset($row);
		return $rows;
	}

	/** Cotizaciones donde este producto del catálogo fue considerado. */
	public static function quoteAppearances(int $productId): array
	{
		$stmt = static::pdo()->prepare(
			'SELECT q.id, q.number, q.status, q.created_at, q.sent_at, q.total,
				c.name AS client_name,
				qi.quantity, qi.unit, qi.cost_price, qi.margin_percent, qi.unit_price, qi.total AS line_total
			 FROM quote_items qi
			 JOIN quotes q ON q.id = qi.quote_id
			 JOIN clients c ON c.id = q.client_id
			 WHERE qi.product_id = ?
			 ORDER BY COALESCE(q.sent_at, q.created_at) DESC, q.id DESC, qi.position ASC'
		);
		$stmt->execute([$productId]);
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

	/** Catálogo para el cotizador: ficha oficial + costo c/IVA + enlace del proveedor. */
	public static function forQuoting(string $query = ''): array
	{
		$sql = 'SELECT id, sku, nombre, descripcion, categoria, precio_compra_iva, proveedor_link, proveedor_empresa
			FROM products';
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
			$row['proveedor_link'] = (string) ($row['proveedor_link'] ?? '');
			$row['proveedor_empresa'] = (string) ($row['proveedor_empresa'] ?? '');
		}
		unset($row);
		return $rows;
	}
}
