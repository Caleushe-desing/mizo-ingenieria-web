<?php
use MizoCrm\Http;

$folder = $folder ?? 'inbox';
$messages = $messages ?? [];
$mailbox = $mailbox ?? [];
$unread = (int) ($unread ?? 0);
?>
<div class="page-head">
	<div>
		<h1><?= $folder === 'sent' ? 'Enviados' : 'Bandeja de entrada' ?></h1>
		<p>Correos de <?= h($mailbox['email'] ?? '') ?>. Si el cliente responde, aparece aquí.</p>
	</div>
	<div class="page-head-actions">
		<a class="btn" href="<?= h(Http::url('/correo' . ($folder === 'sent' ? '/enviados' : '') . '?sync=1')) ?>">Actualizar</a>
		<a class="btn btn-word" href="<?= h(Http::url('/correo/nuevo')) ?>">Nuevo correo</a>
	</div>
</div>

<div class="mail-layout">
	<aside class="mail-nav paper">
		<a class="<?= $folder === 'inbox' ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo')) ?>">Bandeja<?= $unread > 0 ? ' (' . $unread . ')' : '' ?></a>
		<a class="<?= $folder === 'sent' ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo/enviados')) ?>">Enviados</a>
		<a href="<?= h(Http::url('/correo/nuevo')) ?>">Redactar</a>
		<a href="<?= h(Http::url('/correo/cuenta')) ?>">Mi casilla</a>
	</aside>
	<section class="paper" style="padding:0">
		<?php if (!$messages): ?>
			<div class="empty">
				<p><?= $folder === 'sent' ? 'Aún no hay correos enviados desde esta casilla.' : 'La bandeja está vacía. Cuando un cliente responda, el mensaje aparecerá aquí.' ?></p>
			</div>
		<?php else: ?>
			<div class="table-wrap">
				<table class="sheet mail-sheet">
					<thead>
						<tr>
							<th><?= $folder === 'sent' ? 'Para' : 'De' ?></th>
							<th>Asunto</th>
							<th>Cliente</th>
							<th>Fecha</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($messages as $row): ?>
						<tr class="is-link <?= empty($row['seen']) ? 'is-unread' : '' ?>" onclick="location.href='<?= h(Http::url('/correo/' . $row['id'])) ?>'">
							<td>
								<?php if ($folder === 'sent'): ?>
									<?= h($row['to_email']) ?>
								<?php else: ?>
									<?= h($row['from_name'] ?: $row['from_email']) ?>
								<?php endif; ?>
							</td>
							<td><?= h($row['subject'] ?: '(sin asunto)') ?></td>
							<td><?= h($row['client_name'] ?? '') ?></td>
							<td><?= h(when($row['sent_at'])) ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
