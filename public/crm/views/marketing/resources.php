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
$mktTab = 'recursos';
?>
<div class="mkt-sheet">
	<div class="page-head">
		<div>
			<p class="file-kicker">Marketing</p>
			<h1>Recursos y material comercial</h1>
			<p>Flyers, publicaciones para redes y PDFs oficiales listos para descargar y compartir.</p>
		</div>
		<div class="mkt-head-actions">
			<a class="btn btn-word" href="<?= h(Http::url('/marketing/nuevo')) ?>">Nuevo correo comercial</a>
		</div>
	</div>

	<?php require __DIR__ . '/_nav.php'; ?>

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
		<p class="muted">Material visual en alta resolución para Instagram, LinkedIn o WhatsApp.</p>
		<?php if ($visual === []): ?>
			<p class="muted mkt-empty">Aún no hay flyers en esta categoría.<?= $canManage ? ' Sube el primero con el formulario de abajo.' : '' ?></p>
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
			<p class="muted mkt-empty">Todavía no hay PDFs corporativos cargados.<?= $canManage ? ' Publícalos desde el formulario.' : '' ?></p>
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
			<h2 class="section-title word">Publicar material oficial</h2>
			<p class="muted">Solo administración. JPG/PNG/WEBP/GIF o PDF hasta 25 MB. Las imágenes generan miniatura automática.</p>
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
				<label><span>Título</span><input name="title" required maxlength="160" placeholder="Ej: Flyer audio comercial — stories"></label>
				<label><span>Uso recomendado</span><textarea name="description" rows="3" maxlength="500" placeholder="Cuándo y cómo usarlo (Instagram, LinkedIn, propuesta, etc.)"></textarea></label>
				<label>
					<span>Archivo en alta resolución</span>
					<input type="file" name="archivo" required accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
				</label>
				<div class="form-actions">
					<button class="btn btn-excel" type="submit">Publicar recurso</button>
				</div>
			</form>
		</section>
	<?php endif; ?>
</div>
