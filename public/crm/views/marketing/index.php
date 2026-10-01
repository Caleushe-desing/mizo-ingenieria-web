<?php
use MizoCrm\Http;

$templates = $templates ?? [];
$variables = $variables ?? [];
$mktTab = 'correos';
?>
<div class="mkt-sheet">
	<div class="page-head">
		<div>
			<p class="file-kicker">Comunicaciones</p>
			<h1>Marketing</h1>
			<p>Correo comercial, estudio de diseño libre y stock visual Mizo.</p>
		</div>
		<div class="mkt-head-actions">
			<a class="btn btn-word" href="<?= h(Http::url('/marketing/nuevo')) ?>">Nuevo correo comercial</a>
			<a class="btn btn-excel" href="<?= h(Http::url('/marketing/recursos')) ?>">Estudio de diseño</a>
			<a class="btn-text" href="<?= h(Http::url('/marketing/medios')) ?>">Stock Mizo</a>
		</div>
	</div>

	<?php require __DIR__ . '/_nav.php'; ?>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Estudio de diseño libre</h2>
		<p class="muted">Canvas interactivo con textos e imágenes movibles. Guarda piezas para redes o correo comercial.</p>
		<p style="margin-top:12px">
			<a class="btn btn-excel" href="<?= h(Http::url('/marketing/recursos')) ?>">Abrir estudio</a>
		</p>
	</section>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Plantillas de correo</h2>
		<p class="muted">Elige una plantilla para abrir el redactor con variables del CRM.</p>
		<div class="mkt-template-grid">
			<?php foreach ($templates as $tpl): ?>
				<a class="mkt-template-card" href="<?= h(Http::url('/marketing/nuevo?plantilla=' . (int) $tpl['id'])) ?>">
					<strong><?= h($tpl['name']) ?></strong>
					<span><?= h($tpl['subject']) ?></span>
				</a>
			<?php endforeach; ?>
			<?php if ($templates === []): ?>
				<p class="muted">Aún no hay plantillas activas. Créalas en Editar plantillas.</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Variables dinámicas</h2>
		<ul class="mkt-vars">
			<?php foreach ($variables as $key => $label): ?>
				<li><code>{<?= h($key) ?>}</code> <span><?= h($label) ?></span></li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>
