<?php
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;

$client = $client ?? [];
$contacts = $contacts ?? [];
$team = $team ?? [];
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
<div class="client-sheet">
	<div class="page-head">
		<div>
			<h1>Editar cliente</h1>
			<p>Empresa o lugar, RUT y varios contactos de ventas.</p>
		</div>
	</div>

	<form class="paper form" method="post" action="<?= h(Http::url('/clientes/' . $client['id'])) ?>" data-contacts-form>
		<?= Csrf::field() ?>
		<div class="grid-2">
			<label><span>Cliente / Empresa</span><input name="name" value="<?= h($client['name']) ?>" required></label>
			<label><span>RUT</span><input name="rut" value="<?= h($client['rut'] ?? '') ?>" placeholder="Ej: 76.123.456-7"></label>
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

		<h3 class="section-title word">Contactos</h3>
		<p class="muted">Puedes agregar gerentes, administrativos, técnicos, etc.</p>
		<div class="contact-rows" data-contact-rows>
			<?php foreach ($contacts as $i => $c): ?>
				<div class="contact-row" data-contact-row>
					<input type="hidden" name="contact_id[]" value="<?= (int) ($c['id'] ?? 0) ?>">
					<label><span>Nombre</span><input name="contact_name[]" value="<?= h($c['name'] ?? '') ?>"></label>
					<label><span>Cargo</span><input name="contact_title[]" value="<?= h($c['title'] ?? '') ?>"></label>
					<label><span>Correo</span><input name="contact_email[]" type="email" value="<?= h($c['email'] ?? '') ?>"></label>
					<label><span>Teléfono</span><input name="contact_phone[]" value="<?= h($c['phone'] ?? '') ?>"></label>
					<button type="button" class="btn-danger-text" data-remove-contact <?= $i === 0 && count($contacts) < 2 ? 'hidden' : '' ?>>Quitar</button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="btn btn-word" data-add-contact>+ Otro contacto</button>

		<div class="form-actions">
			<button class="btn btn-excel" type="submit">Guardar cliente</button>
		</div>
	</form>

	<form class="client-sheet-links" method="post" action="<?= h(Http::url('/clientes/' . $client['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar a <?= h($client['name']) ?>? También se borran sus comentarios y cotizaciones.');">
		<?= Csrf::field() ?>
		<button class="btn-danger-text" type="submit">Eliminar cliente</button>
	</form>
</div>
