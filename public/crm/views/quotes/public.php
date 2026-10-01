<?php
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Quote;

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
	$reference = Quote::itemLabel($items[0]);
}
$contactEmail = trim((string) ($quote['contact_email'] ?? $quote['client_email'] ?? ''));
$contactBits = array_values(array_filter([
	$contactName !== '' ? ('Contacto: ' . $contactName . (!empty($quote['contact_title']) ? ' · ' . $quote['contact_title'] : '')) : '',
	$contactEmail,
	!empty($quote['client_phone']) ? (string) $quote['client_phone'] : '',
	!empty($quote['client_city']) ? (string) $quote['client_city'] : '',
]));
$companyRut = trim((string) Config::RUT);
if ($companyRut === '') {
	$companyRut = '77.589.163-7';
}
$clientRut = trim((string) ($quote['client_rut'] ?? ''));
$termsText = Quote::termsText($quote);
$aboutText = Quote::aboutText($quote);
?>
<?php if ($preview): ?>
	<div class="doc-banner">Vista previa interna — así lo verá el cliente · Usa Imprimir / Guardar PDF</div>
<?php endif; ?>

<article class="doc">
	<section class="doc-page doc-page-1">
		<?php
		$pageLabel = '1 / 2';
		require __DIR__ . '/partials/letterhead.php';
		?>

		<section class="doc-parties">
			<div class="doc-card">
				<h2>De</h2>
				<p>
					<strong><?= h(Config::COMPANY) ?></strong>
					<span class="doc-rut"> · RUT <?= h($companyRut) ?></span>
				</p>
				<p><?= h(Config::EMAIL) ?> · <?= h(Config::PHONE) ?></p>
				<p><?= h(Config::ADDRESS) ?> · mizo.cl</p>
			</div>
			<div class="doc-card">
				<h2>Para</h2>
				<p>
					<strong><?= h($clientName !== '' ? $clientName : 'Cliente') ?></strong>
					<?php if ($clientRut !== ''): ?>
						<span class="doc-rut"> · RUT <?= h($clientRut) ?></span>
					<?php endif; ?>
				</p>
				<?php if ($contactBits): ?>
					<p><?= h(implode(' · ', $contactBits)) ?></p>
				<?php endif; ?>
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
						<th class="is-num">#</th>
						<th>Descripción</th>
						<th class="is-num">Cant.</th>
						<th class="is-num">P. unit. neto</th>
						<th class="is-num">Total neto</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($items as $i => $item): ?>
					<?php
					$label = Quote::itemLabel($item);
					$detail = trim((string) ($item['description'] ?? ''));
					if ($detail !== '' && strcasecmp($detail, $label) === 0) {
						$detail = '';
					}
					$qty = rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',');
					$unit = trim((string) ($item['unit'] ?? 'un'));
					?>
					<tr>
						<td class="is-num"><?= (int) $i + 1 ?></td>
						<td>
							<strong><?= h($label) ?></strong>
							<?php if ($detail !== ''): ?>
								<div class="doc-item-detail"><?= nl2br(h($detail)) ?></div>
							<?php endif; ?>
						</td>
						<td class="is-num"><?= h($qty) ?><?= $unit !== '' ? ' ' . h($unit) : '' ?></td>
						<td class="is-num"><?= money((int) $item['unit_price']) ?></td>
						<td class="is-num"><?= money((int) $item['total']) ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<div class="doc-summary">
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
				<p class="doc-currency">Montos en CLP. IVA incluido en el total.</p>
			</div>
		</section>

		<?php if (!empty($quote['notes'])): ?>
			<section class="doc-notes doc-notes-brief">
				<p><?= nl2br(h($quote['notes'])) ?></p>
			</section>
		<?php endif; ?>

		<?php if ($preview): ?>
			<p class="doc-status">Documento de vista previa. Aún no ha sido enviado al cliente.</p>
		<?php elseif ($quote['status'] === 'aceptada'): ?>
			<p class="doc-status is-ok">Esta cotización fue aceptada. Su asesor comercial se contactará para seguir con el proceso.</p>
		<?php elseif ($quote['status'] === 'rechazada'): ?>
			<p class="doc-status">Registramos que esta cotización no fue aceptada. Si desea ajustar el alcance, responda el correo.</p>
		<?php elseif (!$locked): ?>
			<form class="doc-actions" method="post" action="<?= h(Http::url('/q/' . $quote['token'])) ?>">
				<?= Csrf::field() ?>
				<p>Si esta propuesta se ajusta a lo requerido, puede aceptarla aquí. También puede responder el correo.</p>
				<button class="doc-accept" name="decision" value="aceptada" type="submit">Aceptar presupuesto</button>
				<button class="doc-decline" name="decision" value="rechazada" type="submit">No por ahora</button>
			</form>
		<?php endif; ?>

		<footer class="doc-foot doc-foot-compact">
			<p><strong>Mizo</strong> · Página 1 de 2 · Condiciones y presentación en la siguiente hoja</p>
		</footer>
	</section>

	<section class="doc-page doc-page-2">
		<?php
		$pageLabel = '2 / 2';
		require __DIR__ . '/partials/letterhead.php';
		?>

		<section class="doc-parties doc-parties-compact">
			<div class="doc-card">
				<h2>De</h2>
				<p>
					<strong><?= h(Config::COMPANY) ?></strong>
					<span class="doc-rut"> · RUT <?= h($companyRut) ?></span>
				</p>
			</div>
			<div class="doc-card">
				<h2>Para</h2>
				<p>
					<strong><?= h($clientName !== '' ? $clientName : 'Cliente') ?></strong>
					<?php if ($clientRut !== ''): ?>
						<span class="doc-rut"> · RUT <?= h($clientRut) ?></span>
					<?php endif; ?>
				</p>
			</div>
		</section>

		<section class="doc-annex">
			<div class="doc-annex-block">
				<h2>Condiciones y modo de pago</h2>
				<div class="doc-annex-body"><?= nl2br(h($termsText)) ?></div>
			</div>
			<div class="doc-annex-block">
				<h2>Mizo · Ingeniería e integración</h2>
				<div class="doc-annex-body"><?= nl2br(h($aboutText)) ?></div>
			</div>
		</section>

		<footer class="doc-foot">
			<p><strong>Mizo</strong> · Ingeniería e instalación profesional · <?= h(Config::PHONE) ?> · <?= h(Config::EMAIL) ?> · mizo.cl</p>
			<p>Frutillar y Santiago, Chile. Documento <?= h($quote['number']) ?> · Página 2 de 2</p>
		</footer>
	</section>
</article>
