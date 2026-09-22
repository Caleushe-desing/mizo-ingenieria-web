<?php
declare(strict_types=1);

namespace MizoCrm;

require_once __DIR__ . '/src/Autoload.php';

date_default_timezone_set('America/Santiago');
session_name('mizo_crm');
session_start([
	'cookie_httponly' => true,
	'cookie_samesite' => 'Lax',
	'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);

try {
	App::run();
} catch (\Throwable $e) {
	http_response_code(500);
	header('Content-Type: text/html; charset=UTF-8');
	$msg = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	echo '<!doctype html><html lang="es"><head><meta charset="UTF-8"><title>Error CRM</title></head><body style="font-family:Segoe UI,Arial,sans-serif;padding:40px;max-width:640px">';
	echo '<h1>No se pudo cargar esta sección</h1>';
	echo '<p>El CRM encontró un problema temporal. Vuelve a <a href="/crm/">Clientes</a> e inténtalo de nuevo.</p>';
	echo '<p style="color:#666;font-size:13px">Detalle: ' . $msg . '</p>';
	echo '</body></html>';
}
