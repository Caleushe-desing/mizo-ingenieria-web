<?php
use MizoCrm\Config;
use MizoCrm\Http;

$openStages = Config::openStages();
?>
<p class="kicker">Tablero</p>
<h1>Ventas en curso</h1>
<p>Un objeto por cliente, un negocio por oportunidad, una cotización por propuesta enviada.</p>

<div class="stats" style="margin-top:20px">
	<div class="stat"><span>Clientes</span><strong><?= (int) $clients ?></strong></div>
	<div class="stat"><span>Negocios abiertos</span><strong><?= (int) $dealsOpen ?></strong></div>
	<div class="stat"><span>Cotizaciones enviadas</span><strong><?= (int) $quotesSent ?></strong></div>
	<div class="stat"><span>Pipeline</span><strong><?= money((int) $pipelineValue) ?></strong></div>
</div>

<div class="card" style="margin-bottom:24px">
	<div class="card-hd">
		<h2>Negocios abiertos</h2>
		<a href="<?= h(Http::url('/negocios')) ?>">Ver todos</a>
	</div>
	<div class="card-bd">
		<div class="kanban">
			<?php foreach ($openStages as $stage): ?>
				<div class="col">
					<div class="col-hd"><?= h(Config::stages()[$stage]) ?> · <?= count($pipeline[$stage] ?? []) ?></div>
					<?php foreach (array_slice($pipeline[$stage] ?? [], 0, 6) as $deal): ?>
						<a class="deal-card" href="<?= h(Http::url('/negocios/' . $deal['id'])) ?>">
							<b><?= h($deal['title']) ?></b>
							<small><?= h($deal['client_name']) ?> · <?= money((int) $deal['amount']) ?></small>
						</a>
					<?php endforeach; ?>
					<?php if (empty($pipeline[$stage])): ?>
						<p class="empty" style="padding:16px">Vacío</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<div class="grid-2">
	<div class="card">
		<div class="card-hd"><h2>Últimas cotizaciones</h2><a href="<?= h(Http::url('/cotizaciones')) ?>">Ver</a></div>
		<table class="table">
			<tbody>
			<?php foreach ($quotes as $quote): ?>
				<tr>
					<td><a href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>"><b><?= h($quote['number']) ?></b></a><div style="color:var(--muted)"><?= h($quote['client_name']) ?></div></td>
					<td><span class="badge <?= h($quote['status']) ?>"><?= h(Config::quoteStatuses()[$quote['status']] ?? $quote['status']) ?></span></td>
					<td><?= money((int) $quote['total']) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (!$quotes): ?><tr><td class="empty">Aún no hay cotizaciones.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<div class="card">
		<div class="card-hd"><h2>Actividad</h2></div>
		<ul class="timeline" style="padding:0 16px">
			<?php foreach ($activity as $item): ?>
				<li>
					<?= h($item['message']) ?>
					<small><?= h($item['user_name'] ?: 'Sistema') ?> · <?= when($item['created_at']) ?></small>
				</li>
			<?php endforeach; ?>
			<?php if (!$activity): ?><li class="empty">El registro aparecerá aquí.</li><?php endif; ?>
		</ul>
	</div>
</div>
