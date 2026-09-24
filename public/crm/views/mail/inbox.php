<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$folder = $folder ?? 'inbox';
$messages = $messages ?? [];
$mailbox = $mailbox ?? [];
$unread = (int) ($unread ?? 0);
$message = $message ?? null;
$attachments = $attachments ?? [];
$query = trim((string) ($query ?? ''));
$sort = (string) ($sort ?? 'fecha');
$filter = (string) ($filter ?? 'todos');
$selectedId = $message ? (int) $message['id'] : 0;
$openClass = $message ? ' has-open' : '';
$folderPath = match ($folder) {
	'sent' => '/correo/enviados',
	'spam' => '/correo/spam',
	default => '/correo',
};
$folderTitle = match ($folder) {
	'sent' => 'Enviados',
	'spam' => 'No deseado',
	default => 'Recibidos',
};
$emptyText = match ($folder) {
	'sent' => 'No hay correos enviados.',
	'spam' => 'No hay correo no deseado.',
	default => 'La bandeja está vacía.',
};
$folderUrl = Http::url($folderPath);
$qs = [];
if ($query !== '') $qs['q'] = $query;
if ($sort !== 'fecha') $qs['orden'] = $sort;
if ($filter !== 'todos') $qs['filtro'] = $filter;
$queryString = $qs ? ('?' . http_build_query($qs)) : '';
$amp = $qs ? ('&' . http_build_query($qs)) : '';
$listBack = $folderPath . $queryString;

$sortOpts = [
	'fecha' => 'Más recientes',
	'fecha_asc' => 'Más antiguos',
	'remitente' => 'Remitente A-Z',
	'asunto' => 'Asunto A-Z',
	'no_leidos' => 'No leídos primero',
];
$filterOpts = [
	'todos' => 'Todos',
	'no_leidos' => 'No leídos',
	'importantes' => 'Importantes',
	'adjuntos' => 'Con adjuntos',
];
?>
<div class="gmail<?= $openClass ?>" data-gmail>
	<?php require __DIR__ . '/nav.php'; ?>
	<section class="gmail-main gmail-split">
		<div class="gmail-list-pane" data-crm-scroll="mail-list">
			<div class="gmail-toolbar">
				<strong><?= h($folderTitle) ?></strong>
				<span class="gmail-toolbar-mail"><?= h($mailbox['email'] ?? '') ?></span>
				<a href="<?= h($folderUrl . '?sync=1' . ($amp !== '' ? $amp : '')) ?>">Actualizar</a>
			</div>
			<form class="gmail-search" method="get" action="<?= h($folderUrl) ?>" role="search">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path></svg>
				<input type="search" name="q" value="<?= h($query) ?>" placeholder="Buscar correo, asunto o cliente" autocomplete="off">
				<select name="orden" title="Ordenar" onchange="this.form.submit()">
					<?php foreach ($sortOpts as $k => $label): ?>
						<option value="<?= h($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= h($label) ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filtro" title="Filtrar" onchange="this.form.submit()">
					<?php foreach ($filterOpts as $k => $label): ?>
						<option value="<?= h($k) ?>" <?= $filter === $k ? 'selected' : '' ?>><?= h($label) ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ($query !== '' || $filter !== 'todos' || $sort !== 'fecha'): ?>
					<a class="gmail-search-clear" href="<?= h($folderUrl) ?>" title="Limpiar">×</a>
				<?php endif; ?>
				<button type="submit">Buscar</button>
			</form>

			<form method="post" action="<?= h(Http::url('/correo/lote')) ?>" class="gmail-bulk" id="bulk-form">
				<?= Csrf::field() ?>
				<input type="hidden" name="back" value="<?= h($listBack) ?>">
				<div class="gmail-bulk-bar">
					<label class="gmail-check-all"><input type="checkbox" data-check-all> Todos</label>
					<button type="submit" name="action" value="read">Leídos</button>
					<button type="submit" name="action" value="unread">No leídos</button>
					<?php if ($folder === 'spam'): ?>
						<button type="submit" name="action" value="unspam">No es spam</button>
					<?php elseif ($folder === 'inbox'): ?>
						<button type="submit" name="action" value="spam">No deseado</button>
					<?php endif; ?>
					<button type="submit" name="action" value="important">Importante</button>
					<button type="submit" name="action" value="delete" onclick="return confirm('¿Eliminar los seleccionados de tu casilla? También se borran en el servidor de correo.');">Eliminar</button>
				</div>
			</form>

			<?php if (!$messages): ?>
				<div class="gmail-empty">
					<?php if ($query !== '' || $filter !== 'todos'): ?>
						<p>No hay correos con ese criterio.</p>
						<p><a href="<?= h($folderUrl) ?>">Ver todos</a></p>
					<?php else: ?>
						<p><?= h($emptyText) ?></p>
					<?php endif; ?>
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
						$isUnread = (int) ($row['seen'] ?? 0) === 0;
						$isImportant = (int) ($row['important'] ?? 0) === 1;
						$hasAtt = (int) ($row['has_attachments'] ?? 0) === 1;
						$hrefQs = $qs;
						$href = Http::url('/correo/' . $row['id'] . ($hrefQs ? ('?' . http_build_query($hrefQs)) : ''));
						?>
						<div class="gmail-row<?= $isUnread ? ' is-unread' : '' ?><?= $isImportant ? ' is-important' : '' ?><?= $isSelected ? ' is-selected' : '' ?>">
							<label class="gmail-row-check"><input type="checkbox" form="bulk-form" name="ids[]" value="<?= (int) $row['id'] ?>"></label>
							<form method="post" action="<?= h(Http::url('/correo/' . $row['id'] . '/estado')) ?>" class="gmail-star-form">
								<?= Csrf::field() ?>
								<input type="hidden" name="action" value="<?= $isImportant ? 'unimportant' : 'important' ?>">
								<input type="hidden" name="back" value="<?= h($listBack) ?>">
								<button type="submit" class="gmail-star<?= $isImportant ? ' is-on' : '' ?>" title="Importante">★</button>
							</form>
							<a class="gmail-row-link" href="<?= h($href) ?>">
								<span class="gmail-avatar" style="background:<?= h(mail_avatar_color($who)) ?>"><?= h(initials($who)) ?></span>
								<span class="gmail-row-main">
									<span class="gmail-from">
										<?php if ($isUnread): ?><span class="gmail-unread-dot" aria-hidden="true"></span><?php endif; ?>
										<?= h($who) ?>
										<?php if ($hasAtt): ?><span class="gmail-clip" title="Adjuntos">📎</span><?php endif; ?>
									</span>
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
		<div class="gmail-read-pane" data-crm-scroll="mail-read">
			<?php if ($message): ?>
				<?php require __DIR__ . '/read.php'; ?>
			<?php else: ?>
				<div class="gmail-read-placeholder">
					<p>Selecciona un correo para leerlo aquí.</p>
					<p class="muted">Usa los filtros y la selección múltiple para gestionar la bandeja de ventas.</p>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
