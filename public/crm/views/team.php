<?php use MizoCrm\Auth; use MizoCrm\Csrf; use MizoCrm\Http; ?>
<div class="page-head">
	<div>
		<h1>Equipo</h1>
		<p>Cada ejecutivo ve solo sus clientes. El administrador ve todos.</p>
	</div>
</div>
<div class="stack">
	<section class="paper">
		<h2 class="section-title excel">Personas con acceso</h2>
		<div class="table-wrap">
			<table class="sheet">
				<thead>
					<tr>
						<th>Nombre</th>
						<th>Correo</th>
						<th>Rol</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($users as $member): ?>
					<tr>
						<td><?= h($member['name']) ?></td>
						<td><?= h($member['email']) ?></td>
						<td><?= h($member['role'] === 'admin' ? 'Administrador' : 'Ejecutivo') ?></td>
						<td>
							<?php if ((int) $member['id'] !== (int) Auth::id()): ?>
								<form method="post" action="<?= h(Http::url('/equipo/' . $member['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Quitar el acceso de <?= h($member['name']) ?>?');">
									<?= Csrf::field() ?>
									<button class="btn-danger-text" type="submit">Quitar acceso</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
	<form class="paper form" method="post" action="<?= h(Http::url('/equipo')) ?>" style="max-width:520px">
		<h2 class="section-title word">Sumar colega</h2>
		<?= Csrf::field() ?>
		<label><span>Nombre</span><input name="name" required></label>
		<label><span>Correo</span><input name="email" type="email" required></label>
		<label><span>Clave inicial</span><input name="password" type="password" minlength="8" required></label>
		<label>
			<span>Rol</span>
			<select name="role">
				<option value="vendedor">Ejecutivo</option>
				<option value="admin">Administrador</option>
			</select>
		</label>
		<div class="form-actions">
			<button class="btn btn-excel" type="submit">Crear acceso</button>
		</div>
	</form>
</div>
