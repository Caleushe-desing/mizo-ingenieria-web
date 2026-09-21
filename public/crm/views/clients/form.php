<?php use MizoCrm\Csrf; use MizoCrm\Http; ?>
<div class="page-head">
	<div>
		<a class="back" href="<?= h(Http::url('/')) ?>">← Clientes</a>
		<h1>Nuevo cliente</h1>
		<p>El cliente es la empresa o lugar. El contacto es la persona con quien hablas.</p>
	</div>
</div>

<form class="paper form" method="post" action="<?= h(Http::url('/clientes')) ?>" style="max-width:640px">
	<?= Csrf::field() ?>
	<div class="grid-2">
		<label><span>Cliente</span><input name="name" required autofocus placeholder="Ej: Hotel Lago"></label>
		<label><span>Contacto</span><input name="contact_name" placeholder="Ej: María Pérez"></label>
		<label><span>Teléfono</span><input name="phone"></label>
		<label><span>Correo</span><input name="email" type="email"></label>
		<label><span>Ciudad</span><input name="city"></label>
	</div>
	<label>
		<span>Primer comentario (opcional)</span>
		<textarea name="comment" rows="4" placeholder="Ej: Llamó por un sistema de sonido para el gimnasio."></textarea>
	</label>
	<div class="form-actions">
		<button class="btn btn-excel" type="submit">Guardar cliente</button>
		<a class="btn-text" href="<?= h(Http::url('/')) ?>">Cancelar</a>
	</div>
</form>
