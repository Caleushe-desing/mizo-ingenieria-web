<?php
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\MarketingMedia;

$items = $items ?? [];
$categories = $categories ?? MarketingMedia::categories();
$filterCategory = $filterCategory ?? '';
$canManage = !empty($canManage);
$mktTab = 'medios';
?>
<div class="mkt-sheet">
	<div class="page-head">
		<div>
			<p class="file-kicker">Marketing</p>
			<h1>Biblioteca de medios / Stock Mizo</h1>
			<p>Fotografías reales de productos y proyectos para usar en el estudio de flyers.</p>
		</div>
		<div class="mkt-head-actions">
			<a class="btn btn-word" href="<?= h(Http::url('/marketing/recursos#estudio')) ?>">Ir al estudio</a>
		</div>
	</div>

	<?php require __DIR__ . '/_nav.php'; ?>

	<section class="paper mkt-panel">
		<div class="mkt-filter-row">
			<a class="mkt-filter <?= $filterCategory === '' ? 'is-on' : '' ?>" href="<?= h(Http::url('/marketing/medios')) ?>">Todas</a>
			<?php foreach ($categories as $cat): ?>
				<a class="mkt-filter <?= $filterCategory === $cat ? 'is-on' : '' ?>"
					href="<?= h(Http::url('/marketing/medios?categoria=' . rawurlencode($cat))) ?>"><?= h($cat) ?></a>
			<?php endforeach; ?>
		</div>
	</section>

	<?php if ($canManage): ?>
		<section class="paper mkt-panel">
			<h2 class="section-title word">Subir fotografía al stock</h2>
			<p class="muted">JPG/PNG/WEBP/GIF hasta 20 MB. Quedará disponible en el estudio bajo “Imágenes de servicios / productos Mizo”.</p>
			<form class="form mkt-compose" method="post" action="<?= h(Http::url('/marketing/medios')) ?>" enctype="multipart/form-data">
				<?= Csrf::field() ?>
				<div class="mkt-compose-grid">
					<label>
						<span>Categoría</span>
						<select name="category" required>
							<?php foreach ($categories as $cat): ?>
								<option value="<?= h($cat) ?>"><?= h($cat) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span>Título</span>
						<input name="title" required maxlength="160" placeholder="Ej: Sala AV corporativa — Las Condes">
					</label>
				</div>
				<label>
					<span>Descripción / uso</span>
					<textarea name="description" rows="2" maxlength="400" placeholder="Proyecto, producto o contexto de la foto"></textarea>
				</label>
				<label>
					<span>Fotografía</span>
					<input type="file" name="archivo" required accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
				</label>
				<div class="form-actions">
					<button class="btn btn-excel" type="submit">Publicar en Stock Mizo</button>
				</div>
			</form>
		</section>
	<?php else: ?>
		<section class="paper mkt-panel">
			<p class="muted">Puedes usar estas fotos en el estudio. La carga de nuevas imágenes la hace administración.</p>
		</section>
	<?php endif; ?>

	<section class="paper mkt-panel">
		<h2 class="section-title word">Stock disponible</h2>
		<?php if ($items === []): ?>
			<p class="muted mkt-empty">Aún no hay fotografías en esta categoría.<?= $canManage ? ' Sube la primera con el formulario.' : '' ?></p>
		<?php else: ?>
			<div class="mkt-stock-admin-grid">
				<?php foreach ($items as $row): ?>
					<?php
					$thumb = trim((string) ($row['thumb_path'] ?? '')) ?: trim((string) ($row['file_path'] ?? ''));
					?>
					<article class="mkt-stock-admin-card">
						<div class="mkt-stock-admin-preview">
							<?php if ($thumb !== ''): ?>
								<img src="<?= h($thumb) ?>" alt="<?= h($row['title']) ?>" loading="lazy">
							<?php endif; ?>
						</div>
						<div class="mkt-stock-admin-body">
							<p class="mkt-resource-cat"><?= h($row['category']) ?></p>
							<strong><?= h($row['title']) ?></strong>
							<?php if (trim((string) ($row['description'] ?? '')) !== ''): ?>
								<p><?= h($row['description']) ?></p>
							<?php endif; ?>
							<div class="mkt-resource-actions">
								<a class="btn-text" href="<?= h(Http::url('/marketing/recursos#estudio')) ?>">Usar en estudio</a>
								<?php if ($canManage): ?>
									<form method="post" action="<?= h(Http::url('/marketing/medios/' . (int) $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Ocultar esta foto del stock?');">
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
