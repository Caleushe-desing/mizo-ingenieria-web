<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$locked = $quote && in_array($quote['status'], ['aceptada', 'rechazada'], true);
$action = $quote
	? Http::url('/cotizaciones/' . $quote['id'])
	: Http::url('/clientes/' . $client['id'] . '/cotizacion');
?>
<div class="page-head">
	<div>
		<a class="back" href="<?= h(Http::url('/clientes/' . $client['id'])) ?>">← <?= h($client['name']) ?></a>
		<h1><?= $quote ? h($quote['number']) : 'Nueva cotización' ?></h1>
		<p>Escribe las partidas, guarda y envía el correo al cliente desde aquí.</p>
	</div>
	<?php if ($quote): ?>
		<div class="page-head-actions">
			<a class="btn-text" href="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/preview')) ?>" target="_blank" rel="noopener">Ver como la ve el cliente</a>
			<form method="post" action="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar la cotización <?= h($quote['number']) ?>?');">
				<?= Csrf::field() ?>
				<button class="btn-danger-text" type="submit">Eliminar cotización</button>
			</form>
		</div>
	<?php endif; ?>
</div>

<form class="paper form" method="post" action="<?= h($action) ?>">
	<?= Csrf::field() ?>
	<div class="grid-2">
		<label>
			<span>Título o descripción breve</span>
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
			<span>Notas al pie</span>
			<input name="notes" value="<?= h($quote['notes'] ?? 'Validez 15 días. Precios en pesos chilenos, neto + IVA.') ?>" <?= $locked ? 'readonly' : '' ?>>
		</label>
	</div>

	<div class="table-wrap" style="margin-top:16px">
		<table class="sheet sheet-edit">
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
					<td><input name="item_description[]" value="<?= h($item['description'] ?? '') ?>" <?= $locked ? 'readonly' : '' ?>></td>
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
		<p style="margin-top:8px"><button class="btn-text" type="button" data-add-item>+ Agregar partida</button></p>
	<?php endif; ?>

	<div class="totals">
		<div><span>Neto</span><b data-neto><?= money((int) ($quote['subtotal'] ?? 0)) ?></b></div>
		<div><span>IVA 19%</span><b data-iva><?= money((int) ($quote['tax'] ?? 0)) ?></b></div>
		<div class="is-total"><span>Total</span><b data-total><?= money((int) ($quote['total'] ?? 0)) ?></b></div>
	</div>

	<?php if ($quote): ?>
		<p class="muted">Estado: <span data-quote-status="<?= (int) $quote['id'] ?>"><?= h(quote_status_label((string) $quote['status'])) ?></span><?= !empty($quote['sent_to']) ? ' · enviada a ' . h($quote['sent_to']) : '' ?></p>
	<?php endif; ?>

	<?php if (!$locked): ?>
		<div class="form-actions">
			<button class="btn btn-word" type="submit" name="intent" value="save">Guardar</button>
			<button class="btn btn-excel" type="submit" name="intent" value="send">Enviar al cliente</button>
		</div>
	<?php endif; ?>
</form>
