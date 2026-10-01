<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$decision = (string) ($decision ?? 'aceptada');
$accepting = $decision === 'aceptada';
?>
<article class="doc" style="padding:28px">
	<header class="doc-letterhead" style="border:0;padding:0 0 16px">
		<div class="doc-brand">
			<img src="/mizo-logo.png" alt="Mizo">
		</div>
		<div class="doc-id">
			<p class="doc-type">Cotización</p>
			<p class="doc-number"><?= h($quote['number']) ?></p>
		</div>
	</header>

	<section style="padding:8px 0 20px">
		<h2 style="margin:0 0 10px;font-size:18px;color:#161616;letter-spacing:0;text-transform:none">
			<?= $accepting ? '¿Aceptar este presupuesto?' : '¿Registrar que no aceptas este presupuesto?' ?>
		</h2>
		<p style="margin:0 0 8px;font-size:14px;color:#444;line-height:1.45">
			<?= $accepting
				? 'Al confirmar, avisamos a Mizo y el proyecto avanza en el tablero a presupuesto aceptado.'
				: 'Al confirmar, avisamos a Mizo. Puedes escribirnos después si quieres ajustar el alcance.' ?>
		</p>
		<p style="margin:0;font-size:13px;color:#666">
			Total: <strong style="color:#f47b20"><?= money((int) ($quote['total'] ?? 0)) ?></strong>
			· Cliente: <?= h((string) ($quote['client_name'] ?? '')) ?>
		</p>
	</section>

	<form class="doc-actions" method="post" action="<?= h(Http::url('/q/' . $quote['token'])) ?>" style="border:0;margin:0;padding:0">
		<?= Csrf::field() ?>
		<button class="<?= $accepting ? 'doc-accept' : 'doc-decline' ?>" name="decision" value="<?= h($decision) ?>" type="submit">
			<?= $accepting ? 'Sí, aceptar presupuesto' : 'Confirmar: no por ahora' ?>
		</button>
		<a class="doc-decline" href="<?= h(Http::url('/q/' . $quote['token'])) ?>" style="text-decoration:none">Cancelar</a>
	</form>
</article>
