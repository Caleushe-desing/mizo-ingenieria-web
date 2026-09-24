<?php use MizoCrm\Csrf; use MizoCrm\Http; ?>
<div class="page-head">
	<div>
		<h1>Catálogo de productos</h1>
		<p>Equipos de referencia para cotizar. El enlace abre la ficha del distribuidor. Visible u oculto define si el equipo se ofrece en el sitio público.</p>
	</div>
	<a class="btn btn-word" href="<?= h(Http::url('/catalogo/nuevo')) ?>">Nuevo producto</a>
</div>

<form class="paper form" method="get" action="<?= h(Http::url('/catalogo')) ?>" style="margin-bottom:16px;max-width:520px">
	<label>
		<span>Buscar</span>
		<input type="search" name="q" value="<?= h($q ?? '') ?>" placeholder="SKU, nombre, categoría o proveedor">
	</label>
	<div class="form-actions">
		<button class="btn" type="submit">Buscar</button>
	</div>
</form>

<section class="paper">
	<?php if (!$products): ?>
		<p class="muted">Todavía no hay equipos<?= ($q ?? '') !== '' ? ' con esa búsqueda' : ' en el catálogo' ?>.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="sheet">
				<thead>
					<tr>
						<th>SKU</th>
						<th>Nombre</th>
						<th>Categoría</th>
						<th>Proveedor</th>
						<th>Enlace</th>
						<th>Estado</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($products as $product): ?>
					<?php $host = parse_url((string) $product['proveedor_link'], PHP_URL_HOST) ?: 'Abrir'; ?>
					<tr>
						<td><?= h($product['sku']) ?></td>
						<td><?= h($product['nombre']) ?></td>
						<td><?= h($product['categoria']) ?></td>
						<td><?= h($product['proveedor_empresa']) ?></td>
						<td><a href="<?= h($product['proveedor_link']) ?>" target="_blank" rel="noopener noreferrer"><?= h($host) ?></a></td>
						<td><?= (int) $product['activo'] === 1 ? 'Visible' : 'Oculto' ?></td>
						<td>
							<a href="<?= h(Http::url('/catalogo/' . $product['id'])) ?>">Editar</a>
							<form method="post" action="<?= h(Http::url('/catalogo/' . $product['id'] . '/visibilidad')) ?>" style="display:inline">
								<?= Csrf::field() ?>
								<button class="btn-text" type="submit"><?= (int) $product['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?></button>
							</form>
							<form method="post" action="<?= h(Http::url('/catalogo/' . $product['id'] . '/eliminar')) ?>" style="display:inline" onsubmit="return confirm('¿Eliminar <?= h($product['sku']) ?> del catálogo?');">
								<?= Csrf::field() ?>
								<button class="btn-danger-text" type="submit">Eliminar</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
