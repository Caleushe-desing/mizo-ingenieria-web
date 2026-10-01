<?php
use MizoCrm\Http;

$mktTab = $mktTab ?? 'correos';
?>
<nav class="mkt-tabs" aria-label="Secciones de Marketing">
	<a class="<?= $mktTab === 'correos' ? 'is-on' : '' ?>" href="<?= h(Http::url('/marketing')) ?>">Correos y plantillas</a>
	<a class="<?= $mktTab === 'recursos' ? 'is-on' : '' ?>" href="<?= h(Http::url('/marketing/recursos')) ?>">Estudio de diseño</a>
	<a class="<?= $mktTab === 'medios' ? 'is-on' : '' ?>" href="<?= h(Http::url('/marketing/medios')) ?>">Biblioteca / Stock Mizo</a>
	<a class="<?= $mktTab === 'plantillas' ? 'is-on' : '' ?>" href="<?= h(Http::url('/marketing/plantillas')) ?>">Editar plantillas</a>
</nav>
