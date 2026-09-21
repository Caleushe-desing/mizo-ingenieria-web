<?php
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;

$path = Http::path();
?>
<!doctype html>
<html lang="es-CL">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= h(($title ?? 'Clientes') . ' | Mizo') ?></title>
	<link rel="stylesheet" href="<?= h(Http::url('/assets/app.css')) ?>">
</head>
<body>
	<header class="titlebar">
		<img src="/mizo-logo-footer.png" alt="Mizo">
		<small>Clientes y cotizaciones</small>
	</header>
	<div class="ribbon">
		<nav class="ribbon-nav">
			<a class="<?= $path === '/' || str_starts_with($path, '/clientes') || str_starts_with($path, '/cotizaciones') ? 'is-on' : '' ?>" href="<?= h(Http::url('/')) ?>">Clientes</a>
			<?php if (Auth::isAdmin()): ?>
				<a class="<?= $path === '/equipo' ? 'is-on' : '' ?>" href="<?= h(Http::url('/equipo')) ?>">Equipo</a>
			<?php endif; ?>
		</nav>
		<div class="ribbon-actions">
			<?php if (!empty($user)): ?>
				<span class="who"><?= h($user['name']) ?></span>
				<form method="post" action="<?= h(Http::url('/logout')) ?>">
					<?= Csrf::field() ?>
					<button class="btn-text" type="submit">Salir</button>
				</form>
			<?php endif; ?>
			<a class="btn btn-excel" href="<?= h(Http::url('/clientes/nuevo')) ?>">Nuevo cliente</a>
		</div>
	</div>
	<main class="workspace">
		<?php if (!empty($flash)): ?>
			<div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
		<?php endif; ?>
		<?= $content ?>
	</main>
	<script src="<?= h(Http::url('/assets/app.js')) ?>"></script>
</body>
</html>
