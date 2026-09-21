<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$client = $client ?? null;
$to = $to ?? '';
$subject = $subject ?? '';
?>
<div class="page-head">
	<div>
		<a class="back" href="<?= h(Http::url('/correo')) ?>">← Bandeja</a>
		<h1>Nuevo correo</h1>
		<p>Sale desde tu casilla. Si el cliente responde, te llega a Bandeja.</p>
	</div>
</div>
<form class="paper form" method="post" action="<?= h(Http::url('/correo')) ?>" style="max-width:720px">
	<?= Csrf::field() ?>
	<?php if ($client): ?>
		<input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
		<p class="muted">Cliente: <a href="<?= h(Http::url('/clientes/' . $client['id'])) ?>"><?= h($client['name']) ?></a></p>
	<?php endif; ?>
	<label><span>Para</span><input name="to" type="email" value="<?= h($to) ?>" required></label>
	<label><span>Asunto</span><input name="subject" value="<?= h($subject) ?>" required></label>
	<label><span>Mensaje</span><textarea name="body" rows="10" required></textarea></label>
	<div class="form-actions">
		<button class="btn btn-word" type="submit">Enviar</button>
		<a class="btn" href="<?= h(Http::url('/correo')) ?>">Cancelar</a>
	</div>
</form>
