<?php
use MizoCrm\Http;
use MizoCrm\Mail\Mime;
use MizoCrm\Models\AdminReport;

$audit = $audit ?? null;
if (!$audit || !\MizoCrm\Auth::isAdmin()) {
	return;
}
$client = $client ?? [];
?>
<section class="paper file-panel" data-file-panel="auditoria" hidden>
	<div class="fold-body admin-audit">
		<p class="muted">Vista solo del administrador. Aquí queda el trabajo hecho sobre este cliente.</p>

		<h3>Movimientos de tarjetas</h3>
		<?php if (empty($audit['moves'])): ?>
			<p class="muted">Todavía no hay cambios de columna.</p>
		<?php else: ?>
			<ul class="notes">
				<?php foreach ($audit['moves'] as $move): ?>
					<li>
						<div class="note-body">
							<p><?= nl2br(h($move['message'])) ?></p>
							<small><?= h($move['user_name'] ?: 'Sistema') ?> · <?= h(when($move['created_at'])) ?><?= !empty($move['deal_title']) ? ' · ' . h($move['deal_title']) : '' ?></small>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h3>Correos</h3>
		<?php if (empty($audit['mails'])): ?>
			<p class="muted">No hay correos vinculados a este cliente.</p>
		<?php else: ?>
			<?php foreach ($audit['mails'] as $mail): ?>
				<article class="audit-mail">
					<header>
						<strong><?= h($mail['subject'] ?: '(sin asunto)') ?></strong>
						<small>
							<?= ($mail['folder'] ?? '') === 'sent' ? 'Enviado' : 'Recibido' ?>
							· <?= h($mail['user_name'] ?: ($mail['from_name'] ?: 'Ejecutivo')) ?>
							· <?= h(when($mail['sent_at'] ?? null)) ?>
							<?php if (!empty($mail['to_email'])): ?> · para <?= h($mail['to_email']) ?><?php endif; ?>
						</small>
					</header>
					<p><?= h(AdminReport::preview((string) ($mail['body_text'] ?? ''))) ?></p>
					<?php if (trim((string) ($mail['body_html'] ?? '')) !== ''): ?>
						<details>
							<summary>Ver mensaje y firma</summary>
							<div class="audit-mail-html"><?= Mime::safeHtml((string) $mail['body_html']) ?></div>
						</details>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>

		<h3>Cotizaciones</h3>
		<?php if (empty($audit['quotes'])): ?>
			<p class="muted">Este cliente no tiene cotizaciones.</p>
		<?php else: ?>
			<div class="table-wrap">
				<table class="sheet">
					<thead>
						<tr><th>Número</th><th>Proyecto</th><th>Ejecutivo</th><th>Estado</th><th>Total</th><th>Enviada</th></tr>
					</thead>
					<tbody>
						<?php foreach ($audit['quotes'] as $quote): ?>
							<tr>
								<td><a href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>"><?= h($quote['number']) ?></a></td>
								<td><?= h($quote['deal_title'] ?: '—') ?></td>
								<td><?= h($quote['author_name'] ?: '—') ?></td>
								<td><?= h(quote_status_label((string) $quote['status'])) ?></td>
								<td><?= money((int) $quote['total']) ?></td>
								<td><?= h(when($quote['sent_at'] ?? null, 'd-m-Y H:i')) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<h3>Notas y llamadas</h3>
		<?php if (empty($audit['notes'])): ?>
			<p class="muted">No hay notas ni llamadas registradas.</p>
		<?php else: ?>
			<ul class="notes">
				<?php foreach ($audit['notes'] as $note): ?>
					<li>
						<div class="note-body">
							<p><strong><?= h(AdminReport::typeLabel((string) $note['type'])) ?>.</strong> <?= nl2br(h($note['message'])) ?></p>
							<small><?= h($note['user_name'] ?: 'Sistema') ?> · <?= h(when($note['created_at'])) ?><?= !empty($note['deal_title']) ? ' · ' . h($note['deal_title']) : '' ?></small>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
