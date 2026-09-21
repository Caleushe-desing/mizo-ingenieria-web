<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$mailbox = $mailbox ?? null;
$email = $email ?? '';
?>
<div class="page-head">
	<div>
		<a class="back" href="<?= h(Http::url('/correo')) ?>">← Correo</a>
		<h1>Mi casilla</h1>
		<p>Conecta el correo de Mizo con el que atiendes a los clientes. No uses la clave del CRM, sino la de esa casilla.</p>
	</div>
</div>
<div class="stack" style="max-width:560px">
	<?php if ($mailbox): ?>
		<section class="paper">
			<h2 class="section-title excel">Conectada</h2>
			<p>Los correos salen y entran por <strong><?= h($mailbox['email']) ?></strong>.</p>
			<form method="post" action="<?= h(Http::url('/correo/cuenta/desconectar')) ?>" onsubmit="return confirm('¿Desconectar esta casilla del CRM?');" style="margin-top:16px">
				<?= Csrf::field() ?>
				<button class="btn-danger-text" type="submit">Desconectar casilla</button>
			</form>
		</section>
	<?php endif; ?>
	<form class="paper form" method="post" action="<?= h(Http::url('/correo/cuenta')) ?>">
		<h2 class="section-title word"><?= $mailbox ? 'Cambiar casilla' : 'Conectar casilla' ?></h2>
		<?= Csrf::field() ?>
		<label><span>Correo</span><input name="email" type="email" value="<?= h($mailbox['email'] ?? $email) ?>" required></label>
		<label><span>Clave del correo</span><input name="password" type="password" required autocomplete="off"></label>
		<p class="muted">Es la misma clave con la que entras a esa casilla en el webmail. Al conectar se prueba el envío y la bandeja.</p>
		<div class="form-actions">
			<button class="btn btn-word" type="submit">Conectar</button>
		</div>
	</form>
</div>
