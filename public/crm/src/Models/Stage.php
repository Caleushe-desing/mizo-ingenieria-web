<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;
use RuntimeException;

final class Stage extends Record
{
	protected static function table(): string
	{
		return 'board_stages';
	}

	/** @return list<array{slug:string,label:string,color:string,position:int,kind:string}> */
	public static function rows(): array
	{
		try {
			$fetched = self::pdo()->query(
				'SELECT slug, label, color, position, kind FROM board_stages ORDER BY position ASC, id ASC'
			)->fetchAll();
		} catch (\Throwable) {
			return self::fallback();
		}
		if (!$fetched) {
			return self::fallback();
		}
		$out = [];
		foreach ($fetched as $row) {
			$out[] = [
				'slug' => (string) $row['slug'],
				'label' => (string) $row['label'],
				'color' => self::color((string) $row['color']),
				'position' => (int) $row['position'],
				'kind' => self::kind((string) $row['kind']),
			];
		}
		return $out;
	}

	/** @return array<string,string> */
	public static function labels(): array
	{
		$out = [];
		foreach (self::rows() as $row) {
			$out[$row['slug']] = $row['label'];
		}
		return $out;
	}

	/** @return array<string,string> */
	public static function colors(): array
	{
		$out = [];
		foreach (self::rows() as $row) {
			$out[$row['slug']] = $row['color'];
		}
		return $out;
	}

	/** @return list<string> */
	public static function openKeys(): array
	{
		$out = [];
		foreach (self::rows() as $row) {
			if ($row['kind'] === 'open') {
				$out[] = $row['slug'];
			}
		}
		return $out !== [] ? $out : ['nuevo'];
	}

	/** @return list<string> */
	public static function slugs(string $kind): array
	{
		$kind = self::kind($kind);
		$out = [];
		foreach (self::rows() as $row) {
			if ($row['kind'] === $kind) {
				$out[] = $row['slug'];
			}
		}
		return $out;
	}

	public static function firstSlug(): string
	{
		$rows = self::rows();
		foreach ($rows as $row) {
			if ($row['kind'] === 'open') {
				return $row['slug'];
			}
		}
		return $rows[0]['slug'] ?? 'nuevo';
	}

	/** @return array<string,int> */
	public static function dealCounts(): array
	{
		$out = [];
		$rows = self::pdo()->query('SELECT stage, COUNT(*) AS n FROM deals GROUP BY stage')->fetchAll();
		foreach ($rows as $row) {
			$out[(string) $row['stage']] = (int) $row['n'];
		}
		return $out;
	}

	public static function create(string $label, string $color, string $kind): string
	{
		$label = self::label($label);
		if (count(self::rows()) >= 12) {
			throw new RuntimeException('El tablero admite hasta 12 columnas.');
		}
		$slug = self::uniqueSlug($label);
		$position = (int) self::pdo()->query('SELECT COALESCE(MAX(position), 0) + 1 FROM board_stages')->fetchColumn();
		self::insert([
			'slug' => $slug,
			'label' => $label,
			'color' => self::color($color),
			'position' => $position,
			'kind' => self::kind($kind),
			'created_at' => date('c'),
		]);
		return $slug;
	}

	public static function save(string $slug, string $label, string $color, string $kind): void
	{
		$row = self::bySlug($slug);
		if (!$row) {
			throw new RuntimeException('Esa columna ya no existe.');
		}
		self::pdo()->prepare('UPDATE board_stages SET label = ?, color = ?, kind = ? WHERE slug = ?')->execute([
			self::label($label),
			self::color($color),
			self::kind($kind),
			$slug,
		]);
	}

	public static function shift(string $slug, int $direction): void
	{
		$rows = self::rows();
		$index = null;
		foreach ($rows as $i => $row) {
			if ($row['slug'] === $slug) {
				$index = $i;
				break;
			}
		}
		if ($index === null) {
			return;
		}
		$other = $index + ($direction < 0 ? -1 : 1);
		if (!isset($rows[$other])) {
			return;
		}
		$pdo = self::pdo();
		$pdo->prepare('UPDATE board_stages SET position = ? WHERE slug = ?')->execute([$rows[$other]['position'], $rows[$index]['slug']]);
		$pdo->prepare('UPDATE board_stages SET position = ? WHERE slug = ?')->execute([$rows[$index]['position'], $rows[$other]['slug']]);
	}

	public static function remove(string $slug, string $moveTo): int
	{
		$rows = self::rows();
		if (count($rows) < 2) {
			throw new RuntimeException('Deja al menos una columna en el tablero.');
		}
		if (!self::bySlug($slug)) {
			throw new RuntimeException('Esa columna ya no existe.');
		}
		$counts = self::dealCounts();
		$n = $counts[$slug] ?? 0;
		if ($n > 0 && ($moveTo === '' || $moveTo === $slug || !self::bySlug($moveTo))) {
			throw new RuntimeException('Elige a qué columna mover las tarjetas antes de eliminar.');
		}
		$pdo = self::pdo();
		$pdo->beginTransaction();
		try {
			if ($n > 0) {
				$pdo->prepare('UPDATE deals SET stage = ?, updated_at = ? WHERE stage = ?')->execute([$moveTo, date('c'), $slug]);
			}
			$pdo->prepare('DELETE FROM board_stages WHERE slug = ?')->execute([$slug]);
			$pdo->commit();
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
		return $n;
	}

	public static function color(string $color): string
	{
		$color = strtolower(trim($color));
		return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '#1c9bd8';
	}

	public static function kind(string $kind): string
	{
		return in_array($kind, ['open', 'won', 'lost'], true) ? $kind : 'open';
	}

	private static function label(string $label): string
	{
		$label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');
		if ($label === '') {
			throw new RuntimeException('Escribe el nombre de la columna.');
		}
		if (mb_strlen($label) > 80) {
			$label = mb_substr($label, 0, 80);
		}
		return $label;
	}

	private static function bySlug(string $slug): ?array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM board_stages WHERE slug = ?');
		$stmt->execute([$slug]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	private static function uniqueSlug(string $label): string
	{
		$base = mb_strtolower($label, 'UTF-8');
		$base = strtr($base, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
		$base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
		$base = trim($base, '-');
		if ($base === '') {
			$base = 'etapa';
		}
		$base = substr($base, 0, 32);
		$slug = $base;
		$n = 2;
		while (self::bySlug($slug)) {
			$slug = $base . '-' . $n;
			$n++;
		}
		return $slug;
	}

	/** @return list<array{slug:string,label:string,color:string,position:int,kind:string}> */
	private static function fallback(): array
	{
		return [
			['slug' => 'nuevo', 'label' => 'Prospecto / Lead nuevo', 'color' => '#1c9bd8', 'position' => 1, 'kind' => 'open'],
			['slug' => 'contactado', 'label' => 'Contacto / Llamada realizada', 'color' => '#0b6ea8', 'position' => 2, 'kind' => 'open'],
			['slug' => 'negociacion', 'label' => 'Diagnóstico / Requerimiento', 'color' => '#5b6b8c', 'position' => 3, 'kind' => 'open'],
			['slug' => 'propuesta', 'label' => 'Propuesta / Cotización enviada', 'color' => '#f47b20', 'position' => 4, 'kind' => 'open'],
			['slug' => 'ganado', 'label' => 'Cierre ganado', 'color' => '#1f8a4c', 'position' => 5, 'kind' => 'won'],
			['slug' => 'perdido', 'label' => 'Descartado / En pausa', 'color' => '#8a9099', 'position' => 6, 'kind' => 'lost'],
		];
	}
}
