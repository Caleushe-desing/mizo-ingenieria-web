<?php
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mail\Mime;

$message = $message ?? null;
$client = $client ?? null;
$query = trim((string) ($query ?? ''));
if (!$message) {
	return;
}
$peer = $message['folder'] === 'sent'
	? (string) $message['to_email']
	: (string) ($message['from_name'] ?: $message['from_email']);
$html = trim((string) ($message['body_html'] ?? ''));
$text = trim((string) ($message['body_text'] ?? ''));
$back = $message['folder'] === 'sent' ? '/correo/enviados' : '/correo';
$isUnread = (int) ($message['seen'] ?? 0) === 0;
$isImportant = (int) ($message['important'] ?? 0) === 1;
$here = '/correo/' . (int) $message['id'] . ($query !== '' ? '?q=' . rawurlencode($query) : '');
$listBack = $back . ($query !== '' ? '?q=' . rawurlencode($query) : '');
?>
<div class="gmail-read">
	<div class="gmail-read-top">
		<a class="gmail-back" href="<?= h(Http::url($listBack)) ?>">← Lista</a>
		<h1><?= h($message['subject'] ?: '(sin asunto)') ?></h1>
		<div class="gmail-read-actions">
			<form method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/estado')) ?>">
				<?= Csrf::field() ?>
				<input type="hidden" name="action" value="<?= $isImportant ? 'unimportant' : 'important' ?>">
				<input type="hidden" name="back" value="<?= h($here) ?>">
				<button class="gmail-icon-btn<?= $isImportant ? ' is-important' : '' ?>" type="submit" title="<?= $isImportant ? 'Quitar importante' : 'Marcar importante' ?>">
					<?= $isImportant ? '★ Importante' : '☆ Importante' ?>
				</button>
			</form>
			<?php if ($message['folder'] === 'inbox'): ?>
				<form method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/estado')) ?>">
					<?= Csrf::field() ?>
					<input type="hidden" name="action" value="<?= $isUnread ? 'read' : 'unread' ?>">
					<input type="hidden" name="back" value="<?= h($here) ?>">
					<button class="gmail-icon-btn" type="submit">
						<?= $isUnread ? 'Marcar leído' : 'Marcar no leído' ?>
					</button>
				</form>
			<?php endif; ?>
			<form method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Quitar este correo de la lista del CRM?');">
				<?= Csrf::field() ?>
				<button class="gmail-icon-btn" type="submit">Quitar</button>
			</form>
		</div>
	</div>
	<div class="gmail-read-meta">
		<span class="gmail-avatar" style="background:<?= h(mail_avatar_color($peer)) ?>"><?= h(initials($peer)) ?></span>
		<div>
			<strong><?= h($peer) ?></strong>
			<small>
				<?= $message['folder'] === 'sent' ? 'para ' . h($message['to_email']) : 'para mí' ?>
				· <?= h(mail_when($message['sent_at'] ?? null)) ?>
				<?php if ($isUnread): ?>
					· <span class="gmail-status-pill">No leído</span>
				<?php else: ?>
					· <span class="gmail-status-pill is-read">Leído</span>
				<?php endif; ?>
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
