<?php
use MizoCrm\Http;

$folder = $folder ?? 'inbox';
$messages = $messages ?? [];
$mailbox = $mailbox ?? [];
$unread = (int) ($unread ?? 0);
$message = $message ?? null;
$selectedId = $message ? (int) $message['id'] : 0;
$openClass = $message ? ' has-open' : '';
?>
<div class="gmail<?= $openClass ?>">
	<?php require __DIR__ . '/nav.php'; ?>
	<section class="gmail-main gmail-split">
		<div class="gmail-list-pane">
			<div class="gmail-toolbar">
				<strong><?= $folder === 'sent' ? 'Enviados' : 'Recibidos' ?></strong>
				<span class="gmail-toolbar-mail"><?= h($mailbox['email'] ?? '') ?></span>
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
						$isSelected = $selectedId === (int) $row['id'];
						?>
						<a class="gmail-row <?= empty($row['seen']) ? 'is-unread' : '' ?><?= $isSelected ? ' is-selected' : '' ?>" href="<?= h(Http::url('/correo/' . $row['id'])) ?>">
							<span class="gmail-avatar" style="background:<?= h(mail_avatar_color($who)) ?>"><?= h(initials($who)) ?></span>
							<span class="gmail-row-main">
								<span class="gmail-from"><?= h($who) ?></span>
								<span class="gmail-snippet">
									<b><?= h($row['subject'] ?: '(sin asunto)') ?></b>
									<?php if ($snippet !== ''): ?>
										<span> — <?= h($snippet) ?></span>
									<?php endif; ?>
								</span>
								<?php if (!empty($row['client_name'])): ?>
									<em class="gmail-client-tag"><?= h($row['client_name']) ?></em>
								<?php endif; ?>
							</span>
							<time class="gmail-date"><?= h(mail_when($row['sent_at'] ?? null)) ?></time>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="gmail-read-pane">
			<?php if ($message): ?>
				<?php require __DIR__ . '/read.php'; ?>
			<?php else: ?>
				<div class="gmail-read-placeholder">
					<p>Selecciona un correo para leerlo aquí.</p>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
