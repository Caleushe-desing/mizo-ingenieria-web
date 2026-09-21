<!doctype html>
<html lang="es-CL">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= h(($title ?? 'CRM') . ' | Mizo') ?></title>
	<link rel="stylesheet" href="<?= h(\MizoCrm\Http::url('/assets/app.css')) ?>">
</head>
<body>
	<div class="auth">
		<div class="auth-card">
			<p class="kicker">Mizo</p>
			<?php if (!empty($flash)): ?>
				<div class="flash <?= h($flash['type']) ?>" style="margin:16px 0"><?= h($flash['message']) ?></div>
			<?php endif; ?>
			<?= $content ?>
		</div>
	</div>
</body>
</html>
