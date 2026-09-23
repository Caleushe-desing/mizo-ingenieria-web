<?php
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\ChatMessage;
use MizoCrm\Models\MailMessage;

$path = Http::path();
$unreadMail = !empty($user) ? MailMessage::unreadCount((int) $user['id']) : 0;
$unreadChat = !empty($user) ? ChatMessage::unreadCount((int) $user['id']) : 0;
$onMail = str_starts_with($path, '/correo');
$onChat = str_starts_with($path, '/chat');
$showRail = !empty($user);
?>
<!doctype html>
<html lang="es-CL">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= h(($title ?? 'Clientes') . ' | Mizo') ?></title>
	<link rel="stylesheet" href="<?= h(Http::url('/assets/app.css')) ?>?v=17">
</head>
<body class="<?= $onMail ? 'is-gmail' : '' ?><?= $onChat ? ' is-chat' : '' ?><?= $showRail ? ' has-rail' : '' ?>" data-crm-base="<?= h(Http::base()) ?>">
	<header class="titlebar">
		<img src="/mizo-logo-footer.png" alt="Mizo">
		<small>Clientes, cotizaciones y correo</small>
	</header>
	<div class="ribbon">
		<nav class="ribbon-nav" data-crm-nav>
			<a data-crm-section="correo" data-crm-home="<?= h(Http::url('/correo')) ?>" id="nav-mail" class="<?= $onMail ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo')) ?>">Correo <span class="mail-badge" id="mail-badge"<?= $unreadMail > 0 ? '' : ' hidden' ?>><?= (int) $unreadMail ?></span></a>
			<?php if (Auth::isAdmin()): ?>
				<a data-crm-section="equipo" data-crm-home="<?= h(Http::url('/equipo')) ?>" class="<?= $path === '/equipo' ? 'is-on' : '' ?>" href="<?= h(Http::url('/equipo')) ?>">Equipo</a>
			<?php endif; ?>
			<a data-crm-section="clientes" data-crm-home="<?= h(Http::url('/')) ?>" class="<?= $path === '/' || str_starts_with($path, '/clientes') || str_starts_with($path, '/cotizaciones') ? 'is-on' : '' ?>" href="<?= h(Http::url('/')) ?>">Inicio</a>
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
			<?php if ($showRail): ?>
				<div class="panel-dock" data-panel-dock>
					<span class="panel-dock-label">Panel</span>
					<button type="button" class="panel-dock-btn is-chat is-on" data-panel-toggle="chat" aria-pressed="true">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2z"></path></svg>
						Chat
						<span class="mail-badge" id="chat-badge"<?= $unreadChat > 0 ? '' : ' hidden' ?>><?= (int) $unreadChat ?></span>
					</button>
					<button type="button" class="panel-dock-btn is-clients is-on" data-panel-toggle="clientes" aria-pressed="true">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm-8 1a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 8 12zm8 2c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4zM8 14c-.3 0-.7 0-1.1.1A5.2 5.2 0 0 0 2 19v1h4v-2c0-1.2.6-2.3 1.6-3.2A9.7 9.7 0 0 1 8 14z"></path></svg>
						Clientes
					</button>
				</div>
			<?php endif; ?>
			<?php if (!empty($user)): ?>
				<span class="who"><?= h($user['name']) ?></span>
				<form method="post" action="<?= h(Http::url('/logout')) ?>">
					<?= Csrf::field() ?>
					<button class="btn-text" type="submit">Salir</button>
				</form>
			<?php endif; ?>
			<?php if ($onMail): ?>
				<a class="btn btn-word" href="<?= h(Http::url('/correo/nuevo')) ?>">Nuevo correo</a>
			<?php elseif ($onChat): ?>
				<a class="btn btn-word" href="<?= h(Http::url('/chat')) ?>">Chats</a>
			<?php else: ?>
				<a class="btn btn-excel" href="<?= h(Http::url('/clientes/nuevo')) ?>">Nuevo cliente</a>
			<?php endif; ?>
		</div>
	</div>
	<div class="crm-shell<?= $showRail ? '' : ' no-rail' ?>">
		<main class="workspace<?= $onMail ? ' workspace-mail' : '' ?><?= $onChat ? ' workspace-chat' : '' ?>">
			<?php if (!empty($flash)): ?>
				<div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
			<?php endif; ?>
			<?= $content ?>
		</main>
		<?php if ($showRail): ?>
			<div class="crm-split" data-crm-split title="Arrastra para ajustar el panel"></div>
			<?php require __DIR__ . '/partials/rail.php'; ?>
		<?php endif; ?>
	</div>
	<script src="<?= h(Http::url('/assets/app.js')) ?>?v=17"></script>
</body>
</html>
