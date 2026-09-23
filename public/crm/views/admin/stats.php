<?php
use MizoCrm\Http;

$pipeline = $pipeline ?? [];
$desk = $desk ?? [];
$web = $web ?? ['forms' => 0, 'web_clients' => 0, 'services' => []];
$trend = $trend ?? ['labels' => [], 'quotes' => [], 'won' => []];
$people = $desk['people'] ?? [];
$stages = $desk['stages'] ?? [];
$rate = static function ($value): string {
	return $value === null ? '—' : (int) $value . '%';
};
$days = static function ($value): string {
	return $value === null ? '—' : (int) $value . ' días';
};
?>
<div class="admin-page">
	<div class="page-head">
		<div>
			<p class="file-kicker">Control</p>
			<h1>Estadísticas</h1>
			<p>Actividad del equipo: prospectos, correos, avance del tablero y respuestas de los clientes. Los montos están en Contabilidad.</p>
		</div>
		<div class="page-head-actions">
			<a class="btn btn-word" href="<?= h(Http::url('/admin')) ?>">Ver auditoría</a>
			<a class="btn" href="<?= h(Http::url('/contabilidad')) ?>">Ir a contabilidad</a>
		</div>
	</div>

	<div class="admin-kpis">
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($desk['prospects'] ?? 0) ?></strong>
			<span>Prospectos en gestión</span>
		</article>
		<article class="admin-kpi is-orange">
			<strong class="stat-num"><?= (int) ($desk['mails'] ?? 0) ?></strong>
			<span>Correos enviados</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($desk['quotes'] ?? 0) ?></strong>
			<span>Presupuestos enviados</span>
		</article>
		<article class="admin-kpi is-orange">
			<strong class="stat-num"><?= (int) ($desk['accepted'] ?? 0) ?></strong>
			<span>Clientes que aceptaron</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($desk['rejected'] ?? 0) ?></strong>
			<span>Rechazaron</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($desk['silent'] ?? 0) ?></strong>
			<span>Sin respuesta</span>
		</article>
		<article class="admin-kpi is-orange">
			<strong class="stat-num"><?= h($rate($desk['response_rate'] ?? null)) ?></strong>
			<span>Tasa de respuesta</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= h($days($desk['avg_reply_days'] ?? null)) ?></strong>
			<span>Días hasta la respuesta</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= h($days($desk['avg_idle_days'] ?? null)) ?></strong>
			<span>Días sin mover la tarjeta</span>
		</article>
		<article class="admin-kpi is-orange">
			<strong class="stat-num"><?= (int) ($web['forms'] ?? 0) ?></strong>
			<span>Formularios del sitio</span>
		</article>
	</div>
	<p class="acct-note">Un prospecto es una tarjeta abierta en el tablero, todavía sin cierre. Sin respuesta cuenta presupuestos enviados o vistos que el cliente no aceptó ni rechazó. Los días sin mover la tarjeta miden desde la última actualización de cada proyecto abierto.</p>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Tarjetas por columna</h2>
			<table class="sheet">
				<thead>
					<tr><th>Columna</th><th>Tarjetas</th></tr>
				</thead>
				<tbody>
					<?php foreach ($pipeline as $row): ?>
						<tr>
							<td><?= h($row['label']) ?></td>
							<td class="stat-num"><?= (int) $row['count'] ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<section class="admin-card">
			<h2>Avance del tablero</h2>
			<canvas id="chart-pipeline" height="220"></canvas>
		</section>
	</div>

	<section class="admin-card">
		<h2>Detalle por ejecutivo</h2>
		<div class="acct-scroll">
			<table class="sheet">
				<thead>
					<tr>
						<th>Ejecutivo</th>
						<th>Prospectos</th>
						<th>Correos</th>
						<th>Llamadas</th>
						<th>Notas</th>
						<th>Enviados</th>
						<th>Aceptaron</th>
						<th>Rechazaron</th>
						<th>Sin respuesta</th>
						<th>Respuesta</th>
						<th>Aceptación</th>
						<th>Días de respuesta</th>
						<th>Días quietos</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!$people): ?>
						<tr><td colspan="13">Todavía no hay ejecutivos activos.</td></tr>
					<?php endif; ?>
					<?php foreach ($people as $person): ?>
						<tr>
							<td><?= h($person['name']) ?></td>
							<td class="stat-num"><?= (int) $person['prospects'] ?></td>
							<td class="stat-num"><?= (int) $person['mails'] ?></td>
							<td class="stat-num"><?= (int) $person['calls'] ?></td>
							<td class="stat-num"><?= (int) $person['notes'] ?></td>
							<td class="stat-num"><?= (int) $person['quotes'] ?></td>
							<td class="stat-num"><?= (int) $person['accepted'] ?></td>
							<td class="stat-num"><?= (int) $person['rejected'] ?></td>
							<td class="stat-num"><?= (int) $person['silent'] ?></td>
							<td class="stat-num"><?= h($rate($person['response_rate'])) ?></td>
							<td class="stat-num"><?= h($rate($person['accept_rate'])) ?></td>
							<td class="stat-num"><?= h($days($person['avg_reply_days'])) ?></td>
							<td class="stat-num"><?= h($days($person['avg_idle_days'])) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="acct-note">La tasa de respuesta es cuántos presupuestos enviados recibieron un sí o un no. La de aceptación es solo los sí, sobre el total enviado. Los días de respuesta van desde el envío hasta que el cliente contestó.</p>
	</section>

	<section class="admin-card">
		<h2>Tablero por ejecutivo</h2>
		<div class="acct-scroll">
			<table class="sheet">
				<thead>
					<tr>
						<th>Ejecutivo</th>
						<?php foreach ($stages as $stage): ?>
							<th><?= h($stage['label']) ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($people as $person): ?>
						<tr>
							<td><?= h($person['name']) ?></td>
							<?php foreach ($stages as $stage): ?>
								<td class="stat-num"><?= (int) ($person['stages'][$stage['slug']] ?? 0) ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="acct-note">Cada número es una tarjeta en esa columna. Sirve para ver en qué parte del camino está el trabajo de cada ejecutivo, sin mirar montos.</p>
	</section>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Tendencia semanal</h2>
			<canvas id="chart-trend" height="220"></canvas>
		</section>
		<section class="admin-card">
			<h2>Servicios consultados en la web</h2>
			<p class="acct-note"><?= (int) ($web['web_clients'] ?? 0) ?> clientes quedaron marcados como origen sitio web.</p>
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
	const pipeline = <?= json_encode(array_map(static fn(array $row): array => ['label' => $row['label'], 'count' => (int) $row['count']], $pipeline), JSON_UNESCAPED_UNICODE) ?>;
	const trend = <?= json_encode($trend, JSON_UNESCAPED_UNICODE) ?>;
	const web = <?= json_encode($web['services'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
	const common = { responsive: true, plugins: { legend: { labels: { color: ink } } } };
	const bars = { ticks: { precision: 0 } };
	new Chart(document.getElementById("chart-pipeline"), {
		type: "bar",
		data: {
			labels: pipeline.map(function (row) { return row.label; }),
			datasets: [{ label: "Tarjetas", data: pipeline.map(function (row) { return row.count; }), backgroundColor: blue }]
		},
		options: Object.assign({}, common, { scales: { y: bars } })
	});
	new Chart(document.getElementById("chart-trend"), {
		type: "line",
		data: {
			labels: trend.labels,
			datasets: [
				{ label: "Presupuestos enviados", data: trend.quotes, borderColor: blue, backgroundColor: blue, tension: 0.25 },
				{ label: "Cierres ganados", data: trend.won, borderColor: orange, backgroundColor: orange, tension: 0.25 }
			]
		},
		options: Object.assign({}, common, { scales: { y: bars } })
	});
	new Chart(document.getElementById("chart-web"), {
		type: "bar",
		data: {
			labels: web.map(function (row) { return row.label; }),
			datasets: [{ label: "Consultas", data: web.map(function (row) { return row.count; }), backgroundColor: orange }]
		},
		options: Object.assign({}, common, { indexAxis: "y", scales: { x: bars } })
	});
})();
</script>
