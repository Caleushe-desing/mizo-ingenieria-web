<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$client = $client ?? null;
$contacts = $contacts ?? [];
$directory = $directory ?? [];
$to = $to ?? '';
$cc = $cc ?? '';
$subject = $subject ?? '';
$folder = 'compose';
$unread = (int) ($unread ?? 0);
?>
<div class="gmail">
	<?php require __DIR__ . '/nav.php'; ?>
	<section class="gmail-main">
		<form class="gmail-window" method="post" action="<?= h(Http::url('/correo')) ?>" data-compose>
			<div class="gmail-window-head">Mensaje nuevo</div>
			<?= Csrf::field() ?>
			<?php if ($client): ?>
				<input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
			<?php endif; ?>

			<?php if ($directory): ?>
				<div class="compose-picker">
					<label>
						<span>Cliente asignado</span>
						<select data-pick-client>
							<option value="">— Elegir cliente —</option>
							<?php foreach ($directory as $item): ?>
								<option value="<?= (int) $item['id'] ?>" <?= $client && (int) $client['id'] === (int) $item['id'] ? 'selected' : '' ?>
									data-contacts="<?= h(json_encode($item['contacts'], JSON_UNESCAPED_UNICODE)) ?>">
									<?= h($item['name']) ?><?= $item['rut'] !== '' ? ' · ' . h($item['rut']) : '' ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<div class="compose-contacts" data-pick-contacts>
						<?php if ($contacts): ?>
							<?php foreach ($contacts as $c): ?>
								<?php if (empty($c['email'])) continue; ?>
								<label class="check-pill">
									<input type="checkbox" data-contact-email value="<?= h($c['email']) ?>" <?= str_contains($to, (string) $c['email']) ? 'checked' : '' ?>>
									<span><?= h($c['name'] ?: $c['email']) ?></span>
									<small><?= h($c['email']) ?></small>
								</label>
							<?php endforeach; ?>
						<?php else: ?>
							<p class="muted">Elige un cliente para ver sus contactos.</p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<label class="gmail-field">
				<span>Para</span>
				<input name="to" type="text" value="<?= h($to) ?>" required placeholder="uno@correo.cl, dos@correo.cl" data-to-field>
			</label>
			<label class="gmail-field">
				<span>Cc</span>
				<input name="cc" type="text" value="<?= h($cc) ?>" placeholder="opcional">
			</label>
			<label class="gmail-field">
				<span>Asunto</span>
				<input name="subject" value="<?= h($subject) ?>" required placeholder="Asunto">
			</label>
			<?php if ($client): ?>
				<p class="gmail-client-hint">Cliente: <a href="<?= h(Http::url('/clientes/' . $client['id'])) ?>"><?= h($client['name']) ?></a></p>
			<?php endif; ?>
			<textarea class="gmail-compose-body" name="body" required placeholder="Redacta tu mensaje de ventas o seguimiento"></textarea>
			<div class="gmail-window-actions">
				<button class="gmail-send" type="submit">Enviar</button>
				<a href="<?= h(Http::url('/correo')) ?>">Descartar</a>
			</div>
		</form>
	</section>
</div>
