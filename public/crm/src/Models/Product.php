<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class Product extends Record
{
	public const SERVICE_SKU = 'MIZO-SP';

	protected static function table(): string
	{
		return 'products';
	}

	public static function isProfessionalService(?array $product): bool
	{
		if (!$product) {
			return false;
		}
		return strcasecmp(trim((string) ($product['sku'] ?? '')), self::SERVICE_SKU) === 0;
	}

	/** Garantiza el producto interno "Servicio profesional" (oculto en web). */
	public static function ensureProfessionalService(): ?array
	{
		$existing = self::findBySku(self::SERVICE_SKU);
		if ($existing) {
			if ((int) ($existing['activo'] ?? 1) !== 0) {
				self::update((int) $existing['id'], [
					'activo' => 0,
					'updated_at' => date('c'),
				]);
				$existing['activo'] = 0;
			}
			return $existing;
		}
		$now = date('c');
		$id = self::insert([
			'sku' => self::SERVICE_SKU,
			'nombre' => 'Servicio profesional',
			'descripcion' => 'Servicio profesional Mizo. El costo se arma en la cotización con ítems netos y se convierte a c/IVA.',
			'categoria' => 'Servicios',
			'proveedor_empresa' => 'Mizo Ingeniería',
			'proveedor_link' => '',
			'activo' => 0,
			'precio_compra_iva' => 0,
			'imagenes' => '[]',
			'created_at' => $now,
			'updated_at' => $now,
		]);
		return self::find((int) $id);
	}

	public static function catalog(string $query = ''): array
	{
		self::ensureProfessionalService();
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
			$row['servicio_profesional'] = self::isProfessionalService($row);
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

	/** Landings públicas donde se pueden destacar productos del catálogo. */
	public static function landings(): array
	{
		return [
			'parlantes-para-iglesias' => 'Parlantes para iglesias',
			'instalacion-de-proyectores' => 'Instalación de proyectores',
			'instalacion-de-musica-ambiental' => 'Instalación de música ambiental',
			'proyectores-interactivos' => 'Proyectores interactivos',
			'instalacion-de-parlantes' => 'Instalación de parlantes',
			'instalacion-de-video-wall' => 'Instalación de video wall',
		];
	}

	/** @return list<string> */
	public static function landingSlugsFor(int $productId): array
	{
		$stmt = static::pdo()->prepare(
			'SELECT landing_slug FROM product_landing_features WHERE product_id = ? ORDER BY position ASC, landing_slug ASC'
		);
		$stmt->execute([$productId]);
		return array_values(array_map(static fn(array $row): string => (string) $row['landing_slug'], $stmt->fetchAll()));
	}

	/** @return array<string, list<int>> */
	public static function landingAssignments(): array
	{
		$map = [];
		foreach (array_keys(self::landings()) as $slug) {
			$map[$slug] = [];
		}
		$rows = static::pdo()->query(
			'SELECT landing_slug, product_id FROM product_landing_features ORDER BY position ASC, product_id ASC'
		)->fetchAll();
		foreach ($rows as $row) {
			$slug = (string) $row['landing_slug'];
			if (!isset($map[$slug])) {
				continue;
			}
			$map[$slug][] = (int) $row['product_id'];
		}
		return $map;
	}

	/** @param list<string> $slugs */
	public static function setLandings(int $productId, array $slugs): void
	{
		$allowed = self::landings();
		$clean = [];
		foreach ($slugs as $slug) {
			$slug = (string) $slug;
			if (isset($allowed[$slug]) && !in_array($slug, $clean, true)) {
				$clean[] = $slug;
			}
		}
		$pdo = static::pdo();
		$pdo->prepare('DELETE FROM product_landing_features WHERE product_id = ?')->execute([$productId]);
		$insert = $pdo->prepare(
			'INSERT INTO product_landing_features (product_id, landing_slug, position) VALUES (?, ?, ?)'
		);
		foreach ($clean as $position => $slug) {
			$insert->execute([$productId, $slug, $position]);
		}
	}

	/**
	 * Reemplaza los productos destacados de cada landing, en el orden recibido.
	 *
	 * @param array<string, list<int>> $byLanding
	 */
	public static function saveLandingAssignments(array $byLanding): void
	{
		$pdo = static::pdo();
		$pdo->beginTransaction();
		try {
			$delete = $pdo->prepare('DELETE FROM product_landing_features WHERE landing_slug = ?');
			$insert = $pdo->prepare(
				'INSERT INTO product_landing_features (product_id, landing_slug, position) VALUES (?, ?, ?)'
			);
			foreach (self::landings() as $slug => $_label) {
				$delete->execute([$slug]);
				$ids = $byLanding[$slug] ?? [];
				$position = 0;
				$seen = [];
				foreach ($ids as $id) {
					$id = (int) $id;
					if ($id <= 0 || isset($seen[$id])) {
						continue;
					}
					$product = self::find($id);
					if (!$product || self::isProfessionalService($product)) {
						continue;
					}
					$seen[$id] = true;
					$insert->execute([$id, $slug, $position]);
					$position++;
				}
			}
			$pdo->commit();
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	public static function addToLanding(string $slug, array $productIds): int
	{
		if (!isset(self::landings()[$slug])) {
			return 0;
		}
		$pdo = static::pdo();
		$max = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM product_landing_features WHERE landing_slug = ?');
		$max->execute([$slug]);
		$position = (int) $max->fetchColumn() + 1;
		$exists = $pdo->prepare('SELECT 1 FROM product_landing_features WHERE product_id = ? AND landing_slug = ?');
		$insert = $pdo->prepare(
			'INSERT INTO product_landing_features (product_id, landing_slug, position) VALUES (?, ?, ?)'
		);
		$added = 0;
		foreach ($productIds as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$product = self::find($id);
			if (!$product || self::isProfessionalService($product)) {
				continue;
			}
			$exists->execute([$id, $slug]);
			if ($exists->fetch()) {
				continue;
			}
			$insert->execute([$id, $slug, $position]);
			$position++;
			$added++;
		}
		return $added;
	}

	public static function removeFromLanding(string $slug, int $productId): void
	{
		if (!isset(self::landings()[$slug]) || $productId <= 0) {
			return;
		}
		static::pdo()->prepare(
			'DELETE FROM product_landing_features WHERE product_id = ? AND landing_slug = ?'
		)->execute([$productId, $slug]);
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
		self::ensureProfessionalService();
		$sql = 'SELECT id, sku, nombre, descripcion, categoria, precio_compra_iva, proveedor_link, proveedor_empresa
			FROM products';
		$params = [];
		$term = trim($query);
		if ($term !== '') {
			$sql .= ' WHERE sku LIKE ? OR nombre LIKE ? OR categoria LIKE ? OR descripcion LIKE ?';
			$like = '%' . $term . '%';
			$params = [$like, $like, $like, $like];
		}
		$sql .= ' ORDER BY CASE WHEN sku = ' . static::pdo()->quote(self::SERVICE_SKU) . ' THEN 0 ELSE 1 END, categoria COLLATE NOCASE, nombre COLLATE NOCASE, sku COLLATE NOCASE';
		$stmt = static::pdo()->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll();
		foreach ($rows as &$row) {
			$row['id'] = (int) $row['id'];
			$row['precio_compra_iva'] = (int) ($row['precio_compra_iva'] ?? 0);
			$row['proveedor_link'] = (string) ($row['proveedor_link'] ?? '');
			$row['proveedor_empresa'] = (string) ($row['proveedor_empresa'] ?? '');
			$row['servicio_profesional'] = self::isProfessionalService($row);
		}
		unset($row);
		return $rows;
	}
}
