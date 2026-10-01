<?php
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;

$summary = $summary ?? [
	'sale_net' => 0,
	'cost_total_net' => 0,
	'cost_total_iva' => 0,
	'profit' => 0,
	'margin_real' => null,
	'count' => 0,
];
$profitPositive = (int) $summary['profit'] >= 0;
?>
<div class="quotes-dir">
	<header class="quotes-dir-hd">
		<div>
			<p class="kicker">Interno</p>
			<h1>Cotizaciones</h1>
			<p>Venta, costo y margen real. Estos números no salen al cliente.</p>
		</div>
		<a class="btn btn-word" href="<?= h(Http::url('/clientes')) ?>">Nueva desde cliente</a>
	</header>

	<section class="quotes-metrics" aria-label="Resumen de rentabilidad">
		<div class="quotes-metric">
			<span>Venta neta</span>
			<strong><?= money((int) $summary['sale_net']) ?></strong>
		</div>
		<div class="quotes-metric">
			<span>Costo neto</span>
			<strong><?= money((int) $summary['cost_total_net']) ?></strong>
		</div>
		<div class="quotes-metric">
			<span>Utilidad</span>
			<strong class="<?= $profitPositive ? 'is-gain' : 'is-loss' ?>"><?= money((int) $summary['profit']) ?></strong>
		</div>
		<div class="quotes-metric">
			<span>Margen real</span>
			<strong class="<?= $profitPositive ? 'is-gain' : 'is-loss' ?>">
				<?= $summary['margin_real'] !== null ? h(number_format((float) $summary['margin_real'], 1, ',', '.')) . '%' : '—' ?>
			</strong>
			<em><?= (int) $summary['count'] ?> cotiz.</em>
		</div>
	</section>

	<nav class="quotes-filters" aria-label="Filtrar por estado">
		<a class="<?= $status === '' ? 'is-on' : '' ?>" href="<?= h(Http::url('/cotizaciones')) ?>">Todas</a>
		<?php foreach (Config::quoteStatuses() as $key => $label): ?>
			<a class="<?= $status === $key ? 'is-on' : '' ?>" href="<?= h(Http::url('/cotizaciones?estado=' . $key)) ?>"><?= h($label) ?></a>
		<?php endforeach; ?>
	</nav>

	<section class="quotes-panel">
		<?php if (!$quotes): ?>
			<div class="quotes-empty">
				<p>Todavía no hay presupuestos<?= $status !== '' ? ' en este estado' : '' ?>.</p>
				<a class="btn" href="<?= h(Http::url('/clientes')) ?>">Ir a clientes</a>
			</div>
		<?php else: ?>
			<div class="quotes-table-wrap">
				<table class="quotes-table">
					<thead>
						<tr>
							<th>Cotización</th>
							<th>Cliente</th>
							<th class="is-num">Venta</th>
							<th class="is-num">Costo</th>
							<th class="is-num">Utilidad</th>
							<th class="is-num">Margen</th>
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
						$statusKey = (string) ($quote['status'] ?? '');
						$statusLabel = Config::quoteStatuses()[$statusKey] ?? $statusKey;
						?>
						<tr>
							<td>
								<a class="quotes-number" href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>"><?= h($quote['number']) ?></a>
								<span class="quotes-status quotes-status-<?= h($statusKey) ?>"><?= h($statusLabel) ?></span>
								<div class="quotes-row-actions">
									<a href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>">Abrir</a>
									<?php if (!empty($quote['last_email_html'])): ?>
										<a href="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/correo')) ?>">Correo</a>
									<?php endif; ?>
									<form method="post" action="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/copiar')) ?>" onsubmit="return confirm('¿Copiar <?= h($quote['number']) ?> con un número nuevo?');">
										<?= Csrf::field() ?>
										<button type="submit">Copiar</button>
									</form>
								</div>
							</td>
							<td>
								<span class="quotes-client"><?= h($quote['client_name']) ?></span>
								<span class="quotes-deal"><?= h($quote['deal_title'] ?: '—') ?></span>
							</td>
							<td class="is-num"><?= money($saleNet) ?></td>
							<td class="is-num" title="<?= (int) ($quote['cost_total_iva'] ?? 0) > 0 ? 'c/IVA ' . money((int) $quote['cost_total_iva']) : '' ?>"><?= money($costNet) ?></td>
							<td class="is-num <?= $profit >= 0 ? 'is-gain' : 'is-loss' ?>"><?= money($profit) ?></td>
							<td class="is-num <?= $profit >= 0 ? 'is-gain' : 'is-loss' ?>">
								<?= $margin !== null ? h(number_format((float) $margin, 1, ',', '.')) . '%' : '—' ?>
							</td>
							<td class="quotes-sent">
								<span><?= $quote['sent_at'] ? when($quote['sent_at'], 'd-m-Y') : 'Sin envío' ?></span>
								<?php if (!empty($quote['sent_to'])): ?>
									<small><?= h($quote['sent_to']) ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
