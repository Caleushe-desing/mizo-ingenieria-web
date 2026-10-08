<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Database;

final class SitePage
{
	public static function catalog(): array
	{
		return [
			'/' => 'Inicio',
			'/industrias' => 'Soluciones',
			'/industrias/restaurantes' => 'Solución · Restaurantes',
			'/industrias/instituciones-educativas' => 'Solución · Educación',
			'/industrias/auditorios' => 'Solución · Auditorios',
			'/industrias/gimnasios' => 'Solución · Gimnasios',
			'/industrias/entretenimiento-residencial' => 'Solución · Residencial',
			'/instalaciones' => 'Instalaciones especializadas',
			'/instalaciones/parlantes-para-iglesias' => 'Landing · Parlantes para iglesias',
			'/instalaciones/instalacion-de-proyectores' => 'Landing · Instalación de proyectores',
			'/instalaciones/instalacion-de-musica-ambiental' => 'Landing · Música ambiental',
			'/instalaciones/proyectores-interactivos' => 'Landing · Proyectores interactivos',
			'/instalaciones/instalacion-de-parlantes' => 'Landing · Instalación de parlantes',
			'/instalaciones/instalacion-de-video-wall' => 'Landing · Video wall',
			'/nosotros' => 'Nosotros',
			'/contacto' => 'Contacto',
			'/equipos' => 'Catálogo público',
		];
	}

	public static function normalizePath(string $path): string
	{
		$path = '/' . trim($path, '/');
		return $path === '/' ? '/' : rtrim($path, '/');
	}

	public static function find(string $path): ?array
	{
		$path = self::normalizePath($path);
		$stmt = Database::pdo()->prepare('SELECT * FROM site_pages WHERE path = ?');
		$stmt->execute([$path]);
		$row = $stmt->fetch();
		if (!$row) {
			return null;
		}
		$row['blocks'] = self::decodeBlocks((string) ($row['blocks'] ?? '[]'));
		$row['active'] = (int) ($row['active'] ?? 0);
		return $row;
	}

	public static function save(string $path, string $title, string $description, array $blocks, bool $active): void
	{
		$path = self::normalizePath($path);
		if (!isset(self::catalog()[$path])) {
			return;
		}
		$json = json_encode(array_values($blocks), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			$json = '[]';
		}
		$now = date('c');
		Database::pdo()->prepare(
			'INSERT INTO site_pages (path, title, description, blocks, active, updated_at)
			 VALUES (?, ?, ?, ?, ?, ?)
			 ON CONFLICT(path) DO UPDATE SET
				title = excluded.title,
				description = excluded.description,
				blocks = excluded.blocks,
				active = excluded.active,
				updated_at = excluded.updated_at'
		)->execute([$path, $title, $description, $json, $active ? 1 : 0, $now]);
	}

	public static function starter(string $path): array
	{
		$path = self::normalizePath($path);
		$label = self::catalog()[$path] ?? 'Página';
		$blocks = [
			['type' => 'hero', 'kicker' => 'Mizo', 'title' => $label, 'text' => 'Edita este título, el texto y la imagen.', 'image' => '', 'button_label' => 'Cotizar', 'button_href' => '/contacto'],
			['type' => 'texto', 'kicker' => 'Contenido', 'title' => 'Nueva sección', 'html' => '<p>Escribe aquí el contenido de la página. Puedes agregar más secciones abajo.</p>'],
		];
		if (str_starts_with($path, '/instalaciones/') && $path !== '/instalaciones') {
			$blocks[] = [
				'type' => 'productos',
				'title' => 'Productos destacados',
				'text' => 'Equipos de esta instalación.',
				'landing' => basename($path),
			];
		}
		$blocks[] = ['type' => 'cta', 'title' => '¿Conversamos el proyecto?', 'text' => 'Cuéntanos el recinto y te respondemos en horario hábil.', 'button_label' => 'Cotizar', 'button_href' => '/contacto'];
		return $blocks;
	}

	/** @return list<array<string, mixed>> */
	public static function decodeBlocks(string $json): array
	{
		$data = json_decode($json, true);
		return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
	}
}
