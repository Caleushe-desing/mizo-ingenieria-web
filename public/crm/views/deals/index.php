<?php use MizoCrm\Config; use MizoCrm\Http; ?>
<div class="top-actions" style="justify-content:space-between;margin-bottom:16px">
	<div>
		<p class="kicker">Objeto</p>
		<h1>Negocios</h1>
	</div>
	<a class="btn" href="<?= h(Http::url('/negocios/nuevo')) ?>">Nuevo negocio</a>
</div>
<div class="filters">
	<a class="<?= $stage === '' ? 'is-on' : '' ?>" href="<?= h(Http::url('/negocios')) ?>">Todos</a>
	<?php foreach (Config::stages() as $key => $label): ?>
		<a class="<?= $stage === $key ? 'is-on' : '' ?>" href="<?= h(Http::url('/negocios?etapa=' . $key)) ?>"><?= h($label) ?></a>
	<?php endforeach; ?>
	<a class="<?= !empty($mine) ? 'is-on' : '' ?>" href="<?= h(Http::url('/negocios?mios=1')) ?>">Míos</a>
</div>
<div class="card">
	<table class="table">
		<thead><tr><th>Negocio</th><th>Cliente</th><th>Línea</th><th>Etapa</th><th>Monto</th></tr></thead>
		<tbody>
		<?php foreach ($deals as $deal): ?>
			<tr>
				<td><a href="<?= h(Http::url('/negocios/' . $deal['id'])) ?>"><b><?= h($deal['title']) ?></b></a><div style="color:var(--muted)"><?= when($deal['updated_at'], 'd-m-Y') ?></div></td>
				<td><?= h($deal['client_name']) ?></td>
				<td><?= h(Config::services()[$deal['service']] ?? $deal['service']) ?></td>
				<td><span class="badge <?= h($deal['stage']) ?>"><?= h(Config::stages()[$deal['stage']]) ?></span></td>
				<td><?= money((int) $deal['amount']) ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$deals): ?><tr><td colspan="5" class="empty">No hay negocios en este filtro.</td></tr><?php endif; ?>
		</tbody>
	</table>
</div>
