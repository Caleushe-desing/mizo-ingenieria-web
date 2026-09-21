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
		<p class="muted" style="margin:-6px 0 16px">Esta firma se agrega al final de los correos y cotizaciones que envíe cada persona. Puedes pegar una imagen con Ctrl+V y dar formato al texto.</p>
		<div class="signature-grid">
			<?php foreach ($users as $member): ?>
				<form class="form signature-card" method="post" action="<?= h(Http::url('/equipo/' . $member['id'] . '/firma')) ?>" data-rich-form>
					<?= Csrf::field() ?>
					<strong><?= h($member['name']) ?></strong>
					<span class="muted"><?= h($member['email']) ?></span>
					<div class="rich-editor" data-rich-editor data-upload="<?= h(Http::url('/equipo/firma-imagen')) ?>" data-csrf="<?= h(Csrf::token()) ?>">
						<div class="rich-toolbar" role="toolbar">
							<button type="button" data-cmd="bold" title="Negrita"><b>B</b></button>
							<button type="button" data-cmd="italic" title="Cursiva"><i>I</i></button>
							<button type="button" data-cmd="underline" title="Subrayado"><u>U</u></button>
							<select data-fontsize title="Tamaño">
								<option value="">Tamaño</option>
								<option value="2">Pequeño</option>
								<option value="3">Normal</option>
								<option value="4">Mediano</option>
								<option value="5">Grande</option>
								<option value="6">Muy grande</option>
							</select>
							<input type="color" data-color value="#444444" title="Color de texto">
							<button type="button" data-cmd="justifyLeft" title="Alinear a la izquierda">Izq</button>
							<button type="button" data-cmd="justifyCenter" title="Centrar">Centro</button>
							<button type="button" data-pick-image title="Agregar imagen">Imagen</button>
							<input type="file" data-file accept="image/png,image/jpeg,image/gif,image/webp" hidden>
						</div>
						<div class="rich-area" contenteditable="true" spellcheck="true"><?= User::signatureEditorHtml($member) ?></div>
						<p class="muted rich-hint">Ctrl+V pega una imagen. Usa la barra para negrita, tamaño, color y alineación.</p>
						<input type="hidden" name="signature" data-rich-input>
					</div>
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
