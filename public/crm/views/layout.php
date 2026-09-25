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
$onBoard = $path === '/' || str_starts_with($path, '/tablero');
$onClients = str_starts_with($path, '/clientes') || str_starts_with($path, '/cotizaciones');
$onCatalog = str_starts_with($path, '/catalogo');
?>
<!doctype html>
<html lang="es-CL">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= h(($title ?? 'Clientes') . ' | Mizo') ?></title>
	<link rel="stylesheet" href="<?= h(Http::url('/assets/app.css')) ?>?v=48">
</head>
<body class="<?= $onMail ? 'is-gmail' : '' ?><?= $onChat ? ' is-chat' : '' ?><?= $onBoard ? ' is-board' : '' ?><?= $onClients ? ' is-clients' : '' ?>" data-crm-base="<?= h(Http::base()) ?>">
	<header class="titlebar">
		<img src="/mizo-logo-footer.png" alt="Mizo">
		<small>Clientes, cotizaciones y correo</small>
	</header>
	<div class="ribbon">
		<div class="ribbon-start">
		<div class="crm-hist" data-crm-hist>
			<button type="button" data-hist="back" aria-label="Atrás" disabled>‹</button>
			<button type="button" data-hist="forward" aria-label="Adelante" disabled>›</button>
		</div>
		<nav class="ribbon-nav" data-crm-nav>
			<a data-crm-section="tablero" data-crm-fixed data-crm-home="<?= h(Http::url('/')) ?>" class="<?= $onBoard ? 'is-on' : '' ?>" href="<?= h(Http::url('/')) ?>">Tablero</a>
			<a data-crm-section="clientes" data-crm-fixed data-crm-home="<?= h(Http::url('/clientes')) ?>" class="<?= $onClients ? 'is-on' : '' ?>" href="<?= h(Http::url('/clientes')) ?>">Clientes</a>
			<a id="nav-mail" data-crm-section="correo" data-crm-home="<?= h(Http::url('/correo')) ?>" class="<?= $onMail ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo')) ?>">Correo <span class="mail-badge" id="mail-badge"<?= $unreadMail > 0 ? '' : ' hidden' ?>><?= (int) $unreadMail ?></span></a>
			<a id="nav-chat" data-crm-section="chat" data-crm-home="<?= h(Http::url('/chat')) ?>" class="<?= $onChat ? 'is-on' : '' ?>" href="<?= h(Http::url('/chat')) ?>">Chat <span class="mail-badge" id="chat-badge"<?= $unreadChat > 0 ? '' : ' hidden' ?>><?= (int) $unreadChat ?></span></a>
			<?php if (Auth::isAdmin()): ?>
				<a data-crm-section="facturas" data-crm-home="<?= h(Http::url('/facturas')) ?>" class="<?= str_starts_with($path, '/facturas') ? 'is-on' : '' ?>" href="<?= h(Http::url('/facturas')) ?>">Facturas</a>
				<a data-crm-section="contabilidad" data-crm-home="<?= h(Http::url('/contabilidad')) ?>" class="<?= str_starts_with($path, '/contabilidad') ? 'is-on' : '' ?>" href="<?= h(Http::url('/contabilidad')) ?>">Contabilidad</a>
				<a data-crm-section="catalogo" data-crm-home="<?= h(Http::url('/catalogo')) ?>" class="<?= $onCatalog ? 'is-on' : '' ?>" href="<?= h(Http::url('/catalogo')) ?>">Catálogo</a>
				<a data-crm-section="control" data-crm-home="<?= h(Http::url('/admin')) ?>" class="<?= str_starts_with($path, '/admin') ? 'is-on' : '' ?>" href="<?= h(Http::url('/admin')) ?>">Control</a>
				<a data-crm-section="equipo" data-crm-home="<?= h(Http::url('/equipo')) ?>" class="<?= $path === '/equipo' ? 'is-on' : '' ?>" href="<?= h(Http::url('/equipo')) ?>">Equipo</a>
			<?php endif; ?>
		</nav>
		</div>
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
			<?php elseif ($onChat): ?>
				<a class="btn btn-word" href="<?= h(Http::url('/chat')) ?>">Chats</a>
			<?php elseif ($onClients && $path !== '/clientes/nuevo'): ?>
				<a class="btn btn-word" href="<?= h(Http::url('/clientes/nuevo')) ?>">Inscribir cliente</a>
			<?php elseif ($onCatalog && $path === '/catalogo'): ?>
				<a class="btn btn-word" href="<?= h(Http::url('/catalogo/nuevo')) ?>">Nuevo producto</a>
			<?php endif; ?>
		</div>
	</div>
	<main class="workspace<?= $onMail ? ' workspace-mail' : '' ?><?= $onChat ? ' workspace-chat' : '' ?>">
		<?php if (!empty($flash)): ?>
			<div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
		<?php endif; ?>
		<?= $content ?>
	</main>
	<script src="<?= h(Http::url('/assets/app.js')) ?>?v=46"></script>
</body>
</html>
