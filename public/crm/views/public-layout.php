<!doctype html>
<html lang="es-CL">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= h($title ?? 'Cotización Mizo') ?></title>
	<link rel="stylesheet" href="<?= h(\MizoCrm\Http::url('/assets/app.css')) ?>">
</head>
<body class="quote-doc">
	<div class="quote-sheet">
		<?php if (!empty($flash)): ?>
			<div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
		<?php endif; ?>
		<?= $content ?>
	</div>
</body>
</html>
