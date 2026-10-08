<?php use MizoCrm\Csrf; use MizoCrm\Http;
$landings = $landings ?? [];
$products = $products ?? [];
$assigned = $assigned ?? [];
?>
<div class="page-head">
	<div>
		<h1>Productos destacados</h1>
		<p>Elige qué equipos del catálogo aparecen en cada landing de Instalaciones Especializadas. Solo se publican los que están visibles en la web.</p>
	</div>
	<a class="btn" href="<?= h(Http::url('/catalogo')) ?>">Volver al catálogo</a>
</div>

<form class="paper form" method="post" action="<?= h(Http::url('/catalogo/destacados')) ?>" style="max-width:960px">
	<?= Csrf::field() ?>
	<?php foreach ($landings as $slug => $label): ?>
		<?php $selected = array_fill_keys($assigned[$slug] ?? [], true); ?>
		<section style="margin:0 0 28px;padding-bottom:20px;border-bottom:1px solid #e4e9ef">
			<h2 class="section-title word"><?= h($label) ?></h2>
			<p class="muted">Página pública: /instalaciones/<?= h($slug) ?></p>
			<label>
				<span>Buscar en el catálogo</span>
				<input type="search" data-feature-filter="<?= h($slug) ?>" placeholder="SKU o nombre">
			</label>
			<div data-feature-list="<?= h($slug) ?>" style="display:grid;gap:6px;max-height:280px;overflow:auto;margin-top:8px">
				<?php if (!$products): ?>
					<p class="muted">No hay productos en el catálogo.</p>
				<?php endif; ?>
				<?php foreach ($products as $product): ?>
					<?php $on = isset($selected[(int) $product['id']]); ?>
					<label data-feature-row data-selected="<?= $on ? '1' : '0' ?>" data-search="<?= h(strtolower($product['sku'] . ' ' . $product['nombre'])) ?>" style="display:flex;gap:8px;align-items:flex-start">
						<input type="checkbox" name="landings[<?= h($slug) ?>][]" value="<?= (int) $product['id'] ?>" <?= $on ? 'checked' : '' ?>>
						<span>
							<strong><?= h($product['nombre']) ?></strong>
							<span class="muted"> · <?= h($product['sku']) ?><?= (int) ($product['activo'] ?? 0) === 1 ? '' : ' · oculto en la web' ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
	<div class="form-actions">
		<button class="btn btn-word" type="submit">Guardar destacados</button>
	</div>
</form>

<script>
(function () {
	document.querySelectorAll('[data-feature-filter]').forEach(function (input) {
		var slug = input.getAttribute('data-feature-filter');
		var list = document.querySelector('[data-feature-list="' + slug + '"]');
		if (!list) return;
		input.addEventListener('input', function () {
			var q = input.value.trim().toLowerCase();
			list.querySelectorAll('[data-feature-row]').forEach(function (row) {
				var hay = row.getAttribute('data-search') || '';
				var checked = row.querySelector('input') && row.querySelector('input').checked;
				row.hidden = q !== '' && !checked && hay.indexOf(q) === -1;
			});
		});
	});
})();
</script>
