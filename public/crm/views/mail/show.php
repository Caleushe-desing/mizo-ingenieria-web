<?php
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mail\Mime;

$message = $message ?? [];
$client = $client ?? null;
$peer = $message['folder'] === 'inbox' ? ($message['from_name'] ?: $message['from_email']) : $message['to_email'];
$html = trim((string) ($message['body_html'] ?? ''));
$text = trim((string) ($message['body_text'] ?? ''));
?>
<div class="page-head">
	<div>
		<a class="back" href="<?= h(Http::url($message['folder'] === 'sent' ? '/correo/enviados' : '/correo')) ?>">← Correos</a>
		<h1><?= h($message['subject'] ?: '(sin asunto)') ?></h1>
		<p>
			<?= $message['folder'] === 'sent' ? 'Para' : 'De' ?>: <?= h($peer) ?>
			· <?= h(when($message['sent_at'])) ?>
			<?php if ($client): ?>
				· Cliente: <a href="<?= h(Http::url('/clientes/' . $client['id'])) ?>"><?= h($client['name']) ?></a>
			<?php endif; ?>
		</p>
	</div>
	<div class="page-head-actions">
		<form method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Quitar este correo de la lista del CRM?');">
			<?= Csrf::field() ?>
			<button class="btn-danger-text" type="submit">Quitar de la lista</button>
		</form>
	</div>
</div>

<div class="stack">
	<section class="paper mail-read">
		<?php if ($html !== ''): ?>
			<div class="mail-body"><?= Mime::safeHtml($html) ?></div>
		<?php else: ?>
			<div class="mail-body"><p><?= nl2br(h($text !== '' ? $text : 'Este correo no tiene texto.')) ?></p></div>
		<?php endif; ?>
	</section>

	<?php if ($message['folder'] === 'inbox'): ?>
		<section class="paper">
			<h2 class="section-title word">Responder</h2>
			<p class="muted">La respuesta sale desde tu casilla. El cliente te escribe de vuelta aquí.</p>
			<form class="form" method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/responder')) ?>" style="margin-top:12px">
				<?= Csrf::field() ?>
				<label>
					<span>Mensaje</span>
					<textarea name="body" rows="6" required placeholder="Escribe la respuesta..."></textarea>
				</label>
				<div class="form-actions">
					<button class="btn btn-word" type="submit">Enviar respuesta</button>
				</div>
			</form>
		</section>
	<?php endif; ?>
</div>
