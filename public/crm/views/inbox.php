<?php
use MizoCrm\Auth;
use MizoCrm\Http;

$filters = [
	'todas' => 'Tablero',
	'pendiente' => 'Por enviar',
	'enviada' => 'Enviadas',
	'ganada' => 'En trabajo',
	'perdida' => 'Perdidas',
];
if (Auth::isAdmin()) {
	$filters = ['libre' => 'Sin asignar'] + $filters;
}
$ejecutivo = (int) ($ejecutivo ?? 0);
$team = $team ?? [];
$filter = $filter ?? 'todas';
$q = $q ?? '';
$order = ['pendiente', 'enviada', 'ganada', 'perdida'];
$groups = [];
foreach ($order as $key) {
	$groups[$key] = [];
}
foreach ($items as $item) {
	$st = work_status($item);
	$groups[$st][] = $item;
}
$visible = $filter === 'todas' || $filter === 'libre' ? $order : [$filter];
$query = ($filter !== 'todas' ? '&ver=' . rawurlencode($filter) : '')
	. ($q !== '' ? '&q=' . rawurlencode($q) : '')
	. ($ejecutivo ? '&ejecutivo=' . $ejecutivo : '');
?>
<div class="board-hd">
	<div>
		<h1>Tablero</h1>
		<p><?= Auth::isAdmin() ? 'Toda la cartera, agrupada por estado.' : 'Tus clientes, agrupados por estado.' ?></p>
	</div>
	<form class="board-tools" method="get" action="<?= h(Http::url('/')) ?>">
		<?php if ($filter !== 'todas'): ?><input type="hidden" name="ver" value="<?= h($filter) ?>"><?php endif; ?>
		<input name="q" value="<?= h($q) ?>" placeholder="Buscar…">
		<?php if (Auth::isAdmin() && $team): ?>
			<select name="ejecutivo" onchange="this.form.submit()">
				<option value="0">Todos</option>
				<?php foreach ($team as $member): ?>
					<option value="<?= (int) $member['id'] ?>" <?= $ejecutivo === (int) $member['id'] ? 'selected' : '' ?>><?= h($member['name']) ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<button class="btn-ghost" type="submit">Buscar</button>
	</form>
</div>

<nav class="filters">
	<?php foreach ($filters as $key => $label): ?>
		<a class="filter-<?= h($key) ?> <?= $filter === $key ? 'is-on' : '' ?>" href="<?= h(Http::url('/?ver=' . $key . ($q !== '' ? '&q=' . rawurlencode($q) : '') . ($ejecutivo ? '&ejecutivo=' . $ejecutivo : ''))) ?>">
			<?= h($label) ?>
			<?php if (!empty($counts[$key])): ?><span class="count"><?= (int) $counts[$key] ?></span><?php endif; ?>
		</a>
	<?php endforeach; ?>
</nav>

<?php if (empty($items)): ?>
	<div class="empty card">
		<p>Este grupo está vacío.</p>
		<a class="btn" href="<?= h(Http::url('/nueva')) ?>">Nuevo caso</a>
	</div>
<?php else: ?>
	<?php foreach ($visible as $key):
		$rows = $groups[$key] ?? [];
		if ($rows === []) {
			continue;
		}
	?>
		<section class="group group-<?= h($key) ?>">
			<header class="group-hd">
				<span class="group-dot"></span>
				<h2><?= h(work_status_label($key)) ?></h2>
				<span class="count"><?= count($rows) ?></span>
			</header>
			<div class="sheet-wrap">
				<table class="sheet">
					<thead>
						<tr>
							<th>Cliente</th>
							<th>Trabajo</th>
							<th>Estado</th>
							<?php if (Auth::isAdmin()): ?><th>Ejecutivo</th><?php endif; ?>
							<th>Monto</th>
							<th>Actualizado</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($rows as $item):
						$st = work_status($item);
						$total = (int) ($item['quote_total'] ?: $item['amount']);
					?>
						<tr class="is-<?= h($st) ?>" onclick="location.href='<?= h(Http::url('/t/' . $item['id'])) ?>'">
							<td>
								<b><?= h($item['client_name']) ?></b>
								<small><?= h($item['client_email'] ?: $item['client_phone'] ?: '—') ?></small>
							</td>
							<td><?= h($item['title']) ?><?= !empty($item['quote_number']) ? ' · ' . h($item['quote_number']) : '' ?></td>
							<td><span class="badge <?= h($st) ?>"><?= h(work_status_label($st)) ?></span></td>
							<?php if (Auth::isAdmin()): ?>
								<td><?= h($item['owner_name'] ?: 'Sin asignar') ?></td>
							<?php endif; ?>
							<td><?= $total > 0 ? money($total) : '—' ?></td>
							<td><?= when($item['updated_at'], 'd-m H:i') ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endforeach; ?>
<?php endif; ?>
