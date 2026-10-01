<?php
/**
 * HTML del PDF (misma estructura visual que la cotización pública).
 * @var array $quote
 * @var array $items
 * @var string $logoSrc ruta local del logo para Dompdf
 */
use MizoCrm\Config;
use MizoCrm\Models\Quote;

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
$logoSrc = $logoSrc ?? '';
$revision = trim((string) ($quote['revision'] ?? ''));
?>
<!doctype html>
<html lang="es-CL">
<head>
	<meta charset="UTF-8">
	<style>
		@page { margin: 10mm 11mm 12mm; size: A4; }
		* { box-sizing: border-box; }
		body {
			margin: 0;
			color: #161616;
			font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
			font-size: 11px;
			line-height: 1.35;
		}
		.page { page-break-after: always; }
		.page:last-child { page-break-after: auto; }
		.letterhead {
			width: 100%;
			border-bottom: 1px solid #ececec;
			padding: 0 0 10px;
			margin-bottom: 12px;
		}
		.letterhead td { vertical-align: bottom; }
		.brand img { height: 36px; width: auto; display: block; }
		.brand p { margin: 5px 0 0; font-size: 9px; color: #6a6a6a; letter-spacing: .03em; }
		.id { text-align: right; }
		.type { margin: 0; font-size: 9px; letter-spacing: .16em; text-transform: uppercase; color: #f47b20; font-weight: 700; }
		.number { margin: 2px 0 4px; font-size: 20px; font-weight: 700; color: #161616; }
		.rev { margin: 0 0 4px; font-size: 11px; color: #666; }
		.meta { margin: 0; font-size: 10px; color: #8a8a8a; }
		.meta strong { color: #161616; font-weight: 600; }
		.parties { width: 100%; margin: 0 0 10px; }
		.parties td { width: 50%; vertical-align: top; padding: 0; }
		.card {
			border: 1px solid #e4e4e4;
			padding: 8px 10px;
			margin-right: 6px;
			min-height: 54px;
		}
		.parties td:last-child .card { margin-right: 0; margin-left: 6px; }
		h2 {
			margin: 0 0 4px;
			font-size: 9px;
			letter-spacing: .08em;
			text-transform: uppercase;
			color: #1c9bd8;
			font-weight: 700;
		}
		.card p { margin: 0 0 2px; font-size: 10.5px; }
		.ref { margin: 0 0 10px; }
		.ref p { margin: 0; font-size: 12px; }
		.items h2 { margin-bottom: 6px; }
		table.grid {
			width: 100%;
			border-collapse: collapse;
			font-size: 10px;
		}
		table.grid th {
			text-align: left;
			padding: 6px 5px;
			border-bottom: 2px solid #1c9bd8;
			color: #1c9bd8;
			font-size: 9px;
			text-transform: uppercase;
			letter-spacing: .04em;
		}
		table.grid td {
			padding: 7px 5px;
			border-bottom: 1px solid #e8e8e8;
			vertical-align: top;
		}
		table.grid .num { text-align: right; white-space: nowrap; }
		table.grid .idx { width: 22px; color: #888; text-align: center; }
		.item-name { font-weight: 700; color: #161616; }
		.item-detail { margin-top: 3px; color: #555; font-size: 9.5px; line-height: 1.35; }
		.summary { width: 100%; margin-top: 10px; }
		.summary td { vertical-align: top; }
		.totals { width: 220px; margin-left: auto; font-size: 11px; }
		.totals td { padding: 3px 0; }
		.totals .label { color: #666; }
		.totals .val { text-align: right; }
		.totals .grand td {
			padding-top: 7px;
			border-top: 2px solid #111;
			font-weight: 700;
			font-size: 13px;
		}
		.totals .grand .val { color: #f47b20; }
		.currency { margin: 4px 0 0; font-size: 9px; color: #888; text-align: right; }
		.notes { margin-top: 10px; font-size: 10px; color: #444; }
		.foot {
			margin-top: 14px;
			padding-top: 8px;
			border-top: 1px solid #ececec;
			font-size: 9px;
			color: #777;
		}
		.annex { margin-top: 8px; }
		.annex-block { margin-bottom: 14px; }
		.annex-block h2 {
			color: #f47b20;
			font-size: 12px;
			letter-spacing: .02em;
			text-transform: none;
			margin-bottom: 6px;
		}
		.annex-body {
			font-size: 10.5px;
			line-height: 1.45;
			color: #333;
			white-space: pre-wrap;
		}
		.compact .card { min-height: 0; }
	</style>
</head>
<body>
<?php
$acceptUrl = (string) ($acceptUrl ?? '');
$rejectUrl = (string) ($rejectUrl ?? '');
$canDecide = $acceptUrl !== '' && $rejectUrl !== '';
?>
	<div class="page">
		<table class="letterhead">
			<tr>
				<td class="brand" style="width:58%">
					<?php if ($logoSrc !== ''): ?>
						<img src="<?= h($logoSrc) ?>" alt="Mizo">
					<?php else: ?>
						<div style="font-size:22px;font-weight:700;color:#161616;">MIZO</div>
					<?php endif; ?>
					<p>Ingeniería en sonido, video, CCTV y soporte TI</p>
				</td>
				<td class="id" style="width:42%">
					<p class="type">Cotización · 1 / 2</p>
					<p class="number"><?= h($quote['number']) ?></p>
					<?php if ($revision !== ''): ?>
						<p class="rev">Versión <?= h($revision) ?></p>
					<?php endif; ?>
					<p class="meta">Fecha <strong><?= h(when($issued, 'd-m-Y')) ?></strong>
						· Válida hasta <strong><?= h(when($quote['valid_until'], 'd-m-Y')) ?></strong></p>
				</td>
			</tr>
		</table>

		<table class="parties">
			<tr>
				<td>
					<div class="card">
						<h2>De</h2>
						<p><strong><?= h(Config::COMPANY) ?></strong> · RUT <?= h($companyRut) ?></p>
						<p><?= h(Config::EMAIL) ?> · <?= h(Config::PHONE) ?></p>
						<p><?= h(Config::ADDRESS) ?> · mizo.cl</p>
					</div>
				</td>
				<td>
					<div class="card">
						<h2>Para</h2>
						<p>
							<strong><?= h($clientName !== '' ? $clientName : 'Cliente') ?></strong>
							<?php if ($clientRut !== ''): ?> · RUT <?= h($clientRut) ?><?php endif; ?>
						</p>
						<?php if ($contactBits): ?>
							<p><?= h(implode(' · ', $contactBits)) ?></p>
						<?php endif; ?>
					</div>
				</td>
			</tr>
		</table>

		<?php if ($reference !== ''): ?>
			<section class="ref">
				<h2>Referencia</h2>
				<p><?= nl2br(h($reference)) ?></p>
			</section>
		<?php endif; ?>

		<section class="items">
			<h2>Detalle de la propuesta</h2>
			<table class="grid">
				<thead>
					<tr>
						<th class="idx">#</th>
						<th>Descripción</th>
						<th class="num">Cant.</th>
						<th class="num">P. unit. neto</th>
						<th class="num">Total neto</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($items as $i => $item): ?>
					<?php
					$label = Quote::itemLabel($item);
					if ($label === '') {
						continue;
					}
					$detail = trim((string) ($item['description'] ?? ''));
					if ($detail !== '' && strcasecmp($detail, $label) === 0) {
						$detail = '';
					}
					$qty = rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',');
					$unit = trim((string) ($item['unit'] ?? 'un'));
					?>
					<tr>
						<td class="idx"><?= (int) $i + 1 ?></td>
						<td>
							<div class="item-name"><?= h($label) ?></div>
							<?php if ($detail !== ''): ?>
								<div class="item-detail"><?= nl2br(h($detail)) ?></div>
							<?php endif; ?>
						</td>
						<td class="num"><?= h($qty) ?><?= $unit !== '' ? ' ' . h($unit) : '' ?></td>
						<td class="num"><?= money((int) $item['unit_price']) ?></td>
						<td class="num"><?= money((int) $item['total']) ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<table class="summary">
				<tr>
					<td></td>
					<td style="width:240px">
						<table class="totals">
							<tr>
								<td class="label">Neto</td>
								<td class="val"><?= money((int) $quote['subtotal']) ?></td>
							</tr>
							<tr>
								<td class="label">IVA <?= (int) $quote['tax_rate'] ?>%</td>
								<td class="val"><?= money((int) $quote['tax']) ?></td>
							</tr>
							<tr class="grand">
								<td class="label">Total</td>
								<td class="val"><?= money((int) $quote['total']) ?></td>
							</tr>
						</table>
						<p class="currency">Montos en CLP. IVA incluido en el total.</p>
					</td>
				</tr>
			</table>
		</section>

		<?php if ($canDecide): ?>
			<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px;border:1px solid #e4e4e4;">
				<tr>
					<td align="center" style="padding:12px 10px;">
						<p style="margin:0 0 10px;font-size:11px;color:#444;">¿Esta propuesta se ajusta a lo requerido? Responde aquí (también puedes responder el correo).</p>
						<a href="<?= h($acceptUrl) ?>" style="display:inline-block;background:#f47b20;color:#ffffff;text-decoration:none;padding:10px 16px;font-size:11px;font-weight:700;margin:0 4px 4px;">Aceptar presupuesto</a>
						<a href="<?= h($rejectUrl) ?>" style="display:inline-block;background:#ffffff;color:#444444;text-decoration:none;padding:9px 15px;font-size:11px;font-weight:700;border:1px solid #cccccc;margin:0 4px 4px;">No por ahora</a>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<?php if (!empty($quote['notes'])): ?>
			<section class="notes"><?= nl2br(h($quote['notes'])) ?></section>
		<?php endif; ?>

		<footer class="foot">
			<strong>Mizo</strong> · Página 1 de 2 · Condiciones y presentación en la siguiente hoja
		</footer>
	</div>

	<div class="page">
		<table class="letterhead">
			<tr>
				<td class="brand" style="width:58%">
					<?php if ($logoSrc !== ''): ?>
						<img src="<?= h($logoSrc) ?>" alt="Mizo">
					<?php else: ?>
						<div style="font-size:22px;font-weight:700;color:#161616;">MIZO</div>
					<?php endif; ?>
					<p>Ingeniería en sonido, video, CCTV y soporte TI</p>
				</td>
				<td class="id" style="width:42%">
					<p class="type">Cotización · 2 / 2</p>
					<p class="number"><?= h($quote['number']) ?></p>
					<?php if ($revision !== ''): ?>
						<p class="rev">Versión <?= h($revision) ?></p>
					<?php endif; ?>
					<p class="meta">Fecha <strong><?= h(when($issued, 'd-m-Y')) ?></strong>
						· Válida hasta <strong><?= h(when($quote['valid_until'], 'd-m-Y')) ?></strong></p>
				</td>
			</tr>
		</table>

		<table class="parties compact">
			<tr>
				<td>
					<div class="card">
						<h2>De</h2>
						<p><strong><?= h(Config::COMPANY) ?></strong> · RUT <?= h($companyRut) ?></p>
					</div>
				</td>
				<td>
					<div class="card">
						<h2>Para</h2>
						<p>
							<strong><?= h($clientName !== '' ? $clientName : 'Cliente') ?></strong>
							<?php if ($clientRut !== ''): ?> · RUT <?= h($clientRut) ?><?php endif; ?>
						</p>
					</div>
				</td>
			</tr>
		</table>

		<section class="annex">
			<div class="annex-block">
				<h2>Condiciones y modo de pago</h2>
				<div class="annex-body"><?= h($termsText) ?></div>
			</div>
			<div class="annex-block">
				<h2>Mizo · Ingeniería e integración</h2>
				<div class="annex-body"><?= h($aboutText) ?></div>
			</div>
		</section>

		<?php if ($canDecide): ?>
			<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;border:1px solid #e4e4e4;">
				<tr>
					<td align="center" style="padding:12px 10px;">
						<p style="margin:0 0 10px;font-size:11px;color:#444;">Respuesta al presupuesto</p>
						<a href="<?= h($acceptUrl) ?>" style="display:inline-block;background:#f47b20;color:#ffffff;text-decoration:none;padding:10px 16px;font-size:11px;font-weight:700;margin:0 4px 4px;">Aceptar presupuesto</a>
						<a href="<?= h($rejectUrl) ?>" style="display:inline-block;background:#ffffff;color:#444444;text-decoration:none;padding:9px 15px;font-size:11px;font-weight:700;border:1px solid #cccccc;margin:0 4px 4px;">No por ahora</a>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<footer class="foot">
			<p><strong>Mizo</strong> · Ingeniería e instalación profesional · <?= h(Config::PHONE) ?> · <?= h(Config::EMAIL) ?> · mizo.cl</p>
			<p>Frutillar y Santiago, Chile. Documento <?= h($quote['number']) ?> · Página 2 de 2</p>
		</footer>
	</div>
</body>
</html>
