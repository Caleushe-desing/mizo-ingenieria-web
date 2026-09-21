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

App::run();
