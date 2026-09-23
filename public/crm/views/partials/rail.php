<?php
use MizoCrm\Auth;
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
		<div class="rail-body">
			<?php if (!$railPeers): ?>
				<p class="rail-empty"><?= Auth::isAdmin() ? 'No hay ejecutivos para chatear.' : 'No hay administrador disponible.' ?></p>
			<?php else: ?>
				<ul class="rail-chat-list">
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
							<a class="rail-chat-item<?= !empty($peerRow['unread']) ? ' is-unread' : '' ?>" href="<?= h(Http::url('/chat/' . $peerRow['id'])) ?>">
								<span class="gmail-avatar" style="background:<?= h(mail_avatar_color((string) $peerRow['name'])) ?>"><?= h(initials((string) $peerRow['name'])) ?></span>
								<span class="rail-chat-copy">
									<strong><?= h($peerRow['name']) ?></strong>
									<small><?= $preview !== '' ? h($preview) : 'Sin mensajes' ?></small>
								</span>
								<?php if (!empty($peerRow['unread'])): ?>
									<em class="rail-badge"><?= (int) $peerRow['unread'] ?></em>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<a class="rail-more" href="<?= h(Http::url('/chat')) ?>">Abrir chat completo</a>
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
