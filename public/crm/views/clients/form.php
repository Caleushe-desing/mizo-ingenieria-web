<?php use MizoCrm\Csrf; use MizoCrm\Http; ?>
<div class="page-head">
	<div>
		<a class="back" href="<?= h(Http::url('/')) ?>">← Clientes</a>
		<h1>Nuevo cliente</h1>
		<p>Empresa o lugar, RUT y varios contactos de ventas.</p>
	</div>
</div>

<form class="paper form" method="post" action="<?= h(Http::url('/clientes')) ?>" style="max-width:720px" data-contacts-form>
	<?= Csrf::field() ?>
	<div class="grid-2">
		<label><span>Cliente / Empresa</span><input name="name" required autofocus placeholder="Ej: Hotel Lago"></label>
		<label><span>RUT</span><input name="rut" placeholder="Ej: 76.123.456-7"></label>
		<label><span>Ciudad</span><input name="city"></label>
	</div>

	<h3 class="section-title word" style="margin-top:18px">Contactos</h3>
	<p class="muted" style="margin-top:-6px">Puedes agregar gerentes, administrativos, técnicos, etc.</p>
	<div class="contact-rows" data-contact-rows>
		<div class="contact-row" data-contact-row>
			<input type="hidden" name="contact_id[]" value="">
			<label><span>Nombre</span><input name="contact_name[]" placeholder="Ej: María Pérez"></label>
			<label><span>Cargo</span><input name="contact_title[]" placeholder="Ej: Gerente"></label>
			<label><span>Correo</span><input name="contact_email[]" type="email"></label>
			<label><span>Teléfono</span><input name="contact_phone[]"></label>
			<button type="button" class="btn-danger-text" data-remove-contact hidden>Quitar</button>
		</div>
	</div>
	<button type="button" class="btn btn-word" data-add-contact style="margin-top:10px">+ Otro contacto</button>

	<label style="margin-top:18px">
		<span>Primer comentario (opcional)</span>
		<textarea name="comment" rows="4" placeholder="Ej: Llamó por un sistema de sonido para el gimnasio."></textarea>
	</label>
	<div class="form-actions">
		<button class="btn btn-excel" type="submit">Guardar cliente</button>
		<a class="btn-text" href="<?= h(Http::url('/')) ?>">Cancelar</a>
	</div>
</form>
