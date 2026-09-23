<?php use MizoCrm\Auth; use MizoCrm\Csrf; use MizoCrm\Http; ?>
<?php
$client = $client ?? [];
$contacts = $contacts ?? [];
$comments = $comments ?? [];
$quotes = $quotes ?? [];
$mails = $mails ?? [];
$projects = $projects ?? [];
$services = $services ?? [];
$team = $team ?? [];
$editContacts = $contacts;
if ($editContacts === []) {
	$editContacts = [[
		'id' => '',
		'name' => '',
		'email' => '',
		'phone' => '',
		'title' => '',
	]];
}
?>
<div class="client-file">
	<div class="page-head">
		<div>
			<h1><?= h($client['name'] ?? 'Cliente') ?></h1>
			<p><?= h(trim((string) (($client['rut'] ?? '') !== '' ? $client['rut'] . ' · ' : '') . ($client['city'] ?? ''))) ?></p>
		</div>
	</div>

	<details class="paper fold" open>
		<summary>Datos del cliente</summary>
		<div class="fold-body">
			<form class="form client-inline is-locked" method="post" action="<?= h(Http::url('/clientes/' . $client['id'])) ?>" data-contacts-form data-client-inline>
				<?= Csrf::field() ?>
				<input type="hidden" name="volver" value="ficha">
				<div class="client-inline-bar">
					<button type="button" class="btn-text" data-lock>Desbloquear datos</button>
					<button class="btn btn-excel" type="submit" data-save hidden>Guardar datos</button>
				</div>
				<div class="grid-2">
					<label><span>Cliente / Empresa</span><input name="name" value="<?= h($client['name'] ?? '') ?>" required></label>
					<label><span>RUT</span><input name="rut" value="<?= h($client['rut'] ?? '') ?>" placeholder="Ej: 76.123.456-7"></label>
					<label><span>Ciudad</span><input name="city" value="<?= h($client['city'] ?? '') ?>"></label>
				</div>
				<?php if (Auth::isAdmin()): ?>
					<label>
						<span>Lo lleva</span>
						<select name="owner_id">
							<option value="0">Sin asignar</option>
							<?php foreach ($team as $member): ?>
								<option value="<?= (int) $member['id'] ?>" <?= (int) ($client['owner_id'] ?? 0) === (int) $member['id'] ? 'selected' : '' ?>><?= h($member['name']) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<h3 class="section-title word">Contactos</h3>
				<div class="contact-rows" data-contact-rows>
					<?php foreach ($editContacts as $i => $c): ?>
						<div class="contact-row" data-contact-row>
							<input type="hidden" name="contact_id[]" value="<?= (int) ($c['id'] ?? 0) ?>">
							<label><span>Nombre</span><input name="contact_name[]" value="<?= h($c['name'] ?? '') ?>"></label>
							<label><span>Cargo</span><input name="contact_title[]" value="<?= h($c['title'] ?? '') ?>"></label>
							<label><span>Correo</span><input name="contact_email[]" type="email" value="<?= h($c['email'] ?? '') ?>"></label>
							<label><span>Teléfono</span><input name="contact_phone[]" value="<?= h($c['phone'] ?? '') ?>"></label>
							<button type="button" class="btn-danger-text" data-remove-contact <?= $i === 0 && count($editContacts) < 2 ? 'hidden' : '' ?>>Quitar</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="btn btn-word" data-add-contact>+ Otro contacto</button>
			</form>
		</div>
	</details>

	<details class="paper fold" open>
		<summary>Proyectos</summary>
		<div class="fold-body">
		<p class="muted">Cada proyecto es una tarjeta del tablero. Desde ahí ves sus notas y cotizaciones.</p>
		<?php if (!$projects): ?>
			<p class="muted">Este cliente todavía no tiene proyectos.</p>
		<?php else: ?>
			<ul class="notes">
				<?php foreach ($projects as $project): ?>
					<li>
						<div class="note-body">
							<p><?= h($project['title']) ?></p>
							<small><?= h($project['service']) ?> · <?= h($project['stage_label']) ?></small>
							<?php if ($contacts): ?>
								<?php $chosen = (int) (($project['contacts'][0]['id'] ?? 0)); ?>
								<form class="project-charge" method="post" action="<?= h(Http::url('/proyectos/' . $project['id'] . '/contactos')) ?>">
									<?= Csrf::field() ?>
									<label>
										<span>A cargo</span>
										<select name="contact_id" onchange="this.form.submit()">
											<option value="">Sin contacto a cargo</option>
											<?php foreach ($contacts as $c): ?>
												<?php
												$label = ($c['name'] ?: 'Contacto');
												if (!empty($c['title'])) {
													$label .= ' · ' . $c['title'];
												}
												if (!empty($c['email'])) {
													$label .= ' · ' . $c['email'];
												}
												if (!empty($c['phone'])) {
													$label .= ' · ' . $c['phone'];
												}
												?>
												<option value="<?= (int) $c['id'] ?>" <?= $chosen === (int) $c['id'] ? 'selected' : '' ?>><?= h($label) ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								</form>
							<?php else: ?>
								<small>Agrega contactos en los datos del cliente.</small>
							<?php endif; ?>
						</div>
						<form method="post" action="<?= h(Http::url('/proyectos/' . $project['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar el proyecto <?= h($project['title']) ?>? Se borran sus cotizaciones y notas. El cliente se mantiene.');">
							<?= Csrf::field() ?>
							<button class="btn-danger-text" type="submit">Eliminar</button>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<form class="form" method="post" action="<?= h(Http::url('/clientes/' . $client['id'] . '/proyecto')) ?>" style="margin-top:12px">
			<?= Csrf::field() ?>
			<div class="grid-2">
				<label><span>Nuevo proyecto</span><input name="title" required placeholder="Ej: Sonido gimnasio"></label>
				<label>
					<span>Servicio</span>
					<select name="service">
						<?php foreach ($services as $key => $label): ?>
							<option value="<?= h($key) ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<?php if (!$contacts): ?>
				<p class="muted">Agrega contactos en los datos del cliente para asignar quién está a cargo.</p>
			<?php else: ?>
				<label>
					<span>A cargo</span>
					<select name="contact_id">
						<option value="">Sin contacto a cargo</option>
						<?php foreach ($contacts as $c): ?>
							<?php
							$label = ($c['name'] ?: 'Contacto');
							if (!empty($c['title'])) {
								$label .= ' · ' . $c['title'];
							}
							if (!empty($c['email'])) {
								$label .= ' · ' . $c['email'];
							}
							if (!empty($c['phone'])) {
								$label .= ' · ' . $c['phone'];
							}
							?>
							<option value="<?= (int) $c['id'] ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<div class="form-actions">
				<button class="btn btn-word" type="submit">Crear proyecto</button>
			</div>
		</form>
		</div>
	</details>

	<details class="paper fold" open>
		<summary>Enviar correo a contactos</summary>
		<div class="fold-body">
		<?php
		$mailable = array_values(array_filter($contacts, static fn($c) => !empty($c['email'])));
		?>
		<?php if (!$mailable): ?>
			<p class="muted">Agrega correos en Editar datos para escribirles desde aquí.</p>
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
		</div>
	</details>

	<details class="paper fold" open>
		<summary>Correos</summary>
		<div class="fold-body">
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
		</div>
	</details>

	<details class="paper fold" open>
		<summary>Anotaciones y recordatorios</summary>
		<div class="fold-body">
		<form class="form" method="post" action="<?= h(Http::url('/clientes/' . $client['id'] . '/comentario')) ?>">
			<?= Csrf::field() ?>
			<label>
				<span>Nueva anotación</span>
				<textarea name="comment" rows="3" required placeholder="Ej: Llamar el viernes para confirmar la visita."></textarea>
			</label>
			<div class="form-actions">
				<button class="btn btn-word" type="submit" name="kind" value="comentario">Guardar anotación</button>
				<button class="btn btn-excel" type="submit" name="kind" value="recordatorio">Guardar recordatorio</button>
			</div>
		</form>
		<?php if (!$comments): ?>
			<p class="muted" style="margin-top:16px">Aún no hay comentarios en este cliente.</p>
		<?php else: ?>
			<ul class="notes">
				<?php foreach ($comments as $note): ?>
					<li>
						<div class="note-body">
							<p><?= ($note['type'] ?? '') === 'recordatorio' ? 'Recordatorio: ' : '' ?><?= nl2br(h($note['message'])) ?></p>
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
		</div>
	</details>

	<details class="paper fold" open>
		<summary>Cotizaciones</summary>
		<div class="fold-body">
		<?php if (!$projects): ?>
			<p class="muted">Crea un proyecto antes de hacer una cotización. Después eliges a cuál asociarla.</p>
		<?php else: ?>
			<p><a class="btn btn-excel" href="<?= h(Http::url('/clientes/' . $client['id'] . '/cotizacion')) ?>">Nueva cotización</a></p>
		<?php endif; ?>
		<?php if (!$quotes): ?>
			<div class="empty">
				<p>Este cliente todavía no tiene cotizaciones.</p>
			</div>
		<?php else: ?>
			<div class="table-wrap">
				<table class="sheet">
					<thead>
						<tr>
							<th>Número</th>
							<th>Proyecto</th>
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
							<td><?php
								$quoteProject = '';
								foreach ($projects as $row) {
									if ((int) $row['id'] === (int) ($quote['deal_id'] ?? 0)) {
										$quoteProject = (string) $row['title'];
										break;
									}
								}
								echo h($quoteProject !== '' ? $quoteProject : '—');
							?></td>
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
		</div>
	</details>
</div>
