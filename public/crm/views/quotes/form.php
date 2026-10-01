<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$project = $project ?? null;
$locked = $quote && in_array($quote['status'], ['aceptada', 'rechazada'], true);
$sentAlready = $quote && (!empty($quote['sent_at']) || in_array((string) $quote['status'], ['enviada', 'vista'], true));
$projects = $projects ?? [];
$catalogProducts = $catalogProducts ?? [];
$action = $quote
	? Http::url('/cotizaciones/' . $quote['id'])
	: Http::url('/clientes/' . $client['id'] . '/cotizacion');
$contacts = $contacts ?? \MizoCrm\Models\ClientContact::forClient((int) $client['id']);
$selectedContact = is_array($quote) ? (int) ($quote['contact_id'] ?? 0) : 0;
if ($selectedContact === 0 && !empty($project['id'])) {
	$assigned = \MizoCrm\Models\Deal::contactsByDeal([(int) $project['id']])[(int) $project['id']] ?? [];
	if ($assigned) {
		$selectedContact = (int) $assigned[0]['id'];
	}
}
if ($selectedContact === 0 && is_array($quote) && !empty($quote['sent_to'])) {
	foreach ($contacts as $c) {
		if (strcasecmp((string) ($c['email'] ?? ''), (string) $quote['sent_to']) === 0) {
			$selectedContact = (int) $c['id'];
			break;
		}
	}
}
if ($selectedContact === 0 && $contacts) {
	foreach ($contacts as $c) {
		if (trim((string) ($c['email'] ?? '')) !== '') {
			$selectedContact = (int) $c['id'];
			break;
		}
	}
	if ($selectedContact === 0) {
		$selectedContact = (int) $contacts[0]['id'];
	}
}
$chosenEmail = '';
foreach ($contacts as $c) {
	if ((int) $c['id'] === $selectedContact) {
		$chosenEmail = trim((string) ($c['email'] ?? ''));
		break;
	}
}
?>
<div
	class="client-sheet quote-sheet"
	data-catalog-create="<?= h(Http::url('/api/catalogo-rapido')) ?>"
	data-catalog-import="<?= h(Http::url('/api/catalogo-importar')) ?>"
	data-csrf="<?= h(Csrf::token()) ?>"
