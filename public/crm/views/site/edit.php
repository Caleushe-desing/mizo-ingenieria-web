<?php use MizoCrm\Csrf; use MizoCrm\Http;
$blocks = $blocks ?? [];
$types = [
	'hero' => 'Cabecera',
	'texto' => 'Texto',
	'tarjetas' => 'Tarjetas',
	'imagen' => 'Imagen',
	'productos' => 'Productos destacados',
	'faq' => 'Preguntas',
	'cta' => 'Llamado a la acción',
];
$uploadUrl = Http::url('/sitio/imagen');
$csrf = Csrf::token();
?>
<div class="page-head">
	<div>
		<h1><?= h($label) ?></h1>
		<p>Agrega, ordena y edita secciones. Los productos destacados se muestran en filas de 5.</p>
	</div>
	<a class="btn" href="<?= h($path) ?>" target="_blank" rel="noopener">Ver página</a>
</div>

<?php if ($blocks === [] && empty($active)): ?>
	<p class="paper">Esta página usa el diseño original.
		<a href="<?= h(Http::url('/sitio/editar?path=' . rawurlencode($path) . '&empezar=1')) ?>">Empezar a personalizarla</a>
	</p>
<?php endif; ?>

<form class="paper form" method="post" action="<?= h(Http::url('/sitio/guardar')) ?>" id="site-editor" style="max-width:980px" data-upload="<?= h($uploadUrl) ?>" data-csrf="<?= h($csrf) ?>">
	<?= Csrf::field() ?>
	<input type="hidden" name="path" value="<?= h($path) ?>">
	<label>
		<span>Título SEO</span>
		<input name="title" maxlength="180" value="<?= h($pageTitle ?? '') ?>" placeholder="Título que ve Google">
	</label>
	<label>
		<span>Descripción SEO</span>
		<textarea name="description" rows="2" maxlength="400"><?= h($description ?? '') ?></textarea>
	</label>
	<label>
		<span><input type="checkbox" name="active" value="1" <?= !empty($active) ? 'checked' : '' ?>> Publicar esta versión y reemplazar el diseño original</span>
	</label>

	<div id="site-blocks">
		<?php foreach ($blocks as $index => $block): ?>
			<?php $type = (string) ($block['type'] ?? 'texto'); ?>
			<fieldset class="paper" data-block style="margin:16px 0;padding:16px">
				<legend><?= h($types[$type] ?? $type) ?></legend>
				<input type="hidden" name="blocks[<?= (int) $index ?>][type]" value="<?= h($type) ?>">
				<div style="display:flex;gap:8px;margin-bottom:8px">
					<button type="button" class="btn" data-move="up">Subir</button>
					<button type="button" class="btn" data-move="down">Bajar</button>
					<button type="button" class="btn-danger-text" data-remove>Quitar sección</button>
				</div>
				<?php if (in_array($type, ['hero', 'texto', 'tarjetas', 'faq', 'cta', 'productos'], true)): ?>
					<label><span>Antetítulo</span><input name="blocks[<?= (int) $index ?>][kicker]" value="<?= h($block['kicker'] ?? '') ?>"></label>
					<label><span>Título</span><input name="blocks[<?= (int) $index ?>][title]" value="<?= h($block['title'] ?? '') ?>"></label>
				<?php endif; ?>
				<?php if (in_array($type, ['hero', 'cta', 'productos', 'imagen'], true)): ?>
					<label><span>Texto</span><textarea name="blocks[<?= (int) $index ?>][text]" rows="3"><?= h($block['text'] ?? $block['caption'] ?? '') ?></textarea></label>
				<?php endif; ?>
				<?php if ($type === 'texto'): ?>
					<label><span>Contenido HTML simple</span><textarea name="blocks[<?= (int) $index ?>][html]" rows="6"><?= h($block['html'] ?? '') ?></textarea></label>
				<?php endif; ?>
				<?php if (in_array($type, ['hero', 'imagen'], true)): ?>
					<label>
						<span>Imagen</span>
						<input name="blocks[<?= (int) $index ?>][image]" value="<?= h($block['image'] ?? '') ?>" data-image-url>
						<input type="file" accept="image/*" data-image-file>
					</label>
					<?php if ($type === 'imagen'): ?>
						<label><span>Texto alternativo</span><input name="blocks[<?= (int) $index ?>][alt]" value="<?= h($block['alt'] ?? '') ?>"></label>
					<?php endif; ?>
				<?php endif; ?>
				<?php if (in_array($type, ['hero', 'cta'], true)): ?>
					<label><span>Botón</span><input name="blocks[<?= (int) $index ?>][button_label]" value="<?= h($block['button_label'] ?? '') ?>"></label>
					<label><span>Enlace del botón</span><input name="blocks[<?= (int) $index ?>][button_href]" value="<?= h($block['button_href'] ?? '') ?>"></label>
				<?php endif; ?>
				<?php if ($type === 'productos'): ?>
					<label><span>Landing de productos (slug)</span><input name="blocks[<?= (int) $index ?>][landing]" value="<?= h($block['landing'] ?? '') ?>" placeholder="parlantes-para-iglesias"></label>
				<?php endif; ?>
				<?php if (in_array($type, ['tarjetas', 'faq'], true)): ?>
					<label>
						<span><?= $type === 'faq' ? 'Preguntas en JSON' : 'Tarjetas en JSON' ?></span>
						<textarea name="blocks[<?= (int) $index ?>][items_json]" rows="6"><?= h(json_encode($block['items'] ?? ($type === 'faq' ? [['q' => 'Pregunta', 'a' => 'Respuesta']] : [['title' => 'Título', 'text' => 'Texto']]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></textarea>
					</label>
				<?php endif; ?>
			</fieldset>
		<?php endforeach; ?>
	</div>

	<div class="form-actions">
		<select id="nuevo-tipo">
			<?php foreach ($types as $key => $name): ?>
				<option value="<?= h($key) ?>"><?= h($name) ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="btn" id="agregar-seccion">Agregar sección</button>
		<button class="btn btn-word" type="submit">Guardar y publicar</button>
	</div>
</form>

<form method="post" action="<?= h(Http::url('/sitio/guardar')) ?>" style="margin-top:12px" onsubmit="return confirm('¿Volver al diseño original de esta página?');">
	<?= Csrf::field() ?>
	<input type="hidden" name="path" value="<?= h($path) ?>">
	<button class="btn-text" name="restaurar" value="1" type="submit">Restaurar diseño original</button>
</form>

<template id="tpl-block">
	<fieldset class="paper" data-block style="margin:16px 0;padding:16px">
		<legend>Nueva sección</legend>
		<input type="hidden" data-name="type" value="texto">
		<div style="display:flex;gap:8px;margin-bottom:8px">
			<button type="button" class="btn" data-move="up">Subir</button>
			<button type="button" class="btn" data-move="down">Bajar</button>
			<button type="button" class="btn-danger-text" data-remove>Quitar sección</button>
		</div>
		<div data-fields></div>
	</fieldset>
</template>

<script>
(function () {
	var form = document.getElementById('site-editor');
	var list = document.getElementById('site-blocks');
	if (!form || !list) return;
	var labels = { hero: 'Cabecera', texto: 'Texto', tarjetas: 'Tarjetas', imagen: 'Imagen', productos: 'Productos destacados', faq: 'Preguntas', cta: 'Llamado a la acción' };

	function field(name, label, value, area) {
		var wrap = document.createElement('label');
		var span = document.createElement('span');
		span.textContent = label;
		wrap.appendChild(span);
		var input = document.createElement(area ? 'textarea' : 'input');
		input.setAttribute('data-name', name);
		if (area) input.rows = 4;
		input.value = value || '';
		wrap.appendChild(input);
		return wrap;
	}

	function fill(type, box) {
		box.innerHTML = '';
		if (['hero', 'texto', 'tarjetas', 'faq', 'cta', 'productos'].indexOf(type) !== -1) {
			box.appendChild(field('kicker', 'Antetítulo', ''));
			box.appendChild(field('title', 'Título', ''));
		}
		if (['hero', 'cta', 'productos', 'imagen'].indexOf(type) !== -1) box.appendChild(field('text', 'Texto', '', true));
		if (type === 'texto') box.appendChild(field('html', 'Contenido HTML simple', '<p></p>', true));
		if (type === 'hero' || type === 'imagen') {
			box.appendChild(field('image', 'Imagen', ''));
			var file = document.createElement('input');
			file.type = 'file';
			file.accept = 'image/*';
			file.setAttribute('data-image-file', '');
			box.appendChild(file);
		}
		if (type === 'imagen') box.appendChild(field('alt', 'Texto alternativo', ''));
		if (type === 'hero' || type === 'cta') {
			box.appendChild(field('button_label', 'Botón', 'Cotizar'));
			box.appendChild(field('button_href', 'Enlace del botón', '/contacto'));
		}
		if (type === 'productos') box.appendChild(field('landing', 'Landing de productos (slug)', ''));
		if (type === 'tarjetas') box.appendChild(field('items_json', 'Tarjetas en JSON', '[{"title":"Título","text":"Texto"}]', true));
		if (type === 'faq') box.appendChild(field('items_json', 'Preguntas en JSON', '[{"q":"Pregunta","a":"Respuesta"}]', true));
	}

	function reindex() {
		list.querySelectorAll('[data-block]').forEach(function (block, index) {
			block.querySelectorAll('[data-name]').forEach(function (input) {
				input.name = 'blocks[' + index + '][' + input.getAttribute('data-name') + ']';
			});
		});
	}

	list.addEventListener('click', function (event) {
		var btn = event.target.closest('button');
		if (!btn) return;
		var block = btn.closest('[data-block]');
		if (!block) return;
		if (btn.hasAttribute('data-remove')) block.remove();
		if (btn.getAttribute('data-move') === 'up' && block.previousElementSibling) list.insertBefore(block, block.previousElementSibling);
		if (btn.getAttribute('data-move') === 'down' && block.nextElementSibling) list.insertBefore(block.nextElementSibling, block);
		reindex();
	});

	document.getElementById('agregar-seccion').addEventListener('click', function () {
		var type = document.getElementById('nuevo-tipo').value;
		var node = document.getElementById('tpl-block').content.firstElementChild.cloneNode(true);
		node.querySelector('legend').textContent = labels[type] || type;
		node.querySelector('[data-name="type"]').value = type;
		fill(type, node.querySelector('[data-fields]'));
		list.appendChild(node);
		reindex();
	});

	form.addEventListener('change', function (event) {
		var input = event.target;
		if (!input.hasAttribute('data-image-file') || !input.files || !input.files[0]) return;
		var data = new FormData();
		data.append('_csrf', form.getAttribute('data-csrf') || '');
		data.append('imagen', input.files[0]);
		fetch(form.getAttribute('data-upload'), { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (payload) {
				if (!payload.ok) throw new Error(payload.error || 'No se pudo subir');
				var url = input.parentElement.querySelector('[data-image-url]') || input.parentElement.querySelector('[data-name="image"]');
				if (url) url.value = payload.url;
			})
			.catch(function (error) { alert(error.message); });
	});

	list.querySelectorAll('[data-name]').forEach(function (input) {
		if (!input.getAttribute('data-name')) {
			var match = (input.name || '').match(/\[([a-z_]+)\]$/);
			if (match) input.setAttribute('data-name', match[1]);
		}
	});
	reindex();
})();
</script>
