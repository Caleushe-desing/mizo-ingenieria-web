<?php
use MizoCrm\Auth;
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;

$stages = $stages ?? Config::stages();
$services = $services ?? Config::services();
$cards = $cards ?? [];
$team = $team ?? [];
$ownerFilter = (int) ($ownerFilter ?? 0);
$execAllow = $execAllow ?? [];
$grouped = [];
foreach (array_keys($stages) as $key) {
	$grouped[$key] = [];
}
foreach ($cards as $card) {
	$stage = (string) ($card['stage'] ?? 'nuevo');
	if (!isset($grouped[$stage])) {
		$fallback = array_key_first($grouped);
		if ($fallback === null) {
			continue;
		}
		$stage = (string) $fallback;
	}
	$grouped[$stage][] = $card;
}
$stageColors = $stageColors ?? [];
$invoiceCards = $invoiceCards ?? [];
foreach ($invoiceCards as $invoice) {
	$stage = (string) ($invoice['stage'] ?? '');
	if (!isset($grouped[$stage])) {
		$fallback = array_key_first($grouped);
		if ($fallback === null) {
			continue;
		}
		$stage = (string) $fallback;
	}
	$grouped[$stage][] = $invoice;
}
?>
<div class="kb" data-board data-move="<?= h(Http::url('/tablero/mover')) ?>" data-csrf="<?= h(Csrf::token()) ?>"<?= $execAllow ? ' data-exec-allow="' . h(implode(' ', $execAllow)) . '"' : '' ?>>
	<div class="kb-bar">
		<div>
			<h1>Tablero comercial</h1>
			<p>Cada proyecto sigue en su hilera. Cada factura de venta es una tarjeta aparte, en Proyecto facturado o Factura pagada.</p>
		</div>
		<div class="kb-filters">
			<input type="search" data-kb-q placeholder="Buscar proyecto o cliente" autocomplete="off">
			<select data-kb-service aria-label="Servicio">
				<option value="">Todos los servicios</option>
				<?php foreach ($services as $key => $label): ?>
					<option value="<?= h($key) ?>"><?= h($label) ?></option>
				<?php endforeach; ?>
			</select>
			<?php if (Auth::isAdmin()): ?>
				<select data-kb-owner aria-label="Ejecutivo">
					<option value="">Todos los ejecutivos</option>
					<?php foreach ($team as $member): ?>
						<option value="<?= (int) $member['id'] ?>" <?= $ownerFilter === (int) $member['id'] ? 'selected' : '' ?>><?= h($member['name']) ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<select data-kb-priority aria-label="Prioridad">
				<option value="">Cualquier prioridad</option>
				<option value="alta">Prioridad alta</option>
				<option value="media">Prioridad media</option>
				<option value="baja">Prioridad baja</option>
			</select>
			<select data-kb-when aria-label="Actividad">
				<option value="">Cualquier fecha</option>
				<option value="7">Actividad en 7 días</option>
				<option value="30">Actividad en 30 días</option>
				<option value="stale">Sin movimiento +7 días</option>
			</select>
			<a class="kb-list-link" href="<?= h(Http::url('/clientes')) ?>">Ver lista</a>
		</div>
	</div>

	<?php if (\MizoCrm\Auth::isAdmin()): ?>
	<?php require __DIR__ . '/columns.php'; ?>
	<?php endif; ?>
	<div class="kb-columns">
		<?php foreach ($stages as $key => $label): ?>
			<section class="kb-col" data-stage="<?= h($key) ?>" style="--kb-accent: <?= h($stageColors[$key] ?? '#1c9bd8') ?>">
				<header>
					<strong><?= h($label) ?></strong>
					<span data-kb-count><?= count($grouped[$key]) ?></span>
				</header>
				<div class="kb-drop" data-drop="<?= h($key) ?>">
					<?php foreach ($grouped[$key] as $card): ?>
						<?php if (!empty($card['invoice_id'])): ?>
							<?php
							$paid = (string) ($card['status'] ?? '') === 'paid';
							$amount = (int) ($card['total'] ?? 0);
							$stamp = strtotime((string) ($card['issued_on'] ?: $card['created_at'] ?: ''));
							$ageDays = $stamp ? (int) floor((time() - $stamp) / 86400) : 0;
							$service = (string) ($card['service'] ?? 'otro');
							$hay = mb_strtolower(trim(
								($card['number'] ?? '') . ' ' . ($card['name'] ?? '') . ' ' . ($card['deal_title'] ?? '') . ' ' . ($card['rut'] ?? '')
							), 'UTF-8');
							?>
							<article class="kb-card kb-card-invoice<?= $paid ? ' is-paid' : '' ?>"
								draggable="false"
								data-invoice="<?= (int) $card['invoice_id'] ?>"
								data-client="<?= (int) $card['client_id'] ?>"
								data-deal="<?= (int) $card['deal_id'] ?>"
								data-service="<?= h($service) ?>"
								data-owner="<?= (int) ($card['owner_id'] ?? 0) ?>"
								data-priority="<?= $paid ? 'baja' : 'alta' ?>"
								data-age="<?= (int) $ageDays ?>"
								data-hay="<?= h($hay) ?>"
								data-detail="<?= h(Http::url('/tablero/proyecto/' . $card['deal_id'])) ?>">
								<div class="kb-card-top">
									<strong>Factura <?= h($card['number']) ?></strong>
									<em class="kb-pri <?= $paid ? 'kb-pri-baja' : 'kb-pri-alta' ?>"><?= $paid ? 'Pagada' : 'Pendiente' ?></em>
								</div>
								<p class="kb-note"><?= h($card['name']) ?></p>
								<span class="kb-tag kb-tag-otro"><?= h($card['deal_title'] ?: 'Proyecto') ?></span>
								<div class="kb-meta">
									<span><?= $amount > 0 ? money($amount) : 'Sin monto' ?></span>
									<span><?= h($card['owner_name'] ?: 'Sin asignar') ?></span>
								</div>
								<p class="kb-note kb-snippet"><?= $paid ? 'Pagada' : 'Pendiente de pago' ?></p>
							</article>
							<?php continue; ?>
						<?php endif; ?>
						<?php
						$service = (string) ($card['service'] ?? '');
						if ($service === '') {
							$service = 'otro';
						}
						$amount = max((int) ($card['amount'] ?? 0), (int) ($card['quote_total'] ?? 0));
						$stamp = strtotime((string) ($card['last_at'] ?: $card['updated_at'] ?: ''));
						$ageDays = $stamp ? (int) floor((time() - $stamp) / 86400) : 99;
						$priority = ($ageDays >= 7 || $amount >= 2000000) ? 'alta' : ($amount >= 500000 ? 'media' : 'baja');
						$hay = mb_strtolower(trim(
							($card['deal_title'] ?? '') . ' ' . ($card['name'] ?? '') . ' ' . ($card['rut'] ?? '') . ' ' . ($card['city'] ?? '') . ' ' . ($card['quote_number'] ?? '') . ' ' . ($card['quote_revision'] ?? '')
						), 'UTF-8');
						$snippet = trim((string) ($card['last_note'] ?? ''));
						if ($snippet !== '' && function_exists('mb_strimwidth')) {
							$snippet = mb_strimwidth($snippet, 0, 72, '…', 'UTF-8');
						}
						?>
						<article class="kb-card"
							draggable="true"
							data-client="<?= (int) $card['client_id'] ?>"
							data-deal="<?= (int) $card['deal_id'] ?>"
							data-service="<?= h($service) ?>"
							data-owner="<?= (int) ($card['owner_id'] ?? 0) ?>"
							data-priority="<?= h($priority) ?>"
							data-age="<?= (int) $ageDays ?>"
							data-hay="<?= h($hay) ?>"
							data-detail="<?= h(Http::url('/tablero/proyecto/' . $card['deal_id'])) ?>">
							<div class="kb-card-top">
								<strong><?= h($card['deal_title'] ?: 'Proyecto') ?></strong>
								<em class="kb-pri kb-pri-<?= h($priority) ?>"><?= h($priority) ?></em>
							</div>
							<p class="kb-note"><?= h($card['name']) ?></p>
							<?php if (($card['quote_status'] ?? '') === 'rechazada'): ?>
								<span class="kb-reject">Presupuesto no aceptado</span>
							<?php endif; ?>
							<?php if (trim((string) ($card['quote_revision'] ?? '')) !== ''): ?>
								<span class="kb-rev"><?= h($card['quote_revision']) ?></span>
							<?php endif; ?>
							<?php if ($service !== '' && isset($services[$service]) && ($card['deal_id'] ?? null)): ?>
								<span class="kb-tag kb-tag-<?= h($service) ?>"><?= h($services[$service]) ?></span>
							<?php else: ?>
								<span class="kb-tag kb-tag-otro">Sin servicio aún</span>
							<?php endif; ?>
							<div class="kb-meta">
								<span><?= $amount > 0 ? money($amount) : 'Sin monto' ?></span>
								<span><?= h($card['owner_name'] ?: 'Sin asignar') ?></span>
							</div>
							<p class="kb-note kb-snippet"><?= $snippet !== '' ? h($snippet) : 'Sin nota del proyecto' ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
