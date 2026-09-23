<?php
declare(strict_types=1);

namespace MizoCrm;

final class App
{
	public static function run(): void
	{
		header('X-Frame-Options: SAMEORIGIN');
		header('Referrer-Policy: same-origin');
		header('Cache-Control: no-store');

		$path = Http::path();
		$method = Http::method();

		if (str_starts_with($path, '/q/')) {
			self::dispatch($method, $path);
			return;
		}

		if (Auth::needsSetup() && !str_starts_with($path, '/setup')) {
			Http::redirect('/setup');
		}

		if (!Auth::needsSetup() && str_starts_with($path, '/setup')) {
			Http::redirect('/login');
		}

		$public = ['/login', '/setup'];
		if (!in_array($path, $public, true) && !Auth::user() && $path !== '/logout') {
			Http::redirect('/login');
		}

		self::dispatch($method, $path);
	}

	private static function dispatch(string $method, string $path): void
	{
		$routes = [
			['GET', '#^/setup$#', [Controllers\SetupController::class, 'show']],
			['POST', '#^/setup$#', [Controllers\SetupController::class, 'store']],
			['GET', '#^/login$#', [Controllers\AuthController::class, 'show']],
			['POST', '#^/login$#', [Controllers\AuthController::class, 'login']],
			['POST', '#^/logout$#', [Controllers\AuthController::class, 'logout']],
			['GET', '#^/$#', [Controllers\BoardController::class, 'index']],
			['GET', '#^/tablero/cliente/(\d+)/ficha$#', [Controllers\BoardController::class, 'file']],
			['GET', '#^/tablero/proyecto/(\d+)$#', [Controllers\BoardController::class, 'project']],
			['POST', '#^/tablero/proyecto/(\d+)/nota$#', [Controllers\BoardController::class, 'projectNote']],
			['GET', '#^/tablero/cliente/(\d+)$#', [Controllers\BoardController::class, 'show']],
			['POST', '#^/tablero/mover$#', [Controllers\BoardController::class, 'move']],
			['POST', '#^/tablero/cliente/(\d+)/nota$#', [Controllers\BoardController::class, 'note']],
			['GET', '#^/avisos$#', [Controllers\AlertController::class, 'ping']],
			['GET', '#^/chat/(\d+)/mensajes$#', [Controllers\ChatController::class, 'poll']],
			['POST', '#^/chat/(\d+)$#', [Controllers\ChatController::class, 'send']],
			['GET', '#^/chat/(\d+)$#', [Controllers\ChatController::class, 'show']],
			['GET', '#^/chat$#', [Controllers\ChatController::class, 'index']],
			['GET', '#^/correo/cuenta$#', [Controllers\MailController::class, 'account']],
			['POST', '#^/correo/cuenta$#', [Controllers\MailController::class, 'connect']],
			['POST', '#^/correo/cuenta/desconectar$#', [Controllers\MailController::class, 'disconnect']],
			['GET', '#^/correo/nuevo$#', [Controllers\MailController::class, 'compose']],
			['GET', '#^/correo/enviados$#', [Controllers\MailController::class, 'sent']],
			['POST', '#^/correo/(\d+)/responder$#', [Controllers\MailController::class, 'reply']],
			['POST', '#^/correo/(\d+)/eliminar$#', [Controllers\MailController::class, 'destroy']],
			['POST', '#^/correo/(\d+)/estado$#', [Controllers\MailController::class, 'status']],
			['POST', '#^/correo/lote$#', [Controllers\MailController::class, 'bulk']],
			['GET', '#^/correo/adjunto/(\d+)$#', [Controllers\MailController::class, 'attachment']],
			['GET', '#^/correo/(\d+)$#', [Controllers\MailController::class, 'show']],
			['GET', '#^/correo$#', [Controllers\MailController::class, 'inbox']],
			['POST', '#^/correo$#', [Controllers\MailController::class, 'send']],
			['GET', '#^/clientes/nuevo$#', [Controllers\ClientController::class, 'create']],
			['POST', '#^/clientes$#', [Controllers\ClientController::class, 'store']],
			['GET', '#^/clientes/(\d+)/cotizacion$#', [Controllers\QuoteController::class, 'create']],
			['POST', '#^/clientes/(\d+)/cotizacion$#', [Controllers\QuoteController::class, 'store']],
			['POST', '#^/clientes/(\d+)/proyecto$#', [Controllers\BoardController::class, 'storeProject']],
			['POST', '#^/proyectos/(\d+)/contactos$#', [Controllers\BoardController::class, 'assignContacts']],
			['POST', '#^/proyectos/(\d+)/eliminar$#', [Controllers\BoardController::class, 'destroyProject']],
			['GET', '#^/proyectos/(\d+)/cotizacion$#', [Controllers\QuoteController::class, 'createForDeal']],
			['POST', '#^/proyectos/(\d+)/cotizacion$#', [Controllers\QuoteController::class, 'storeForDeal']],
			['POST', '#^/clientes/(\d+)/comentarios/(\d+)/eliminar$#', [Controllers\ClientController::class, 'destroyComment']],
			['POST', '#^/clientes/(\d+)/eliminar$#', [Controllers\ClientController::class, 'destroy']],
			['POST', '#^/clientes/(\d+)/comentario$#', [Controllers\ClientController::class, 'comment']],
			['GET', '#^/clientes/(\d+)$#', [Controllers\ClientController::class, 'show']],
			['POST', '#^/clientes/(\d+)$#', [Controllers\ClientController::class, 'update']],
			['GET', '#^/clientes$#', [Controllers\ClientController::class, 'index']],
			['GET', '#^/cotizaciones/(\d+)/preview$#', [Controllers\QuoteController::class, 'preview']],
			['POST', '#^/cotizaciones/(\d+)/enviar$#', [Controllers\QuoteController::class, 'send']],
			['POST', '#^/cotizaciones/(\d+)/eliminar$#', [Controllers\QuoteController::class, 'destroy']],
			['GET', '#^/cotizaciones/(\d+)$#', [Controllers\QuoteController::class, 'show']],
			['POST', '#^/cotizaciones/(\d+)$#', [Controllers\QuoteController::class, 'update']],
			['POST', '#^/equipo/firma-imagen$#', [Controllers\TeamController::class, 'signatureImage']],
			['POST', '#^/equipo/(\d+)/firma$#', [Controllers\TeamController::class, 'signature']],
			['POST', '#^/equipo/(\d+)/eliminar$#', [Controllers\TeamController::class, 'destroy']],
			['GET', '#^/admin/estadisticas$#', [Controllers\AdminController::class, 'stats']],
			['GET', '#^/admin$#', [Controllers\AdminController::class, 'index']],
			['GET', '#^/equipo$#', [Controllers\TeamController::class, 'index']],
			['POST', '#^/equipo$#', [Controllers\TeamController::class, 'store']],
			['GET', '#^/nueva$#', [Controllers\ClientController::class, 'create']],
			['GET', '#^/t/(\d+)$#', [Controllers\ClientController::class, 'fromDeal']],
			['GET', '#^/q/([a-zA-Z0-9]+)$#', [Controllers\PublicQuoteController::class, 'show']],
			['POST', '#^/q/([a-zA-Z0-9]+)$#', [Controllers\PublicQuoteController::class, 'respond']],
		];

		foreach ($routes as [$verb, $pattern, $handler]) {
			if ($verb !== $method) {
				continue;
			}
			if (!preg_match($pattern, $path, $matches)) {
				continue;
			}
			array_shift($matches);
			[$class, $action] = $handler;
			(new $class())->{$action}(...$matches);
			return;
		}

		http_response_code(404);
		echo 'Página no encontrada.';
	}

	public static function origin(): string
	{
		$host = $_SERVER['HTTP_HOST'] ?? 'mizo.cl';
		$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $host === 'mizo.cl';
		return ($https ? 'https' : 'http') . '://' . $host;
	}

	public static function absolute(string $path): string
	{
		$path = '/' . ltrim($path, '/');
		return self::origin() . Http::url($path);
	}
}
