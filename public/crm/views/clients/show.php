<?php
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;

$client = $client ?? [];
$contacts = $contacts ?? [];
$comments = $comments ?? [];
$quotes = $quotes ?? [];
$team = $team ?? [];
$mails = $mails ?? [];
if ($contacts === []) {
	$contacts = [[
		'id' => '',
		'name' => $client['contact_name'] ?? '',
		'email' => $client['email'] ?? '',
		'phone' => $client['phone'] ?? '',
		'title' => '',
	]];
}
?>
<div class="page-head">
	<div>
		<a class="back" href="<?= h(Http::url('/')) ?>">← Clientes</a>
		<h1><?= h($client['name']) ?></h1>
		<p>
			<?php if (!empty($client['rut'])): ?>RUT <?= h($client['rut']) ?> · <?php endif; ?>
			<?= count($contacts) ?> contacto<?= count($contacts) === 1 ? '' : 's' ?>
		</p>
	</div>
	<div class="page-head-actions">
		<form method="post" action="<?= h(Http::url('/clientes/' . $client['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar a <?= h($client['name']) ?>? También se borran sus comentarios y cotizaciones.');">
			<?= Csrf::field() ?>
			<button class="btn-danger-text" type="submit">Eliminar cliente</button>
		</form>
		<a class="btn btn-word" href="<?= h(Http::url('/correo/nuevo?cliente=' . $client['id'])) ?>">Escribir correo</a>
		<a class="btn btn-excel" href="<?= h(Http::url('/clientes/' . $client['id'] . '/cotizacion')) ?>">Nueva cotización</a>
	</div>
</div>

<div class="stack">
	<section class="paper">
		<h2 class="section-title word">Datos del cliente</h2>
		<form class="form" method="post" action="<?= h(Http::url('/clientes/' . $client['id'])) ?>" data-contacts-form>
			<?= Csrf::field() ?>
			<div class="grid-2">
				<label><span>Cliente / Empresa</span><input name="name" value="<?= h($client['name']) ?>" required></label>
				<label><span>RUT</span><input name="rut" value="<?= h($client['rut'] ?? '') ?>" placeholder="76.123.456-7"></label>
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

			<h3 class="section-title word" style="margin-top:18px">Contactos</h3>
			<div class="contact-rows" data-contact-rows>
				<?php foreach ($contacts as $i => $c): ?>
					<div class="contact-row" data-contact-row>
						<input type="hidden" name="contact_id[]" value="<?= (int) ($c['id'] ?? 0) ?>">
						<label><span>Nombre</span><input name="contact_name[]" value="<?= h($c['name'] ?? '') ?>"></label>
						<label><span>Cargo</span><input name="contact_title[]" value="<?= h($c['title'] ?? '') ?>"></label>
						<label><span>Correo</span><input name="contact_email[]" type="email" value="<?= h($c['email'] ?? '') ?>"></label>
						<label><span>Teléfono</span><input name="contact_phone[]" value="<?= h($c['phone'] ?? '') ?>"></label>
						<button type="button" class="btn-danger-text" data-remove-contact <?= $i === 0 ? 'hidden' : '' ?>>Quitar</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="btn btn-word" data-add-contact style="margin-top:10px">+ Otro contacto</button>

			<div class="form-actions" style="margin-top:16px">
				<button class="btn btn-word" type="submit">Guardar datos</button>
			</div>
		</form>
	</section>

	<section class="paper">
		<h2 class="section-title word">Enviar correo a contactos</h2>
		<?php
		$mailable = array_values(array_filter($contacts, static fn($c) => !empty($c['email'])));
		?>
		<?php if (!$mailable): ?>
			<p class="muted">Agrega correos a los contactos para escribirles desde aquí.</p>
		<?php else: ?>
			<form method="get" action="<?= h(Http::url('/correo/nuevo')) ?>" class="contact-mail-pick">
				<input type="hidden" name="cliente" value="<?= (int) $client['id'] ?>">
				<?php foreach ($mailable as $c): ?>
					<label class="check-pill">
						<input type="checkbox" name="para_list[]" value="<?= h($c['email']) ?>" form="noop">
						<span><?= h(($c['name'] ?: $c['email']) . (!empty($c['title']) ? ' · ' . $c['title'] : '')) ?></span>
						<small><?= h($c['email']) ?></small>
					</label>
				<?php endforeach; ?>
				<p class="muted" style="margin:8px 0 0">Marca contactos y pulsa el botón (se abrirá el redactor con sus correos).</p>
				<a class="btn btn-word" id="mail-selected-contacts" href="<?= h(Http::url('/correo/nuevo?cliente=' . $client['id'])) ?>">Escribir a seleccionados</a>
			</form>
			<script>
			(function () {
				const link = document.getElementById('mail-selected-contacts');
				if (!link) return;
				const boxes = document.querySelectorAll('.contact-mail-pick input[type=checkbox]');
				function refresh() {
					const emails = [];
					boxes.forEach(function (b) { if (b.checked) emails.push(b.value); });
					const base = <?= json_encode(Http::url('/correo/nuevo?cliente=' . (int) $client['id'])) ?>;
					link.href = emails.length ? base + '&para=' + encodeURIComponent(emails.join(', ')) : base;
				}
				boxes.forEach(function (b) { b.addEventListener('change', refresh); });
				refresh();
			})();
			</script>
		<?php endif; ?>
	</section>

	<section class="paper">
		<h2 class="section-title word">Correos</h2>
		<?php if (empty($mails)): ?>
			<div class="empty">
				<p>Aún no hay correos con este cliente en tu casilla.</p>
				<a class="btn btn-word" href="<?= h(Http::url('/correo/nuevo?cliente=' . $client['id'])) ?>">Escribir correo</a>
			</div>
		<?php else: ?>
			<div class="table-wrap">
				<table class="sheet">
					<thead>
						<tr>
							<th></th>
							<th>Asunto</th>
							<th>Fecha</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($mails as $mail): ?>
							<tr class="is-link <?= empty($mail['seen']) && $mail['folder'] === 'inbox' ? 'is-unread' : '' ?>" onclick="location.href='<?= h(Http::url('/correo/' . $mail['id'])) ?>'">
								<td><?= $mail['folder'] === 'sent' ? 'Enviado' : 'Recibido' ?></td>
								<td><?= h($mail['subject'] ?: '(sin asunto)') ?></td>
								<td><?= h(mail_when($mail['sent_at'] ?? null)) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
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
						<tr data-quote-row="<?= (int) $quote['id'] ?>">
							<td><a href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>"><?= h($quote['number']) ?></a></td>
							<td data-quote-status="<?= (int) $quote['id'] ?>"><?= h(quote_status_label((string) $quote['status'])) ?></td>
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
