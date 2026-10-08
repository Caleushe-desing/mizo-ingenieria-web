<?php use MizoCrm\Http; ?>
<div class="page-head">
	<div>
		<h1>Editor del sitio</h1>
		<p>Abre la página real y edita encima: haz clic en un texto o en una imagen, agrega secciones y guarda. El menú y el pie, si los cambias, se aplican a todo el sitio.</p>
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
					<td><a href="<?= h($page['path'] . '?editar=1') ?>">Editar en la página</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
