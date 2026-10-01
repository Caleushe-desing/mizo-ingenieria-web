<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class MarketingMedia extends Record
{
	protected static function table(): string
	{
		return 'marketing_media';
	}

	/** @return list<string> */
	public static function categories(): array
	{
		return [
			'Domótica',
			'Audio',
			'Iluminación',
			'Proyectos Corporativos',
			'Residencia',
			'Automatización',
			'Video',
			'Otros',
		];
	}

	public static function storageRoot(): string
	{
		return dirname(__DIR__, 2) . '/uploads/marketing/stock';
	}

	/** @return list<array<string, mixed>> */
	public static function active(?string $category = null): array
	{
		$sql = 'SELECT * FROM marketing_media WHERE active = 1';
		$params = [];
		if ($category !== null && $category !== '') {
			$sql .= ' AND category = ?';
			$params[] = $category;
		}
		$sql .= ' ORDER BY category COLLATE NOCASE, position ASC, id DESC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll() ?: [];
	}

	/** Payload liviano para el estudio de diseño. @return list<array<string, mixed>> */
	public static function forStudio(): array
	{
		$rows = self::active();
		$out = [];
		foreach ($rows as $row) {
			$thumb = trim((string) ($row['thumb_path'] ?? ''));
			$file = trim((string) ($row['file_path'] ?? ''));
			$preview = $thumb !== '' ? $thumb : $file;
			if ($file === '') {
				continue;
			}
			$out[] = [
				'id' => (int) $row['id'],
				'title' => (string) $row['title'],
				'category' => (string) $row['category'],
				'url' => $file,
				'thumb' => $preview,
			];
		}
		return $out;
	}

	public static function nextPosition(): int
	{
		return (int) self::pdo()->query('SELECT COALESCE(MAX(position), 0) FROM marketing_media')->fetchColumn() + 1;
	}

	public static function absolutePath(array $row): ?string
	{
		$rel = trim((string) ($row['file_path'] ?? ''));
		if ($rel === '' || !preg_match('#^/crm/uploads/marketing/stock/\d+/[^/]+$#', $rel)) {
			return null;
		}
		$path = dirname(__DIR__, 2) . substr($rel, strlen('/crm'));
		return is_file($path) ? $path : null;
	}
}
