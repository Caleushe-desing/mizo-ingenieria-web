<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$client = $client ?? null;
$to = $to ?? '';
$subject = $subject ?? '';
$folder = 'compose';
$unread = (int) ($unread ?? 0);
?>
<div class="gmail">
	<?php require __DIR__ . '/nav.php'; ?>
	<section class="gmail-main">
		<form class="gmail-window" method="post" action="<?= h(Http::url('/correo')) ?>">
			<div class="gmail-window-head">Mensaje nuevo</div>
			<?= Csrf::field() ?>
			<?php if ($client): ?>
				<input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
			<?php endif; ?>
			<label class="gmail-field">
				<span>Para</span>
				<input name="to" type="email" value="<?= h($to) ?>" required placeholder="destinatario@correo.cl">
			</label>
			<label class="gmail-field">
				<span>Asunto</span>
				<input name="subject" value="<?= h($subject) ?>" required placeholder="Asunto">
			</label>
			<?php if ($client): ?>
				<p class="gmail-client-hint">Cliente: <a href="<?= h(Http::url('/clientes/' . $client['id'])) ?>"><?= h($client['name']) ?></a></p>
			<?php endif; ?>
			<textarea class="gmail-compose-body" name="body" required placeholder="Redacta tu mensaje"></textarea>
			<div class="gmail-window-actions">
				<button class="gmail-send" type="submit">Enviar</button>
				<a href="<?= h(Http::url('/correo')) ?>">Descartar</a>
			</div>
		</form>
	</section>
</div>
