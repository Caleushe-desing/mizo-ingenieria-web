<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$folder = $folder ?? 'inbox';
$messages = $messages ?? [];
$mailbox = $mailbox ?? [];
$unread = (int) ($unread ?? 0);
$message = $message ?? null;
$query = trim((string) ($query ?? ''));
$selectedId = $message ? (int) $message['id'] : 0;
$openClass = $message ? ' has-open' : '';
$folderPath = '/correo' . ($folder === 'sent' ? '/enviados' : '');
$folderUrl = Http::url($folderPath);
$qSuffix = $query !== '' ? '?q=' . rawurlencode($query) : '';
$qAmp = $query !== '' ? '&q=' . rawurlencode($query) : '';
$listBack = $folderPath . $qSuffix;
?>
<div class="gmail<?= $openClass ?>">
	<?php require __DIR__ . '/nav.php'; ?>
	<section class="gmail-main gmail-split">
		<div class="gmail-list-pane">
			<div class="gmail-toolbar">
				<strong><?= $folder === 'sent' ? 'Enviados' : 'Recibidos' ?></strong>
				<span class="gmail-toolbar-mail"><?= h($mailbox['email'] ?? '') ?></span>
				<a href="<?= h($folderUrl . '?sync=1' . $qAmp) ?>">Actualizar</a>
			</div>
			<form class="gmail-search" method="get" action="<?= h($folderUrl) ?>" role="search">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path></svg>
				<input type="search" name="q" value="<?= h($query) ?>" placeholder="Buscar correo, asunto o cliente" autocomplete="off">
				<?php if ($query !== ''): ?>
					<a class="gmail-search-clear" href="<?= h($folderUrl) ?>" title="Limpiar búsqueda">×</a>
				<?php endif; ?>
				<button type="submit">Buscar</button>
			</form>
			<?php if (!$messages): ?>
				<div class="gmail-empty">
					<?php if ($query !== ''): ?>
						<p>No hay correos que coincidan con «<?= h($query) ?>».</p>
						<p><a href="<?= h($folderUrl) ?>">Ver todos</a></p>
					<?php else: ?>
						<p><?= $folder === 'sent' ? 'No hay correos enviados.' : 'La bandeja está vacía. Cuando un cliente responda, el mensaje aparece aquí.' ?></p>
					<?php endif; ?>
				</div>
			<?php else: ?>
				<?php if ($query !== ''): ?>
					<p class="gmail-search-meta"><?= count($messages) ?> resultado<?= count($messages) === 1 ? '' : 's' ?> para «<?= h($query) ?>»</p>
				<?php endif; ?>
				<div class="gmail-list">
					<?php foreach ($messages as $row): ?>
						<?php
						$who = $folder === 'sent'
							? (string) $row['to_email']
							: (string) ($row['from_name'] ?: $row['from_email']);
						$snippet = mail_snippet($row['body_html'] ?? '', $row['body_text'] ?? '');
						$isSelected = $selectedId === (int) $row['id'];
						$isUnread = (int) ($row['seen'] ?? 0) === 0;
						$isImportant = (int) ($row['important'] ?? 0) === 1;
						$href = Http::url('/correo/' . $row['id'] . ($query !== '' ? '?q=' . rawurlencode($query) : ''));
						$rowBack = $selectedId > 0
							? '/correo/' . $selectedId . ($query !== '' ? '?q=' . rawurlencode($query) : '')
							: $listBack;
						?>
						<div class="gmail-row<?= $isUnread ? ' is-unread' : '' ?><?= $isImportant ? ' is-important' : '' ?><?= $isSelected ? ' is-selected' : '' ?>">
							<form method="post" action="<?= h(Http::url('/correo/' . $row['id'] . '/estado')) ?>" class="gmail-star-form">
								<?= Csrf::field() ?>
								<input type="hidden" name="action" value="<?= $isImportant ? 'unimportant' : 'important' ?>">
								<input type="hidden" name="back" value="<?= h($rowBack) ?>">
								<button type="submit" class="gmail-star<?= $isImportant ? ' is-on' : '' ?>" title="<?= $isImportant ? 'Quitar importante' : 'Marcar importante' ?>">★</button>
							</form>
							<a class="gmail-row-link" href="<?= h($href) ?>">
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
						</div>
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
