<?php
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\ChatMessage;
use MizoCrm\Models\MailMessage;

$path = Http::path();
$unreadMail = !empty($user) ? MailMessage::unreadCount((int) $user['id']) : 0;
$unreadChat = !empty($user) ? ChatMessage::unreadCount((int) $user['id']) : 0;
$liveUnread = 0;
if (Auth::isAdmin()) {
	try {
		$liveUnread = \MizoCrm\Models\SiteLive::unread();
	} catch (\Throwable $e) {
		$liveUnread = 0;
	}
}
$onMail = str_starts_with($path, '/correo');
$onChat = str_starts_with($path, '/chat');
$onBoard = $path === '/' || str_starts_with($path, '/tablero');
$onClients = str_starts_with($path, '/clientes') || str_starts_with($path, '/cotizaciones');
$onCatalog = str_starts_with($path, '/catalogo');
$onMarketing = str_starts_with($path, '/marketing');
$onQuoteEditor = str_ends_with($path, '/cotizacion')
	|| (bool) preg_match('#^/cotizaciones/\d+(?:/(?:enviar|reenviar|correo|copiar|preview))?$#', $path);
?>
<!doctype html>
<html lang="es-CL">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="#2b579a">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-title" content="Mizo CRM">
	<link rel="manifest" href="<?= h(Http::url('/manifest.webmanifest')) ?>">
	<link rel="apple-touch-icon" href="<?= h(Http::url('/icon-192.png')) ?>">
	<title><?= h(($title ?? 'Clientes') . ' | Mizo') ?></title>
	<link rel="stylesheet" href="<?= h(Http::url('/assets/app.css')) ?>?v=72">
	<?php if ($onMarketing): ?>
		<link rel="stylesheet" href="<?= h(Http::url('/assets/marketing.css')) ?>?v=12">
	<?php endif; ?>
</head>
<?php
	$vapidPublic = '';
	try {
		$vapidPublic = WebPush::publicKey();
	} catch (\Throwable $e) {
		$vapidPublic = '';
	}
