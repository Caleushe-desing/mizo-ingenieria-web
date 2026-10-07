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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Archivo+Black&family=Barlow+Condensed:wght@600;700;800&family=Bebas+Neue&family=Montserrat:wght@600;700;800;900&family=Oswald:wght@500;600;700&family=Poppins:wght@600;700;800&family=Rajdhani:wght@600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

<div class="mkt-sheet mkt-free">
	<div class="page-head mkt-free-head">
		<div>
			<p class="file-kicker">Marketing</p>
			<h1>Estudio de diseño profesional</h1>
			<p>Workspace tipo Photoshop: capas, herramientas, propiedades y lienzo WYSIWYG al tamaño exacto de cada red.</p>
		</div>
		<div class="mkt-head-actions">
			<a class="btn-text" href="<?= h(Http::url('/marketing/medios')) ?>">Stock Mizo</a>
			<a class="btn-text" href="#galeria">Galería guardada</a>
		</div>
	</div>

	<?php require __DIR__ . '/_nav.php'; ?>

	<section class="paper mkt-panel mkt-free-studio mkt-ps" id="estudio"
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

		<div class="mkt-ps-menubar">
			<div class="mkt-ps-menu-left">
				<strong class="mkt-ps-brand">Mizo Studio</strong>
				<label class="mkt-ps-format">
					<span>Formato</span>
					<select data-canvas-format aria-label="Formato de red social">
						<option value="feed" selected>Feed 4:5 · 1080×1350</option>
						<option value="post">Post 1:1 · 1080×1080</option>
						<option value="story">Story 9:16 · 1080×1920</option>
						<option value="landscape">Horizontal · 1920×1080</option>
					</select>
				</label>
				<span class="mkt-ps-size" data-canvas-size-label>1080 × 1350 px</span>
			</div>
			<div class="mkt-ps-menu-right">
				<button type="button" class="mkt-ps-btn" data-act="preview">Vista previa</button>
				<button type="button" class="mkt-ps-btn is-primary" data-act="save">Guardar</button>
				<p class="mkt-studio-status" data-studio-status></p>
			</div>
		</div>

		<div class="mkt-ps-workspace">
			<aside class="mkt-ps-toolbox" aria-label="Herramientas">
				<button type="button" class="mkt-ps-tool is-on" data-tool="select" title="Seleccionar (V)">↖</button>
				<button type="button" class="mkt-ps-tool" data-act="add-text" title="Texto (T)">T</button>
				<button type="button" class="mkt-ps-tool" data-shape="rect" title="Rectángulo">▭</button>
				<button type="button" class="mkt-ps-tool" data-shape="circle" title="Círculo">●</button>
				<button type="button" class="mkt-ps-tool" data-shape="triangle" title="Triángulo">▲</button>
				<button type="button" class="mkt-ps-tool" data-shape="line" title="Línea">／</button>
				<button type="button" class="mkt-ps-tool" data-act="add-logo" title="Logo Mizo">◎</button>
				<label class="mkt-ps-tool mkt-file-btn" title="Importar imagen">
					⇧
					<input type="file" accept="image/*" data-act="upload-image" hidden>
				</label>
				<button type="button" class="mkt-ps-tool" data-act="remove-bg" data-remove-bg disabled title="Quitar fondo">✂</button>
				<span class="mkt-ps-tool-sep"></span>
				<button type="button" class="mkt-ps-tool" data-act="delete" title="Eliminar">⌫</button>
			</aside>

			<aside class="mkt-ps-panel mkt-ps-panel-left" data-pro-sidebar>
				<details class="mkt-side-block" open>
					<summary>Plantillas</summary>
					<div class="mkt-stock-filters" data-tpl-filters></div>
					<div class="mkt-bg-tpl-gallery" data-tpl-gallery></div>
				</details>
				<details class="mkt-side-block" open>
					<summary>Formas</summary>
					<label class="mkt-color-row"><span>Color</span><input type="color" value="#f47b20" data-shape-color></label>
					<div class="mkt-shape-grid">
						<button type="button" class="mkt-shape-btn" data-shape="rect" title="Rectángulo">▭</button>
						<button type="button" class="mkt-shape-btn" data-shape="round" title="Redondeado">▢</button>
						<button type="button" class="mkt-shape-btn" data-shape="circle" title="Círculo">●</button>
						<button type="button" class="mkt-shape-btn" data-shape="triangle" title="Triángulo">▲</button>
						<button type="button" class="mkt-shape-btn" data-shape="line" title="Línea">／</button>
						<button type="button" class="mkt-shape-btn" data-shape="bar" title="Barra">▬</button>
					</div>
				</details>
				<details class="mkt-side-block">
					<summary>Stock Mizo</summary>
					<div class="mkt-stock-filters" data-stock-filters></div>
					<div class="mkt-stock-gallery" data-stock-gallery></div>
				</details>
				<details class="mkt-side-block">
					<summary>Guardar</summary>
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
					<label><span>Archivo</span>
						<select data-save-format>
							<option value="png">PNG</option>
							<option value="jpg" selected>JPG</option>
						</select>
					</label>
					<label><span>Nota</span><textarea rows="2" data-save-desc maxlength="500"><?= h($editResource['description'] ?? '') ?></textarea></label>
					<button type="button" class="btn btn-word mkt-save-btn" data-act="save">Guardar diseño</button>
					<button type="button" class="btn-text" data-act="clear">Lienzo nuevo</button>
				</details>
			</aside>

			<div class="mkt-pro-stage mkt-ps-stage">
				<div class="mkt-ps-stage-meta">
					<span>WYSIWYG · exporta a tamaño real</span>
					<span data-zoom-label>100%</span>
				</div>
				<div class="mkt-canvas-shell" data-canvas-shell>
					<canvas id="mizo-free-canvas" width="<?= $canvasWidth ?>" height="<?= $canvasHeight ?>"></canvas>
				</div>
			</div>

			<aside class="mkt-ps-panel mkt-ps-panel-right">
				<div class="mkt-ps-panel-section">
					<h3>Propiedades</h3>
					<div class="mkt-ps-props" data-props-panel>
						<p class="mkt-ps-empty" data-props-empty>Selecciona una capa para editar.</p>
						<div class="mkt-ps-props-body" data-props-body hidden>
							<label><span>Opacidad</span>
								<input type="range" min="0" max="100" value="100" data-opacity>
								<em data-opacity-label>100%</em>
							</label>
							<label class="mkt-color-row"><span>Relleno</span><input type="color" value="#ffffff" data-text-color></label>
							<label><span>Fuente</span>
								<select data-font>
									<option value="Montserrat">Montserrat</option>
									<option value="Bebas Neue">Bebas Neue</option>
									<option value="Oswald">Oswald</option>
									<option value="Anton">Anton</option>
									<option value="Archivo Black">Archivo Black</option>
									<option value="Poppins">Poppins</option>
									<option value="Barlow Condensed">Barlow Condensed</option>
									<option value="Rajdhani">Rajdhani</option>
									<option value="Space Grotesk">Space Grotesk</option>
									<option value="Impact">Impact</option>
									<option value="Arial Black">Arial Black</option>
									<option value="Segoe UI">Segoe UI</option>
								</select>
							</label>
							<label><span>Tamaño texto</span><input type="range" min="18" max="160" value="64" data-font-size></label>
							<div class="mkt-text-actions">
								<button type="button" class="btn-text" data-act="bold">Negrita</button>
								<button type="button" class="btn-text" data-act="align-left">Izq.</button>
								<button type="button" class="btn-text" data-act="align-center">Centro</button>
								<button type="button" class="btn-text" data-act="align-right">Der.</button>
							</div>
							<hr class="mkt-ps-hr">
							<label class="mkt-ps-check"><input type="checkbox" data-stroke-on> <span>Trazo / borde</span></label>
							<label class="mkt-color-row"><span>Color trazo</span><input type="color" value="#ffffff" data-stroke-color></label>
							<label><span>Grosor trazo</span><input type="range" min="0" max="40" value="0" data-stroke-width></label>
							<hr class="mkt-ps-hr">
							<label class="mkt-ps-check"><input type="checkbox" data-shadow-on> <span>Sombra paralela</span></label>
							<label class="mkt-color-row"><span>Color sombra</span><input type="color" value="#000000" data-shadow-color></label>
							<label><span>Desenfoque</span><input type="range" min="0" max="60" value="12" data-shadow-blur></label>
							<label><span>Distancia X</span><input type="range" min="-40" max="40" value="4" data-shadow-x></label>
							<label><span>Distancia Y</span><input type="range" min="-40" max="40" value="6" data-shadow-y></label>
						</div>
						<hr class="mkt-ps-hr">
						<label class="mkt-color-row"><span>Fondo lienzo</span><input type="color" value="#0b1c2c" data-bg-color></label>
						<div class="mkt-palette" data-bg-palette>
							<button type="button" data-bg="#0b1c2c" style="background:#0b1c2c" title="Navy"></button>
							<button type="button" data-bg="#0b6ea8" style="background:#0b6ea8" title="Azul"></button>
							<button type="button" data-bg="#1c9bd8" style="background:#1c9bd8" title="Celeste"></button>
							<button type="button" data-bg="#f47b20" style="background:#f47b20" title="Naranja"></button>
							<button type="button" data-bg="#111418" style="background:#111418" title="Negro"></button>
							<button type="button" data-bg="#ffffff" style="background:#ffffff;border:1px solid #cfd8e3" title="Blanco"></button>
						</div>
					</div>
				</div>
				<div class="mkt-ps-panel-section mkt-ps-layers-wrap">
					<div class="mkt-ps-layers-hd">
						<h3>Capas</h3>
						<div class="mkt-ps-layer-acts">
							<button type="button" class="mkt-ps-mini" data-act="layer-up" title="Subir">▲</button>
							<button type="button" class="mkt-ps-mini" data-act="layer-down" title="Bajar">▼</button>
							<button type="button" class="mkt-ps-mini" data-act="front" title="Al frente">⤴</button>
							<button type="button" class="mkt-ps-mini" data-act="back" title="Al fondo">⤵</button>
						</div>
					</div>
					<ul class="mkt-ps-layers" data-layers-list></ul>
				</div>
			</aside>
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
<script src="<?= h(Http::url('/assets/marketing-free-canvas.js')) ?>?v=6"></script>
