<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\SitePage;
use MizoCrm\View;

final class SiteController
{
	public function index(): void
	{
		Auth::requireAdmin();
		$pages = [];
		foreach (SitePage::catalog() as $path => $label) {
			$row = SitePage::find($path);
			$pages[] = [
				'path' => $path,
				'label' => $label,
				'active' => (int) ($row['active'] ?? 0) === 1,
				'updated_at' => $row['updated_at'] ?? null,
			];
		}
		View::render('site/index', [
			'title' => 'Editor del sitio',
			'pages' => $pages,
		]);
	}

	public function edit(): void
	{
		Auth::requireAdmin();
		$path = SitePage::normalizePath((string) ($_GET['path'] ?? '/'));
		if (!isset(SitePage::catalog()[$path])) {
			Http::redirect('/sitio');
		}
		header('Location: ' . $path . '?editar=1', true, 302);
		exit;
	}

	public function save(): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$path = SitePage::normalizePath(Http::string('path', 180));
		if (!isset(SitePage::catalog()[$path])) {
			Http::redirect('/sitio');
		}
		$restore = isset($_POST['restaurar']);
		if ($restore) {
			SitePage::save($path, '', '', [], false);
			View::flash('ok', 'La página volvió al diseño original del sitio.');
			Http::redirect('/sitio/editar?path=' . rawurlencode($path));
		}
		SitePage::save(
			$path,
			Http::string('title', 180),
			Http::text('description', 400),
			$this->postedBlocks(),
			isset($_POST['active'])
		);
		View::flash('ok', 'Página publicada. Recarga el sitio para ver los cambios.');
		Http::redirect('/sitio/editar?path=' . rawurlencode($path));
	}

	public function upload(): void
	{
		Auth::requireAdmin();
		header('Content-Type: application/json; charset=utf-8');
		$sent = (string) ($_POST['_csrf'] ?? '');
		if ($sent === '' || !hash_equals(Csrf::token(), $sent)) {
			http_response_code(419);
			echo json_encode(['ok' => false, 'error' => 'Sesión expirada.'], JSON_UNESCAPED_UNICODE);
			return;
		}
		$file = $_FILES['imagen'] ?? null;
		if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			http_response_code(422);
			echo json_encode(['ok' => false, 'error' => 'No se recibió la imagen.'], JSON_UNESCAPED_UNICODE);
			return;
		}
		if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
			http_response_code(422);
			echo json_encode(['ok' => false, 'error' => 'La imagen debe pesar máximo 8 MB.'], JSON_UNESCAPED_UNICODE);
			return;
		}
		$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
		$map = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];
		if (!isset($map[$ext])) {
			http_response_code(422);
			echo json_encode(['ok' => false, 'error' => 'Usa JPG, PNG o WEBP.'], JSON_UNESCAPED_UNICODE);
			return;
		}
		$dir = dirname(__DIR__, 2) . '/uploads/sitio';
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			http_response_code(500);
			echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen.'], JSON_UNESCAPED_UNICODE);
			return;
		}
		$name = date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $map[$ext];
		if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
			http_response_code(500);
			echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen.'], JSON_UNESCAPED_UNICODE);
			return;
		}
		echo json_encode(['ok' => true, 'url' => '/crm/uploads/sitio/' . $name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/** @return list<array<string, mixed>> */
	private function postedBlocks(): array
	{
		$posted = $_POST['blocks'] ?? [];
		if (!is_array($posted)) {
			return [];
		}
		$allowed = ['hero', 'texto', 'tarjetas', 'imagen', 'productos', 'faq', 'cta'];
		$blocks = [];
		foreach ($posted as $block) {
			if (!is_array($block)) {
				continue;
			}
			$type = (string) ($block['type'] ?? '');
			if (!in_array($type, $allowed, true)) {
				continue;
			}
			$clean = ['type' => $type];
			foreach (['kicker', 'title', 'text', 'image', 'alt', 'caption', 'button_label', 'button_href', 'landing'] as $key) {
				if (isset($block[$key])) {
					$clean[$key] = trim(strip_tags((string) $block[$key]));
				}
			}
			if (isset($block['html'])) {
				$clean['html'] = $this->cleanHtml((string) $block['html']);
			}
			if (isset($block['items_json'])) {
				$items = json_decode((string) $block['items_json'], true);
				$clean['items'] = [];
				if (is_array($items)) {
					foreach (array_slice($items, 0, 12) as $item) {
						if (!is_array($item)) {
							continue;
						}
						$clean['items'][] = [
							'title' => trim(strip_tags((string) ($item['title'] ?? $item['q'] ?? ''))),
							'text' => trim(strip_tags((string) ($item['text'] ?? $item['a'] ?? ''))),
							'q' => trim(strip_tags((string) ($item['q'] ?? ''))),
							'a' => trim(strip_tags((string) ($item['a'] ?? ''))),
						];
					}
				}
			}
			$blocks[] = $clean;
			if (count($blocks) >= 40) {
				break;
			}
		}
		return $blocks;
	}

	private function cleanHtml(string $html): string
	{
		$html = strip_tags($html, '<p><br><strong><b><em><i><a><ul><ol><li><h2><h3>');
		$html = preg_replace('/\son\w+\s*=\s*(\"[^\"]*\"|\'[^\']*\')/i', '', $html) ?? $html;
		return preg_replace('/javascript\s*:/i', '', $html) ?? $html;
	}
}
