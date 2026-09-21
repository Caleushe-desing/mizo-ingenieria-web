<?php
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;

$peers = $peers ?? [];
$peer = $peer ?? null;
$messages = $messages ?? [];
$meId = (int) (($user['id'] ?? 0));
$openClass = $peer ? ' has-open' : '';
$lastId = 0;
foreach ($messages as $row) {
	$lastId = max($lastId, (int) $row['id']);
}
$emojis = ['😀','😁','😂','😊','😉','😍','😎','🤩','🙂','😢','😭','😡','👍','👎','👏','🙏','💪','🔥','✅','❌','⭐','🎉','👋','❤️','💙','🧡','💯','📌','📞','📧','🏗️','🛠️','⚡','💡','📝','🤝'];
?>
<div class="chat<?= $openClass ?>">
	<aside class="chat-peers">
		<div class="chat-peers-head">
			<strong>Chat interno</strong>
			<span>Conversaciones con el equipo</span>
		</div>
		<?php if (!$peers): ?>
			<div class="chat-empty">
				<p><?= Auth::isAdmin() ? 'Aún no hay ejecutivos en el equipo.' : 'No hay un administrador disponible para chatear.' ?></p>
			</div>
		<?php else: ?>
			<div class="chat-peer-list">
				<?php foreach ($peers as $row): ?>
					<?php
					$active = $peer && (int) $peer['id'] === (int) $row['id'];
					$preview = trim((string) ($row['last_body'] ?? ''));
					if ($preview !== '') {
						if (function_exists('mb_strlen') && mb_strlen($preview, 'UTF-8') > 48) {
							$preview = mb_substr($preview, 0, 48, 'UTF-8') . '…';
						} elseif (strlen($preview) > 48) {
							$preview = substr($preview, 0, 48) . '…';
						}
					}
					?>
					<a class="chat-peer <?= $active ? 'is-on' : '' ?> <?= !empty($row['unread']) ? 'is-unread' : '' ?>" href="<?= h(Http::url('/chat/' . $row['id'])) ?>">
						<span class="gmail-avatar" style="background:<?= h(mail_avatar_color((string) $row['name'])) ?>"><?= h(initials((string) $row['name'])) ?></span>
						<span class="chat-peer-copy">
							<strong><?= h($row['name']) ?></strong>
							<small><?= $preview !== '' ? h($preview) : 'Sin mensajes aún' ?></small>
						</span>
						<?php if (!empty($row['unread'])): ?>
							<span class="chat-unread-pill"><?= (int) $row['unread'] ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</aside>

	<section class="chat-thread">
		<?php if (!$peer): ?>
			<div class="chat-placeholder">
				<p>Elige un ejecutivo para ver el historial y escribirle.</p>
			</div>
		<?php else: ?>
			<div class="chat-thread-head">
				<a class="chat-back" href="<?= h(Http::url('/chat')) ?>">← Chats</a>
				<span class="gmail-avatar" style="background:<?= h(mail_avatar_color((string) $peer['name'])) ?>"><?= h(initials((string) $peer['name'])) ?></span>
				<div>
					<strong><?= h($peer['name']) ?></strong>
					<small><?= h($peer['email']) ?></small>
				</div>
			</div>
			<div class="chat-messages" id="chat-messages" data-poll="<?= h(Http::url('/chat/' . $peer['id'] . '/mensajes')) ?>" data-last="<?= (int) $lastId ?>">
				<?php if (!$messages): ?>
					<p class="chat-start">Escribe el primer mensaje. Quedará guardado en el historial.</p>
				<?php endif; ?>
				<?php foreach ($messages as $row): ?>
					<?php $mine = (int) $row['from_user_id'] === $meId; ?>
					<div class="chat-bubble <?= $mine ? 'is-mine' : 'is-theirs' ?>" data-id="<?= (int) $row['id'] ?>">
						<div class="chat-bubble-body"><?= nl2br(h($row['body']), false) ?></div>
						<time><?= h(when($row['created_at'] ?? null)) ?></time>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="chat-compose-wrap">
				<div class="chat-emoji-panel" id="chat-emoji-panel" hidden>
					<?php foreach ($emojis as $emoji): ?>
						<button type="button" class="chat-emoji-btn" data-emoji="<?= h($emoji) ?>"><?= $emoji ?></button>
					<?php endforeach; ?>
				</div>
				<form class="chat-compose" method="post" action="<?= h(Http::url('/chat/' . $peer['id'])) ?>" data-chat-form>
					<?= Csrf::field() ?>
					<button class="chat-emoji-toggle" type="button" id="chat-emoji-toggle" title="Emoticones" aria-label="Emoticones">😊</button>
					<textarea name="body" rows="2" required placeholder="Escribe y pulsa Enter para enviar…" maxlength="4000" data-chat-input></textarea>
					<button class="btn btn-word" type="submit">Enviar</button>
				</form>
				<p class="chat-compose-hint">Enter envía · Shift+Enter salto de línea</p>
			</div>
		<?php endif; ?>
	</section>
</div>