?>
<body class="<?= $onMail ? 'is-gmail' : '' ?><?= $onChat ? ' is-chat' : '' ?><?= $onBoard ? ' is-board' : '' ?><?= $onClients ? ' is-clients' : '' ?><?= $onMarketing ? ' is-marketing' : '' ?><?= $onQuoteEditor ? ' is-quote-editor' : '' ?>" data-crm-base="<?= h(Http::base()) ?>" data-vapid="<?= h($vapidPublic) ?>" data-csrf="<?= h(Csrf::token()) ?>"<?= Auth::isAdmin() ? ' data-vivo="' . h(Http::url('/visitas/vivo')) . '"' : '' ?>>
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
			<a data-crm-section="clientes" data-crm-fixed data-crm-home="<?= h(Http::url('/clientes')) ?>" class="<?= $onClients && !str_starts_with($path, '/cotizaciones') ? 'is-on' : '' ?>" href="<?= h(Http::url('/clientes')) ?>">Clientes</a>
			<a data-crm-section="cotizaciones" data-crm-fixed data-crm-home="<?= h(Http::url('/cotizaciones')) ?>" class="<?= str_starts_with($path, '/cotizaciones') ? 'is-on' : '' ?>" href="<?= h(Http::url('/cotizaciones')) ?>">Cotizaciones</a>
			<a data-crm-section="marketing" data-crm-home="<?= h(Http::url('/marketing')) ?>" class="<?= $onMarketing ? 'is-on' : '' ?>" href="<?= h(Http::url('/marketing')) ?>">Marketing</a>
			<a id="nav-mail" data-crm-section="correo" data-crm-home="<?= h(Http::url('/correo')) ?>" class="<?= $onMail ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo')) ?>">Correo <span class="mail-badge" id="mail-badge"<?= $unreadMail > 0 ? '' : ' hidden' ?>><?= (int) $unreadMail ?></span></a>
			<a id="nav-chat" data-crm-section="chat" data-crm-home="<?= h(Http::url('/chat')) ?>" class="<?= $onChat ? 'is-on' : '' ?>" href="<?= h(Http::url('/chat')) ?>">Chat <span class="mail-badge" id="chat-badge"<?= $unreadChat > 0 ? '' : ' hidden' ?>><?= (int) $unreadChat ?></span></a>
			<?php if (Auth::isAdmin()): ?>
				<a data-crm-section="facturas" data-crm-home="<?= h(Http::url('/facturas')) ?>" class="<?= str_starts_with($path, '/facturas') ? 'is-on' : '' ?>" href="<?= h(Http::url('/facturas')) ?>">Facturas</a>
				<a data-crm-section="contabilidad" data-crm-home="<?= h(Http::url('/contabilidad')) ?>" class="<?= str_starts_with($path, '/contabilidad') ? 'is-on' : '' ?>" href="<?= h(Http::url('/contabilidad')) ?>">Contabilidad</a>
				<a data-crm-section="sitio" data-crm-home="<?= h(Http::url('/sitio')) ?>" class="<?= str_starts_with($path, '/sitio') ? 'is-on' : '' ?>" href="<?= h(Http::url('/sitio')) ?>">Sitio</a>
				<a data-crm-section="visitas" data-crm-home="<?= h(Http::url('/visitas')) ?>" class="<?= str_starts_with($path, '/visitas') ? 'is-on' : '' ?>" href="<?= h(Http::url('/visitas')) ?>">Visitas <span class="mail-badge" id="visitas-badge"<?= $liveUnread > 0 ? '' : ' hidden' ?>><?= (int) $liveUnread ?></span></a>
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
			<?php elseif ($onMarketing): ?>
				<a class="btn btn-word" href="<?= h(Http::url('/marketing/nuevo')) ?>">Nuevo correo comercial</a>
			<?php elseif ($onChat): ?>
				<a class="btn btn-word" href="<?= h(Http::url('/chat')) ?>">Chats</a>
			<?php elseif ($onClients && $path !== '/clientes/nuevo'): ?>
				<a class="btn btn-word" href="<?= h(Http::url('/clientes/nuevo')) ?>">Inscribir cliente</a>
			<?php elseif ($onCatalog && $path === '/catalogo'): ?>
				<a class="btn" href="<?= h(Http::url('/catalogo/destacados')) ?>">Destacados</a>
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
	<script src="<?= h(Http::url('/assets/app.js')) ?>?v=66"></script>
	<div id="crm-push" class="crm-install" hidden>
		<p id="crm-push-copy">Activa los avisos para recibir leads, correos y chats aunque el CRM esté cerrado.</p>
		<button type="button" id="crm-push-go">Activar avisos</button>
		<button type="button" id="crm-push-no">Ahora no</button>
	</div>
	<div id="crm-install" class="crm-install" hidden>
		<p id="crm-install-copy">Instala Mizo CRM en este celular para abrirlo como una aplicación.</p>
		<button type="button" id="crm-install-go">Instalar</button>
		<button type="button" id="crm-install-no">Ahora no</button>
	</div>
	<script>
	(function () {
		function vapidKey(value) {
			var pad = '='.repeat((4 - (value.length % 4)) % 4);
			var raw = atob(value.replace(/-/g, '+').replace(/_/g, '/') + pad);
			var out = new Uint8Array(raw.length);
			for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
			return out;
		}
		function subscribePush() {
			var vapid = document.body.getAttribute('data-vapid') || '';
			var csrf = document.body.getAttribute('data-csrf') || '';
			if (!vapid || !('serviceWorker' in navigator) || !('PushManager' in window)) return;
			navigator.serviceWorker.ready.then(function (reg) {
				return reg.pushManager.getSubscription().then(function (sub) {
					if (sub) return sub;
					return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: vapidKey(vapid) });
				});
			}).then(function (sub) {
				return fetch('<?= h(Http::url('/push/suscribir')) ?>', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ _csrf: csrf, subscription: sub.toJSON() })
				});
			}).catch(function () {});
		}
		if ('serviceWorker' in navigator) {
			navigator.serviceWorker.register('<?= h(Http::url('/sw.js')) ?>', { scope: '/crm/' }).then(function () {
				if (window.Notification && Notification.permission === 'granted') subscribePush();
			}).catch(function () {});
		}
		var pushBox = document.getElementById('crm-push');
		var pushCopy = document.getElementById('crm-push-copy');
		var pushDismissed = false;
		try { pushDismissed = localStorage.getItem('crm-push-no') === '1'; } catch (error) {}
		if (pushBox && window.Notification && Notification.permission === 'default' && !pushDismissed) {
			if (/iphone|ipad|ipod/i.test(navigator.userAgent) && !(window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone)) {
				pushCopy.textContent = 'En iPhone instala Mizo CRM y luego activa los avisos. Solo así llegan con la app cerrada.';
			}
			pushBox.hidden = false;
		}
		var pushGo = document.getElementById('crm-push-go');
		if (pushGo) {
			pushGo.addEventListener('click', function () {
				if (!window.Notification) return;
				Notification.requestPermission().then(function (perm) {
					if (perm === 'granted') subscribePush();
					if (pushBox) pushBox.hidden = true;
				});
			});
		}
		var pushNo = document.getElementById('crm-push-no');
		if (pushNo) {
			pushNo.addEventListener('click', function () {
				if (pushBox) pushBox.hidden = true;
				try { localStorage.setItem('crm-push-no', '1'); } catch (error) {}
			});
		}
		var box = document.getElementById('crm-install');
		var copy = document.getElementById('crm-install-copy');
		var go = document.getElementById('crm-install-go');
		var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
		var dismissed = false;
		try { dismissed = sessionStorage.getItem('crm-install-no') === '1'; } catch (error) {}
		var deferred = null;
		window.addEventListener('beforeinstallprompt', function (event) {
			event.preventDefault();
			deferred = event;
			if (!standalone && !dismissed) box.hidden = false;
		});
		var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
		if (ios && !standalone && !dismissed) {
			copy.textContent = 'En Safari toca Compartir y luego “Agregar a inicio” para instalar Mizo CRM.';
			go.hidden = true;
			box.hidden = false;
		}
		go.addEventListener('click', function () {
			if (!deferred) return;
			deferred.prompt();
			deferred.userChoice.finally(function () { box.hidden = true; deferred = null; });
		});
		document.getElementById('crm-install-no').addEventListener('click', function () {
			box.hidden = true;
			try { sessionStorage.setItem('crm-install-no', '1'); } catch (error) {}
		});

		var vivoUrl = document.body.getAttribute('data-vivo');
		if (!vivoUrl) return;
		var lastUnread = null;
		var baseTitle = document.title;
		function beep() {
			try {
				var ctx = new (window.AudioContext || window.webkitAudioContext)();
				var osc = ctx.createOscillator();
				var gain = ctx.createGain();
				osc.frequency.value = 880;
				osc.connect(gain);
				gain.connect(ctx.destination);
				gain.gain.setValueAtTime(0.06, ctx.currentTime);
				osc.start();
				osc.stop(ctx.currentTime + 0.18);
				setTimeout(function () { ctx.close(); }, 500);
			} catch (error) {}
			if (navigator.vibrate) navigator.vibrate([140, 60, 140]);
		}
		function watch() {
			fetch(vivoUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
				.then(function (res) { return res.json(); })
				.then(function (data) {
					var unread = Number(data && data.unread) || 0;
					var badge = document.getElementById('visitas-badge');
					if (badge) {
						badge.hidden = unread < 1;
						badge.textContent = String(unread);
					}
					if (lastUnread !== null && unread > lastUnread) {
						beep();
						document.title = '(' + unread + ') Mensaje en el sitio';
						if (window.Notification && Notification.permission === 'granted') {
							new Notification('Mensaje en el sitio', { body: 'Un visitante escribió en el chat de mizo.cl' });
						}
					} else if (unread < 1) {
						document.title = baseTitle;
					}
					lastUnread = unread;
				})
				.catch(function () {});
		}
		if (window.Notification && Notification.permission === 'default') {
			document.addEventListener('click', function once() {
				Notification.requestPermission();
				document.removeEventListener('click', once);
			});
		}
		watch();
		setInterval(watch, 5000);
	})();
	</script>
</body>
</html>
