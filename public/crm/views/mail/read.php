<?php
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mail\Mime;
use MizoCrm\Models\MailAttachment;

$message = $message ?? null;
$client = $client ?? null;
$attachments = $attachments ?? [];
$query = trim((string) ($query ?? ''));
$sort = (string) ($sort ?? 'fecha');
$filter = (string) ($filter ?? 'todos');
if (!$message) {
	return;
}
$peer = $message['folder'] === 'sent'
	? (string) $message['to_email']
	: (string) ($message['from_name'] ?: $message['from_email']);
$html = trim((string) ($message['body_html'] ?? ''));
$text = trim((string) ($message['body_text'] ?? ''));
$back = match ($message['folder']) {
	'sent' => '/correo/enviados',
	'spam' => '/correo/spam',
	default => '/correo',
};
$isUnread = (int) ($message['seen'] ?? 0) === 0;
$isImportant = (int) ($message['important'] ?? 0) === 1;
$qs = array_filter(['q' => $query ?: null, 'orden' => $sort !== 'fecha' ? $sort : null, 'filtro' => $filter !== 'todos' ? $filter : null]);
$here = '/correo/' . (int) $message['id'] . ($qs ? ('?' . http_build_query($qs)) : '');
$listBack = $back . ($qs ? ('?' . http_build_query($qs)) : '');
?>
<div class="gmail-read">
	<div class="gmail-read-top">
		<h1><?= h($message['subject'] ?: '(sin asunto)') ?></h1>
		<div class="gmail-read-actions">
			<button type="button" class="gmail-icon-btn" data-mail-full title="Ver correo a pantalla completa">Pantalla completa</button>
			<form method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/estado')) ?>">
				<?= Csrf::field() ?>
				<input type="hidden" name="action" value="<?= $isImportant ? 'unimportant' : 'important' ?>">
				<input type="hidden" name="back" value="<?= h($here) ?>">
				<button class="gmail-icon-btn<?= $isImportant ? ' is-important' : '' ?>" type="submit"><?= $isImportant ? '★ Importante' : '☆ Importante' ?></button>
			</form>
			<?php if ($message['folder'] === 'inbox' || $message['folder'] === 'spam'): ?>
				<form method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/estado')) ?>">
					<?= Csrf::field() ?>
					<input type="hidden" name="action" value="<?= $message['folder'] === 'spam' ? 'unspam' : 'spam' ?>">
					<input type="hidden" name="back" value="<?= h($here) ?>">
					<button class="gmail-icon-btn" type="submit"><?= $message['folder'] === 'spam' ? 'No es spam' : 'No deseado' ?></button>
				</form>
			<?php endif; ?>
			<?php if ($message['folder'] === 'inbox'): ?>
				<form method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/estado')) ?>">
					<?= Csrf::field() ?>
					<input type="hidden" name="action" value="<?= $isUnread ? 'read' : 'unread' ?>">
					<input type="hidden" name="back" value="<?= h($here) ?>">
					<button class="gmail-icon-btn" type="submit"><?= $isUnread ? 'Marcar leído' : 'Marcar no leído' ?></button>
				</form>
			<?php endif; ?>
			<form method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar este correo de tu casilla? También se borra en el servidor de correo.');">
				<?= Csrf::field() ?>
				<button class="gmail-icon-btn" type="submit">Eliminar</button>
			</form>
		</div>
	</div>
	<div class="gmail-read-meta">
		<span class="gmail-avatar" style="background:<?= h(mail_avatar_color($peer)) ?>"><?= h(initials($peer)) ?></span>
		<div>
			<strong><?= h($peer) ?></strong>
			<small>
				<?= $message['folder'] === 'sent' ? 'para ' . h($message['to_email']) : 'de ' . h($message['from_email'] ?? '') ?>
				<?php if (!empty($message['cc_email'])): ?>
					· cc <?= h($message['cc_email']) ?>
				<?php endif; ?>
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

	<?php if ($attachments): ?>
		<div class="mail-attachments">
			<strong>Adjuntos (<?= count($attachments) ?>)</strong>
			<ul>
				<?php foreach ($attachments as $att): ?>
					<?php
					$kind = MailAttachment::kind((string) $att['mime'], (string) $att['filename']);
					$url = Http::url('/correo/adjunto/' . $att['id']);
					$canPreview = MailAttachment::isPreviewable((string) $att['mime'], (string) $att['filename']);
					?>
					<li class="mail-att mail-att-<?= h($kind) ?>">
						<span class="mail-att-name"><?= h($att['filename']) ?></span>
						<small><?= number_format((int) $att['size'] / 1024, 0, ',', '.') ?> KB · <?= h(strtoupper($kind)) ?></small>
						<span class="mail-att-actions">
							<?php if ($canPreview): ?>
								<a href="<?= h($url) ?>" target="_blank" rel="noopener">Ver</a>
							<?php endif; ?>
							<a href="<?= h($url . '?dl=1') ?>">Descargar</a>
						</span>
						<?php if ($kind === 'pdf'): ?>
							<iframe class="mail-att-frame" src="<?= h($url) ?>" title="<?= h($att['filename']) ?>"></iframe>
						<?php elseif ($kind === 'image'): ?>
							<img class="mail-att-img" src="<?= h($url) ?>" alt="<?= h($att['filename']) ?>">
						<?php elseif (in_array($kind, ['word', 'excel'], true)): ?>
							<p class="muted">Descárgalo para abrirlo en Word o Excel. El archivo queda guardado en este correo del CRM.</p>
						<?php elseif ($kind === 'file' && str_starts_with(strtolower((string) $att['mime']), 'text/')): ?>
							<iframe class="mail-att-frame" src="<?= h($url) ?>" title="<?= h($att['filename']) ?>"></iframe>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ($html !== ''): ?>
		<div class="mail-body"><?= Mime::safeHtml($html) ?></div>
	<?php else: ?>
		<div class="mail-body"><p><?= nl2br(h($text !== '' ? $text : 'Este correo no tiene texto.')) ?></p></div>
	<?php endif; ?>

	<?php if ($message['folder'] === 'inbox'): ?>
		<form class="gmail-reply" method="post" action="<?= h(Http::url('/correo/' . $message['id'] . '/responder')) ?>" enctype="multipart/form-data" data-reply-form>
			<?= Csrf::field() ?>
			<label>
				<span>Responder a <?= h($peer) ?></span>
				<textarea name="body" rows="6" required placeholder="Redacta tu respuesta"></textarea>
			</label>
			<div class="gmail-forward-to" data-forward-box hidden>
				<label>
					<span>Reenviar a</span>
					<input type="text" name="forward_to" placeholder="correo@ejemplo.cl" autocomplete="off">
				</label>
			</div>
			<label class="mail-attach">
				<span>Adjuntos</span>
				<input type="file" name="adjuntos[]" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xml,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rtf" multiple>
			</label>
			<div class="gmail-reply-actions">
				<button class="gmail-send" type="submit" name="mode" value="one">Responder</button>
				<button class="gmail-send gmail-send-secondary" type="submit" name="mode" value="all">Responder a todos</button>
				<button class="gmail-send gmail-send-secondary" type="button" data-forward>Reenviar</button>
			</div>
		</form>
		<script>
		(function () {
			var form = document.querySelector("[data-reply-form]");
			var open = document.querySelector("[data-forward]");
			if (!form || !open) return;
			open.addEventListener("click", function (event) {
				if (open.type !== "submit") event.preventDefault();
				var box = form.querySelector("[data-forward-box]");
				var input = form.querySelector("[name=forward_to]");
				var note = form.querySelector("textarea");
				if (box) box.hidden = false;
				if (note) note.required = false;
				if (input) {
					input.required = true;
					input.focus();
				}
				open.type = "submit";
				open.name = "mode";
				open.value = "forward";
				open.textContent = "Enviar reenvío";
			});
		})();
		</script>
	<?php endif; ?>
</div>
