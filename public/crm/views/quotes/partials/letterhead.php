<?php
use MizoCrm\Config;

$issued = $issued ?? ($quote['sent_at'] ?? $quote['created_at'] ?? null);
$pageLabel = $pageLabel ?? '';
?>
<header class="doc-letterhead">
	<div class="doc-brand">
		<img src="/mizo-logo.png" alt="Mizo">
		<p>Ingeniería en sonido, video, CCTV y soporte TI</p>
	</div>
	<div class="doc-id">
		<p class="doc-type">Cotización<?= $pageLabel !== '' ? ' · ' . h($pageLabel) : '' ?></p>
		<p class="doc-number"><?= h($quote['number']) ?></p>
		<?php if (trim((string) ($quote['revision'] ?? '')) !== ''): ?>
			<p class="doc-rev">Versión <?= h($quote['revision']) ?></p>
		<?php endif; ?>
		<dl>
			<div><dt>Fecha</dt><dd><?= h(when($issued, 'd-m-Y')) ?></dd></div>
			<div><dt>Válida hasta</dt><dd><?= h(when($quote['valid_until'], 'd-m-Y')) ?></dd></div>
		</dl>
	</div>
</header>
