<?php
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;

$client = $client ?? [];
$comments = $comments ?? [];
$quotes = $quotes ?? [];
$team = $team ?? [];
?>
<div class="page-head">
	<div>
		<a class="back" href="<?= h(Http::url('/')) ?>">← Clientes</a>
		<h1><?= h($client['name']) ?></h1>
		<?php if (!empty($client['contact_name'])): ?>
			<p>Contacto: <?= h($client['contact_name']) ?></p>
		<?php else: ?>
			<p>Deja comentarios de lo que hablaste y arma la cotización cuando corresponda.</p>
		<?php endif; ?>
	</div>
	<div class="page-head-actions">
		<form method="post" action="<?= h(Http::url('/clientes/' . $client['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar a <?= h($client['name']) ?>? También se borran sus comentarios y cotizaciones.');">
			<?= Csrf::field() ?>
			<button class="btn-danger-text" type="submit">Eliminar cliente</button>
		</form>
		<a class="btn btn-excel" href="<?= h(Http::url('/clientes/' . $client['id'] . '/cotizacion')) ?>">Nueva cotización</a>
	</div>
</div>

<div class="stack">
	<section class="paper">
		<h2 class="section-title word">Datos del cliente</h2>
		<form class="form" method="post" action="<?= h(Http::url('/clientes/' . $client['id'])) ?>">
			<?= Csrf::field() ?>
			<div class="grid-2">
				<label><span>Cliente</span><input name="name" value="<?= h($client['name']) ?>" required></label>
				<label><span>Contacto</span><input name="contact_name" value="<?= h($client['contact_name'] ?? '') ?>"></label>
				<label><span>Teléfono</span><input name="phone" value="<?= h($client['phone'] ?? '') ?>"></label>
				<label><span>Correo</span><input name="email" type="email" value="<?= h($client['email'] ?? '') ?>"></label>
				<label><span>Ciudad</span><input name="city" value="<?= h($client['city'] ?? '') ?>"></label>
			</div>
			<?php if (Auth::isAdmin()): ?>
				<label>
					<span>Lo lleva</span>
					<select name="owner_id">
						<option value="0">Sin asignar</option>
						<?php foreach ($team as $member): ?>
							<option value="<?= (int) $member['id'] ?>" <?= (int) ($client['owner_id'] ?? 0) === (int) $member['id'] ? 'selected' : '' ?>>
								<?= h($member['name']) ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<div class="form-actions">
				<button class="btn btn-word" type="submit">Guardar datos</button>
			</div>
		</form>
	</section>

	<section class="paper">
		<h2 class="section-title">Comentarios</h2>
		<form class="form" method="post" action="<?= h(Http::url('/clientes/' . $client['id'] . '/comentario')) ?>">
			<?= Csrf::field() ?>
			<label>
				<span>Nuevo comentario</span>
				<textarea name="comment" rows="3" required placeholder="Ej: Visité el local. Hay que cotizar 6 cámaras y un NVR."></textarea>
			</label>
			<div class="form-actions">
				<button class="btn btn-word" type="submit">Agregar comentario</button>
			</div>
		</form>
		<?php if (!$comments): ?>
			<p class="muted" style="margin-top:16px">Aún no hay comentarios en este cliente.</p>
		<?php else: ?>
			<ul class="notes">
				<?php foreach ($comments as $note): ?>
					<li>
						<div class="note-body">
							<p><?= nl2br(h($note['message'])) ?></p>
							<small><?= h($note['user_name'] ?: 'Sistema') ?> · <?= h(when($note['created_at'])) ?></small>
						</div>
						<form method="post" action="<?= h(Http::url('/clientes/' . $client['id'] . '/comentarios/' . $note['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar este comentario?');">
							<?= Csrf::field() ?>
							<button class="btn-danger-text" type="submit">Eliminar</button>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<section class="paper">
		<h2 class="section-title excel">Cotizaciones</h2>
		<?php if (!$quotes): ?>
			<div class="empty">
				<p>Este cliente todavía no tiene cotizaciones.</p>
				<a class="btn btn-excel" href="<?= h(Http::url('/clientes/' . $client['id'] . '/cotizacion')) ?>">Crear cotización</a>
			</div>
		<?php else: ?>
			<div class="table-wrap">
				<table class="sheet">
					<thead>
						<tr>
							<th>Número</th>
							<th>Estado</th>
							<th>Total</th>
							<th>Fecha</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($quotes as $quote): ?>
						<tr>
							<td><a href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>"><?= h($quote['number']) ?></a></td>
							<td><?= h(quote_status_label((string) $quote['status'])) ?></td>
							<td><?= money((int) $quote['total']) ?></td>
							<td><?= h(when($quote['created_at'], 'd-m-Y')) ?></td>
							<td>
								<form method="post" action="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar la cotización <?= h($quote['number']) ?>?');">
									<?= Csrf::field() ?>
									<button class="btn-danger-text" type="submit">Eliminar</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
