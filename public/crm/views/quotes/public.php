<?php
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;

$locked = in_array($quote['status'], ['aceptada', 'rechazada'], true);
$preview = !empty($preview);
$clientName = (string) ($quote['client_name'] ?? '');
$contactName = (string) ($quote['contact_name'] ?? '');
$issued = $quote['sent_at'] ?? $quote['created_at'] ?? null;
$reference = trim((string) ($quote['intro'] ?? ''));
if ($reference === '') {
	$reference = trim((string) ($quote['deal_title'] ?? ''));
}
if ($reference === '' && $items) {
	$reference = (string) ($items[0]['description'] ?? '');
}
?>
<?php if ($preview): ?>
	<div class="doc-banner">Vista previa interna — así lo verá el cliente</div>
<?php endif; ?>

<article class="doc">
	<header class="doc-letterhead">
		<div class="doc-brand">
			<img src="/mizo-logo.png" alt="Mizo">
			<p>Ingeniería en sonido, video, CCTV y soporte TI</p>
		</div>
		<div class="doc-id">
			<p class="doc-type">Cotización</p>
			<p class="doc-number"><?= h($quote['number']) ?></p>
			<dl>
				<div><dt>Fecha</dt><dd><?= h(when($issued, 'd-m-Y')) ?></dd></div>
				<div><dt>Válida hasta</dt><dd><?= h(when($quote['valid_until'], 'd-m-Y')) ?></dd></div>
			</dl>
		</div>
	</header>

	<section class="doc-parties">
		<div class="doc-card">
			<h2>De</h2>
			<p><strong><?= h(Config::COMPANY) ?></strong></p>
			<p><?= h(Config::EMAIL) ?></p>
			<p><?= h(Config::PHONE) ?></p>
			<p><?= h(Config::ADDRESS) ?></p>
			<p>www.mizo.cl</p>
		</div>
		<div class="doc-card">
			<h2>Para</h2>
			<p><strong><?= h($clientName !== '' ? $clientName : 'Cliente') ?></strong></p>
			<?php if ($contactName !== '' && $contactName !== $clientName): ?>
				<p>Contacto: <?= h($contactName) ?></p>
			<?php endif; ?>
			<?php if (!empty($quote['client_email'])): ?><p><?= h($quote['client_email']) ?></p><?php endif; ?>
			<?php if (!empty($quote['client_phone'])): ?><p><?= h($quote['client_phone']) ?></p><?php endif; ?>
			<?php if (!empty($quote['client_city'])): ?><p><?= h($quote['client_city']) ?></p><?php endif; ?>
		</div>
	</section>

	<?php if ($reference !== ''): ?>
		<section class="doc-ref">
			<h2>Referencia</h2>
			<p><?= nl2br(h($reference)) ?></p>
		</section>
	<?php endif; ?>

	<section class="doc-items">
		<h2>Detalle de la propuesta</h2>
		<table>
			<thead>
				<tr>
					<th class="is-num">Ítem</th>
					<th>Descripción</th>
					<th class="is-num">Cant.</th>
					<th>Unidad</th>
					<th class="is-num">P. unitario neto</th>
					<th class="is-num">Total neto</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($items as $i => $item): ?>
				<tr>
					<td class="is-num"><?= (int) $i + 1 ?></td>
					<td><?= h($item['description']) ?></td>
					<td class="is-num"><?= h(rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',')) ?></td>
					<td><?= h($item['unit']) ?></td>
					<td class="is-num"><?= money((int) $item['unit_price']) ?></td>
					<td class="is-num"><?= money((int) $item['total']) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<table class="doc-totals">
			<tr>
				<th>Neto</th>
				<td><?= money((int) $quote['subtotal']) ?></td>
			</tr>
			<tr>
				<th>IVA <?= (int) $quote['tax_rate'] ?>%</th>
				<td><?= money((int) $quote['tax']) ?></td>
			</tr>
			<tr class="is-grand">
				<th>Total</th>
				<td><?= money((int) $quote['total']) ?></td>
			</tr>
		</table>
		<p class="doc-currency">Montos en pesos chilenos (CLP). IVA incluido en el total.</p>
	</section>

	<?php if (!empty($quote['notes'])): ?>
		<section class="doc-notes">
			<h2>Condiciones</h2>
			<p><?= nl2br(h($quote['notes'])) ?></p>
		</section>
	<?php endif; ?>

	<?php if ($preview): ?>
		<p class="doc-status">Documento de vista previa. Aún no ha sido enviado al cliente.</p>
	<?php elseif ($quote['status'] === 'aceptada'): ?>
		<p class="doc-status is-ok">Esta cotización fue aceptada. Un ingeniero Mizo se contactará para coordinar el trabajo.</p>
	<?php elseif ($quote['status'] === 'rechazada'): ?>
		<p class="doc-status">Registramos que esta cotización no fue aceptada. Si desea ajustar el alcance, responda el correo.</p>
	<?php elseif (!$locked && $quote['status'] !== 'borrador'): ?>
		<form class="doc-actions" method="post" action="<?= h(Http::url('/q/' . $quote['token'])) ?>">
			<?= Csrf::field() ?>
			<p>Si esta propuesta se ajusta a lo requerido, puede aceptarla aquí. También puede responder el correo.</p>
			<button class="doc-accept" name="decision" value="aceptada" type="submit">Aceptar cotización</button>
			<button class="doc-decline" name="decision" value="rechazada" type="submit">No por ahora</button>
		</form>
	<?php endif; ?>

	<footer class="doc-foot">
		<p><strong>Mizo</strong> · Ingeniería e instalación profesional</p>
		<p><?= h(Config::PHONE) ?> · <?= h(Config::EMAIL) ?> · mizo.cl</p>
		<p>Frutillar y Santiago, Chile. Documento <?= h($quote['number']) ?>.</p>
	</footer>
</article>
