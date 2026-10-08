<?php use MizoCrm\Csrf; use MizoCrm\Http;
$isEdit = !empty($product['id']);
$action = $isEdit ? Http::url('/catalogo/' . $product['id']) : Http::url('/catalogo');
$categories = ['Audio', 'Video', 'Automatización', 'Redes', 'Control', 'Iluminación'];
?>
<div class="page-head">
	<div>
		<h1><?= $isEdit ? 'Editar producto' : 'Nuevo producto' ?></h1>
		<p><?= !empty($imported) ? 'Revisa los datos importados, asigna el SKU, el precio de compra y guarda.' : 'Ficha interna del equipo. El precio de compra con IVA se usa en el cotizador para calcular la venta neta.' ?></p>
	</div>
</div>

<?php if (!empty($error)): ?>
	<div class="flash error"><?= h($error) ?></div>
<?php endif; ?>

<form class="paper form" method="post" action="<?= h($action) ?>" style="max-width:920px">
	<?= Csrf::field() ?>
	<?php
		$imageList = $product['imagenes'] ?? [];
		if (is_string($imageList)) {
			$imageList = json_decode($imageList, true) ?: [];
		}
		if (!is_array($imageList)) {
			$imageList = [];
		}
	?>
	<input type="hidden" name="imagenes" value="<?= h(json_encode(array_values($imageList), JSON_UNESCAPED_SLASHES)) ?>">
	<?php if ($imageList !== []): ?>
		<div class="product-gallery" data-product-gallery data-photos="<?= h(json_encode(array_values($imageList), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">
			<p class="product-gallery-label"><?= count($imageList) === 1 ? '1 foto' : count($imageList) . ' fotos' ?></p>
			<div class="product-gallery-stage">
				<img data-gallery-main src="<?= h($imageList[0]) ?>" alt="<?= h($product['nombre'] ?? 'Foto del producto') ?>">
			</div>
			<?php if (count($imageList) > 1): ?>
				<div class="product-gallery-nav">
					<button type="button" class="btn" data-gallery-prev>Anterior</button>
					<button type="button" class="btn" data-gallery-next>Siguiente</button>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<label>
		<span>SKU</span>
		<input name="sku" required maxlength="80" value="<?= h($product['sku'] ?? '') ?>" autocomplete="off"<?= !empty($imported) ? ' autofocus' : '' ?>>
	</label>
	<label>
		<span>Nombre</span>
		<input name="nombre" required maxlength="180" value="<?= h($product['nombre'] ?? '') ?>">
	</label>
	<label>
		<span>Descripción</span>
		<textarea name="descripcion" required rows="6"><?= h($product['descripcion'] ?? '') ?></textarea>
	</label>
	<label>
		<span>Precio de compra con IVA (referencia proveedor)</span>
		<input name="precio_compra_iva" inputmode="numeric" value="<?= (int) ($product['precio_compra_iva'] ?? 0) > 0 ? h((string) (int) $product['precio_compra_iva']) : '' ?>" placeholder="0">
		<span class="muted" style="display:block;margin-top:4px;font-size:0.9em">Costo de referencia. En el cotizador se combina con el margen % de cada partida para calcular la venta neta al cliente.</span>
	</label>
	<label>
		<span>Categoría</span>
		<input name="categoria" required maxlength="80" list="categorias-producto" value="<?= h($product['categoria'] ?? '') ?>" placeholder="Audio, Video, Automatización">
		<datalist id="categorias-producto">
			<?php foreach ($categories as $category): ?>
				<option value="<?= h($category) ?>"></option>
			<?php endforeach; ?>
		</datalist>
	</label>
	<label>
		<span>Empresa proveedora</span>
		<input name="proveedor_empresa" required maxlength="160" value="<?= h($product['proveedor_empresa'] ?? '') ?>" placeholder="Distribuidor en Chile">
	</label>
	<label>
		<span>Enlace URL del proveedor</span>
		<input name="proveedor_link" type="url" required maxlength="500" value="<?= h($product['proveedor_link'] ?? '') ?>" placeholder="https://">
		<span class="muted" style="display:block;margin-top:4px;font-size:0.9em">Para verificar el precio publicado. También queda disponible desde el cotizador al elegir este producto.</span>
	</label>
	<label>
		<span>Visibilidad</span>
		<span><input type="checkbox" name="activo" value="1" <?= (int) ($product['activo'] ?? 1) === 1 ? 'checked' : '' ?>> Visible en el sitio público</span>
	</label>
	<?php
		$landings = $landings ?? \MizoCrm\Models\Product::landings();
		$landingSlugs = $landingSlugs ?? [];
	?>
	<?php if (empty($product['servicio_profesional'])): ?>
		<fieldset>
			<legend>Productos destacados en landings</legend>
			<p class="muted">Marca en qué páginas de Instalaciones Especializadas aparece este equipo. Tiene que estar visible en la web para publicarse.</p>
			<?php foreach ($landings as $slug => $label): ?>
				<label>
					<span><input type="checkbox" name="landings[]" value="<?= h($slug) ?>" <?= in_array($slug, $landingSlugs, true) ? 'checked' : '' ?>> <?= h($label) ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
	<?php endif; ?>
	<div class="form-actions">
		<button class="btn btn-word" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Agregar al catálogo' ?></button>
		<a class="btn" href="<?= h(Http::url('/catalogo')) ?>">Volver</a>
	</div>
