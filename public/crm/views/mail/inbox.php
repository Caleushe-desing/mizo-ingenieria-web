<?php
use MizoCrm\Http;

$folder = $folder ?? 'inbox';
$messages = $messages ?? [];
$mailbox = $mailbox ?? [];
$unread = (int) ($unread ?? 0);
?>
<div class="gmail">
	<?php require __DIR__ . '/nav.php'; ?>
	<section class="gmail-main">
		<div class="gmail-toolbar">
			<strong><?= $folder === 'sent' ? 'Enviados' : 'Recibidos' ?></strong>
			<span><?= h($mailbox['email'] ?? '') ?></span>
			<a href="<?= h(Http::url('/correo' . ($folder === 'sent' ? '/enviados' : '') . '?sync=1')) ?>">Actualizar</a>
		</div>
		<?php if (!$messages): ?>
			<div class="gmail-empty">
				<p><?= $folder === 'sent' ? 'No hay correos enviados.' : 'La bandeja está vacía. Cuando un cliente responda, el mensaje aparece aquí.' ?></p>
			</div>
		<?php else: ?>
			<div class="gmail-list">
				<?php foreach ($messages as $row): ?>
					<?php
					$who = $folder === 'sent'
						? (string) $row['to_email']
						: (string) ($row['from_name'] ?: $row['from_email']);
					$snippet = mail_snippet($row['body_html'] ?? '', $row['body_text'] ?? '');
					?>
					<a class="gmail-row <?= empty($row['seen']) ? 'is-unread' : '' ?>" href="<?= h(Http::url('/correo/' . $row['id'])) ?>">
						<span class="gmail-avatar" style="background:<?= h(mail_avatar_color($who)) ?>"><?= h(initials($who)) ?></span>
						<span class="gmail-from"><?= h($who) ?></span>
						<span class="gmail-snippet">
							<b><?= h($row['subject'] ?: '(sin asunto)') ?></b>
							<?php if ($snippet !== ''): ?>
								<span> — <?= h($snippet) ?></span>
							<?php endif; ?>
							<?php if (!empty($row['client_name'])): ?>
								<em><?= h($row['client_name']) ?></em>
							<?php endif; ?>
						</span>
						<time class="gmail-date"><?= h(mail_when($row['sent_at'] ?? null)) ?></time>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</div>
