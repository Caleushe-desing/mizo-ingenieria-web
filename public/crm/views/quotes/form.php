<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$project = $project ?? null;
$locked = $quote && in_array($quote['status'], ['aceptada', 'rechazada'], true);
$sentAlready = $quote && (!empty($quote['sent_at']) || in_array((string) $quote['status'], ['enviada', 'vista'], true));
$action = $quote
	? Http::url('/cotizaciones/' . $quote['id'])
	: ($project
		? Http::url('/proyectos/' . $project['id'] . '/cotizacion')
		: Http::url('/clientes/' . $client['id'] . '/cotizacion'));
?>
<div class="client-sheet quote-sheet">
	<div class="page-head">
		<div>
			<h1><?= $quote ? h($quote['number']) : 'Nueva cotización' ?></h1>
			<p>
				<?= h($client['name']) ?>
				<?php if ($project): ?> · <?= h($project['title']) ?><?php endif; ?>
				<?php if ($quote): ?> · <?= h(quote_status_label((string) $quote['status'])) ?><?php endif; ?>
			</p>
		</div>
	</div>

	<?php if ($sentAlready && !$locked): ?>
		<p class="quote-notice">Esta cotización ya se envió al cliente. Si la cambias y la vuelves a enviar, se crea una revisión (A, B, C…) y esta queda igual.</p>
	<?php endif; ?>

	<form class="paper form quote-work" method="post" action="<?= h($action) ?>">
		<?= Csrf::field() ?>
		<h2 class="section-title word">Para quién y hasta cuándo</h2>
		<div class="grid-2">
			<label>
				<span>Título que verá el cliente</span>
				<input name="intro" value="<?= h($quote['intro'] ?? '') ?>" placeholder="Ej: Sistema de sonido para gimnasio" <?= $locked ? 'readonly' : '' ?>>
			</label>
			<label>
				<span>Enviar a este correo</span>
				<input name="sent_to" type="email" value="<?= h($quote['sent_to'] ?? $client['email'] ?? '') ?>" placeholder="correo@cliente.cl">
			</label>
			<label>
				<span>Válida hasta</span>
				<input name="valid_until" type="date" value="<?= h($quote['valid_until'] ?? date('Y-m-d', strtotime('+15 days'))) ?>" <?= $locked ? 'readonly' : '' ?>>
			</label>
			<label>
				<span>Nota al pie</span>
				<input name="notes" value="<?= h($quote['notes'] ?? 'Validez 15 días. Precios en pesos chilenos, neto + IVA.') ?>" <?= $locked ? 'readonly' : '' ?>>
			</label>
		</div>

		<h2 class="section-title word">Partidas</h2>
		<p class="muted">Escribe qué se cotiza, la cantidad y el precio neto. El total se calcula solo.</p>
		<div class="table-wrap">
			<table class="sheet sheet-edit quote-lines">
				<thead>
					<tr>
						<th>Descripción</th>
						<th>Cant.</th>
						<th>Unidad</th>
						<th>Precio neto</th>
						<th>Total</th>
						<?php if (!$locked): ?><th></th><?php endif; ?>
					</tr>
				</thead>
				<tbody data-items>
				<?php foreach ($items as $item): ?>
					<tr data-item-row>
						<td><input name="item_description[]" value="<?= h($item['description'] ?? '') ?>" placeholder="Ej: Parlante de techo" <?= $locked ? 'readonly' : '' ?>></td>
						<td><input name="item_quantity[]" value="<?= h((string) ($item['quantity'] ?? 1)) ?>" <?= $locked ? 'readonly' : '' ?>></td>
						<td><input name="item_unit[]" value="<?= h($item['unit'] ?? 'un') ?>" <?= $locked ? 'readonly' : '' ?>></td>
						<td><input name="item_price[]" value="<?= h((string) ($item['unit_price'] ?? 0)) ?>" <?= $locked ? 'readonly' : '' ?>></td>
						<td data-line><?= money((int) ($item['total'] ?? 0)) ?></td>
						<?php if (!$locked): ?>
							<td><button class="btn-text" type="button" data-remove>Quitar</button></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if (!$locked): ?>
			<button class="btn btn-word" type="button" data-add-item>+ Agregar partida</button>
		<?php endif; ?>

		<div class="totals quote-totals">
			<div><span>Neto</span><b data-neto><?= money((int) ($quote['subtotal'] ?? 0)) ?></b></div>
			<div><span>IVA 19%</span><b data-iva><?= money((int) ($quote['tax'] ?? 0)) ?></b></div>
			<div class="is-total"><span>Total</span><b data-total><?= money((int) ($quote['total'] ?? 0)) ?></b></div>
		</div>

		<?php if (!$locked): ?>
			<div class="form-actions">
				<button class="btn btn-excel" type="submit" name="intent" value="save">Guardar cotización</button>
				<button class="btn btn-word" type="submit" name="intent" value="send"><?= $sentAlready ? 'Enviar revisión' : 'Enviar al cliente' ?></button>
			</div>
		<?php else: ?>
			<p class="muted">Esta cotización ya fue <?= h(quote_status_label((string) $quote['status'])) ?> y no se puede modificar.</p>
		<?php endif; ?>
	</form>

	<?php if ($quote): ?>
		<div class="client-sheet-links">
			<a class="btn-text" href="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/preview')) ?>" target="_blank" rel="noopener">Ver como la ve el cliente</a>
			<form method="post" action="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar la cotización <?= h($quote['number']) ?>?');">
				<?= Csrf::field() ?>
				<button class="btn-danger-text" type="submit">Eliminar cotización</button>
			</form>
		</div>
	<?php endif; ?>
</div>
