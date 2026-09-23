<?php
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\ChatMessage;
use MizoCrm\Models\Client;

$railUser = $user ?? Auth::user();
if (!$railUser) {
	return;
}
$railPeers = ChatMessage::peersFor((int) $railUser['id'], Auth::isAdmin());
$railClients = Client::forRail(Auth::ownerScope());
$railChatUnread = (int) ($unreadChat ?? ChatMessage::unreadCount((int) $railUser['id']));
?>
<aside class="crm-rail" data-crm-rail>
	<details class="rail-panel" data-rail-panel="chat" open>
		<summary>
			<span>Chat</span>
			<?php if ($railChatUnread > 0): ?>
				<em class="rail-badge"><?= $railChatUnread ?></em>
			<?php endif; ?>
		</summary>
		<div class="rail-body rail-chat" data-rail-chat data-chat-base="<?= h(Http::url('/chat/')) ?>">
			<?php if (!$railPeers): ?>
				<p class="rail-empty"><?= Auth::isAdmin() ? 'No hay ejecutivos para chatear.' : 'No hay administrador disponible.' ?></p>
			<?php else: ?>
				<ul class="rail-chat-list" data-rail-peers>
					<?php foreach ($railPeers as $peerRow): ?>
						<?php
						$preview = trim((string) ($peerRow['last_body'] ?? ''));
						if ($preview !== '') {
							$preview = function_exists('mb_strimwidth')
								? mb_strimwidth($preview, 0, 42, '…', 'UTF-8')
								: (strlen($preview) > 42 ? substr($preview, 0, 42) . '…' : $preview);
						}
						?>
						<li>
							<button type="button" class="rail-chat-item<?= !empty($peerRow['unread']) ? ' is-unread' : '' ?>" data-rail-peer="<?= (int) $peerRow['id'] ?>" data-rail-peer-name="<?= h($peerRow['name']) ?>">
								<span class="gmail-avatar" style="background:<?= h(mail_avatar_color((string) $peerRow['name'])) ?>"><?= h(initials((string) $peerRow['name'])) ?></span>
								<span class="rail-chat-copy">
									<strong><?= h($peerRow['name']) ?></strong>
									<small><?= $preview !== '' ? h($preview) : 'Sin mensajes' ?></small>
								</span>
								<?php if (!empty($peerRow['unread'])): ?>
									<em class="rail-badge"><?= (int) $peerRow['unread'] ?></em>
								<?php endif; ?>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
				<div class="rail-thread" data-rail-thread hidden>
					<div class="rail-thread-head">
						<button type="button" class="rail-back" data-rail-back>←</button>
						<strong data-rail-thread-name></strong>
					</div>
					<div class="rail-msgs" data-rail-msgs></div>
					<form class="rail-compose" data-rail-chat-form>
						<?= Csrf::field() ?>
						<textarea name="body" rows="2" required placeholder="Escribe y pulsa Enter…" maxlength="4000" data-rail-chat-input></textarea>
						<button class="btn btn-word" type="submit">Enviar</button>
					</form>
				</div>
			<?php endif; ?>
		</div>
	</details>

	<details class="rail-panel" data-rail-panel="clientes" open>
		<summary>
			<span>Mis clientes</span>
			<em class="rail-count"><?= count($railClients) ?></em>
		</summary>
		<div class="rail-body">
			<label class="rail-search">
				<input type="search" data-rail-client-q placeholder="Buscar RUT, cliente o contacto" autocomplete="off">
			</label>
			<ul class="rail-client-list" data-rail-client-list>
				<?php if (!$railClients): ?>
					<li class="rail-empty">Aún no tienes clientes asignados.</li>
				<?php else: ?>
					<?php foreach ($railClients as $c): ?>
						<?php
						$hay = mb_strtolower(trim(
							($c['name'] ?? '') . ' ' .
							($c['rut'] ?? '') . ' ' .
							($c['contact_name'] ?? '') . ' ' .
							($c['contact_names'] ?? '') . ' ' .
							($c['email'] ?? '') . ' ' .
							($c['phone'] ?? '') . ' ' .
							($c['city'] ?? '')
						), 'UTF-8');
						$sub = trim((string) ($c['rut'] ?: ($c['contact_name'] ?: ($c['city'] ?? ''))));
						?>
						<li data-rail-client data-hay="<?= h($hay) ?>">
							<a href="<?= h(Http::url('/clientes/' . $c['id'])) ?>">
								<strong><?= h($c['name']) ?></strong>
								<small><?= $sub !== '' ? h($sub) : 'Sin RUT / contacto' ?></small>
							</a>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
			<p class="rail-empty" data-rail-client-empty hidden>No hay coincidencias.</p>
			<a class="rail-more" href="<?= h(Http::url('/clientes/nuevo')) ?>">+ Nuevo cliente</a>
		</div>
	</details>
</aside>
