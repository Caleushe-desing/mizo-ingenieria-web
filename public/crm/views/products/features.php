<?php use MizoCrm\Csrf; use MizoCrm\Http;
$landings = $landings ?? [];
$products = $products ?? [];
$assigned = $assigned ?? [];
?>
<div class="page-head">
	<div>
		<h1>Productos destacados</h1>
		<p>Busca el equipo, márcalo y agrégalo a la landing. No hace falta abrir la ficha de cada producto.</p>
	</div>
	<a class="btn" href="<?= h(Http::url('/catalogo')) ?>">Volver al catálogo</a>
</div>

<?php foreach ($landings as $slug => $label): ?>
	<?php
		$selectedIds = array_fill_keys($assigned[$slug] ?? [], true);
		$selectedProducts = [];
		foreach ($products as $product) {
			if (isset($selectedIds[(int) $product['id']])) {
				$selectedProducts[] = $product;
			}
		}
	?>
	<section class="paper" style="margin-bottom:16px;max-width:960px">
		<h2 class="section-title word"><?= h($label) ?></h2>
		<p class="muted">Se publica al inicio de /instalaciones/<?= h($slug) ?>. Solo si el producto está visible en la web.</p>

		<?php if (!$selectedProducts): ?>
			<p class="muted">Todavía no hay productos en esta landing.</p>
		<?php else: ?>
			<ul style="margin:12px 0;padding:0;list-style:none;display:grid;gap:6px">
				<?php foreach ($selectedProducts as $product): ?>
					<li style="display:flex;justify-content:space-between;gap:12px;align-items:center">
						<span><strong><?= h($product['nombre']) ?></strong> <span class="muted">· <?= h($product['sku']) ?></span></span>
						<form method="post" action="<?= h(Http::url('/catalogo/destacados/quitar')) ?>">
							<?= Csrf::field() ?>
							<input type="hidden" name="landing" value="<?= h($slug) ?>">
							<input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
							<button class="btn-text" type="submit">Quitar</button>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<form method="post" action="<?= h(Http::url('/catalogo/destacados/agregar')) ?>" style="margin-top:12px">
			<?= Csrf::field() ?>
			<input type="hidden" name="landing" value="<?= h($slug) ?>">
			<input type="hidden" name="volver" value="destacados">
			<label>
				<span>Buscar y agregar</span>
				<input type="search" data-feature-filter="<?= h($slug) ?>" placeholder="Escribe SKU o nombre">
			</label>
			<div data-feature-list="<?= h($slug) ?>" hidden style="display:grid;gap:6px;max-height:220px;overflow:auto;margin-top:8px">
				<?php foreach ($products as $product): ?>
					<?php if (isset($selectedIds[(int) $product['id']])) continue; ?>
					<label data-feature-row data-search="<?= h(mb_strtolower($product['sku'] . ' ' . $product['nombre'])) ?>" hidden style="display:flex;gap:8px;align-items:flex-start">
						<input type="checkbox" name="ids[]" value="<?= (int) $product['id'] ?>">
						<span><strong><?= h($product['nombre']) ?></strong> <span class="muted">· <?= h($product['sku']) ?></span></span>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="form-actions">
				<button class="btn btn-word" type="submit">Agregar a esta landing</button>
			</div>
		</form>
	</section>
<?php endforeach; ?>

<script>
(function () {
	document.querySelectorAll('[data-feature-filter]').forEach(function (input) {
		var slug = input.getAttribute('data-feature-filter');
		var list = document.querySelector('[data-feature-list="' + slug + '"]');
		if (!list) return;
		input.addEventListener('input', function () {
			var q = input.value.trim().toLowerCase();
			var any = false;
			list.querySelectorAll('[data-feature-row]').forEach(function (row) {
				var hay = (row.getAttribute('data-search') || '');
				var show = q.length >= 2 && hay.indexOf(q) !== -1;
				row.hidden = !show;
				if (!show) {
					var box = row.querySelector('input');
					if (box) box.checked = false;
				} else {
					any = true;
				}
			});
			list.hidden = !any;
		});
	});
})();
</script>
