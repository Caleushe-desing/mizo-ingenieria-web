<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$book = $book ?? ['clients' => [], 'deals' => [], 'sales' => [], 'purchases' => []];
?>
<div class="admin-page">
	<div class="page-head">
		<div>
			<p class="file-kicker">Administración</p>
			<h1>Ingreso de facturas</h1>
			<p>Cada factura de venta aparece en el tablero. El proyecto aceptado sale solo cuando lo pagado cubre el monto. Si queda algo pendiente, el proyecto sigue visible.</p>
		</div>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Factura de venta</h2>
			<form class="form" method="post" action="<?= h(Http::url('/facturas/venta')) ?>">
				<?= Csrf::field() ?>
				<div class="grid-2">
					<label><span>Número</span><input name="number" required maxlength="40"></label>
					<label><span>Fecha</span><input type="date" name="issued_on" required value="<?= h(date('Y-m-d')) ?>"></label>
					<label>
						<span>Cliente</span>
						<select name="client_id" id="sale-client" required>
							<option value="">Elegir</option>
							<?php foreach ($book['clients'] as $client): ?>
								<option value="<?= (int) $client['id'] ?>"><?= h($client['name']) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span>Proyecto / presupuesto</span>
						<select name="deal_id" id="sale-deal" required>
							<option value="">Elegir</option>
							<?php foreach ($book['deals'] as $deal): ?>
								<option value="<?= (int) $deal['id'] ?>" data-client="<?= (int) $deal['client_id'] ?>"><?= h($deal['client_name'] . ' · ' . $deal['title']) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><span>Neto</span><input name="net" inputmode="numeric" required placeholder="0"></label>
					<label><span>IVA</span><input name="tax" inputmode="numeric" placeholder="0"></label>
					<label><span>Total</span><input name="total" inputmode="numeric" placeholder="Neto + IVA"></label>
				</div>
				<div class="form-actions"><button class="btn btn-word" type="submit">Registrar venta</button></div>
			</form>
			<script>
			document.getElementById('sale-client')?.addEventListener('change', function () {
				var id = this.value;
				var deals = document.getElementById('sale-deal');
				if (!deals) return;
				Array.from(deals.options).forEach(function (opt) {
					if (!opt.value) return;
					opt.hidden = id !== '' && opt.getAttribute('data-client') !== id;
				});
				if (deals.selectedOptions[0] && deals.selectedOptions[0].hidden) deals.value = '';
			});
			</script>
		</section>

		<section class="admin-card">
			<h2>Factura de compra</h2>
			<form class="form" method="post" action="<?= h(Http::url('/facturas/compra')) ?>">
				<?= Csrf::field() ?>
				<div class="grid-2">
					<label><span>Proveedor</span><input name="supplier" required maxlength="120"></label>
					<label><span>Número</span><input name="number" required maxlength="40"></label>
					<label><span>Fecha</span><input type="date" name="issued_on" required value="<?= h(date('Y-m-d')) ?>"></label>
					<label>
						<span>Proyecto (para el margen)</span>
						<select name="deal_id">
							<option value="0">Gasto general</option>
							<?php foreach ($book['deals'] as $deal): ?>
								<option value="<?= (int) $deal['id'] ?>"><?= h($deal['client_name'] . ' · ' . $deal['title']) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><span>Neto</span><input name="net" inputmode="numeric" placeholder="0"></label>
					<label><span>IVA</span><input name="tax" inputmode="numeric" placeholder="0"></label>
					<label><span>Viáticos</span><input name="travel" inputmode="numeric" placeholder="0"></label>
					<label><span>Gastos de operación</span><input name="operations" inputmode="numeric" placeholder="0"></label>
					<label><span>Otros costos</span><input name="other_costs" inputmode="numeric" placeholder="0"></label>
				</div>
				<div class="form-actions"><button class="btn btn-word" type="submit">Registrar compra</button></div>
			</form>
		</section>
	</div>

	<section class="admin-card">
		<h2>Ventas</h2>
		<?php if (!$book['sales']): ?>
			<p class="muted">Todavía no hay facturas de venta.</p>
		<?php else: ?>
			<div class="table-wrap">
				<table class="sheet">
					<thead>
						<tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Proyecto</th><th>Neto</th><th>IVA</th><th>Total</th><th>Pago</th><th></th></tr>
					</thead>
					<tbody>
						<?php foreach ($book['sales'] as $row): ?>
							<tr>
								<td><?= h($row['number']) ?></td>
								<td><?= h(when($row['issued_on'], 'd-m-Y')) ?></td>
								<td><?= h($row['client_name']) ?></td>
								<td><?= h($row['deal_title']) ?></td>
								<td><?= money((int) $row['net']) ?></td>
								<td><?= money((int) $row['tax']) ?></td>
								<td><?= money((int) $row['total']) ?></td>
								<td><?= ($row['status'] ?? '') === 'paid' ? 'Pagada' : 'Pendiente' ?></td>
								<td>
									<?php if (($row['status'] ?? '') !== 'paid'): ?>
										<form method="post" action="<?= h(Http::url('/facturas/venta/' . $row['id'] . '/pagar')) ?>">
											<?= Csrf::field() ?>
											<button class="btn btn-excel" type="submit">Marcar pagada</button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>

	<section class="admin-card">
		<h2>Compras y gastos</h2>
		<?php if (!$book['purchases']): ?>
			<p class="muted">Todavía no hay facturas de compra.</p>
		<?php else: ?>
			<div class="table-wrap">
				<table class="sheet">
					<thead>
						<tr><th>Número</th><th>Fecha</th><th>Proveedor</th><th>Proyecto</th><th>Neto</th><th>IVA</th><th>Viáticos</th><th>Operación</th><th>Otros</th></tr>
					</thead>
					<tbody>
						<?php foreach ($book['purchases'] as $row): ?>
							<tr>
								<td><?= h($row['number']) ?></td>
								<td><?= h(when($row['issued_on'], 'd-m-Y')) ?></td>
								<td><?= h($row['supplier']) ?></td>
								<td><?= h($row['deal_title'] ?: 'General') ?></td>
								<td><?= money((int) $row['net']) ?></td>
								<td><?= money((int) $row['tax']) ?></td>
								<td><?= money((int) $row['travel']) ?></td>
								<td><?= money((int) $row['operations']) ?></td>
								<td><?= money((int) $row['other_costs']) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
