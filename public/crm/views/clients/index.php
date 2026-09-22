<?php use MizoCrm\Auth; use MizoCrm\Csrf; use MizoCrm\Http; ?>
<div class="page-head">
	<div>
		<h1>Clientes</h1>
		<p>Abre un cliente para ver sus comentarios y cotizaciones.</p>
	</div>
	<form class="search" method="get" action="<?= h(Http::url('/')) ?>">
		<input name="q" value="<?= h($q ?? '') ?>" placeholder="Buscar por cliente, RUT, contacto o correo" aria-label="Buscar cliente">
		<button class="btn btn-word" type="submit">Buscar</button>
	</form>
</div>

<div class="paper">
	<?php if (!$clients): ?>
		<div class="empty">
			<p>Todavía no hay clientes<?= ($q ?? '') !== '' ? ' con esa búsqueda' : '' ?>.</p>
			<a class="btn btn-excel" href="<?= h(Http::url('/clientes/nuevo')) ?>">Agregar el primero</a>
		</div>
	<?php else: ?>
		<div class="table-wrap">
			<table class="sheet">
				<thead>
					<tr>
						<th>Cliente</th>
						<th>RUT</th>
						<th>Contacto</th>
						<th>Teléfono</th>
						<th>Correo</th>
						<th>Último comentario</th>
						<th>Cotizaciones</th>
						<?php if (Auth::isAdmin()): ?><th>Ejecutivo</th><?php endif; ?>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($clients as $row): ?>
					<tr>
						<td><a href="<?= h(Http::url('/clientes/' . $row['id'])) ?>"><?= h($row['name']) ?></a></td>
						<td><?= h($row['rut'] ?: '—') ?></td>
						<td><?= h($row['contact_name'] ?: '—') ?></td>
						<td><?= h($row['phone'] ?: '—') ?></td>
						<td><?= h($row['email'] ?: '—') ?></td>
						<td class="muted"><?= h($row['last_note'] ? (function_exists('mb_strimwidth') ? mb_strimwidth((string) $row['last_note'], 0, 70, '…', 'UTF-8') : substr((string) $row['last_note'], 0, 70)) : 'Sin comentarios') ?></td>
						<td><?= (int) $row['quotes_count'] ?></td>
						<?php if (Auth::isAdmin()): ?>
							<td><?= h($row['owner_name'] ?: 'Sin asignar') ?></td>
						<?php endif; ?>
						<td>
							<form method="post" action="<?= h(Http::url('/clientes/' . $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar a <?= h($row['name']) ?>? También se borran sus comentarios y cotizaciones.');">
								<?= Csrf::field() ?>
								<button class="btn-danger-text" type="submit">Eliminar</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
