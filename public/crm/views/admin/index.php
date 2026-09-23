<?php
use MizoCrm\Http;
use MizoCrm\Models\AdminReport;

$feed = $feed ?? [];
$team = $team ?? [];
$stages = $stages ?? [];
$filters = $filters ?? ['ejecutivo' => 0, 'desde' => '', 'hasta' => '', 'estado' => ''];
?>
<div class="admin-page">
	<div class="page-head">
		<div>
			<p class="file-kicker">Control</p>
			<h1>Auditoría</h1>
			<p>Movimientos, correos, cotizaciones, notas y llamadas de cada ejecutivo.</p>
		</div>
		<a class="btn btn-word" href="<?= h(Http::url('/admin/estadisticas')) ?>">Ver estadísticas</a>
	</div>

	<form class="admin-filters" method="get" action="<?= h(Http::url('/admin')) ?>">
		<label>
			<span>Ejecutivo asignado</span>
			<select name="ejecutivo">
				<option value="0">Todos</option>
				<?php foreach ($team as $member): ?>
					<option value="<?= (int) $member['id'] ?>" <?= (int) $filters['ejecutivo'] === (int) $member['id'] ? 'selected' : '' ?>><?= h($member['name']) ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span>Desde</span>
			<input type="date" name="desde" value="<?= h($filters['desde']) ?>">
		</label>
		<label>
			<span>Hasta</span>
			<input type="date" name="hasta" value="<?= h($filters['hasta']) ?>">
		</label>
		<label>
			<span>Estado del proyecto</span>
			<select name="estado">
				<option value="">Todos</option>
				<?php foreach ($stages as $key => $label): ?>
					<option value="<?= h($key) ?>" <?= $filters['estado'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button class="btn btn-word" type="submit">Filtrar</button>
	</form>

	<?php if (!$feed): ?>
		<div class="empty"><p>No hay movimientos con esos filtros.</p></div>
	<?php else: ?>
		<div class="table-wrap">
			<table class="sheet">
				<thead>
					<tr>
						<th>Fecha</th>
						<th>Quién lo hizo</th>
						<th>Cliente</th>
						<th>Asignado a</th>
						<th>Tipo</th>
						<th>Detalle</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($feed as $row): ?>
						<tr>
							<td><?= h(when($row['created_at'] ?? null)) ?></td>
							<td><?= h($row['user_name'] ?: 'Sistema') ?></td>
							<td>
								<?php if (!empty($row['client_id'])): ?>
									<a href="<?= h(Http::url('/tablero/cliente/' . $row['client_id'] . '/ficha#auditoria')) ?>"><?= h($row['client_name'] ?: 'Cliente') ?></a>
								<?php else: ?>
									—
								<?php endif; ?>
							</td>
							<td><?= h($row['owner_name'] ?: 'Sin asignar') ?></td>
							<td><?= h(AdminReport::typeLabel((string) $row['type'])) ?></td>
							<td><?= nl2br(h($row['message'])) ?><?php if (!empty($row['deal_title']) && !str_contains((string) $row['message'], (string) $row['deal_title'])): ?><br><small><?= h($row['deal_title']) ?></small><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="muted">Se muestran los últimos <?= count($feed) ?> registros. Los movimientos nuevos indican de qué columna a cuál se movió la tarjeta.</p>
	<?php endif; ?>
</div>
