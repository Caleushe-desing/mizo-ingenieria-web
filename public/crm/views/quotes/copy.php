<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$projects = $projects ?? [];
$project = $project ?? null;
$selected = (int) ($project['id'] ?? $quote['deal_id'] ?? 0);
?>
<div class="client-sheet quote-sheet quote-send-sheet">
	<header class="quote-toolbar">
		<div>
			<p class="file-kicker">Copiar cotización</p>
			<h1><?= h($quote['number']) ?></h1>
			<p><?= h($client['name']) ?> · Se creará un borrador con el siguiente número correlativo</p>
		</div>
		<div class="quote-toolbar-actions">
			<a class="btn-text" href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>">Cancelar</a>
		</div>
	</header>

	<form class="quote-block is-solid quote-send-panel form" method="post" action="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/copiar')) ?>">
		<?= Csrf::field() ?>
		<div class="quote-block-hd">
			<div>
				<h2>Proyecto de la copia</h2>
				<p class="muted">Elige a qué proyecto del cliente irá esta cotización. Puedes dejar el mismo o cambiarlo.</p>
			</div>
		</div>
		<div class="quote-send-body">
			<label>
				<span>Proyecto</span>
				<select name="project_id" required>
					<?php foreach ($projects as $row): ?>
						<option value="<?= (int) $row['id'] ?>" <?= $selected === (int) $row['id'] ? 'selected' : '' ?>><?= h($row['title']) ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<p class="muted">Se copian partidas, condiciones e institucional. La copia nace como borrador, sin envío.</p>
			<div class="quote-send-actions is-flush">
				<a class="btn-text" href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>">Volver</a>
				<button class="btn btn-excel" type="submit">Crear copia</button>
			</div>
		</div>
	</form>
</div>
