<?php
use MizoCrm\Auth;
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;

$deal = $deal ?? null;
$client = $client ?? null;
$quote = $quote ?? null;
$status = $status ?? 'pendiente';
$history = $history ?? [];
$owner = $owner ?? null;
$team = $team ?? [];
$activity = $activity ?? [];
$quoteLocked = in_array($status, ['ganada', 'perdida'], true) || (($quote['status'] ?? '') === 'aceptada');
$lost = $status === 'perdida';
$action = $deal ? Http::url('/t/' . $deal['id']) : Http::url('/nueva');
$defaultNotes = 'Validez 15 días. Precios en pesos chilenos, neto + IVA. Instalación sujeta a visita técnica.';
$jobStatus = $deal['job_status'] ?? 'consulta';
$tab = $deal ? 'actividad' : 'datos';
?>
<div class="deal">
	<header class="deal-hd">
		<div class="deal-title">
			<h1><?= $deal ? h($client['name'] ?? 'Cliente') : 'Nuevo caso' ?></h1>
			<p>
				<?php if ($quote): ?><?= h($quote['number']) ?> · <?php endif; ?>
				<?php if ($owner): ?>A cargo de <?= h($owner['name']) ?><?php elseif ($deal): ?>Sin asignar<?php else: ?>Completa los datos y el presupuesto<?php endif; ?>
			</p>
		</div>
		<div class="deal-hd-meta">
			<?php if ($deal): ?>
				<span class="badge <?= h($status) ?>"><?= h(work_status_label($status)) ?></span>
				<span class="badge"><?= h(Config::jobStatuses()[$jobStatus] ?? 'Consulta') ?></span>
			<?php endif; ?>
			<?php if ($deal && Auth::isAdmin() && $team): ?>
				<form method="post" action="<?= h(Http::url('/t/' . $deal['id'] . '/asignar')) ?>" class="inline-form">
					<?= Csrf::field() ?>
					<select name="owner_id" onchange="this.form.submit()">
						<option value="0">Sin asignar</option>
						<?php foreach ($team as $member): ?>
							<option value="<?= (int) $member['id'] ?>" <?= (int) ($deal['owner_id'] ?? 0) === (int) $member['id'] ? 'selected' : '' ?>><?= h($member['name']) ?></option>
						<?php endforeach; ?>
					</select>
				</form>
			<?php endif; ?>
		</div>
	</header>

	<?php if ($deal): ?>
		<nav class="tabs">
			<button type="button" class="is-on" data-tab="actividad">Actividad</button>
			<button type="button" data-tab="presupuesto">Presupuesto</button>
			<button type="button" data-tab="datos">Cliente y faena</button>
		</nav>
	<?php endif; ?>

	<div class="deal-body <?= $deal ? '' : 'is-new' ?>">
		<?php if ($deal): ?>
			<section class="deal-feed" data-panel="actividad">
				<form class="composer" method="post" action="<?= h(Http::url('/t/' . $deal['id'] . '/nota')) ?>">
					<?= Csrf::field() ?>
					<div class="composer-top">
						<select name="kind">
							<?php foreach (Config::activityKinds() as $key => $label): ?>
								<option value="<?= h($key) ?>"><?= h($label) ?></option>
							<?php endforeach; ?>
						</select>
						<button class="btn" type="submit">Registrar</button>
					</div>
					<textarea name="note" rows="3" placeholder="Llamé, visitamos, falta material…" required></textarea>
				</form>
				<?php if (empty($activity)): ?>
					<p class="empty-feed">El historial del cliente aparece aquí. Cada llamada, visita o instalación debe quedar registrada.</p>
				<?php else: ?>
					<ol class="feed">
						<?php foreach ($activity as $event): ?>
							<li>
								<span class="log-kind kind-<?= h($event['type']) ?>"><?= h(activity_label((string) $event['type'])) ?></span>
								<div>
									<p><?= h($event['message']) ?></p>
									<small><?= h($event['user_name'] ?: 'Sitio web') ?> · <?= when($event['created_at']) ?></small>
								</div>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
				<?php if ($history): ?>
					<div class="other-jobs">
						<h2>Otros trabajos</h2>
						<?php foreach ($history as $other): ?>
							<a href="<?= h(Http::url('/t/' . $other['id'])) ?>"><?= h($other['title']) ?> · <?= when($other['updated_at'], 'd-m-Y') ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<form class="deal-panel form" method="post" action="<?= h($action) ?>" <?= $deal ? 'data-panel="presupuesto" hidden' : '' ?>>
			<?= Csrf::field() ?>
			<input type="hidden" name="title" value="<?= h($deal['title'] ?? '') ?>">
			<?php if ($deal): ?>
				<input type="hidden" name="name" value="<?= h($client['name'] ?? '') ?>">
				<input type="hidden" name="email" value="<?= h($client['email'] ?? '') ?>">
				<input type="hidden" name="phone" value="<?= h($client['phone'] ?? '') ?>">
				<input type="hidden" name="rut" value="<?= h($client['rut'] ?? '') ?>">
				<input type="hidden" name="city" value="<?= h($client['city'] ?? '') ?>">
				<input type="hidden" name="need" value="<?= h($deal['notes'] ?? '') ?>">
				<input type="hidden" name="service" value="<?= h($deal['service'] ?? '') ?>">
				<input type="hidden" name="job_status" value="<?= h($jobStatus) ?>">
				<input type="hidden" name="site_address" value="<?= h($deal['site_address'] ?? '') ?>">
				<input type="hidden" name="visit_at" value="<?= h($deal['visit_at'] ?? '') ?>">
			<?php endif; ?>

			<?php if (!$deal): ?>
				<div class="panel-block">
					<h2>Cliente</h2>
					<div class="form-row">
						<label><span>Nombre</span><input name="name" required></label>
						<label><span>Correo</span><input name="email" type="email"></label>
					</div>
					<div class="form-row">
						<label><span>Teléfono</span><input name="phone"></label>
						<label>
							<span>Servicio</span>
							<select name="service" data-service>
								<?php foreach (Config::services() as $key => $label): ?>
									<option value="<?= h($key) ?>" <?= ($service ?? '') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
					<label><span>Qué necesita</span><textarea name="need" rows="2" placeholder="Sala, cámaras, fecha…"></textarea></label>
				</div>
			<?php endif; ?>

			<div class="panel-block">
				<div class="panel-hd">
					<h2>Partidas</h2>
					<?php if (!$quoteLocked): ?>
						<button class="btn-ghost" type="button" data-add-item>Agregar</button>
					<?php endif; ?>
				</div>
				<table class="sheet quote-sheet">
					<thead>
						<tr>
							<th>Descripción</th>
							<th>Cant.</th>
							<th>Un.</th>
							<th>Neto</th>
							<th>Total</th>
							<th></th>
						</tr>
					</thead>
					<tbody data-items>
					<?php foreach ($items as $item): ?>
						<tr data-item-row>
							<td><input name="item_description[]" value="<?= h($item['description'] ?? '') ?>" placeholder="Equipo, instalación…" <?= $quoteLocked ? 'readonly' : '' ?>></td>
							<td><input name="item_quantity[]" value="<?= h((string) ($item['quantity'] ?? 1)) ?>" <?= $quoteLocked ? 'readonly' : '' ?>></td>
							<td><input name="item_unit[]" value="<?= h($item['unit'] ?? 'un') ?>" <?= $quoteLocked ? 'readonly' : '' ?>></td>
							<td><input name="item_price[]" value="<?= h((string) ($item['unit_price'] ?? '')) ?>" <?= $quoteLocked ? 'readonly' : '' ?>></td>
							<td data-line><?= money((int) ($item['total'] ?? 0)) ?></td>
							<td><?php if (!$quoteLocked): ?><button class="btn-ghost" type="button" data-remove>×</button><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="totals-line">
					<span>Neto <b data-neto><?= money((int) ($quote['subtotal'] ?? 0)) ?></b></span>
					<span>IVA 19% <b data-iva><?= money((int) ($quote['tax'] ?? 0)) ?></b></span>
					<span>Total <b data-total><?= money((int) ($quote['total'] ?? 0)) ?></b></span>
				</div>
				<div class="form-row">
					<label><span>Válida hasta</span><input name="valid_until" type="date" value="<?= h($quote['valid_until'] ?? date('Y-m-d', strtotime('+15 days'))) ?>" <?= $quoteLocked ? 'readonly' : '' ?>></label>
					<label><span>Presentación</span><input name="intro" value="<?= h($quote['intro'] ?? '') ?>" <?= $quoteLocked ? 'readonly' : '' ?>></label>
				</div>
				<label><span>Notas al cliente</span><textarea name="notes" rows="2" <?= $quoteLocked ? 'readonly' : '' ?>><?= h($quote['notes'] ?? $defaultNotes) ?></textarea></label>
			</div>

			<div class="sticky-actions">
				<button class="btn-ghost" type="submit" name="intent" value="save"><?= $deal ? 'Guardar' : 'Crear caso' ?></button>
				<?php if (!$quoteLocked): ?>
					<button class="btn" type="submit" name="intent" value="send">Enviar cotización</button>
				<?php endif; ?>
				<?php if ($quote && $deal): ?>
					<a class="btn-ghost" href="<?= h(Http::url('/t/' . $deal['id'] . '/preview')) ?>">Vista cliente</a>
				<?php endif; ?>
			</div>
		</form>

		<?php if ($deal): ?>
			<form class="deal-panel form" method="post" action="<?= h($action) ?>" data-panel="datos" hidden>
				<?= Csrf::field() ?>
				<input type="hidden" name="intent" value="save">
				<input type="hidden" name="title" value="<?= h($deal['title'] ?? '') ?>">
				<div class="panel-block">
					<h2>Cliente</h2>
					<div class="form-row">
						<label><span>Nombre</span><input name="name" value="<?= h($client['name'] ?? '') ?>" required></label>
						<label><span>Correo</span><input name="email" type="email" value="<?= h($client['email'] ?? '') ?>"></label>
					</div>
					<div class="form-row">
						<label><span>Teléfono</span><input name="phone" value="<?= h($client['phone'] ?? '') ?>"></label>
						<label><span>RUT</span><input name="rut" value="<?= h($client['rut'] ?? '') ?>"></label>
					</div>
					<div class="form-row">
						<label><span>Ciudad</span><input name="city" value="<?= h($client['city'] ?? '') ?>"></label>
						<label>
							<span>Servicio</span>
							<select name="service" data-service>
								<?php foreach (Config::services() as $key => $label): ?>
									<option value="<?= h($key) ?>" <?= ($service ?? '') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
					<label><span>Qué necesita</span><textarea name="need" rows="3"><?= h($deal['notes'] ?? '') ?></textarea></label>
				</div>
				<div class="panel-block">
					<h2>Faena</h2>
					<div class="form-row">
						<label>
							<span>Estado del trabajo</span>
							<select name="job_status">
								<?php foreach (Config::jobStatuses() as $key => $label): ?>
									<option value="<?= h($key) ?>" <?= $jobStatus === $key ? 'selected' : '' ?>><?= h($label) ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label><span>Visita</span><input name="visit_at" type="date" value="<?= h($deal['visit_at'] ?? '') ?>"></label>
					</div>
					<label><span>Dirección</span><textarea name="site_address" rows="2"><?= h($deal['site_address'] ?? '') ?></textarea></label>
				</div>
				<?php if (!$lost): ?>
					<div class="close-row">
						<button class="btn-green" form="close-form" type="submit" name="outcome" value="ganada">Ganada</button>
						<button class="btn-ghost" form="close-form" type="submit" name="outcome" value="entregado">Entregado</button>
						<button class="btn-ghost btn-danger" form="close-form" type="submit" name="outcome" value="perdida">Perdida</button>
					</div>
				<?php endif; ?>
				<div class="sticky-actions">
					<button class="btn" type="submit">Guardar ficha</button>
				</div>
			</form>
			<?php if (!$lost): ?>
				<form id="close-form" method="post" action="<?= h(Http::url('/t/' . $deal['id'] . '/cerrar')) ?>">
					<?= Csrf::field() ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
