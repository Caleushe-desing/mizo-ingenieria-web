<?php use MizoCrm\Http; ?>
<div class="page-head">
	<div>
		<h1>Editor del sitio</h1>
		<p>Cambia textos, imágenes y secciones de cada página pública. Al publicar, esa página reemplaza el diseño actual. Si no publicas, el sitio sigue igual.</p>
	</div>
</div>
<section class="paper">
	<div class="table-wrap">
		<table class="sheet">
			<thead>
				<tr>
					<th>Página</th>
					<th>Ruta</th>
					<th>Estado</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($pages as $page): ?>
				<tr>
					<td><?= h($page['label']) ?></td>
					<td><a href="<?= h($page['path']) ?>" target="_blank" rel="noopener"><?= h($page['path']) ?></a></td>
					<td><?= $page['active'] ? 'Personalizada' : 'Diseño original' ?></td>
					<td><a href="<?= h(Http::url('/sitio/editar?path=' . rawurlencode($page['path']))) ?>">Editar</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
