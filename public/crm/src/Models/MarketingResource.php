<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class MarketingResource extends Record
{
	protected static function table(): string
	{
		return 'marketing_resources';
	}

	/** @return list<string> */
	public static function categories(): array
	{
		return [
			'Audio Comercial',
			'Domótica',
			'Automatización Residencial',
			'Proyectos Especiales',
			'Redes Sociales',
			'Presentaciones',
			'Catálogos',
		];
	}

	/** @return array<string, string> */
	public static function kinds(): array
	{
		return [
			'flyer' => 'Flyer / publicación',
			'social' => 'Redes sociales',
			'pdf' => 'PDF corporativo',
		];
	}

	/** @return list<array<string, mixed>> */
	public static function active(?string $kind = null, ?string $category = null): array
	{
		$sql = 'SELECT * FROM marketing_resources WHERE active = 1';
		$params = [];
		if ($kind !== null && $kind !== '') {
			$sql .= ' AND kind = ?';
			$params[] = $kind;
		}
		if ($category !== null && $category !== '') {
			$sql .= ' AND category = ?';
			$params[] = $category;
		}
		$sql .= ' ORDER BY category COLLATE NOCASE, position ASC, id DESC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll() ?: [];
	}

	/** @return list<array<string, mixed>> */
	public static function visual(): array
	{
		$stmt = self::pdo()->query(
			"SELECT * FROM marketing_resources
			 WHERE active = 1 AND kind IN ('flyer', 'social')
			 ORDER BY category COLLATE NOCASE, position ASC, id DESC"
		);
		return $stmt->fetchAll() ?: [];
	}

	/** @return list<array<string, mixed>> */
	public static function documents(): array
	{
		$stmt = self::pdo()->query(
			"SELECT * FROM marketing_resources
			 WHERE active = 1 AND kind = 'pdf'
			 ORDER BY category COLLATE NOCASE, position ASC, id DESC"
		);
		return $stmt->fetchAll() ?: [];
	}

	public static function storageRoot(): string
	{
		return dirname(__DIR__, 2) . '/uploads/marketing';
	}

	public static function absolutePath(array $row): ?string
	{
		$rel = trim((string) ($row['file_path'] ?? ''));
		if ($rel === '' || !preg_match('#^/crm/uploads/marketing/\d+/[^/]+$#', $rel)) {
			return null;
		}
		$path = dirname(__DIR__, 2) . substr($rel, strlen('/crm'));
		return is_file($path) ? $path : null;
	}

	public static function thumbUrl(array $row): ?string
	{
		$thumb = trim((string) ($row['thumb_path'] ?? ''));
		if ($thumb !== '' && preg_match('#^/crm/uploads/marketing/\d+/[^/]+$#', $thumb)) {
			$abs = dirname(__DIR__, 2) . substr($thumb, strlen('/crm'));
			if (is_file($abs)) {
				return $thumb;
			}
		}
		$mime = strtolower((string) ($row['mime'] ?? ''));
		if (str_starts_with($mime, 'image/')) {
			$file = trim((string) ($row['file_path'] ?? ''));
			if ($file !== '') {
				return $file;
			}
		}
		return null;
	}

	public static function nextPosition(): int
	{
		return (int) self::pdo()->query('SELECT COALESCE(MAX(position), 0) FROM marketing_resources')->fetchColumn() + 1;
	}

	public static function humanSize(array $row): string
	{
		$bytes = (int) ($row['file_size'] ?? 0);
		if ($bytes <= 0) {
			return '';
		}
		if ($bytes >= 1048576) {
			return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
		}
		return number_format(max(1, (int) round($bytes / 1024)), 0, ',', '.') . ' KB';
	}
}
