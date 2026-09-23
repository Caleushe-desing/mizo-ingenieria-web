<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$report = $report ?? [];
$vista = $vista ?? 'resumen';
$year = (int) ($report['year'] ?? date('Y'));
$sales = $report['sales'] ?? ['net' => 0, 'tax' => 0, 'total' => 0, 'paid_total' => 0];
$buy = $report['purchases'] ?? ['cost' => 0, 'tax' => 0, 'cash' => 0];
$ob = $report['obligations'] ?? [];
$months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$kinds = ['gasto' => 'Gasto operativo', 'deuda' => 'Deuda', 'compromiso' => 'Compromiso de pago'];
$statuses = ['pending' => 'Pendiente', 'paid' => 'Pagado'];
$tabs = [
	'resumen' => 'Resumen general',
	'iva' => 'Facturación y IVA',
	'gastos' => 'Gastos y deudas',
	'comisiones' => 'Comisiones del equipo',
];
$show = static function (int $value): string {
	$text = $value < 0 ? '−' . money(abs($value)) : money($value);
	return $value < 0 ? '<span class="acct-neg">' . $text . '</span>' : $text;
};
$pct = static function (float $rate): string {
	$text = number_format($rate, 2, ',', '.');
	$text = rtrim(rtrim($text, '0'), ',');
	return $text . '%';
};
$plain = static function (float $rate): string {
	return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
};
$tip = static function (string $text): string {
	return '<span class="tip"><button type="button" class="tip-btn" aria-label="Qué significa">i</button><span class="tip-box" role="tooltip">' . h($text) . '</span></span>';
};
$href = static function (string $tab) use ($year): string {
	return Http::url('/contabilidad?anio=' . $year . '&vista=' . $tab);
};
?>
<div class="admin-page">
	<div class="page-head">
		<div>
			<p class="file-kicker">Contabilidad y administración</p>
			<h1><?= h($tabs[$vista] ?? 'Resumen general') ?></h1>
			<p>El dinero de Mizo, explicado sin jerga. El año que estás viendo es <?= (int) $year ?>.</p>
		</div>
		<form class="acct-filters" method="get" action="<?= h(Http::url('/contabilidad')) ?>">
			<input type="hidden" name="vista" value="<?= h($vista) ?>">
			<label>Año
				<input type="number" name="anio" min="2000" max="2100" value="<?= (int) $year ?>">
			</label>
			<button class="btn btn-word" type="submit">Ver año</button>
		</form>
	</div>

	<nav class="acct-tabs" aria-label="Secciones de contabilidad">
		<?php foreach ($tabs as $key => $label): ?>
			<a class="<?= $vista === $key ? 'is-on' : '' ?>" href="<?= h($href($key)) ?>"><?= h($label) ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ($vista === 'resumen'): ?>
		<div class="admin-kpis">
			<article class="admin-kpi">
				<strong><?= $show((int) $report['gross_profit']) ?></strong>
				<span>Utilidad bruta <?= $tip('Es lo que quedó de las ventas después de pagar lo que costó hacer los proyectos. Se calcula con el neto, sin IVA: facturas de venta menos facturas de compra, viáticos y otros costos del proyecto.') ?></span>
			</article>
			<article class="admin-kpi is-orange">
				<strong><?= $show((int) $report['profit_before_tax']) ?></strong>
				<span>Antes de impuesto <?= $tip('A la utilidad bruta se le restan los gastos operativos que registraste y las comisiones de los ejecutivos. Todavía no se descuenta el impuesto de la empresa.') ?></span>
			</article>
			<article class="admin-kpi">
				<strong><?= money((int) $report['income_tax']) ?></strong>
				<span>Impuesto estimado <?= $tip('Impuesto de Primera Categoría: un porcentaje de la utilidad del año, no de cada factura. Si el año termina en pérdida, aquí queda en cero. Es una reserva para planificar, no la declaración ante el SII.') ?></span>
			</article>
			<article class="admin-kpi is-orange">
				<strong><?= $show((int) $report['profit_after_tax']) ?></strong>
				<span>Después de impuestos <?= $tip('Lo que quedaría para Mizo si se paga el impuesto estimado. Es la utilidad antes de impuesto, menos esa reserva.') ?></span>
			</article>
			<article class="admin-kpi">
				<strong><?= $show((int) $report['cash_balance']) ?></strong>
				<span>Caja realizada <?= $tip('Plata que ya entró o salió del banco. Entra el bruto cobrado de las ventas. Salen las compras registradas y los gastos, deudas o compromisos que marcaste como pagados.') ?></span>
			</article>
			<article class="admin-kpi">
				<strong><?= $show((int) $report['cash_forecast']) ?></strong>
				<span>Caja prevista <?= $tip('La caja realizada, menos lo que todavía hay que pagar: gastos pendientes, deudas, compromisos y comisiones del equipo.') ?></span>
			</article>
		</div>
		<section class="admin-card">
			<h2>Cómo se llega al impuesto <?= $tip('Primera Categoría es el impuesto anual de la empresa sobre su utilidad. No es el IVA. El IVA se ve en la pestaña Facturación y IVA.') ?></h2>
			<table class="sheet">
				<tbody>
					<tr><td>Ventas netas</td><td><?= money((int) $sales['net']) ?></td></tr>
					<tr><td>Costos de compra y proyecto</td><td><?= money((int) $buy['cost']) ?></td></tr>
					<tr><td>Utilidad bruta</td><td><?= $show((int) $report['gross_profit']) ?></td></tr>
					<tr><td>Gastos operativos</td><td><?= money((int) ($ob['expense_net'] ?? 0)) ?></td></tr>
					<tr><td>Comisiones del equipo</td><td><?= money((int) $report['commissions_total']) ?></td></tr>
					<tr><td>Utilidad antes de impuesto</td><td><?= $show((int) $report['profit_before_tax']) ?></td></tr>
					<tr><td>Impuesto (<?= h($pct((float) $report['tax_rate'])) ?>)</td><td><?= money((int) $report['income_tax']) ?></td></tr>
					<tr><td>Utilidad después de impuestos</td><td><?= $show((int) $report['profit_after_tax']) ?></td></tr>
				</tbody>
			</table>
			<form class="form" method="post" action="<?= h(Http::url('/contabilidad/tasa')) ?>" style="margin-top:12px">
				<?= Csrf::field() ?>
				<input type="hidden" name="anio" value="<?= (int) $year ?>">
				<input type="hidden" name="vista" value="resumen">
				<label><span>Porcentaje de Primera Categoría <?= $tip('En Chile muchas empresas usan 27%. Si Mizo está en otro régimen, cambia este número. Solo afecta la estimación de esta pantalla.') ?></span>
					<input name="income_tax_rate" inputmode="decimal" value="<?= h($plain((float) $report['tax_rate'])) ?>">
				</label>
				<div class="form-actions"><button class="btn btn-word" type="submit">Guardar porcentaje</button></div>
			</form>
		</section>
	<?php elseif ($vista === 'iva'): ?>
		<div class="admin-kpis">
			<article class="admin-kpi">
				<strong><?= money((int) $sales['net']) ?></strong>
				<span>Neto vendido <?= $tip('El precio sin IVA. Este número sirve para saber si el trabajo dejó ganancia. No es lo que entra completo al banco.') ?></span>
			</article>
			<article class="admin-kpi is-orange">
				<strong><?= money((int) $sales['tax']) ?></strong>
				<span>IVA débito <?= $tip('El IVA que cobraste en las facturas de venta. No es plata de Mizo: es un monto que después se declara en el F29.') ?></span>
			</article>
			<article class="admin-kpi">
				<strong><?= money((int) $sales['total']) ?></strong>
				<span>Bruto facturado <?= $tip('Neto más IVA. Es el total de la factura, lo que el cliente debe pagar y lo que, al cobrarse, entra a la cuenta bancaria.') ?></span>
			</article>
			<article class="admin-kpi is-orange">
				<strong><?= money((int) $sales['paid_total']) ?></strong>
				<span>Ya cobrado <?= $tip('Facturas de venta marcadas como pagadas. Solo esta parte ya está en el banco. El resto sigue por cobrar.') ?></span>
			</article>
			<article class="admin-kpi">
				<strong><?= money((int) $report['vat_credit']) ?></strong>
				<span>IVA crédito <?= $tip('El IVA de las facturas de compra y de los gastos que registraste. Se descuenta del IVA de las ventas en la previsión del F29.') ?></span>
			</article>
			<article class="admin-kpi">
				<strong><?= $show((int) $report['vat_due']) ?></strong>
				<span><?= (int) $report['vat_due'] >= 0 ? 'IVA a pagar' : 'Remanente a favor' ?> <?= $tip((int) $report['vat_due'] >= 0
					? 'IVA de las ventas menos IVA de compras y gastos. Si es positivo, es lo que habría que enterar en el F29 de este año, sumando los meses.'
					: 'Remanente: el IVA de compras y gastos fue mayor que el de las ventas. Queda un saldo a favor, que en la práctica se arrastra al período siguiente. No es una ganancia.') ?></span>
			</article>
		</div>
		<p class="acct-note">Por cobrar este año: <?= money((int) $sales['total'] - (int) $sales['paid_total']) ?>. Las compras registradas salen de caja por <?= money((int) $buy['cash']) ?>, neto más IVA y costos extras.</p>
		<section class="admin-card">
			<h2>Previsión de IVA por mes <?= $tip('Cada fila imita la idea del F29 de ese mes: IVA cobrado en ventas menos IVA de compras y gastos. No reemplaza el formulario declarado en el SII.') ?></h2>
			<div class="acct-scroll">
				<table class="sheet">
					<thead>
						<tr><th>Mes</th><th>IVA débito</th><th>IVA crédito</th><th>A pagar o a favor</th></tr>
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
	<?php elseif ($vista === 'gastos'): ?>
		<section class="admin-card">
			<h2>Registrar un movimiento</h2>
			<form class="form" method="post" action="<?= h(Http::url('/contabilidad/obligaciones')) ?>">
				<?= Csrf::field() ?>
				<input type="hidden" name="anio" value="<?= (int) $year ?>">
				<input type="hidden" name="vista" value="gastos">
				<div class="grid-2">
					<label><span>Tipo <?= $tip('Gasto: baja la utilidad del año, por ejemplo arriendo o sueldos externos. Deuda: plata que Mizo debe y no se resta de nuevo como gasto. Compromiso: un pago futuro que todavía no es un gasto, pero sí se reserva en la caja prevista.') ?></span>
						<select name="kind" required>
							<?php foreach ($kinds as $key => $label): ?>
								<option value="<?= h($key) ?>"><?= h($label) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><span>Estado <?= $tip('Pendiente significa que todavía no sale del banco. Pagado significa que ya se giró la plata.') ?></span>
						<select name="status">
							<option value="pending">Pendiente</option>
							<option value="paid">Pagado</option>
						</select>
					</label>
					<label><span>Concepto</span><input name="concept" required maxlength="160"></label>
					<label><span>Fecha</span><input type="date" name="issued_on" required value="<?= h(date('Y-m-d')) ?>"></label>
					<label><span>Vence</span><input type="date" name="due_on"></label>
					<label><span>Neto <?= $tip('El monto sin IVA. En un gasto, este valor es el que baja la utilidad.') ?></span><input name="net" inputmode="numeric" placeholder="0"></label>
					<label><span>IVA <?= $tip('Si el documento trae IVA, anótalo aquí. Suma al crédito fiscal de la pestaña Facturación y IVA. Si no hay IVA, déjalo en cero.') ?></span><input name="tax" inputmode="numeric" placeholder="0"></label>
					<label><span>Total a pagar <?= $tip('Lo que sale del banco cuando el movimiento está pagado: normalmente neto más IVA.') ?></span><input name="total" inputmode="numeric" placeholder="Neto + IVA"></label>
				</div>
				<label><span>Nota</span><input name="notes" maxlength="500"></label>
				<div class="form-actions"><button class="btn btn-word" type="submit">Registrar</button></div>
			</form>
		</section>
		<section class="admin-card">
			<h2>Movimientos de <?= (int) $year ?></h2>
			<?php if ((int) ($report['open_debt_other_years'] ?? 0) > 0): ?>
				<p class="acct-note">Además hay <?= money((int) $report['open_debt_other_years']) ?> en deudas pendientes de otros años. Siguen descontadas en la caja prevista.</p>
			<?php endif; ?>
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
											<input type="hidden" name="vista" value="gastos">
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
										<input type="hidden" name="vista" value="gastos">
										<button class="btn-text" type="submit">Eliminar</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php else: ?>
		<section class="admin-card">
			<h2>Porcentaje de cada ejecutivo <?= $tip('Comisión por facturación: un porcentaje del neto de las facturas de venta de los proyectos asignados a esa persona. No se calcula sobre el IVA. El monto se resta en el resumen, antes del impuesto de la empresa.') ?></h2>
			<form method="post" action="<?= h(Http::url('/contabilidad/comisiones')) ?>">
				<?= Csrf::field() ?>
				<input type="hidden" name="anio" value="<?= (int) $year ?>">
				<input type="hidden" name="vista" value="comisiones">
				<div class="acct-scroll">
					<table class="sheet">
						<thead>
							<tr><th>Persona</th><th>%</th><th>Neto facturado</th><th>Comisión a pagar</th></tr>
						</thead>
						<tbody>
							<?php foreach ($report['executives'] as $person): ?>
								<tr>
									<td><?= h($person['name']) ?><?= $person['role'] === 'admin' ? ' · Admin' : '' ?></td>
									<td><input name="rate[<?= (int) $person['id'] ?>]" inputmode="decimal" value="<?= h($plain((float) $person['rate'])) ?>" style="width:72px"></td>
									<td><?= money((int) $person['net']) ?></td>
									<td><?= money((int) $person['commission']) ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="form-actions"><button class="btn btn-word" type="submit">Guardar comisiones</button></div>
			</form>
			<p class="acct-note">Total de comisiones del año: <?= money((int) $report['commissions_total']) ?>. Ese total ya está descontado en Resumen general, junto con los gastos, antes de calcular el impuesto.</p>
		</section>
		<section class="admin-card">
			<h2>Cruce con los proyectos <?= $tip('Cada fila toma las facturas del proyecto en este año. La comisión es el neto por el porcentaje del ejecutivo asignado. El margen es lo que queda después de costos y de esa comisión.') ?></h2>
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
							<tr><td colspan="10">Este año todavía no hay facturas asociadas a un proyecto.</td></tr>
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
		</section>
	<?php endif; ?>
</div>
