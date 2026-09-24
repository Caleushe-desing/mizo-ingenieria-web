<?php use MizoCrm\Csrf; use MizoCrm\Http;
$isEdit = !empty($product['id']);
$action = $isEdit ? Http::url('/catalogo/' . $product['id']) : Http::url('/catalogo');
$categories = ['Audio', 'Video', 'Automatización', 'Redes', 'Control', 'Iluminación'];
?>
<div class="page-head">
	<div>
		<h1><?= $isEdit ? 'Editar producto' : 'Nuevo producto' ?></h1>
		<p>Ficha interna del equipo y del distribuidor en Chile. No es una tienda: no hay precio ni carrito.</p>
	</div>
</div>

<?php if (!empty($error)): ?>
	<div class="flash error"><?= h($error) ?></div>
<?php endif; ?>

<form class="paper form" method="post" action="<?= h($action) ?>" style="max-width:720px">
	<?= Csrf::field() ?>
	<label>
		<span>SKU</span>
		<input name="sku" required maxlength="80" value="<?= h($product['sku'] ?? '') ?>" autocomplete="off">
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