</div>

<aside class="kb-drawer" data-drawer hidden>
	<div class="kb-drawer-backdrop" data-drawer-close></div>
	<div class="kb-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="kb-drawer-title">
		<header>
			<div>
				<p class="kb-kicker" data-drawer-kicker>Negocio</p>
				<h2 id="kb-drawer-title" data-drawer-title>Cliente</h2>
			</div>
			<button type="button" class="kb-x" data-drawer-close aria-label="Cerrar">×</button>
		</header>
		<div class="kb-drawer-body" data-drawer-body>
			<p class="muted">Cargando…</p>
		</div>
		<div class="kb-pop" data-mail-pop hidden>
			<form data-mail-pop-form>
				<h3>Enviar correo</h3>
				<p class="muted" data-mail-who></p>
				<label><span>Para</span><input type="email" name="to" required data-mail-to></label>
				<label><span>Asunto</span><input name="subject" required data-mail-subject></label>
				<label><span>Mensaje</span><textarea name="body" required rows="6" placeholder="Escribe el mensaje"></textarea></label>
				<label class="mail-attach">
					<span>Adjuntos</span>
					<input type="file" name="adjuntos[]" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xml,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rtf" multiple data-mail-files>
					<small>Puedes elegir varios juntos o sumar de a uno. PDF, imágenes, XML, Excel, Word o ZIP.</small>
				</label>
				<p class="muted" data-mail-status></p>
				<div class="kb-actions">
					<button type="submit" class="is-primary">Enviar</button>
					<button type="button" data-mail-close>Cerrar</button>
				</div>
			</form>
		</div>
	</div>
</aside>
