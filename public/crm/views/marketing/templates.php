<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$templates = $templates ?? [];
$variables = $variables ?? [];
?>
<div class="mkt-sheet">
	<div class="page-head">
		<div>
			<p class="file-kicker">Marketing</p>
			<h1>Plantillas de correo</h1>
			<p>Edita las plantillas del equipo. Las variables se reemplazan al enviar.</p>
		</div>
		<div class="mkt-head-actions">
			<a class="btn btn-word" href="<?= h(Http::url('/marketing/nuevo')) ?>">Nuevo correo</a>
			<a class="btn-text" href="<?= h(Http::url('/marketing')) ?>">Volver</a>
		</div>
	</div>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Variables</h2>
		<ul class="mkt-vars">
			<?php foreach ($variables as $key => $label): ?>
				<li><code>{<?= h($key) ?>}</code> <span><?= h($label) ?></span></li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Nueva plantilla</h2>
		<form class="form mkt-compose" method="post" action="<?= h(Http::url('/marketing/plantillas')) ?>">
			<?= Csrf::field() ?>
			<label><span>Nombre</span><input name="name" required placeholder="Ej: Recordatorio de visita"></label>
			<label><span>Asunto</span><input name="subject" required placeholder="Asunto con {nombre_empresa}"></label>
			<label><span>Cuerpo</span><textarea name="body" rows="8" required placeholder="Hola {nombre_cliente}, ..."></textarea></label>
			<div class="form-actions">
				<button class="btn btn-excel" type="submit">Guardar plantilla</button>
			</div>
		</form>
	</section>

	<?php foreach ($templates as $tpl): ?>
		<section class="paper mkt-panel">
			<form class="form mkt-compose" method="post" action="<?= h(Http::url('/marketing/plantillas/' . (int) $tpl['id'])) ?>">
				<?= Csrf::field() ?>
				<div class="mkt-compose-grid">
					<label><span>Nombre</span><input name="name" value="<?= h($tpl['name']) ?>" required></label>
					<label class="mkt-active">
						<span>Activa</span>
						<label class="check-pill">
							<input type="checkbox" name="active" value="1" <?= !empty($tpl['active']) ? 'checked' : '' ?>>
							<span>Visible en el editor</span>
						</label>
					</label>
				</div>
				<label><span>Asunto</span><input name="subject" value="<?= h($tpl['subject']) ?>" required></label>
				<label><span>Cuerpo</span><textarea name="body" rows="8" required><?= h($tpl['body']) ?></textarea></label>
				<div class="form-actions">
					<button class="btn btn-excel" type="submit">Guardar cambios</button>
				</div>
			</form>
			<form method="post" action="<?= h(Http::url('/marketing/plantillas/' . (int) $tpl['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Ocultar esta plantilla?');">
				<?= Csrf::field() ?>
				<button class="btn-danger-text" type="submit">Ocultar plantilla</button>
			</form>
		</section>
	<?php endforeach; ?>
</div>
