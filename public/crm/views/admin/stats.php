<?php
use MizoCrm\Http;

$pipeline = $pipeline ?? [];
$executives = $executives ?? [];
$web = $web ?? ['forms' => 0, 'web_clients' => 0, 'services' => [], 'projects' => []];
$trend = $trend ?? ['labels' => [], 'quotes' => [], 'won' => []];
$finance = $finance ?? [
	'sales_net' => 0,
	'sales_tax' => 0,
	'purchase_tax' => 0,
	'vat_net' => 0,
	'travel' => 0,
	'operations' => 0,
	'other_costs' => 0,
	'margin' => 0,
	'cost' => 0,
];
$margins = $margins ?? [];
$quotesSent = 0;
foreach ($executives as $person) {
	$quotesSent += (int) ($person['quotes_sent'] ?? 0);
}
$closed = (int) ($won ?? 0) + (int) ($lost ?? 0);
$conversion = $closed > 0 ? (int) round(100 * (int) $won / $closed) : null;
?>
<div class="admin-page">
	<div class="page-head">
		<div>
			<p class="file-kicker">Control</p>
			<h1>Estadísticas</h1>
			<p>Embudo, rendimiento de cada ejecutivo y consultas que llegan desde el sitio.</p>
		</div>
		<a class="btn btn-word" href="<?= h(Http::url('/admin')) ?>">Ver auditoría</a>
	</div>

	<div class="admin-kpis">
		<article class="admin-kpi">
			<strong><?= (int) $openCount ?></strong>
			<span>Proyectos abiertos</span>
		</article>
		<article class="admin-kpi is-orange">
			<strong><?= money((int) $openAmount) ?></strong>
			<span>Dinero en el embudo</span>
		</article>
		<article class="admin-kpi">
			<strong><?= (int) $quotesSent ?></strong>
			<span>Cotizaciones enviadas</span>
		</article>
		<article class="admin-kpi is-orange">
			<strong><?= $conversion === null ? '—' : $conversion . '%' ?></strong>
			<span>Conversión ganados / cerrados</span>
		</article>
		<article class="admin-kpi">
			<strong><?= (int) ($web['forms'] ?? 0) ?></strong>
			<span>Formularios del sitio</span>
		</article>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Embudo de ventas</h2>
			<table class="sheet">
				<thead>
					<tr><th>Etapa</th><th>Proyectos</th><th>Monto</th></tr>
				</thead>
				<tbody>
					<?php foreach ($pipeline as $row): ?>
						<tr>
							<td><?= h($row['label']) ?></td>
							<td><?= (int) $row['count'] ?></td>
							<td><?= money((int) $row['amount']) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<section class="admin-card">
			<h2>Proyectos por etapa</h2>
			<canvas id="chart-pipeline" height="220"></canvas>
		</section>
	</div>

	<section class="admin-card">
		<h2>Márgenes y facturación</h2>
		<p class="muted">El margen es la venta neta menos la compra neta, viáticos, gastos de operación y otros costos. El IVA no entra en la rentabilidad.</p>
		<div class="admin-kpis">
			<article class="admin-kpi">
				<strong><?= money((int) $finance['sales_net']) ?></strong>
				<span>Ventas netas</span>
			</article>
			<article class="admin-kpi is-orange">
				<strong><?= money((int) $finance['sales_tax']) ?></strong>
				<span>IVA por pagar</span>
			</article>
			<article class="admin-kpi">
				<strong><?= money((int) $finance['purchase_tax']) ?></strong>
				<span>IVA a recuperar</span>
			</article>
			<article class="admin-kpi is-orange">
				<strong><?= money((int) $finance['vat_net']) ?></strong>
				<span>IVA neto</span>
			</article>
			<article class="admin-kpi">
				<strong><?= money((int) $finance['travel']) ?></strong>
				<span>Viáticos</span>
			</article>
			<article class="admin-kpi is-orange">
				<strong><?= money((int) $finance['operations']) ?></strong>
				<span>Gastos de operación</span>
			</article>
			<article class="admin-kpi">
				<strong><?= money((int) ($finance['other_costs'] ?? 0)) ?></strong>
				<span>Otros costos</span>
			</article>
			<article class="admin-kpi is-orange">
				<strong><?= money((int) $finance['margin']) ?></strong>
				<span>Rentabilidad real</span>
			</article>
		</div>
		<?php if (!$margins): ?>
			<p class="muted">Cuando registres facturas, el margen de cada proyecto aparece aquí.</p>
		<?php else: ?>
			<div class="table-wrap">
				<table class="sheet">
					<thead>
						<tr><th>Cliente</th><th>Proyecto</th><th>Venta neta</th><th>Costos</th><th>Margen</th><th>Estado</th></tr>
					</thead>
					<tbody>
						<?php foreach ($margins as $row): ?>
							<tr>
								<td><?= h($row['client_name']) ?></td>
								<td><?= h($row['title']) ?></td>
								<td><?= money((int) $row['revenue']) ?></td>
								<td><?= money((int) $row['cost']) ?></td>
								<td><?= money((int) $row['margin']) ?></td>
								<td><?= !empty($row['archived']) ? 'Completado' : 'Activo' ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
	<section class="admin-card">
		<h2>Rendimiento por ejecutivo</h2>
		<div class="table-wrap">
			<table class="sheet">
				<thead>
					<tr>
						<th>Ejecutivo</th>
						<th>Cotizaciones enviadas</th>
						<th>Abiertos</th>
						<th>Ganados</th>
						<th>Descartados</th>
						<th>Conversión</th>
						<th>Correos</th>
						<th>Llamadas</th>
						<th>Notas</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($executives as $person): ?>
						<tr>
							<td><?= h($person['name']) ?></td>
							<td><?= (int) $person['quotes_sent'] ?></td>
							<td><?= (int) $person['open_deals'] ?> · <?= money((int) $person['open_amount']) ?></td>
							<td><?= (int) $person['won'] ?></td>
							<td><?= (int) $person['lost'] ?></td>
							<td><?= $person['conversion'] === null ? '—' : (int) $person['conversion'] . '%' ?></td>
							<td><?= (int) $person['mails'] ?></td>
							<td><?= (int) $person['calls_total'] ?></td>
							<td><?= (int) $person['notes'] ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="muted">La conversión compara proyectos ganados con descartados. Las llamadas suman las registradas en la ficha y los pasos a «Contacto / Llamada realizada».</p>
	</section>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Tendencia semanal</h2>
			<canvas id="chart-trend" height="220"></canvas>
		</section>
		<section class="admin-card">
			<h2>Servicios consultados en la web</h2>
			<p class="muted"><?= (int) ($web['web_clients'] ?? 0) ?> clientes quedaron marcados como origen sitio web.</p>
			<canvas id="chart-web" height="220"></canvas>
		</section>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
	const blue = "#1c9bd8";
	const orange = "#f47b20";
	const ink = "#252423";
	const pipeline = <?= json_encode($pipeline, JSON_UNESCAPED_UNICODE) ?>;
	const trend = <?= json_encode($trend, JSON_UNESCAPED_UNICODE) ?>;
	const web = <?= json_encode($web['services'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
	const common = { responsive: true, plugins: { legend: { labels: { color: ink } } } };
	new Chart(document.getElementById("chart-pipeline"), {
		type: "bar",
		data: {
			labels: pipeline.map(function (row) { return row.label; }),
			datasets: [{ label: "Proyectos", data: pipeline.map(function (row) { return row.count; }), backgroundColor: blue }]
		},
		options: common
	});
	new Chart(document.getElementById("chart-trend"), {
		type: "line",
		data: {
			labels: trend.labels,
			datasets: [
				{ label: "Cotizaciones enviadas", data: trend.quotes, borderColor: blue, backgroundColor: blue, tension: 0.25 },
				{ label: "Cierres ganados", data: trend.won, borderColor: orange, backgroundColor: orange, tension: 0.25 }
			]
		},
		options: common
	});
	new Chart(document.getElementById("chart-web"), {
		type: "bar",
		data: {
			labels: web.map(function (row) { return row.label; }),
			datasets: [{ label: "Consultas", data: web.map(function (row) { return row.count; }), backgroundColor: orange }]
		},
		options: Object.assign({}, common, { indexAxis: "y" })
	});
})();
</script>
