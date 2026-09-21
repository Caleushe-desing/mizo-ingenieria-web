<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$mailbox = $mailbox ?? null;
$email = $email ?? '';
$folder = 'account';
$unread = (int) ($unread ?? 0);
?>
<div class="gmail">
	<?php require __DIR__ . '/nav.php'; ?>
	<section class="gmail-main">
		<div class="gmail-settings">
			<h1>Configuración</h1>
			<p>Conecta el correo de Mizo con el que atiendes a los clientes. No uses la clave del CRM, sino la de esa casilla.</p>
			<?php if ($mailbox): ?>
				<div class="gmail-card">
					<strong>Casilla conectada</strong>
					<p>Los correos salen y entran por <b><?= h($mailbox['email']) ?></b>.</p>
					<form method="post" action="<?= h(Http::url('/correo/cuenta/desconectar')) ?>" onsubmit="return confirm('¿Desconectar esta casilla del CRM?');">
						<?= Csrf::field() ?>
						<button class="gmail-link-danger" type="submit">Desconectar casilla</button>
					</form>
				</div>
			<?php endif; ?>
			<form class="gmail-card gmail-settings-form" method="post" action="<?= h(Http::url('/correo/cuenta')) ?>">
				<strong><?= $mailbox ? 'Cambiar casilla' : 'Conectar casilla' ?></strong>
				<?= Csrf::field() ?>
				<label><span>Correo</span><input name="email" type="email" value="<?= h($mailbox['email'] ?? $email) ?>" required></label>
				<label><span>Clave del correo</span><input name="password" type="password" required autocomplete="off"></label>
				<p>Es la misma clave con la que entras a esa casilla en el webmail.</p>
				<button class="gmail-send" type="submit">Conectar</button>
			</form>
		</div>
	</section>
</div>
