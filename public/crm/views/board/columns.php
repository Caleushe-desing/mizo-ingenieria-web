<?php
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Stage;

$rows = $stageRows ?? Stage::rows();
$counts = $stageCounts ?? [];
$kinds = ['open' => 'Abierta', 'won' => 'Cierre ganado', 'lost' => 'Descartado'];
?>
<details class="kb-stage-admin">
	<summary>Administrar columnas</summary>
	<p>El nombre, el color y el orden se ven en el tablero de todo el equipo.</p>
	<form class="kb-stage-create" method="post" action="<?= h(Http::url('/tablero/columnas')) ?>">
		<?= Csrf::field() ?>
		<label><span>Nueva columna</span><input name="label" required maxlength="80" placeholder="Ej: Visita técnica"></label>
		<label><span>Color</span><input name="color" type="color" value="#1c9bd8"></label>
		<label>
			<span>Tipo</span>
			<select name="kind">
				<?php foreach ($kinds as $key => $name): ?>
					<option value="<?= h($key) ?>"><?= h($name) ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button class="btn btn-word" type="submit">Agregar</button>
	</form>
	<ul class="kb-stage-list">
		<?php foreach ($rows as $i => $row): ?>
			<li>
				<span class="kb-stage-swatch" style="background: <?= h($row['color']) ?>"></span>
				<form method="post" action="<?= h(Http::url('/tablero/columnas/' . $row['slug'])) ?>">
					<?= Csrf::field() ?>
					<input name="label" value="<?= h($row['label']) ?>" required maxlength="80" aria-label="Nombre">
					<input name="color" type="color" value="<?= h($row['color']) ?>" aria-label="Color">
					<select name="kind" aria-label="Tipo">
						<?php foreach ($kinds as $key => $name): ?>
							<option value="<?= h($key) ?>" <?= $row['kind'] === $key ? 'selected' : '' ?>><?= h($name) ?></option>
						<?php endforeach; ?>
					</select>
					<button class="btn btn-word" type="submit">Guardar</button>
				</form>
				<div class="kb-stage-order">
					<?php if ($i > 0): ?>
						<form method="post" action="<?= h(Http::url('/tablero/columnas/' . $row['slug'] . '/mover')) ?>">
							<?= Csrf::field() ?>
							<input type="hidden" name="dir" value="up">
							<button type="submit" aria-label="Subir">↑</button>
						</form>
					<?php endif; ?>
					<?php if ($i < count($rows) - 1): ?>
						<form method="post" action="<?= h(Http::url('/tablero/columnas/' . $row['slug'] . '/mover')) ?>">
							<?= Csrf::field() ?>
							<input type="hidden" name="dir" value="down">
							<button type="submit" aria-label="Bajar">↓</button>
						</form>
					<?php endif; ?>
				</div>
				<form method="post" action="<?= h(Http::url('/tablero/columnas/' . $row['slug'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar esta columna?');">
					<?= Csrf::field() ?>
					<?php $n = (int) ($counts[$row['slug']] ?? 0); ?>
					<?php if ($n > 0): ?>
						<label>
							<span><?= $n ?> tarjeta<?= $n === 1 ? '' : 's' ?> a</span>
							<select name="move_to" required>
								<option value="">Elegir columna</option>
								<?php foreach ($rows as $other): ?>
									<?php if ($other['slug'] === $row['slug']) continue; ?>
									<option value="<?= h($other['slug']) ?>"><?= h($other['label']) ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>
					<button class="btn-danger-text" type="submit">Eliminar</button>
				</form>
			</li>
		<?php endforeach; ?>
	</ul>
</details>
