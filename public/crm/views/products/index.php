<?php use MizoCrm\Csrf; use MizoCrm\Http; ?>
<div class="page-head">
	<div>
		<h1>Catálogo de productos</h1>
		<p>Equipos de referencia para cotizar. Ordenados por uso en cotizaciones. El precio de compra y el enlace del proveedor alimentan al cotizador.</p>
	</div>
	<div style="display:flex;gap:8px;flex-wrap:wrap">
		<a class="btn" href="<?= h(Http::url('/catalogo/destacados')) ?>">Destacados en landings</a>
		<a class="btn btn-word" href="<?= h(Http::url('/catalogo/nuevo')) ?>">Nuevo producto</a>
	</div>
</div>

<form class="paper form" method="post" action="<?= h(Http::url('/catalogo/importar')) ?>" style="margin-bottom:16px;max-width:720px">
	<h2 class="section-title word">Importar desde URL</h2>
	<?= Csrf::field() ?>
	<label>
		<span>URL de la ficha</span>
		<input type="url" name="url" required maxlength="800" placeholder="https://proveedor.cl/producto">
	</label>
	<p class="muted">Pega la página del producto. Se completan el nombre, la descripción y el proveedor. El SKU lo asignas tú.</p>
	<div class="form-actions">
		<button class="btn btn-word" type="submit">Importar desde URL</button>
	</div>
</form>

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
						<th>Compra c/IVA</th>
						<th>Cotizaciones</th>
						<th>Proveedor</th>
						<th>Enlace</th>
						<th>Estado</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($products as $product): ?>
					<?php
					$host = parse_url((string) $product['proveedor_link'], PHP_URL_HOST) ?: 'Abrir';
					$quoteCount = (int) ($product['quote_count'] ?? 0);
					?>
					<tr>
						<td><?= h($product['sku']) ?></td>
						<td>
							<?= h($product['nombre']) ?>
							<?php if (!empty($product['servicio_profesional'])): ?>
								<div class="muted" style="font-size:12px">Interno CRM · no sale a la web</div>
							<?php endif; ?>
						</td>
						<td><?= h($product['categoria']) ?></td>
						<td><?= (int) ($product['precio_compra_iva'] ?? 0) > 0 ? money((int) $product['precio_compra_iva']) : '—' ?></td>
						<td>
							<?php if ($quoteCount > 0): ?>
								<a href="<?= h(Http::url('/catalogo/' . $product['id'])) ?>#trazabilidad"><?= (int) $quoteCount ?></a>
							<?php else: ?>
								<span class="muted">0</span>
							<?php endif; ?>
						</td>
						<td><?= h($product['proveedor_empresa']) ?></td>
						<td>
							<?php if (trim((string) ($product['proveedor_link'] ?? '')) !== ''): ?>
								<a href="<?= h($product['proveedor_link']) ?>" target="_blank" rel="noopener noreferrer"><?= h($host) ?></a>
							<?php else: ?>
								<span class="muted">—</span>
							<?php endif; ?>
						</td>
						<td><?= !empty($product['servicio_profesional']) ? 'Interno' : ((int) $product['activo'] === 1 ? 'Visible' : 'Oculto') ?></td>
						<td>
							<a href="<?= h(Http::url('/catalogo/' . $product['id'])) ?>">Editar</a>
							<?php if (empty($product['servicio_profesional'])): ?>
								<form method="post" action="<?= h(Http::url('/catalogo/' . $product['id'] . '/visibilidad')) ?>" style="display:inline">
									<?= Csrf::field() ?>
									<button class="btn-text" type="submit"><?= (int) $product['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?></button>
								</form>
								<form method="post" action="<?= h(Http::url('/catalogo/' . $product['id'] . '/eliminar')) ?>" style="display:inline" onsubmit="return confirm('¿Eliminar <?= h($product['sku']) ?> del catálogo?');">
									<?= Csrf::field() ?>
									<button class="btn-danger-text" type="submit">Eliminar</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
