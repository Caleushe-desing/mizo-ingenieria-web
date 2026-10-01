<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$templates = $templates ?? [];
$template = $template ?? null;
$variables = $variables ?? [];
$client = $client ?? null;
$contacts = $contacts ?? [];
$directory = $directory ?? [];
$quote = $quote ?? null;
$quotes = $quotes ?? [];
$to = $to ?? '';
$cc = $cc ?? '';
$subject = $subject ?? '';
$body = $body ?? '';
$publicUrl = $publicUrl ?? '';
?>
<div class="mkt-sheet" data-mkt-compose
	data-templates="<?= h(json_encode(array_map(static fn($t) => [
		'id' => (int) $t['id'],
		'name' => (string) $t['name'],
		'subject' => (string) $t['subject'],
		'body' => (string) $t['body'],
	], $templates), JSON_UNESCAPED_UNICODE)) ?>"
	data-vars="<?= h(json_encode($previewVars ?? [], JSON_UNESCAPED_UNICODE)) ?>">
	<div class="page-head">
		<div>
			<p class="file-kicker">Marketing</p>
			<h1>Correo comercial</h1>
			<p>
				<?php if ($client): ?>
					Cliente: <a href="<?= h(Http::url('/tablero/cliente/' . $client['id'] . '/ficha')) ?>"><?= h($client['name']) ?></a>
				<?php else: ?>
					Elige un cliente o parte desde una plantilla.
				<?php endif; ?>
				<?php if ($quote): ?>
					· Cotización <?= h($quote['number']) ?><?= trim((string) ($quote['revision'] ?? '')) !== '' ? ' · ' . h($quote['revision']) : '' ?>
				<?php endif; ?>
			</p>
		</div>
		<div class="mkt-head-actions">
			<a class="btn-text" href="<?= h(Http::url('/marketing')) ?>">Volver</a>
			<a class="btn-text" href="<?= h(Http::url('/marketing/plantillas')) ?>">Plantillas</a>
		</div>
	</div>

	<form class="paper form mkt-compose" method="post" action="<?= h(Http::url('/marketing/enviar')) ?>">
		<?= Csrf::field() ?>
		<?php if ($client): ?>
			<input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
		<?php endif; ?>

		<div class="mkt-compose-grid">
			<label>
				<span>Plantilla</span>
				<select name="template_id" data-mkt-template>
					<option value="">— Sin plantilla —</option>
					<?php foreach ($templates as $tpl): ?>
						<option value="<?= (int) $tpl['id'] ?>" <?= $template && (int) $template['id'] === (int) $tpl['id'] ? 'selected' : '' ?>>
							<?= h($tpl['name']) ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label>
				<span>Cliente</span>
				<select data-mkt-client>
					<option value="">— Elegir cliente —</option>
					<?php foreach ($directory as $item): ?>
						<option value="<?= (int) $item['id'] ?>"
							<?= $client && (int) $client['id'] === (int) $item['id'] ? 'selected' : '' ?>
							data-contacts="<?= h(json_encode($item['contacts'], JSON_UNESCAPED_UNICODE)) ?>">
							<?= h($item['name']) ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<?php
			$quoteOptions = $quotes;
			if ($quote) {
				$found = false;
				foreach ($quoteOptions as $q) {
					if ((int) ($q['id'] ?? 0) === (int) $quote['id']) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					array_unshift($quoteOptions, $quote);
				}
			}
			?>
			<?php if ($quoteOptions !== []): ?>
				<label>
					<span>Cotización (opcional)</span>
					<select name="quote_id" data-mkt-quote>
						<option value="0">— Sin cotización —</option>
						<?php foreach ($quoteOptions as $q): ?>
							<option value="<?= (int) $q['id'] ?>" <?= $quote && (int) $quote['id'] === (int) $q['id'] ? 'selected' : '' ?>>
								<?= h($q['number'] ?? '') ?>
								<?= trim((string) ($q['revision'] ?? '')) !== '' ? ' · ' . h($q['revision']) : '' ?>
								· <?= h(quote_status_label((string) ($q['status'] ?? ''))) ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php else: ?>
				<input type="hidden" name="quote_id" value="0">
			<?php endif; ?>
		</div>

		<?php if ($contacts): ?>
			<div class="mkt-contacts" data-mkt-contacts>
				<?php foreach ($contacts as $c): ?>
					<?php if (empty($c['email'])) continue; ?>
					<label class="check-pill">
						<input type="checkbox" data-contact-email value="<?= h($c['email']) ?>" <?= str_contains($to, (string) $c['email']) ? 'checked' : '' ?>>
						<span><?= h($c['name'] ?: $c['email']) ?></span>
						<small><?= h($c['email']) ?></small>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<label><span>Para</span><input name="to" type="text" value="<?= h($to) ?>" required placeholder="uno@correo.cl, dos@correo.cl" data-to-field></label>
		<label><span>Cc</span><input name="cc" type="text" value="<?= h($cc) ?>" placeholder="opcional"></label>
		<label><span>Asunto</span><input name="subject" value="<?= h($subject) ?>" required data-mkt-subject></label>
		<label>
			<span>Mensaje</span>
			<textarea name="body" rows="14" required data-mkt-body><?= h($body) ?></textarea>
		</label>

		<div class="mkt-chips">
			<span class="muted">Insertar variable:</span>
			<?php foreach ($variables as $key => $label): ?>
				<button type="button" class="mkt-chip" data-mkt-insert="{<?= h($key) ?>}" title="<?= h($label) ?>">{<?= h($key) ?></button>
			<?php endforeach; ?>
		</div>

		<?php if ($quote || $quotes !== []): ?>
			<div class="mkt-options">
				<label class="check-pill">
					<input type="checkbox" name="attach_pdf" value="1" <?= $quote ? 'checked' : '' ?>>
					<span>Adjuntar PDF de la cotización</span>
				</label>
				<label class="check-pill">
					<input type="checkbox" name="sync_board" value="1" <?= $quote ? 'checked' : '' ?>>
					<span>Registrar envío y mover tarjeta a Presupuesto enviado</span>
				</label>
			</div>
			<?php if ($publicUrl !== ''): ?>
				<p class="muted">Enlace del cliente: <a href="<?= h($publicUrl) ?>" target="_blank" rel="noopener"><?= h($publicUrl) ?></a></p>
			<?php endif; ?>
		<?php endif; ?>

		<div class="form-actions">
			<button class="btn btn-word" type="submit">Enviar correo comercial</button>
			<a class="btn-text" href="<?= h(Http::url('/marketing')) ?>">Cancelar</a>
		</div>
	</form>
</div>
<script>
(function () {
	const root = document.querySelector('[data-mkt-compose]');
	if (!root) return;
	const templates = JSON.parse(root.getAttribute('data-templates') || '[]');
	const tplSelect = root.querySelector('[data-mkt-template]');
	const subject = root.querySelector('[data-mkt-subject]');
	const body = root.querySelector('[data-mkt-body]');
	const clientSelect = root.querySelector('[data-mkt-client]');
	const quoteSelect = root.querySelector('[data-mkt-quote]');
	const toField = root.querySelector('[data-to-field]');

	function applyTemplate(id) {
		const tpl = templates.find(function (t) { return String(t.id) === String(id); });
		if (!tpl || !subject || !body) return;
		if (subject.value && body.value && !confirm('¿Reemplazar asunto y mensaje con la plantilla?')) return;
		subject.value = tpl.subject;
		body.value = tpl.body;
	}
	tplSelect && tplSelect.addEventListener('change', function () {
		if (tplSelect.value) applyTemplate(tplSelect.value);
	});

	clientSelect && clientSelect.addEventListener('change', function () {
		const id = clientSelect.value;
		if (!id) return;
		const url = new URL(window.location.href);
		url.searchParams.set('cliente', id);
		url.searchParams.delete('cotizacion');
		if (tplSelect && tplSelect.value) url.searchParams.set('plantilla', tplSelect.value);
		window.location.href = url.pathname + '?' + url.searchParams.toString();
	});

	quoteSelect && quoteSelect.addEventListener('change', function () {
		const id = quoteSelect.value;
		const url = new URL(window.location.href);
		if (id && id !== '0') url.searchParams.set('cotizacion', id);
		else url.searchParams.delete('cotizacion');
		if (clientSelect && clientSelect.value) url.searchParams.set('cliente', clientSelect.value);
		if (tplSelect && tplSelect.value) url.searchParams.set('plantilla', tplSelect.value);
		window.location.href = url.pathname + '?' + url.searchParams.toString();
	});

	root.querySelectorAll('[data-contact-email]').forEach(function (box) {
		box.addEventListener('change', function () {
			if (!toField) return;
			const emails = [];
			root.querySelectorAll('[data-contact-email]:checked').forEach(function (b) { emails.push(b.value); });
			toField.value = emails.join(', ');
		});
	});

	root.querySelectorAll('[data-mkt-insert]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			const token = btn.getAttribute('data-mkt-insert') || '';
			if (!body) return;
			const start = body.selectionStart || body.value.length;
			const end = body.selectionEnd || body.value.length;
			body.value = body.value.slice(0, start) + token + body.value.slice(end);
			body.focus();
			body.selectionStart = body.selectionEnd = start + token.length;
		});
	});
})();
</script>
