<?php
use MizoCrm\Config;
use MizoCrm\Http;

$summary = $summary ?? [
	'sale_net' => 0,
	'cost_total_net' => 0,
	'cost_total_iva' => 0,
	'profit' => 0,
	'margin_real' => null,
	'count' => 0,
];
?>
<div class="top-actions" style="justify-content:space-between;margin-bottom:16px">
	<div>
		<p class="kicker">Interno</p>
		<h1>Cotizaciones</h1>
		<p class="muted">Rentabilidad sobre costo. Costos y utilidad no se muestran al cliente.</p>
	</div>
	<a class="btn" href="<?= h(Http::url('/clientes')) ?>">Nueva desde cliente</a>
</div>

<section class="quote-profit-summary" aria-label="Resumen de rentabilidad">
	<div>
		<span>Venta neta</span>
		<strong><?= money((int) $summary['sale_net']) ?></strong>
	</div>
	<div>
		<span>Costo neto</span>
		<strong><?= money((int) $summary['cost_total_net']) ?></strong>
		<small class="muted">c/IVA <?= money((int) $summary['cost_total_iva']) ?></small>
	</div>
	<div>
		<span>Utilidad</span>
		<strong class="<?= (int) $summary['profit'] >= 0 ? 'is-gain' : 'is-loss' ?>"><?= money((int) $summary['profit']) ?></strong>
	</div>
	<div>
		<span>Margen real</span>
		<strong class="<?= (int) $summary['profit'] >= 0 ? 'is-gain' : 'is-loss' ?>">
			<?= $summary['margin_real'] !== null ? h(number_format((float) $summary['margin_real'], 1, ',', '.')) . '%' : '—' ?>
		</strong>
		<small class="muted"><?= (int) $summary['count'] ?> cotización<?= (int) $summary['count'] === 1 ? '' : 'es' ?></small>
	</div>
</section>

<div class="filters">
	<a class="<?= $status === '' ? 'is-on' : '' ?>" href="<?= h(Http::url('/cotizaciones')) ?>">Todas</a>
	<?php foreach (Config::quoteStatuses() as $key => $label): ?>
		<a class="<?= $status === $key ? 'is-on' : '' ?>" href="<?= h(Http::url('/cotizaciones?estado=' . $key)) ?>"><?= h($label) ?></a>
	<?php endforeach; ?>
</div>
<div class="card">
	<div class="table-wrap">
		<table class="table">
			<thead>
				<tr>
					<th>Número</th>
					<th>Cliente / negocio</th>
					<th>Estado</th>
					<th class="is-num">Venta neta</th>
					<th class="is-num">Costo neto</th>
					<th class="is-num">Utilidad</th>
					<th class="is-num">Margen real</th>
					<th>Envío</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($quotes as $quote): ?>
				<?php
				$saleNet = (int) ($quote['sale_net'] ?? $quote['subtotal'] ?? 0);
				$costNet = (int) ($quote['cost_total_net'] ?? 0);
				$profit = (int) ($quote['profit'] ?? ($saleNet - $costNet));
				$margin = $quote['margin_real'] ?? null;
				?>
				<tr>
					<td><a href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>"><b><?= h($quote['number']) ?></b></a></td>
					<td><?= h($quote['client_name']) ?><div class="muted"><?= h($quote['deal_title']) ?></div></td>
					<td><span class="badge <?= h($quote['status']) ?>"><?= h(Config::quoteStatuses()[$quote['status']] ?? $quote['status']) ?></span></td>
					<td class="is-num"><?= money($saleNet) ?></td>
					<td class="is-num">
						<?= money($costNet) ?>
						<?php if ((int) ($quote['cost_total_iva'] ?? 0) > 0): ?>
							<div class="muted" style="font-size:12px">c/IVA <?= money((int) $quote['cost_total_iva']) ?></div>
						<?php endif; ?>
					</td>
					<td class="is-num <?= $profit >= 0 ? 'is-gain' : 'is-loss' ?>"><?= money($profit) ?></td>
					<td class="is-num <?= $profit >= 0 ? 'is-gain' : 'is-loss' ?>">
						<?= $margin !== null ? h(number_format((float) $margin, 1, ',', '.')) . '%' : '—' ?>
					</td>
					<td><?= $quote['sent_at'] ? when($quote['sent_at'], 'd-m-Y H:i') : '—' ?><div class="muted"><?= h($quote['sent_to'] ?: '') ?></div></td>
				</tr>
			<?php endforeach; ?>
			<?php if (!$quotes): ?>
				<tr><td colspan="8" class="empty">Todavía no hay presupuestos registrados.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
