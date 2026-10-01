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
			<p>Correos comerciales con plantillas, y material gráfico listo para vender y publicar.</p>
		</div>
		<div class="mkt-head-actions">
			<a class="btn btn-word" href="<?= h(Http::url('/marketing/nuevo')) ?>">Nuevo correo comercial</a>
			<a class="btn btn-excel" href="<?= h(Http::url('/marketing/recursos')) ?>">Ver recursos</a>
		</div>
	</div>

	<?php require __DIR__ . '/_nav.php'; ?>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Plantillas listas</h2>
		<p class="muted">Elige una plantilla para abrir el editor con el texto base. Luego puedes vincular cliente o cotización.</p>
		<div class="mkt-template-grid">
			<?php foreach ($templates as $tpl): ?>
				<a class="mkt-template-card" href="<?= h(Http::url('/marketing/nuevo?plantilla=' . (int) $tpl['id'])) ?>">
					<strong><?= h($tpl['name']) ?></strong>
					<span><?= h($tpl['subject']) ?></span>
				</a>
			<?php endforeach; ?>
			<?php if ($templates === []): ?>
				<p class="muted">Aún no hay plantillas activas. Créalas en Plantillas.</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Centro de recursos</h2>
		<p class="muted">Flyers, piezas para redes y PDFs institucionales en un solo lugar.</p>
		<p style="margin-top:12px">
			<a class="btn btn-excel" href="<?= h(Http::url('/marketing/recursos')) ?>">Abrir Recursos y material comercial</a>
		</p>
	</section>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Variables dinámicas</h2>
		<p class="muted">Úsalas en asunto o cuerpo; se completan al elegir cliente o cotización.</p>
		<ul class="mkt-vars">
			<?php foreach ($variables as $key => $label): ?>
				<li><code>{<?= h($key) ?>}</code> <span><?= h($label) ?></span></li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>
