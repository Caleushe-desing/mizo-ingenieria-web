<?php
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mail\Mime;

$message = $message ?? null;
$client = $client ?? null;
if (!$message) {
	return;
}
$peer = $message['folder'] === 'sent'
	? (string) $message['to_email']
	: (string) ($message['from_name'] ?: $message['from_email']);
$html = trim((string) ($message['body_html'] ?? ''));
$text = trim((string) ($message['body_text'] ?? ''));
$back = $message['folder'] === 'sent' ? '/correo/enviados' : '/correo';
?>
<div class="gmail-read">
	<div class="gmail-read-top">
		<a class="gmail-back" href="<?= h(Http::url($back)) ?>">← Lista</a>
		<h1><?= h($message['subject'] ?: '(sin asunto)') ?></h1>
		<form method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Quitar este correo de la lista del CRM?');">
			<?= Csrf::field() ?>
			<button class="gmail-icon-btn" type="submit">Quitar</button>
		</form>
	</div>
	<div class="gmail-read-meta">
		<span class="gmail-avatar" style="background:<?= h(mail_avatar_color($peer)) ?>"><?= h(initials($peer)) ?></span>
		<div>
			<strong><?= h($peer) ?></strong>
			<small>
				<?= $message['folder'] === 'sent' ? 'para ' . h($message['to_email']) : 'para mí' ?>
				· <?= h(mail_when($message['sent_at'] ?? null)) ?>
				<?php if ($client): ?>
					· <a href="<?= h(Http::url('/clientes/' . $client['id'])) ?>"><?= h($client['name']) ?></a>
				<?php endif; ?>
			</small>
		</div>
	</div>
	<?php if ($html !== ''): ?>
		<div class="mail-body"><?= Mime::safeHtml($html) ?></div>
	<?php else: ?>
		<div class="mail-body"><p><?= nl2br(h($text !== '' ? $text : 'Este correo no tiene texto.')) ?></p></div>
	<?php endif; ?>

	<?php if ($message['folder'] === 'inbox'): ?>
		<form class="gmail-reply" method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/responder')) ?>">
			<?= Csrf::field() ?>
			<label>
				<span>Responder a <?= h($peer) ?></span>
				<textarea name="body" rows="6" required placeholder="Redacta tu respuesta"></textarea>
			</label>
			<button class="gmail-send" type="submit">Enviar</button>
		</form>
	<?php endif; ?>
</div>
