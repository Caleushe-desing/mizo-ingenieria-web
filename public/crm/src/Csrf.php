<?php
declare(strict_types=1);

namespace MizoCrm;

final class Csrf
{
	public static function token(): string
	{
		if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
			$_SESSION['_csrf'] = bin2hex(random_bytes(16));
		}
		return $_SESSION['_csrf'];
	}

	public static function field(): string
	{
		return '<input type="hidden" name="_csrf" value="' . h(self::token()) . '">';
	}

	public static function check(): void
	{
		$sent = (string) ($_POST['_csrf'] ?? '');
		if ($sent === '' || !hash_equals(self::token(), $sent)) {
			http_response_code(419);
			echo 'Sesión expirada. Vuelve a intentar.';
			exit;
		}
	}
}