>
	<header class="quote-toolbar">
		<div>
			<p class="quote-kicker">Cotizador</p>
			<h1><?= $quote ? h($quote['number'] . ((trim((string) ($quote['revision'] ?? '')) !== '') ? ' · ' . $quote['revision'] : '')) : 'Nueva cotización' ?></h1>
			<p>
				<?= h($client['name']) ?>
				<?php if ($project): ?> · <?= h($project['title']) ?><?php endif; ?>
				<?php if ($quote): ?> · <?= h(quote_status_label((string) $quote['status'])) ?><?php endif; ?>
			</p>
		</div>
		<?php if (!$locked): ?>
			<div class="quote-toolbar-actions">
				<button class="btn btn-excel" type="submit" form="quote-form" name="intent" value="save">Guardar</button>
				<button class="btn btn-word" type="submit" form="quote-form" name="intent" value="send"><?= $sentAlready ? 'Enviar revisión' : 'Enviar' ?></button>
			</div>
		<?php endif; ?>
	</header>

	<?php if ($sentAlready && !$locked): ?>
		<p class="quote-notice">Esta cotización ya se envió. Al guardar una versión (REV-01, OC u otra), reemplaza a la anterior en la misma tarjeta del tablero.</p>
	<?php endif; ?>

	<form id="quote-form" class="paper form quote-work" method="post" action="<?= h($action) ?>">
		<?= Csrf::field() ?>

		<details class="quote-block" open>
			<summary>
				<span>1. Datos de envío</span>
				<small>Título, contacto, validez</small>
			</summary>
			<div class="quote-block-body">
				<?php if (!$quote): ?>
					<label>
						<span>Proyecto</span>
						<select name="project_id" required>
							<option value="">Elige el proyecto</option>
							<?php foreach ($projects as $row): ?>
								<option value="<?= (int) $row['id'] ?>" <?= (int) ($project['id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>><?= h($row['title']) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php elseif ($project): ?>
					<p class="muted" style="margin:0 0 10px">Proyecto: <?= h($project['title']) ?></p>
				<?php endif; ?>
				<div class="grid-2">
					<label>
						<span>Título que verá el cliente</span>
						<input name="intro" value="<?= h($quote['intro'] ?? '') ?>" placeholder="Ej: Sistema de sonido para gimnasio" <?= $locked ? 'readonly' : '' ?>>
					</label>
					<label>
						<span>Contacto al que va dirigida</span>
						<?php if ($contacts): ?>
							<select name="contact_id" <?= $locked ? 'disabled' : '' ?>>
								<?php foreach ($contacts as $c): ?>
									<?php
									$label = trim((string) ($c['name'] ?? '')) !== '' ? (string) $c['name'] : 'Contacto';
									if (trim((string) ($c['title'] ?? '')) !== '') {
										$label .= ' · ' . $c['title'];
									}
									$email = trim((string) ($c['email'] ?? ''));
									$label .= $email !== '' ? ' · ' . $email : ' · sin correo';
									?>
									<option value="<?= (int) $c['id'] ?>" data-email="<?= h($email) ?>" <?= $selectedContact === (int) $c['id'] ? 'selected' : '' ?>><?= h($label) ?></option>
								<?php endforeach; ?>
							</select>
							<small class="muted" data-quote-mail><?= $chosenEmail !== '' ? h($chosenEmail) : 'Este contacto no tiene correo.' ?></small>
						<?php else: ?>
							<input name="sent_to" type="email" value="<?= h($quote['sent_to'] ?? $client['email'] ?? '') ?>" placeholder="correo@cliente.cl">
							<small class="muted">Este cliente no tiene contactos. Agrégalos en su ficha.</small>
						<?php endif; ?>
					</label>
					<?php if ($sentAlready && !$locked): ?>
					<label>
						<span>Versión</span>
						<input name="revision" value="<?= h($quote['revision'] ?? '') ?>" placeholder="REV-01, OC" maxlength="24" required>
					</label>
					<?php endif; ?>
					<label>
						<span>Válida hasta</span>
						<input name="valid_until" type="date" value="<?= h($quote['valid_until'] ?? date('Y-m-d', strtotime('+15 days'))) ?>" <?= $locked ? 'readonly' : '' ?>>
					</label>
					<label class="quote-notes-field">
						<span>Nota al pie</span>
						<input name="notes" value="<?= h($quote['notes'] ?? 'Validez 15 días. Precios en pesos chilenos, neto + IVA.') ?>" <?= $locked ? 'readonly' : '' ?>>
					</label>
				</div>
			</div>
		</details>

		<section class="quote-block is-solid">
			<div class="quote-block-hd">
				<div>
					<h2>2. Partidas</h2>
					<p class="muted">Busca en el catálogo, crea un producto si no existe, o usa un ítem libre. Ajusta margen y revisa la venta neta.</p>
				</div>
				<?php if (!$locked): ?>
					<button class="btn btn-word" type="button" data-add-item>+ Agregar partida</button>
				<?php endif; ?>
			</div>
			<script type="application/json" id="quote-catalog-json"><?= json_encode(array_values($catalogProducts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
			<div class="quote-lines quote-line-cards" data-tax-rate="19" data-items>
			<?php foreach ($items as $index => $item): ?>
				<?php
				$cost = (int) ($item['cost_price'] ?? 0);
				$margin = (float) ($item['margin_percent'] ?? 0);
				$price = (int) ($item['unit_price'] ?? 0);
				if ($cost > 0) {
					$price = \MizoCrm\Models\Quote::netSaleFromCost($cost, $margin);
				}
				$name = trim((string) ($item['name'] ?? ''));
				$detail = (string) ($item['description'] ?? '');
				if ($name === '' && $detail !== '') {
					$name = $detail;
					$detail = '';
				}
				$productId = (int) ($item['product_id'] ?? 0);
				$supplierHref = '';
				$pickedLabel = 'Buscar en catálogo…';
				if ($productId > 0) {
					foreach ($catalogProducts as $catalogProduct) {
						if ((int) $catalogProduct['id'] === $productId) {
							$supplierHref = (string) ($catalogProduct['proveedor_link'] ?? '');
							$pickedLabel = trim(($catalogProduct['sku'] ?? '') . ' — ' . ($catalogProduct['nombre'] ?? ''));
							break;
						}
					}
				}
				$supplierHost = $supplierHref !== '' ? (parse_url($supplierHref, PHP_URL_HOST) ?: 'Abrir ficha') : '';
				?>
				<article class="quote-line-card" data-item-row>
					<div class="quote-line-hd">
						<strong data-line-index>Partida <?= (int) $index + 1 ?></strong>
						<?php if (!$locked): ?>
							<button class="btn-text quote-line-remove" type="button" data-remove>Quitar</button>
						<?php endif; ?>
					</div>
					<div class="quote-line-top">
						<div class="quote-product-cell">
							<span class="quote-line-label">Producto</span>
							<?php if (!$locked): ?>
								<button class="catalog-pick" type="button" data-catalog-open aria-label="Buscar producto del catálogo">
									<span data-catalog-label><?= h($pickedLabel) ?></span>
								</button>
							<?php endif; ?>
							<input type="hidden" name="item_product_id[]" value="<?= $productId > 0 ? (string) $productId : '' ?>">
							<input name="item_name[]" value="<?= h($name) ?>" placeholder="Nombre del producto" <?= $locked ? 'readonly' : '' ?>>
						</div>
						<label class="quote-desc-cell">
							<span class="quote-line-label">Descripción / especificaciones</span>
							<textarea name="item_description[]" rows="6" placeholder="Especificaciones técnicas, notas, alcance…" <?= $locked ? 'readonly' : '' ?>><?= h($detail) ?></textarea>
						</label>
					</div>
					<div class="quote-line-metrics">
						<label><span>Cant.</span><input name="item_quantity[]" value="<?= h((string) ($item['quantity'] ?? 1)) ?>" <?= $locked ? 'readonly' : '' ?>></label>
						<label><span>Unidad</span><input name="item_unit[]" value="<?= h($item['unit'] ?? 'un') ?>" <?= $locked ? 'readonly' : '' ?>></label>
						<label><span>Costo c/IVA</span><input name="item_cost[]" inputmode="numeric" value="<?= $cost > 0 ? h((string) $cost) : '' ?>" placeholder="0" <?= $locked ? 'readonly' : '' ?>></label>
						<div class="quote-supplier-cell">
							<span class="quote-line-label">URL proveedor</span>
							<div class="catalog-supplier" data-catalog-supplier<?= $supplierHref === '' ? ' hidden' : '' ?>>
								<a class="btn catalog-supplier-link" data-catalog-link href="<?= h($supplierHref !== '' ? $supplierHref : '#') ?>" target="_blank" rel="noopener noreferrer">Ver precio</a>
								<span class="catalog-supplier-host" data-catalog-host title="<?= h($supplierHref) ?>"><?= h($supplierHost) ?></span>
							</div>
							<span class="muted catalog-supplier-empty" data-catalog-empty<?= $supplierHref !== '' ? ' hidden' : '' ?>>—</span>
						</div>
						<label><span>Margen %</span><input name="item_margin[]" inputmode="decimal" value="<?= $margin > 0 ? h(rtrim(rtrim(number_format($margin, 2, '.', ''), '0'), '.')) : '' ?>" placeholder="0" <?= $locked ? 'readonly' : '' ?>></label>
						<label><span>Venta neta</span><input name="item_price[]" data-sale-net value="<?= h((string) $price) ?>" <?= $locked || $cost > 0 ? 'readonly' : '' ?>></label>
						<div class="quote-line-total">
							<span class="quote-line-label">Total</span>
							<strong data-line><?= money((int) round(((float) ($item['quantity'] ?? 1)) * $price)) ?></strong>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
			</div>
		</section>

		<?php
		$profitLive = \MizoCrm\Models\Quote::profitFromItems($items);
		$profitClass = ((int) $profitLive['profit'] >= 0) ? 'is-gain' : 'is-loss';
		?>
		<aside class="quote-profit-panel" aria-label="Utilidad interna">
			<div class="quote-profit-panel-hd">
				<strong>Utilidad interna</strong>
				<span class="muted">Solo CRM · no sale al cliente</span>
			</div>
			<div class="quote-profit-grid">
				<div>
					<span>Costo total c/IVA</span>
					<b data-profit-cost-iva><?= money((int) $profitLive['cost_total_iva']) ?></b>
				</div>
				<div>
					<span>Costo neto</span>
					<b data-profit-cost-net><?= money((int) $profitLive['cost_total_net']) ?></b>
				</div>
				<div>
					<span>Venta neta</span>
					<b data-profit-sale-net><?= money((int) $profitLive['sale_net']) ?></b>
				</div>
				<div>
					<span>Utilidad</span>
					<b class="<?= $profitClass ?>" data-profit-money><?= money((int) $profitLive['profit']) ?></b>
				</div>
				<div>
					<span>Margen real</span>
					<b class="<?= $profitClass ?>" data-profit-margin><?= $profitLive['margin_real'] !== null ? h(number_format((float) $profitLive['margin_real'], 1, ',', '.')) . '%' : '—' ?></b>
				</div>
			</div>
		</aside>

		<div class="quote-footer-bar">
			<div class="totals quote-totals">
				<div><span>Neto</span><b data-neto><?= money((int) ($quote['subtotal'] ?? 0)) ?></b></div>
				<div><span>IVA 19%</span><b data-iva><?= money((int) ($quote['tax'] ?? 0)) ?></b></div>
				<div class="is-total"><span>Total</span><b data-total><?= money((int) ($quote['total'] ?? 0)) ?></b></div>
			</div>
			<?php if (!$locked): ?>
				<div class="form-actions quote-footer-actions">
					<button class="btn btn-excel" type="submit" name="intent" value="save">Guardar cotización</button>
					<button class="btn btn-word" type="submit" name="intent" value="send"><?= $sentAlready ? 'Enviar revisión' : 'Enviar al cliente' ?></button>
				</div>
			<?php else: ?>
				<p class="muted">Esta cotización ya fue <?= h(quote_status_label((string) $quote['status'])) ?> y no se puede modificar.</p>
			<?php endif; ?>
		</div>
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
<?php if ($contacts && !$locked): ?>
<script>
(function () {
	var select = document.querySelector(".quote-work select[name=contact_id]");
	var mail = document.querySelector("[data-quote-mail]");
	if (!select || !mail) return;
	select.addEventListener("change", function () {
		var opt = select.options[select.selectedIndex];
		var email = opt ? (opt.getAttribute("data-email") || "") : "";
		mail.textContent = email || "Este contacto no tiene correo.";
	});
})();
</script>
<?php endif; ?>
