<?php use MizoCrm\Auth; use MizoCrm\Csrf; use MizoCrm\Http; ?>
<div class="kb clients-dir">
	<div class="kb-bar">
		<div>
			<h1>Clientes</h1>
			<p>Listado de empresas. El clic en la tarjeta abre la ficha del cliente.</p>
		</div>
		<form class="kb-filters" method="get" action="<?= h(Http::url('/clientes')) ?>">
			<input type="search" name="q" value="<?= h($q ?? '') ?>" placeholder="Buscar cliente, RUT o contacto" aria-label="Buscar cliente">
			<button class="kb-go" type="submit">Buscar</button>
			<a class="kb-enroll" href="<?= h(Http::url('/clientes/nuevo')) ?>">Inscribir cliente</a>
		</form>
	</div>

	<?php if (!$clients): ?>
		<div class="clients-empty">
			<p>Todavía no hay clientes<?= ($q ?? '') !== '' ? ' con esa búsqueda' : '' ?>.</p>
			<a class="kb-enroll" href="<?= h(Http::url('/clientes/nuevo')) ?>">Inscribir el primero</a>
		</div>
	<?php else: ?>
		<div class="clients-grid">
			<?php foreach ($clients as $row): ?>
				<article class="kb-card clients-card">
					<a class="clients-card-open" href="<?= h(Http::url('/tablero/cliente/' . $row['id'] . '/ficha')) ?>">
					<div class="kb-card-top">
						<strong><?= h($row['name']) ?></strong>
						<?php if (!empty($row['rut'])): ?>
							<em class="kb-pri kb-pri-media"><?= h($row['rut']) ?></em>
						<?php endif; ?>
					</div>
					<span class="kb-tag kb-tag-video"><?= h($row['contact_name'] ?: 'Sin contacto') ?></span>
					<div class="kb-meta">
						<span><?= h($row['phone'] ?: 'Sin teléfono') ?></span>
						<span><?= (int) $row['quotes_count'] ?> cotiz.</span>
					</div>
					<p class="kb-note"><?= h($row['email'] ?: 'Sin correo') ?></p>
					<?php if (Auth::isAdmin()): ?>
						<p class="kb-note"><?= h($row['owner_name'] ?: 'Sin ejecutivo') ?></p>
					<?php endif; ?>
					</a>
					<div class="clients-card-actions">
						<form method="post" action="<?= h(Http::url('/clientes/' . $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar a <?= h($row['name']) ?>? También se borran sus comentarios y cotizaciones.');">
							<?= Csrf::field() ?>
							<button type="submit">Eliminar</button>
						</form>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
