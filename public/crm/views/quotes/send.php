<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$contacts = $contacts ?? [];
$selectedContact = (int) ($selectedContact ?? 0);
$selectedCc = $selectedCc ?? [];
$alreadySent = !empty($alreadySent);
$emailHtml = (string) ($emailHtml ?? '');
$subject = (string) ($subject ?? '');
$publicUrl = (string) ($publicUrl ?? '');
?>
<div class="client-sheet quote-sheet quote-send-sheet">
	<header class="quote-toolbar">
		<div>
			<p class="file-kicker">Enviar cotización</p>
			<h1><?= h($quote['number']) ?><?php if (trim((string) ($quote['revision'] ?? '')) !== ''): ?> · <?= h($quote['revision']) ?><?php endif; ?></h1>
			<p><?= h($client['name']) ?> · Revisa destinatarios y el correo antes de enviar</p>
		</div>
		<div class="quote-toolbar-actions">
			<a class="btn-text" href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>">Volver a editar</a>
			<a class="btn btn-word" href="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/preview')) ?>" target="_blank" rel="noopener">PDF / vista cliente</a>
		</div>
	</header>

	<form class="quote-send-layout" method="post" action="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/enviar')) ?>">
		<?= Csrf::field() ?>

		<section class="quote-block is-solid quote-send-panel">
			<div class="quote-block-hd">
				<div>
					<h2>Destinatarios</h2>
					<p class="muted">Elige el contacto principal y marca a quién más quieres incluir en copia.</p>
				</div>
			</div>
			<div class="quote-send-body">
				<?php
				$showRevision = !empty($alreadySent) || trim((string) ($quote['revision'] ?? '')) !== '';
				$revisionRequired = !empty($alreadySent);
				?>
				<?php if ($showRevision): ?>
					<label>
						<span>Versión (REV)</span>
						<input name="revision" value="<?= h($quote['revision'] ?? '') ?>" placeholder="REV-01, OC" maxlength="24" <?= $revisionRequired ? 'required' : '' ?>>
					</label>
				<?php endif; ?>

				<?php
				$mailContacts = [];
				foreach ($contacts as $c) {
					if (trim((string) ($c['email'] ?? '')) !== '') {
						$mailContacts[] = $c;
					}
				}
				?>
				<?php if ($mailContacts): ?>
					<label>
						<span>Para (contacto principal)</span>
						<select name="contact_id" required data-send-contact>
							<?php foreach ($mailContacts as $c): ?>
								<?php
								$email = trim((string) ($c['email'] ?? ''));
								$label = trim((string) ($c['name'] ?? '')) !== '' ? (string) $c['name'] : 'Contacto';
								if (trim((string) ($c['title'] ?? '')) !== '') {
									$label .= ' · ' . $c['title'];
								}
								$label .= ' · ' . $email;
								?>
								<option value="<?= (int) $c['id'] ?>" <?= $selectedContact === (int) $c['id'] ? 'selected' : '' ?>><?= h($label) ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<div class="quote-send-cc">
						<span class="quote-line-label">También en copia (Cc)</span>
						<div class="quote-send-pills">
							<?php foreach ($mailContacts as $c):
								$email = trim((string) ($c['email'] ?? ''));
								$checked = in_array(mb_strtolower($email), array_map('mb_strtolower', $selectedCc), true)
									&& $selectedContact !== (int) $c['id'];
								?>
								<label class="check-pill">
									<input type="checkbox" name="cc_contact_id[]" value="<?= (int) $c['id'] ?>" data-cc-contact="<?= (int) $c['id'] ?>" <?= $checked ? 'checked' : '' ?>>
									<span><?= h(trim((string) ($c['name'] ?? '')) !== '' ? $c['name'] : $email) ?></span>
									<small><?= h($email) ?></small>
								</label>
							<?php endforeach; ?>
						</div>
						<?php if (count($mailContacts) < 2): ?>
							<p class="muted">Para sumar más destinatarios, agrega contactos con correo en la ficha del cliente.</p>
						<?php endif; ?>
					</div>
				<?php else: ?>
					<label>
						<span>Para</span>
						<input name="sent_to" type="email" value="<?= h($quote['sent_to'] ?? $client['email'] ?? '') ?>" required placeholder="correo@cliente.cl">
					</label>
					<p class="muted">Este cliente no tiene contactos con correo. Escribe el destinatario o agrégalos en su ficha.</p>
				<?php endif; ?>

				<label>
					<span>Cc adicional (opcional)</span>
					<input name="cc" type="text" value="" placeholder="otro@correo.cl, mas@correo.cl">
				</label>
			</div>
		</section>

		<section class="quote-block is-solid quote-send-panel">
			<div class="quote-block-hd">
				<div>
					<h2>Vista previa del correo</h2>
					<p class="muted">Así se verá el mensaje. Arriba va el botón <strong>Descargar presupuesto</strong>; al enviar también se adjunta el mismo PDF.</p>
				</div>
			</div>
			<div class="quote-send-body">
				<p class="quote-send-subject"><strong>Asunto:</strong> <?= h($subject) ?></p>
				<div class="quote-email-frame" tabindex="0">
					<?= $emailHtml ?>
				</div>
			</div>
		</section>

		<div class="quote-send-actions">
			<a class="btn-text" href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>">Cancelar</a>
			<button class="btn btn-word" type="submit"><?= $alreadySent ? 'Enviar revisión' : 'Confirmar y enviar' ?></button>
		</div>
	</form>
</div>
<script>
(function () {
	var select = document.querySelector("[data-send-contact]");
	if (!select) return;
	function syncCc() {
		var main = select.value;
		document.querySelectorAll("[data-cc-contact]").forEach(function (box) {
			if (box.getAttribute("data-cc-contact") === main) {
				box.checked = false;
				box.disabled = true;
			} else {
				box.disabled = false;
			}
		});
	}
	syncCc();
	select.addEventListener("change", function () {
		var id = select.value;
		var url = new URL(window.location.href);
		if (url.searchParams.get("contacto") === id) {
			syncCc();
			return;
		}
		url.searchParams.set("contacto", id);
		window.location.href = url.toString();
	});
})();
</script>
