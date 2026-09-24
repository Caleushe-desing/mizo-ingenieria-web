<?php
use MizoCrm\Auth;
use MizoCrm\Http;
use MizoCrm\Models\MailMessage;

$folder = $folder ?? 'inbox';
$unread = (int) ($unread ?? 0);
$spamCount = Auth::id() > 0 ? MailMessage::countIn(Auth::id(), 'spam') : 0;
?>
<aside class="gmail-nav">
	<a class="gmail-compose-btn" href="<?= h(Http::url('/correo/nuevo')) ?>">
		<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"></path></svg>
		Redactar
	</a>
	<a class="<?= $folder === 'inbox' ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo')) ?>">
		<span>Recibidos</span>
		<?php if ($unread > 0): ?><span class="gmail-count"><?= $unread ?></span><?php endif; ?>
	</a>
	<a class="<?= $folder === 'spam' ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo/spam')) ?>">
		<span>No deseado</span>
		<?php if ($spamCount > 0): ?><span class="gmail-count"><?= $spamCount ?></span><?php endif; ?>
	</a>
	<a class="<?= $folder === 'sent' ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo/enviados')) ?>">Enviados</a>
	<a class="<?= $folder === 'account' ? 'is-on' : '' ?>" href="<?= h(Http::url('/correo/cuenta')) ?>">Configuración</a>
</aside>
