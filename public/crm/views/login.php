<?php use MizoCrm\Csrf; use MizoCrm\Http; ?>
<h1>Entrar</h1>
<p>Usa tu correo de Mizo para ver tus clientes, dejar comentarios y enviar cotizaciones.</p>
<form class="form" method="post" action="<?= h(Http::url('/login')) ?>" style="margin-top:20px">
	<?= Csrf::field() ?>
	<label><span>Correo</span><input name="email" type="email" required autofocus></label>
	<label><span>Clave</span><input name="password" type="password" required></label>
	<button class="btn btn-word" type="submit">Entrar</button>
</form>
