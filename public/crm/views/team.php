<?php use MizoCrm\Auth; use MizoCrm\Csrf; use MizoCrm\Http; use MizoCrm\Models\User; ?>
<div class="page-head">
	<div>
		<h1>Equipo</h1>
		<p>Cada ejecutivo ve solo sus clientes. El administrador ve todos y configura las firmas de correo.</p>
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

	<section class="paper">
		<h2 class="section-title word">Firmas de correo</h2>
		<p class="muted" style="margin:-6px 0 16px">Esta firma se agrega al final de los correos y cotizaciones que envíe cada persona desde el CRM.</p>
		<div class="signature-grid">
			<?php foreach ($users as $member): ?>
				<form class="form signature-card" method="post" action="<?= h(Http::url('/equipo/' . $member['id'] . '/firma')) ?>">
					<?= Csrf::field() ?>
					<strong><?= h($member['name']) ?></strong>
					<span class="muted"><?= h($member['email']) ?></span>
					<label>
						<span>Firma</span>
						<textarea name="signature" rows="6" required><?= h(User::signatureText($member)) ?></textarea>
					</label>
					<div class="form-actions">
						<button class="btn btn-word" type="submit">Guardar firma</button>
					</div>
				</form>
			<?php endforeach; ?>
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
