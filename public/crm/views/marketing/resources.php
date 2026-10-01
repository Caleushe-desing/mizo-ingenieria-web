<?php
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\MarketingResource;

$visual = $visual ?? [];
$documents = $documents ?? [];
$categories = $categories ?? MarketingResource::categories();
$kinds = $kinds ?? MarketingResource::kinds();
$filterCategory = $filterCategory ?? '';
$canManage = !empty($canManage);
$studioTemplates = $studioTemplates ?? [];
$studioBackgrounds = $studioBackgrounds ?? [];
$studioBrand = $studioBrand ?? [];
$csrf = $csrf ?? Csrf::token();
$saveDesignUrl = $saveDesignUrl ?? Http::url('/marketing/recursos/diseno');
$libraryUploadUrl = $libraryUploadUrl ?? Http::url('/marketing/recursos/biblioteca');
$mktTab = 'recursos';
?>
<div class="mkt-sheet">
	<div class="page-head">
		<div>
			<p class="file-kicker">Marketing</p>
			<h1>Recursos y material comercial</h1>
			<p>Estudio de flyers Mizo, biblioteca visual y PDFs listos para el equipo comercial.</p>
		</div>
		<div class="mkt-head-actions">
			<a class="btn btn-word" href="#estudio">Abrir estudio</a>
			<a class="btn-text" href="<?= h(Http::url('/marketing/nuevo')) ?>">Correo comercial</a>
		</div>
	</div>

	<?php require __DIR__ . '/_nav.php'; ?>

	<section class="paper mkt-panel mkt-studio" id="estudio" data-mkt-studio
		data-templates="<?= h(json_encode($studioTemplates, JSON_UNESCAPED_UNICODE)) ?>"
		data-backgrounds="<?= h(json_encode($studioBackgrounds, JSON_UNESCAPED_UNICODE)) ?>"
		data-brand="<?= h(json_encode($studioBrand, JSON_UNESCAPED_UNICODE)) ?>"
		data-save-url="<?= h($saveDesignUrl) ?>"
		data-csrf="<?= h($csrf) ?>">
		<div class="mkt-studio-head">
			<div>
				<h2 class="section-title word">Estudio de diseño Mizo</h2>
				<p class="muted">Elige una plantilla base, edita título / descripción / CTA y guarda en alta resolución para redes o impresión.</p>
			</div>
			<p class="mkt-studio-status" data-studio-status></p>
		</div>

		<div class="mkt-studio-layout">
			<div class="mkt-studio-sidebar">
				<h3>Plantillas base Mizo</h3>
				<div class="mkt-studio-templates" data-studio-templates></div>

				<h3>Fondos e imágenes corporativas</h3>
				<div class="mkt-studio-backgrounds" data-studio-backgrounds></div>
				<?php if ($canManage): ?>
					<form class="mkt-library-upload" method="post" action="<?= h($libraryUploadUrl) ?>" enctype="multipart/form-data">
						<?= Csrf::field() ?>
						<label>
							<span>Sumar fondo a la biblioteca</span>
							<input type="file" name="archivo" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" required>
						</label>
						<button class="btn btn-excel" type="submit">Subir a biblioteca</button>
					</form>
				<?php endif; ?>

				<h3>Campos de la plantilla</h3>
				<label><span>Título</span><input type="text" data-studio-title maxlength="120"></label>
				<label><span>Breve descripción</span><textarea rows="3" data-studio-description maxlength="280"></textarea></label>
				<label><span>Llamada a la acción</span><input type="text" data-studio-cta maxlength="60"></label>

				<div class="mkt-compose-grid">
					<label>
						<span>Tamaño título</span>
						<input type="range" min="48" max="96" value="72" data-studio-font-size>
					</label>
					<label>
						<span>Alineación</span>
						<select data-studio-align>
							<option value="left">Izquierda</option>
							<option value="center">Centro</option>
							<option value="right">Derecha</option>
						</select>
					</label>
					<label>
						<span>Color texto</span>
						<input type="color" value="#ffffff" data-studio-color>
					</label>
					<label>
						<span>Logo Mizo</span>
						<select data-studio-logo-mode>
							<option value="corner">Esquina (marca)</option>
							<option value="watermark">Marca de agua</option>
							<option value="off">Sin logo</option>
						</select>
					</label>
				</div>

				<h3>Capa de texto libre</h3>
				<div class="mkt-extra-text">
					<input type="text" data-studio-extra-text placeholder="Texto adicional" maxlength="80">
					<button type="button" class="btn btn-word" data-studio-add-text>Añadir capa</button>
				</div>
				<div class="mkt-layers" data-studio-layers></div>
			</div>

			<div class="mkt-studio-stage">
				<div class="mkt-canvas-wrap">
					<canvas data-studio-canvas width="1080" height="1350" aria-label="Vista previa del flyer"></canvas>
				</div>
				<div class="mkt-studio-export paper">
					<h3>Guardar diseño en Recursos</h3>
					<div class="mkt-compose-grid">
						<label>
							<span>Nombre del recurso</span>
							<input type="text" data-studio-resource-title maxlength="160" placeholder="Ej: Flyer domótica — marzo">
						</label>
						<label>
							<span>Categoría</span>
							<select data-studio-category>
								<?php foreach ($categories as $cat): ?>
									<option value="<?= h($cat) ?>"><?= h($cat) ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label>
							<span>Formato final</span>
							<select data-studio-format>
								<option value="png">PNG (transparencia / nitidez)</option>
								<option value="jpg" selected>JPG (redes / WhatsApp)</option>
								<option value="pdf">PDF (impresión / propuesta)</option>
							</select>
						</label>
					</div>
					<label>
						<span>Uso recomendado</span>
						<textarea rows="2" data-studio-resource-desc maxlength="500" placeholder="Stories, LinkedIn, carpeta de visita…"></textarea>
					</label>
					<div class="form-actions">
						<button type="button" class="btn btn-word" data-studio-save>Guardar diseño</button>
					</div>
					<p class="muted">Se guarda en la biblioteca del equipo con miniatura y descarga directa.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="paper mkt-panel">
		<div class="mkt-filter-row">
			<a class="mkt-filter <?= $filterCategory === '' ? 'is-on' : '' ?>" href="<?= h(Http::url('/marketing/recursos')) ?>">Todas</a>
			<?php foreach ($categories as $cat): ?>
				<a class="mkt-filter <?= $filterCategory === $cat ? 'is-on' : '' ?>"
					href="<?= h(Http::url('/marketing/recursos?categoria=' . rawurlencode($cat))) ?>"><?= h($cat) ?></a>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Flyers y publicaciones</h2>
		<p class="muted">Material listo para Instagram, LinkedIn o WhatsApp (incluye diseños del estudio).</p>
		<?php if ($visual === []): ?>
			<p class="muted mkt-empty">Aún no hay flyers guardados. Crea el primero en el estudio de arriba.</p>
		<?php else: ?>
			<div class="mkt-resource-grid">
				<?php foreach ($visual as $row): ?>
					<?php
					$thumb = MarketingResource::thumbUrl($row);
					$dl = Http::url('/marketing/recursos/' . (int) $row['id'] . '/descargar');
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
								<a class="btn btn-word" href="<?= h($dl) ?>">Descargar<?= $size !== '' ? ' · ' . h($size) : '' ?></a>
								<?php if ($canManage): ?>
									<form method="post" action="<?= h(Http::url('/marketing/recursos/' . (int) $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Ocultar este recurso?');">
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

	<section class="paper mkt-panel">
		<h2 class="section-title word">Presentaciones y catálogos PDF</h2>
		<p class="muted">Portafolio, fichas y presentaciones institucionales actualizadas.</p>
		<?php if ($documents === []): ?>
			<p class="muted mkt-empty">Todavía no hay PDFs corporativos cargados.<?= $canManage ? ' Publícalos con el formulario de archivo listo.' : '' ?></p>
		<?php else: ?>
			<div class="mkt-doc-list">
				<?php foreach ($documents as $row): ?>
					<?php
					$dl = Http::url('/marketing/recursos/' . (int) $row['id'] . '/descargar');
					$size = MarketingResource::humanSize($row);
					?>
					<article class="mkt-doc-card">
						<div class="mkt-doc-icon" aria-hidden="true">PDF</div>
						<div class="mkt-doc-body">
							<p class="mkt-resource-cat"><?= h($row['category']) ?></p>
							<strong><?= h($row['title']) ?></strong>
							<?php if (trim((string) ($row['description'] ?? '')) !== ''): ?>
								<p><?= h($row['description']) ?></p>
							<?php endif; ?>
						</div>
						<div class="mkt-doc-actions">
							<a class="btn btn-excel" href="<?= h($dl) ?>">Descargar PDF<?= $size !== '' ? ' · ' . h($size) : '' ?></a>
							<?php if (!empty($row['file_path'])): ?>
								<a class="btn-text" href="<?= h($row['file_path']) ?>" target="_blank" rel="noopener">Ver rápido</a>
							<?php endif; ?>
							<?php if ($canManage): ?>
								<form method="post" action="<?= h(Http::url('/marketing/recursos/' . (int) $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Ocultar este PDF?');">
									<?= Csrf::field() ?>
									<button class="btn-danger-text" type="submit">Ocultar</button>
								</form>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<?php if ($canManage): ?>
		<section class="paper mkt-panel">
			<h2 class="section-title word">Subir archivo listo (PDF o pieza externa)</h2>
			<p class="muted">Para material ya diseñado fuera del estudio. JPG/PNG/WEBP/GIF o PDF hasta 25 MB.</p>
			<form class="form mkt-compose" method="post" action="<?= h(Http::url('/marketing/recursos')) ?>" enctype="multipart/form-data">
				<?= Csrf::field() ?>
				<div class="mkt-compose-grid">
					<label>
						<span>Tipo</span>
						<select name="kind" required>
							<?php foreach ($kinds as $value => $label): ?>
								<option value="<?= h($value) ?>"><?= h($label) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span>Categoría</span>
						<select name="category" required>
							<?php foreach ($categories as $cat): ?>
								<option value="<?= h($cat) ?>"><?= h($cat) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				<label><span>Título</span><input name="title" required maxlength="160" placeholder="Ej: Portafolio institucional 2026"></label>
				<label><span>Uso recomendado</span><textarea name="description" rows="3" maxlength="500" placeholder="Cuándo y cómo usarlo"></textarea></label>
				<label>
					<span>Archivo</span>
					<input type="file" name="archivo" required accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
				</label>
				<div class="form-actions">
					<button class="btn btn-excel" type="submit">Publicar recurso</button>
				</div>
			</form>
		</section>
	<?php endif; ?>
</div>
<script src="<?= h(Http::url('/assets/marketing-studio.js')) ?>?v=1"></script>
