<?php use MizoCrm\Csrf; use MizoCrm\Http; ?>
<h1>Activar el CRM</h1>
<p>Crea el primer usuario administrador. Después podrás sumar a tus colegas.</p>
<form class="form" method="post" action="<?= h(Http::url('/setup')) ?>" style="margin-top:20px">
	<?= Csrf::field() ?>
	<label><span>Tu nombre</span><input name="name" required></label>
	<label><span>Correo</span><input name="email" type="email" required></label>
	<label><span>Clave (mínimo 8)</span><input name="password" type="password" minlength="8" required></label>
	<button class="btn btn-word" type="submit">Crear acceso</button>
</form>
