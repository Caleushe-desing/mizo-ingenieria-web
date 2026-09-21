<?php
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\MailMessage;

$path = Http::path();
$unreadMail = !empty($user) ? MailMessage::unreadCount((int) $user['id']) : 0;
$onMail = str_starts_with($path, '/correo');
?>
<!doctype html>
<html lang="es-CL">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= h(($title ?? 'Clientes') . ' | Mizo') ?></title>
	<link rel="stylesheet" href="<?= h(Http::url('/assets/app.css')) ?>?v=4">
</head>
<body class="<?= $onMail ? 'is-gmail' : '' ?>">
	<header class="titlebar">
		<img src="/mizo-logo-footer.png" alt="Mizo">
		<small>Clientes, cotizaciones y correo</small>
	</header>
	<div class="ribbon">
		<nav class="ribbon-nav">
			<a class="<?= $path === '/' || str_starts_with($path, '/clientes') || str_starts_with($path, '/cotizaciones') ? 'is-on' : '' ?>" href="<?= h(Http::url('/')) ?>">Clientes</a>
			<a id="nav-mail" class="<?= $onMail ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo')) ?>">Correo <span class="mail-badge" id="mail-badge"<?= $unreadMail > 0 ? '' : ' hidden' ?>><?= (int) $unreadMail ?></span></a>
			<?php if (Auth::isAdmin()): ?>
				<a class="<?= $path === '/equipo' ? 'is-on' : '' ?>" href="<?= h(Http::url('/equipo')) ?>">Equipo</a>
			<?php endif; ?>
		</nav>
		<div class="ribbon-live" id="live-alert" data-live-url="<?= h(Http::url('/avisos')) ?>" hidden>
			<a class="live-alert" href="#">
				<span class="live-alert-dot"></span>
				<span class="live-alert-copy">
					<strong></strong>
					<span></span>
				</span>
			</a>
		</div>
		<div class="ribbon-actions">
			<?php if (!empty($user)): ?>
				<span class="who"><?= h($user['name']) ?></span>
				<form method="post" action="<?= h(Http::url('/logout')) ?>">
					<?= Csrf::field() ?>
					<button class="btn-text" type="submit">Salir</button>
				</form>
			<?php endif; ?>
			<?php if ($onMail): ?>
				<a class="btn btn-word" href="<?= h(Http::url('/correo/nuevo')) ?>">Nuevo correo</a>
			<?php else: ?>
				<a class="btn btn-excel" href="<?= h(Http::url('/clientes/nuevo')) ?>">Nuevo cliente</a>
			<?php endif; ?>
		</div>
	</div>
	<main class="workspace<?= $onMail ? ' workspace-mail' : '' ?>">
		<?php if (!empty($flash)): ?>
			<div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
		<?php endif; ?>
		<?= $content ?>
	</main>
	<script src="<?= h(Http::url('/assets/app.js')) ?>?v=3"></script>
</body>
</html>
