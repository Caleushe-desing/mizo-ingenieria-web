<?php
use MizoCrm\Http;

$report = $report ?? [];
$days = (int) ($days ?? 7);
$views = (int) ($report['views'] ?? 0);

$bar = static function (array $rows): void {
	$max = 1;
	foreach ($rows as $row) {
		$max = max($max, (int) ($row['count'] ?? 0));
	}
	if ($rows === []) {
		echo '<p class="muted">Todavía no hay visitas en este período.</p>';
		return;
	}
	echo '<ul class="visit-bars">';
	foreach ($rows as $row) {
		$count = (int) ($row['count'] ?? 0);
		$width = (int) round($count * 100 / $max);
		echo '<li><span>' . h((string) ($row['label'] ?? '')) . '</span><b style="width:' . $width . '%"></b><em>' . $count . '</em></li>';
	}
	echo '</ul>';
};
?>
<div class="admin-page">
	<div class="page-head">
		<div>
			<p class="file-kicker">Sitio</p>
			<h1>Visitas</h1>
			<p>Quién entra a mizo.cl, desde dónde llega y qué páginas mira. El registro parte desde ahora; no incluye visitas anteriores.</p>
		</div>
		<div class="page-head-actions">
			<?php foreach ([1 => 'Hoy', 7 => '7 días', 30 => '30 días', 90 => '90 días'] as $value => $label): ?>
				<a class="btn<?= $days === $value ? ' btn-word' : '' ?>" href="<?= h(Http::url('/visitas?dias=' . $value)) ?>"><?= h($label) ?></a>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="admin-kpis">
		<article class="admin-kpi is-orange">
			<strong class="stat-num"><?= $views ?></strong>
			<span>Páginas vistas</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($report['people'] ?? 0) ?></strong>
			<span>Visitantes</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($report['sessions'] ?? 0) ?></strong>
			<span>Sesiones</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= h((string) ($report['pages'] ?? 0)) ?></strong>
			<span>Páginas por visita</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($report['bounce'] ?? 0) ?>%</strong>
			<span>Salieron en la primera página</span>
		</article>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Visitas por día</h2>
			<?php $bar($report['days'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>De dónde llegan</h2>
			<?php $bar($report['sources'] ?? []); ?>
		</section>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Páginas más vistas</h2>
			<?php $bar($report['pages_top'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>Página de entrada</h2>
			<?php $bar($report['landings'] ?? []); ?>
		</section>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Campañas (utm)</h2>
			<?php $bar($report['campaigns'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>Horario (Chile)</h2>
			<?php $bar($report['hours'] ?? []); ?>
		</section>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Dispositivo</h2>
			<?php $bar($report['devices'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>Navegador</h2>
			<?php $bar($report['browsers'] ?? []); ?>
		</section>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Sistema</h2>
			<?php $bar($report['systems'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>Idioma y país</h2>
			<?php
			$geo = array_merge($report['countries'] ?? [], $report['languages'] ?? []);
			$bar($geo);
			?>
		</section>
	</div>

	<section class="admin-card">
		<h2>Últimas visitas</h2>
		<div class="table-wrap">
			<table class="sheet">
				<thead>
					<tr>
						<th>Hora</th>
						<th>Página</th>
						<th>Origen</th>
						<th>Campaña</th>
						<th>Equipo</th>
						<th>Idioma</th>
						<th>País</th>
						<th>IP</th>
					</tr>
				</thead>
				<tbody>
				<?php if (($report['recent'] ?? []) === []): ?>
					<tr><td colspan="8">Cuando alguien entre al sitio, la visita aparece aquí.</td></tr>
				<?php else: ?>
					<?php foreach ($report['recent'] as $row): ?>
						<tr>
							<td><?= h(substr((string) $row['visited_at'], 11, 5)) ?> · <?= h(substr((string) $row['visited_at'], 8, 2)) ?>/<?= h(substr((string) $row['visited_at'], 5, 2)) ?></td>
							<td>
								<a href="<?= h((string) $row['path']) ?>" target="_blank" rel="noopener"><?= h((string) $row['path']) ?></a>
								<?php if (!empty($row['title'])): ?><br><span class="muted"><?= h((string) $row['title']) ?></span><?php endif; ?>
							</td>
							<td>
								<?= h((string) $row['source']) ?>
								<?php if (!empty($row['referrer']) && (string) $row['source'] !== 'Directo'): ?><br><span class="muted"><?= h((string) $row['referrer']) ?></span><?php endif; ?>
							</td>
							<td><?= h((string) ($row['utm_campaign'] ?: $row['utm_medium'])) ?></td>
							<td><?= h((string) $row['device']) ?> · <?= h((string) $row['browser']) ?> · <?= h((string) $row['os']) ?><?php if (!empty($row['screen'])): ?><br><span class="muted"><?= h((string) $row['screen']) ?></span><?php endif; ?></td>
							<td><?= h((string) $row['language']) ?></td>
							<td><?= h((string) ($row['country'] ?: '—')) ?></td>
							<td><?= h((string) $row['ip']) ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
<style>
.visit-bars { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.visit-bars li { display: grid; grid-template-columns: minmax(88px, 180px) 1fr 36px; gap: 8px; align-items: center; font-size: 13px; }
.visit-bars span, .visit-bars em { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.visit-bars em { font-style: normal; text-align: right; color: #605e5c; }
.visit-bars b { display: block; height: 8px; background: #1c9bd8; border-radius: 99px; min-width: 2px; }
</style>
