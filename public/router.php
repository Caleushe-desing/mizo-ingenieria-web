<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
	return false;
}

if (str_starts_with($uri, '/crm')) {
	require __DIR__ . '/crm/index.php';
	return true;
}

return false;
