<?php use MizoCrm\Config; use MizoCrm\Http; ?>
<div class="top-actions" style="justify-content:space-between;margin-bottom:16px">
	<div>
		<p class="kicker">Objeto</p>
		<h1>Cotizaciones</h1>
	</div>
	<a class="btn" href="<?= h(Http::url('/cotizaciones/nueva')) ?>">Nueva cotización</a>
</div>
<div class="filters">
	<a class="<?= $status === '' ? 'is-on' : '' ?>" href="<?= h(Http::url('/cotizaciones')) ?>">Todas</a>
	<?php foreach (Config::quoteStatuses() as $key => $label): ?>
		<a class="<?= $status === $key ? 'is-on' : '' ?>" href="<?= h(Http::url('/cotizaciones?estado=' . $key)) ?>"><?= h($label) ?></a>
	<?php endforeach; ?>
</div>
<div class="card">
	<table class="table">
		<thead><tr><th>Número</th><th>Cliente / negocio</th><th>Estado</th><th>Total</th><th>Envío</th></tr></thead>
		<tbody>
		<?php foreach ($quotes as $quote): ?>
			<tr>
				<td><a href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>"><b><?= h($quote['number']) ?></b></a></td>
				<td><?= h($quote['client_name']) ?><div style="color:var(--muted)"><?= h($quote['deal_title']) ?></div></td>
				<td><span class="badge <?= h($quote['status']) ?>"><?= h(Config::quoteStatuses()[$quote['status']]) ?></span></td>
				<td><?= money((int) $quote['total']) ?></td>
				<td><?= $quote['sent_at'] ? when($quote['sent_at'], 'd-m-Y H:i') : '—' ?><div style="color:var(--muted)"><?= h($quote['sent_to'] ?: '') ?></div></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$quotes): ?><tr><td colspan="5" class="empty">Todavía no hay presupuestos registrados.</td></tr><?php endif; ?>
		</tbody>
	</table>
</div>
