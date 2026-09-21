<?php
declare(strict_types=1);

namespace MizoCrm;

final class View
{
	public static function render(string $template, array $data = [], string $layout = 'layout'): void
	{
		$data['user'] = $data['user'] ?? Auth::user();
		$data['flash'] = self::pullFlash();
		extract($data, EXTR_SKIP);
		$templateFile = dirname(__DIR__) . '/views/' . $template . '.php';
		ob_start();
		require $templateFile;
		$content = ob_get_clean() ?: '';
		require dirname(__DIR__) . '/views/' . $layout . '.php';
	}

	public static function flash(string $type, string $message): void
	{
		$_SESSION['_flash'] = ['type' => $type, 'message' => $message];
	}

	private static function pullFlash(): ?array
	{
		$flash = $_SESSION['_flash'] ?? null;
		unset($_SESSION['_flash']);
		return is_array($flash) ? $flash : null;
	}
}

