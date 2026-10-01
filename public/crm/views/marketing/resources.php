<?php
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\MarketingResource;

$visual = $visual ?? [];
$categories = $categories ?? MarketingResource::categories();
$filterCategory = $filterCategory ?? '';
$studioStock = $studioStock ?? [];
$studioStockCategories = $studioStockCategories ?? [];
$csrf = $csrf ?? Csrf::token();
$saveDesignUrl = $saveDesignUrl ?? Http::url('/marketing/recursos/diseno');
$editResource = $editResource ?? null;
$canvasWidth = (int) ($canvasWidth ?? 1080);
$canvasHeight = (int) ($canvasHeight ?? 1350);
$mktTab = 'recursos';
$editJson = $editResource ? (string) ($editResource['design_json'] ?? '') : '';
?>
<div class="mkt-sheet mkt-free">
	<div class="page-head">
		<div>
			<p class="file-kicker">Marketing</p>
			<h1>Estudio de diseño libre</h1>
			<p>Canvas interactivo: mueve textos e imágenes con total libertad y guarda el resultado en Recursos.</p>
		</div>
		<div class="mkt-head-actions">
			<a class="btn-text" href="<?= h(Http::url('/marketing/medios')) ?>">Stock Mizo</a>
			<a class="btn-text" href="#galeria">Galería guardada</a>
		</div>
	</div>

	<?php require __DIR__ . '/_nav.php'; ?>

	<section class="paper mkt-panel mkt-free-studio" id="estudio"
		data-free-studio
		data-save-url="<?= h($saveDesignUrl) ?>"
		data-csrf="<?= h($csrf) ?>"
		data-width="<?= $canvasWidth ?>"
		data-height="<?= $canvasHeight ?>"
		data-stock="<?= h(json_encode($studioStock, JSON_UNESCAPED_UNICODE)) ?>"
		data-stock-categories="<?= h(json_encode($studioStockCategories, JSON_UNESCAPED_UNICODE)) ?>"
		data-resource-id="<?= $editResource ? (int) $editResource['id'] : 0 ?>"
		data-design-json="<?= h($editJson) ?>"
		data-logo="/mizo-logo-footer.png">
		<div class="mkt-free-toolbar">
			<div class="mkt-free-tools">
				<button type="button" class="btn btn-word" data-act="add-text">Texto</button>
				<button type="button" class="btn btn-word" data-act="add-logo">Logo Mizo</button>
				<label class="btn btn-excel mkt-file-btn">
					Importar imagen
					<input type="file" accept="image/*" data-act="upload-image" hidden>
				</label>
				<button type="button" class="btn btn-word" data-act="preview">Vista previa real</button>
				<button type="button" class="btn-text" data-act="delete">Eliminar</button>
				<button type="button" class="btn-text" data-act="front">Traer al frente</button>
				<button type="button" class="btn-text" data-act="back">Enviar al fondo</button>
			</div>
			<p class="mkt-studio-status" data-studio-status></p>
		</div>

		<div class="mkt-free-layout">
			<aside class="mkt-free-sidebar">
				<h3>Plantillas y fondos profesionales</h3>
				<p class="muted mkt-stock-hint">Un clic aplica el fondo al lienzo. El texto e imágenes se mantienen.</p>
				<div class="mkt-stock-filters" data-bg-tpl-filters></div>
				<div class="mkt-bg-tpl-gallery" data-bg-tpl-gallery></div>

				<h3>Color sólido</h3>
				<div class="mkt-palette" data-bg-palette>
					<button type="button" data-bg="#0b1c2c" style="background:#0b1c2c" title="Navy"></button>
					<button type="button" data-bg="#0b6ea8" style="background:#0b6ea8" title="Azul Mizo"></button>
					<button type="button" data-bg="#1c9bd8" style="background:#1c9bd8" title="Celeste"></button>
					<button type="button" data-bg="#f47b20" style="background:#f47b20" title="Naranja"></button>
					<button type="button" data-bg="#1f2328" style="background:#1f2328" title="Carbón"></button>
					<button type="button" data-bg="#ffffff" style="background:#ffffff;border:1px solid #cfd8e3" title="Blanco"></button>
					<button type="button" data-bg="#e8f6fc" style="background:#e8f6fc" title="Hielo"></button>
				</div>
				<label class="mkt-color-row"><span>Color libre</span><input type="color" value="#0b1c2c" data-bg-color></label>

				<h3>Texto seleccionado</h3>
				<label><span>Fuente</span>
					<select data-font>
						<option value="Segoe UI">Segoe UI</option>
						<option value="Arial">Arial</option>
						<option value="Georgia">Georgia</option>
						<option value="Trebuchet MS">Trebuchet MS</option>
						<option value="Verdana">Verdana</option>
						<option value="Impact">Impact</option>
					</select>
				</label>
				<label><span>Tamaño</span><input type="range" min="16" max="140" value="48" data-font-size></label>
				<label class="mkt-color-row"><span>Color</span><input type="color" value="#ffffff" data-text-color></label>
				<div class="mkt-text-actions">
					<button type="button" class="btn-text" data-act="bold">Negrita</button>
					<button type="button" class="btn-text" data-act="align-left">Izq.</button>
					<button type="button" class="btn-text" data-act="align-center">Centro</button>
					<button type="button" class="btn-text" data-act="align-right">Der.</button>
				</div>

				<h3>Stock Mizo</h3>
				<p class="muted mkt-stock-hint">Clic para insertar la foto en el lienzo (puedes moverla y redimensionarla).</p>
				<div class="mkt-stock-filters" data-stock-filters></div>
				<div class="mkt-stock-gallery" data-stock-gallery></div>

				<h3>Guardar en CRM</h3>
				<label><span>Título</span><input type="text" data-save-title maxlength="160" value="<?= h($editResource['title'] ?? 'Diseño Mizo') ?>"></label>
				<label><span>Categoría</span>
					<select data-save-category>
						<?php foreach ($categories as $cat): ?>
							<option value="<?= h($cat) ?>" <?= ($editResource['category'] ?? 'Redes Sociales') === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><span>Uso</span>
					<select data-save-kind>
						<option value="flyer" <?= ($editResource['kind'] ?? '') === 'flyer' ? 'selected' : '' ?>>Flyer / publicación</option>
						<option value="social" <?= ($editResource['kind'] ?? '') === 'social' ? 'selected' : '' ?>>Redes sociales</option>
					</select>
				</label>
				<label><span>Formato</span>
					<select data-save-format>
						<option value="png">PNG</option>
						<option value="jpg" selected>JPG</option>
					</select>
				</label>
				<label><span>Nota de uso</span><textarea rows="2" data-save-desc maxlength="500"><?= h($editResource['description'] ?? '') ?></textarea></label>
				<button type="button" class="btn btn-word" data-act="save">Guardar diseño</button>
				<button type="button" class="btn-text" data-act="clear">Lienzo nuevo</button>
			</aside>

			<div class="mkt-free-stage">
				<div class="mkt-canvas-shell">
					<canvas id="mizo-free-canvas" width="<?= $canvasWidth ?>" height="<?= $canvasHeight ?>"></canvas>
				</div>
			</div>
		</div>

		<div class="mkt-preview-modal" data-preview-modal hidden>
			<div class="mkt-preview-backdrop" data-preview-close></div>
			<div class="mkt-preview-dialog" role="dialog" aria-modal="true" aria-label="Vista previa real">
				<div class="mkt-preview-head">
					<strong>Simulador móvil</strong>
					<div class="mkt-preview-formats" data-preview-formats>
						<button type="button" class="mkt-filter is-on" data-preview-format="story">Story / WhatsApp</button>
						<button type="button" class="mkt-filter" data-preview-format="post">Post cuadrado</button>
						<button type="button" class="mkt-filter" data-preview-format="feed">Feed vertical</button>
					</div>
					<button type="button" class="btn-text" data-preview-close>Cerrar</button>
				</div>
				<div class="mkt-phone">
					<div class="mkt-phone-notch" aria-hidden="true"></div>
					<div class="mkt-phone-screen" data-preview-screen>
						<img alt="Vista previa del diseño" data-preview-image>
					</div>
					<p class="mkt-phone-caption" data-preview-caption>Instagram Story · 9:16</p>
				</div>
			</div>
		</div>
	</section>

	<section class="paper mkt-panel" id="galeria">
		<div class="mkt-filter-row">
			<a class="mkt-filter <?= $filterCategory === '' ? 'is-on' : '' ?>" href="<?= h(Http::url('/marketing/recursos')) ?>">Todas</a>
			<?php foreach ($categories as $cat): ?>
				<a class="mkt-filter <?= $filterCategory === $cat ? 'is-on' : '' ?>"
					href="<?= h(Http::url('/marketing/recursos?categoria=' . rawurlencode($cat))) ?>"><?= h($cat) ?></a>
			<?php endforeach; ?>
		</div>
		<h2 class="section-title word">Diseños guardados</h2>
		<p class="muted">Descarga para redes, reabre en el estudio o envía por correo comercial.</p>
		<?php if ($visual === []): ?>
			<p class="muted mkt-empty">Aún no hay diseños. Crea el primero en el estudio.</p>
		<?php else: ?>
			<div class="mkt-resource-grid">
				<?php foreach ($visual as $row): ?>
					<?php
					$thumb = MarketingResource::thumbUrl($row);
					$id = (int) $row['id'];
					$dl = Http::url('/marketing/recursos/' . $id . '/descargar');
					$mail = Http::url('/marketing/nuevo?recurso=' . $id);
					$edit = Http::url('/marketing/recursos?editar=' . $id . '#estudio');
					$size = MarketingResource::humanSize($row);
					?>
					<article class="mkt-resource-card">
						<div class="mkt-resource-preview<?= $thumb ? '' : ' is-empty' ?>">
							<?php if ($thumb): ?>
								<img src="<?= h($thumb) ?>" alt="<?= h($row['title']) ?>" loading="lazy">
							<?php else: ?>
								<span>IMG</span>
							<?php endif; ?>
						</div>
						<div class="mkt-resource-body">
							<p class="mkt-resource-cat"><?= h($row['category']) ?></p>
							<strong><?= h($row['title']) ?></strong>
							<?php if (trim((string) ($row['description'] ?? '')) !== ''): ?>
								<p><?= h($row['description']) ?></p>
							<?php endif; ?>
							<div class="mkt-resource-actions">
								<a class="btn btn-word" href="<?= h($dl) ?>">Redes / descargar<?= $size !== '' ? ' · ' . h($size) : '' ?></a>
								<a class="btn btn-excel" href="<?= h($mail) ?>">Correo comercial</a>
								<a class="btn-text" href="<?= h($edit) ?>">Editar</a>
								<?php if (!empty($canManage)): ?>
									<form method="post" action="<?= h(Http::url('/marketing/recursos/' . $id . '/eliminar')) ?>" onsubmit="return confirm('¿Ocultar este diseño?');">
										<?= Csrf::field() ?>
										<button class="btn-danger-text" type="submit">Ocultar</button>
									</form>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</div>
<script src="<?= h(Http::url('/assets/fabric.min.js')) ?>?v=531"></script>
<script src="<?= h(Http::url('/assets/marketing-free-canvas.js')) ?>?v=3"></script>
