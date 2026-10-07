<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$subject = (string) ($subject ?? '');
$emailHtml = (string) ($emailHtml ?? '');
$mailId = (int) ($mailId ?? 0);
$sentTo = trim((string) ($quote['sent_to'] ?? ''));
$sentCc = trim((string) ($quote['sent_cc'] ?? ''));
?>
<div class="client-sheet quote-sheet quote-send-sheet">
	<header class="quote-toolbar">
		<div>
			<p class="file-kicker">Correo enviado</p>
			<h1><?= h($quote['number']) ?><?php if (trim((string) ($quote['revision'] ?? '')) !== ''): ?> · <?= h($quote['revision']) ?><?php endif; ?></h1>
			<p>
				<?= h($client['name']) ?>
				<?php if (!empty($quote['sent_at'])): ?>
					· <?= h(when($quote['sent_at'], 'd-m-Y H:i')) ?>
				<?php endif; ?>
			</p>
		</div>
		<div class="quote-toolbar-actions">
			<a class="btn-text" href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>">Volver a la cotización</a>
			<?php if ($mailId > 0): ?>
				<a class="btn-text" href="<?= h(Http::url('/correo/' . $mailId)) ?>">Abrir en Correo</a>
			<?php endif; ?>
			<a class="btn btn-word" href="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/reenviar')) ?>">Reenviar cotización</a>
			<a class="btn-text" href="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/enviar')) ?>">Nueva versión (REV)</a>
		</div>
	</header>

	<?php $sendHistory = $sendHistory ?? []; ?>
	<?php if ($sendHistory): ?>
		<section class="quote-block is-solid quote-send-panel">
			<div class="quote-block-hd">
				<div>
					<h2>Historial de envíos</h2>
					<p class="muted">Auditoría de cada envío y reenvío (sin crear versiones nuevas).</p>
				</div>
			</div>
			<div class="quote-send-body">
				<div class="quote-send-log-table-wrap">
					<table class="quote-send-log-table">
						<thead>
							<tr>
								<th>Fecha y hora</th>
								<th>Tipo</th>
								<th>Enviado por</th>
								<th>Destinatario</th>
								<th>Cc</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($sendHistory as $row): ?>
							<?php
							$kind = (string) ($row['kind'] ?? 'send');
							$who = trim((string) ($row['user_name'] ?? '')) !== '' ? (string) $row['user_name'] : 'Usuario';
							$destName = trim((string) ($row['contact_name'] ?? ''));
							$destEmail = trim((string) ($row['to_email'] ?? ''));
							$dest = $destName !== '' ? ($destName . ' · ' . $destEmail) : $destEmail;
							?>
							<tr>
								<td><?= h(when($row['sent_at'] ?? null, 'd-m-Y H:i')) ?></td>
								<td><?= $kind === 'resend' ? 'Reenvío' : 'Envío' ?></td>
								<td><?= h($who) ?></td>
								<td><?= h($dest !== '' ? $dest : '—') ?></td>
								<td><?= h(trim((string) ($row['cc_email'] ?? '')) !== '' ? $row['cc_email'] : '—') ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="quote-block is-solid quote-send-panel">
		<div class="quote-block-hd">
			<div>
				<h2>Detalle del envío</h2>
				<p class="muted">Copia del mensaje que salió al cliente. Puedes verificar contenido y destinatarios.</p>
			</div>
		</div>
		<div class="quote-send-body">
			<p class="quote-send-subject"><strong>Asunto:</strong> <?= h($subject !== '' ? $subject : '(sin asunto)') ?></p>
			<p class="quote-send-subject"><strong>Para:</strong> <?= h($sentTo !== '' ? $sentTo : '—') ?></p>
			<?php if ($sentCc !== ''): ?>
				<p class="quote-send-subject"><strong>Cc:</strong> <?= h($sentCc) ?></p>
			<?php endif; ?>
			<div class="quote-email-frame" tabindex="0">
				<?= $emailHtml ?>
			</div>
		</div>
	</section>

	<div class="quote-send-actions">
		<a class="btn-text" href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>">Editar cotización</a>
		<a class="btn btn-word" href="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/reenviar')) ?>">Reenviar cotización</a>
		<a class="btn btn-excel" href="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/copiar')) ?>">Copiar con número nuevo</a>
	</div>
</div>
