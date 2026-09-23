<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$report = $report ?? [];
$year = (int) ($report['year'] ?? date('Y'));
$sales = $report['sales'] ?? ['net' => 0, 'tax' => 0, 'total' => 0, 'paid_total' => 0];
$buy = $report['purchases'] ?? ['cost' => 0, 'tax' => 0, 'cash' => 0, 'extras' => 0];
$ob = $report['obligations'] ?? [];
$months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$kinds = ['gasto' => 'Gasto operativo', 'deuda' => 'Deuda', 'compromiso' => 'Compromiso de pago'];
$statuses = ['pending' => 'Pendiente', 'paid' => 'Pagado'];
$show = static function (int $value): string {
	$text = $value < 0 ? '−' . money(abs($value)) : money($value);
	return $value < 0 ? '<span class="acct-neg">' . $text . '</span>' : $text;
};
$pct = static function (float $rate): string {
	$text = number_format($rate, 2, ',', '.');
	$text = rtrim(rtrim($text, '0'), ',');
	return $text . '%';
};
?>
<div class="admin-page">
	<div class="page-head">
		<div>
			<p class="file-kicker">Contabilidad y administración</p>
			<h1>Balance <?= (int) $year ?></h1>
			<p>El neto alimenta el margen. El bruto con IVA alimenta la caja y la previsión del F29.</p>
		</div>
		<form class="acct-filters" method="get" action="<?= h(Http::url('/contabilidad')) ?>">
			<label>Año
				<input type="number" name="anio" min="2000" max="2100" value="<?= (int) $year ?>">
			</label>
			<button class="btn btn-word" type="submit">Ver año</button>
		</form>
	</div>

	<div class="admin-kpis">
		<article class="admin-kpi">
			<strong><?= money((int) $sales['net']) ?></strong>
			<span>Facturación neta</span>
		</article>
		<article class="admin-kpi">
			<strong><?= money((int) $sales['tax']) ?></strong>
			<span>IVA débito de ventas</span>
		</article>
		<article class="admin-kpi is-orange">
			<strong><?= money((int) $sales['total']) ?></strong>
			<span>Facturación bruta, con IVA</span>
		</article>
		<article class="admin-kpi">
			<strong><?= money((int) $sales['paid_total']) ?></strong>
			<span>Ya cobrado en el banco</span>
		</article>
		<article class="admin-kpi">
			<strong><?= money((int) $sales['total'] - (int) $sales['paid_total']) ?></strong>
			<span>Por cobrar</span>
		</article>
	</div>

	<div class="admin-kpis">
		<article class="admin-kpi">
			<strong><?= $show((int) $report['vat_due']) ?></strong>
			<span>IVA estimado del año <?= (int) $report['vat_due'] >= 0 ? 'a pagar' : 'a favor' ?></span>
		</article>
		<article class="admin-kpi">
			<strong><?= money((int) $report['vat_credit']) ?></strong>
			<span>IVA crédito (compras y gastos)</span>
		</article>
		<article class="admin-kpi is-orange">
			<strong><?= $show((int) $report['cash_balance']) ?></strong>
			<span>Saldo de caja realizado</span>
		</article>
		<article class="admin-kpi">
			<strong><?= money((int) $report['cash_out']) ?></strong>
			<span>Salidas de caja del año</span>
		</article>
		<article class="admin-kpi">
			<strong><?= $show((int) $report['cash_forecast']) ?></strong>
			<span>Caja prevista, restando lo pendiente</span>
		</article>
	</div>
	<p class="acct-note">La caja realizada suma lo cobrado de facturas de venta y resta compras registradas, gastos pagados, deudas pagadas y compromisos pagados. La previsión además descuenta comisiones, gastos, deudas y compromisos que siguen pendientes<?= (int) ($report['open_debt_other_years'] ?? 0) > 0 ? ', más ' . money((int) $report['open_debt_other_years']) . ' de deudas pendientes de otros años' : '' ?>.</p>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Impuesto de Primera Categoría</h2>
			<table class="sheet">
				<tbody>
					<tr><td>Utilidad bruta (neto vendido − costos de compra)</td><td><?= $show((int) $report['gross_profit']) ?></td></tr>
					<tr><td>Gastos operativos</td><td><?= money((int) ($ob['expense_net'] ?? 0)) ?></td></tr>
					<tr><td>Comisiones de ejecutivos</td><td><?= money((int) $report['commissions_total']) ?></td></tr>
					<tr><td>Utilidad antes de impuesto</td><td><?= $show((int) $report['profit_before_tax']) ?></td></tr>
					<tr><td>Impuesto estimado (<?= h($pct((float) $report['tax_rate'])) ?>)</td><td><?= money((int) $report['income_tax']) ?></td></tr>
					<tr><td>Utilidad después de impuestos</td><td><?= $show((int) $report['profit_after_tax']) ?></td></tr>
				</tbody>
			</table>
			<p class="acct-note">Si el año cierra con pérdida, la estimación de impuesto queda en cero. Es una reserva interna, no la declaración de renta.</p>
			<form class="form" method="post" action="<?= h(Http::url('/contabilidad/tasa')) ?>" style="margin-top:12px">
				<?= Csrf::field() ?>
				<input type="hidden" name="anio" value="<?= (int) $year ?>">
				<label><span>Porcentaje anual sobre la utilidad</span>
					<input name="income_tax_rate" inputmode="decimal" value="<?= h(rtrim(rtrim(number_format((float) $report['tax_rate'], 2, '.', ''), '0'), '.')) ?>">
				</label>
				<div class="form-actions"><button class="btn btn-word" type="submit">Guardar porcentaje</button></div>
			</form>
		</section>

		<section class="admin-card">
			<h2>Comisión por ejecutivo</h2>
			<p class="acct-note">El porcentaje se aplica a la facturación neta de los proyectos asignados a esa persona, emitida en <?= (int) $year ?>.</p>
			<form method="post" action="<?= h(Http::url('/contabilidad/comisiones')) ?>">
				<?= Csrf::field() ?>
				<input type="hidden" name="anio" value="<?= (int) $year ?>">
				<div class="acct-scroll">
					<table class="sheet">
						<thead>
							<tr><th>Persona</th><th>%</th><th>Neto facturado</th><th>A pagar</th></tr>
						</thead>
						<tbody>
							<?php foreach ($report['executives'] as $person): ?>
								<tr>
									<td><?= h($person['name']) ?><?= $person['role'] === 'admin' ? ' · Admin' : '' ?></td>
									<td><input name="rate[<?= (int) $person['id'] ?>]" inputmode="decimal" value="<?= h(rtrim(rtrim(number_format((float) $person['rate'], 2, '.', ''), '0'), '.')) ?>" style="width:72px"></td>
									<td><?= money((int) $person['net']) ?></td>
									<td><?= money((int) $person['commission']) ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="form-actions"><button class="btn btn-word" type="submit">Guardar comisiones</button></div>
			</form>
		</section>
	</div>

	<section class="admin-card">
		<h2>Balance por proyecto</h2>
		<div class="acct-scroll">
			<table class="sheet">
				<thead>
					<tr>
						<th>Proyecto</th>
						<th>Cliente</th>
						<th>Ejecutivo</th>
						<th>Neto</th>
						<th>IVA</th>
						<th>Bruto</th>
						<th>Cobrado</th>
						<th>Costos</th>
						<th>Comisión</th>
						<th>Margen</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!$report['projects']): ?>
						<tr><td colspan="10">Este año todavía no hay facturas de venta ni de compra asociadas a un proyecto.</td></tr>
					<?php endif; ?>
					<?php foreach ($report['projects'] as $project): ?>
						<tr>
							<td><?= h($project['title']) ?></td>
							<td><?= h($project['client']) ?></td>
							<td><?= h($project['owner']) ?> · <?= h($pct((float) $project['rate'])) ?></td>
							<td><?= money((int) $project['net']) ?></td>
							<td><?= money((int) $project['tax']) ?></td>
							<td><?= money((int) $project['total']) ?></td>
							<td><?= money((int) $project['paid_total']) ?></td>
							<td><?= money((int) $project['cost']) ?></td>
							<td><?= money((int) $project['commission']) ?></td>
							<td><?= $show((int) $project['margin']) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="acct-note">El margen del proyecto es el neto facturado, menos los costos de compra de ese proyecto y menos la comisión. Las compras sin proyecto quedan en el balance de la empresa, no en esta tabla.</p>
	</section>

	<section class="admin-card">
		<h2>Previsión de IVA por mes</h2>
		<div class="acct-scroll">
			<table class="sheet">
				<thead>
					<tr><th>Mes</th><th>IVA débito</th><th>IVA crédito</th><th>A pagar / a favor</th></tr>
				</thead>
				<tbody>
					<?php foreach ($report['months'] as $month): ?>
						<tr>
							<td><?= h($months[(int) $month['month']] ?? '') ?></td>
							<td><?= money((int) $month['debit']) ?></td>
							<td><?= money((int) $month['credit']) ?></td>
							<td><?= $show((int) $month['due']) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="admin-card">
		<h2>Gastos, deudas y compromisos</h2>
		<form class="form" method="post" action="<?= h(Http::url('/contabilidad/obligaciones')) ?>">
			<?= Csrf::field() ?>
			<input type="hidden" name="anio" value="<?= (int) $year ?>">
			<div class="grid-2">
				<label><span>Tipo</span>
					<select name="kind" required>
						<?php foreach ($kinds as $key => $label): ?>
							<option value="<?= h($key) ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><span>Estado</span>
					<select name="status">
						<option value="pending">Pendiente</option>
						<option value="paid">Pagado</option>
					</select>
				</label>
				<label><span>Concepto</span><input name="concept" required maxlength="160"></label>
				<label><span>Fecha</span><input type="date" name="issued_on" required value="<?= h(date('Y-m-d')) ?>"></label>
				<label><span>Vence</span><input type="date" name="due_on"></label>
				<label><span>Neto</span><input name="net" inputmode="numeric" placeholder="0"></label>
				<label><span>IVA</span><input name="tax" inputmode="numeric" placeholder="0"></label>
				<label><span>Total a pagar</span><input name="total" inputmode="numeric" placeholder="Neto + IVA"></label>
			</div>
			<label><span>Nota</span><input name="notes" maxlength="500"></label>
			<div class="form-actions"><button class="btn btn-word" type="submit">Registrar</button></div>
		</form>
		<p class="acct-note">Un gasto baja la utilidad y, si tiene IVA, suma crédito fiscal. Una deuda o un compromiso no baja la utilidad: entra a la caja solo cuando queda marcado como pagado, y mientras está pendiente resta en la previsión.</p>

		<div class="acct-scroll">
			<table class="sheet">
				<thead>
					<tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Neto</th><th>IVA</th><th>Total</th><th>Estado</th><th></th></tr>
				</thead>
				<tbody>
					<?php if (!$report['rows']): ?>
						<tr><td colspan="8">No hay movimientos registrados en <?= (int) $year ?>.</td></tr>
					<?php endif; ?>
					<?php foreach ($report['rows'] as $row): ?>
						<tr>
							<td><?= h(when((string) $row['issued_on'], 'd-m-Y')) ?></td>
							<td><?= h($kinds[$row['kind']] ?? $row['kind']) ?></td>
							<td><?= h($row['concept']) ?><?= trim((string) $row['notes']) !== '' ? '<br><small>' . h($row['notes']) . '</small>' : '' ?></td>
							<td><?= money((int) $row['net']) ?></td>
							<td><?= money((int) $row['tax']) ?></td>
							<td><?= money((int) $row['total']) ?></td>
							<td><?= h($statuses[$row['status']] ?? $row['status']) ?><?= $row['due_on'] ? '<br><small>Vence ' . h(when((string) $row['due_on'], 'd-m-Y')) . '</small>' : '' ?></td>
							<td>
								<details class="invoice-edit">
									<summary>Editar</summary>
									<form method="post" action="<?= h(Http::url('/contabilidad/obligaciones/' . $row['id'])) ?>">
										<?= Csrf::field() ?>
										<input type="hidden" name="anio" value="<?= (int) $year ?>">
										<select name="kind">
											<?php foreach ($kinds as $key => $label): ?>
												<option value="<?= h($key) ?>" <?= $row['kind'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
											<?php endforeach; ?>
										</select>
										<input name="concept" value="<?= h($row['concept']) ?>" required maxlength="160">
										<input type="date" name="issued_on" value="<?= h(substr((string) $row['issued_on'], 0, 10)) ?>" required>
										<input type="date" name="due_on" value="<?= h(substr((string) ($row['due_on'] ?? ''), 0, 10)) ?>">
										<input name="net" value="<?= (int) $row['net'] ?>" inputmode="numeric">
										<input name="tax" value="<?= (int) $row['tax'] ?>" inputmode="numeric">
										<input name="total" value="<?= (int) $row['total'] ?>" inputmode="numeric">
										<select name="status">
											<?php foreach ($statuses as $key => $label): ?>
												<option value="<?= h($key) ?>" <?= $row['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
											<?php endforeach; ?>
										</select>
										<input name="notes" value="<?= h($row['notes']) ?>" maxlength="500">
										<button class="btn btn-word" type="submit">Guardar</button>
									</form>
								</details>
								<form method="post" action="<?= h(Http::url('/contabilidad/obligaciones/' . $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar este movimiento?');">
									<?= Csrf::field() ?>
									<input type="hidden" name="anio" value="<?= (int) $year ?>">
									<button class="btn-text" type="submit">Eliminar</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