</form>

<?php if ($isEdit): ?>
	<?php
	$quoteUsage = $quoteUsage ?? [];
	$quoteIds = [];
	foreach ($quoteUsage as $usageRow) {
		$quoteIds[(int) $usageRow['id']] = true;
	}
	$distinctQuotes = count($quoteIds);
	?>
	<section class="paper product-usage" id="trazabilidad" style="max-width:920px;margin-top:20px">
		<h2 class="section-title word">Trazabilidad en cotizaciones</h2>
		<p class="muted">
			<?php if ($distinctQuotes === 0): ?>
				Este producto aún no aparece en ninguna cotización.
			<?php else: ?>
				Considerado en <?= (int) $distinctQuotes ?> cotización<?= $distinctQuotes === 1 ? '' : 'es' ?>
				(<?= count($quoteUsage) ?> partida<?= count($quoteUsage) === 1 ? '' : 's' ?>).
			<?php endif; ?>
		</p>
		<?php if ($quoteUsage): ?>
			<div class="table-wrap">
				<table class="sheet">
					<thead>
						<tr>
							<th>Cotización</th>
							<th>Cliente</th>
							<th>Fecha</th>
							<th>Estado</th>
							<th>Cant.</th>
							<th>Costo c/IVA</th>
							<th>Margen %</th>
							<th>Venta neta</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($quoteUsage as $usage): ?>
						<?php
						$when = $usage['sent_at'] ?: ($usage['created_at'] ?? null);
						$margin = (float) ($usage['margin_percent'] ?? 0);
						?>
						<tr>
							<td><?= h($usage['number']) ?></td>
							<td><?= h($usage['client_name'] ?? '') ?></td>
							<td><?= h(when($when, 'd-m-Y')) ?></td>
							<td><?= h(quote_status_label((string) ($usage['status'] ?? ''))) ?></td>
							<td><?= h(rtrim(rtrim(number_format((float) ($usage['quantity'] ?? 0), 2, '.', ''), '0'), '.') ?: '0') ?> <?= h($usage['unit'] ?? 'un') ?></td>
							<td><?= (int) ($usage['cost_price'] ?? 0) > 0 ? money((int) $usage['cost_price']) : '—' ?></td>
							<td><?= $margin > 0 ? h(rtrim(rtrim(number_format($margin, 2, '.', ''), '0'), '.') . '%') : '—' ?></td>
							<td><?= money((int) ($usage['unit_price'] ?? 0)) ?></td>
							<td><a href="<?= h(Http::url('/cotizaciones/' . $usage['id'])) ?>">Abrir</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<script>
(function () {
	var root = document.querySelector('[data-product-gallery]');
	if (!root) return;
	var main = root.querySelector('[data-gallery-main]');
	var photos = [];
	try { photos = JSON.parse(root.getAttribute('data-photos') || '[]'); } catch (error) { photos = []; }
	var index = 0;
	function show(next) {
		if (!photos.length || !main) return;
		index = (next + photos.length) % photos.length;
		main.src = photos[index];
	}
	var prev = root.querySelector('[data-gallery-prev]');
	var next = root.querySelector('[data-gallery-next]');
	if (prev) prev.addEventListener('click', function () { show(index - 1); });
	if (next) next.addEventListener('click', function () { show(index + 1); });
})();
</script>
