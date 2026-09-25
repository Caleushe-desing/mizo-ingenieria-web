<?php use MizoCrm\Csrf; use MizoCrm\Http;
$isEdit = !empty($product['id']);
$action = $isEdit ? Http::url('/catalogo/' . $product['id']) : Http::url('/catalogo');
$categories = ['Audio', 'Video', 'Automatización', 'Redes', 'Control', 'Iluminación'];
?>
<div class="page-head">
	<div>
		<h1><?= $isEdit ? 'Editar producto' : 'Nuevo producto' ?></h1>
		<p><?= !empty($imported) ? 'Revisa los datos importados, asigna el SKU y guarda.' : 'Ficha interna del equipo y del distribuidor en Chile. No es una tienda: no hay precio ni carrito.' ?></p>
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
		<div class="product-gallery" data-product-gallery>
			<p class="product-gallery-label"><?= count($imageList) === 1 ? '1 foto' : count($imageList) . ' fotos' ?></p>
			<div class="product-gallery-stage">
				<img data-gallery-main src="<?= h($imageList[0]) ?>" alt="<?= h($product['nombre'] ?? 'Foto del producto') ?>">
			</div>
			<?php if (count($imageList) > 1): ?>
				<div class="product-gallery-thumbs">
					<?php foreach ($imageList as $index => $src): ?>
						<button type="button" class="<?= $index === 0 ? 'is-on' : '' ?>" data-gallery-thumb data-src="<?= h($src) ?>" aria-label="Foto <?= $index + 1 ?>">
							<img src="<?= h($src) ?>" alt="">
						</button>
					<?php endforeach; ?>
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
		<span>Enlace del proveedor</span>
		<input name="proveedor_link" type="url" required maxlength="500" value="<?= h($product['proveedor_link'] ?? '') ?>" placeholder="https://">
	</label>
	<label>
		<span>Visibilidad</span>
		<span><input type="checkbox" name="activo" value="1" <?= (int) ($product['activo'] ?? 1) === 1 ? 'checked' : '' ?>> Visible en el sitio público</span>
	</label>
	<div class="form-actions">
		<button class="btn btn-word" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Agregar al catálogo' ?></button>
		<a class="btn" href="<?= h(Http::url('/catalogo')) ?>">Volver</a>
	</div>
</form>
<script>
(function () {
	var root = document.querySelector('[data-product-gallery]');
	if (!root) return;
	var main = root.querySelector('[data-gallery-main]');
	root.querySelectorAll('[data-gallery-thumb]').forEach(function (button) {
		button.addEventListener('click', function () {
			main.src = button.getAttribute('data-src');
			root.querySelectorAll('[data-gallery-thumb]').forEach(function (item) {
				item.classList.toggle('is-on', item === button);
			});
		});
	});
})();
</script>
