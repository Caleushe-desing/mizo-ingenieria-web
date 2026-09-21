<?php
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;
?>
<div class="grid-2">
	<div>
		<p class="kicker">Negocio</p>
		<h1><?= h($deal['title']) ?></h1>
		<p>
			<a href="<?= h(Http::url('/clientes/' . $client['id'])) ?>"><?= h($client['name']) ?></a>
			· <?= h(Config::services()[$deal['service']] ?? '') ?>
			· <?= money((int) $deal['amount']) ?>
		</p>
		<div class="top-actions" style="margin:16px 0 20px">
			<a class="btn" href="<?= h(Http::url('/cotizaciones/nueva?negocio=' . $deal['id'])) ?>">Emitir cotización</a>
		</div>
		<div class="card" style="margin-bottom:16px">
			<div class="card-hd"><h2>Mover etapa</h2></div>
			<div class="card-bd">
				<form method="post" action="<?= h(Http::url('/negocios/' . $deal['id'] . '/etapa')) ?>" class="form">
					<?= Csrf::field() ?>
					<div class="filters" style="margin:0">
						<?php foreach (Config::stages() as $key => $label): ?>
							<button class="btn-ghost <?= $deal['stage'] === $key ? 'btn' : '' ?>" name="stage" value="<?= h($key) ?>" type="submit"><?= h($label) ?></button>
						<?php endforeach; ?>
					</div>
					<label style="margin-top:12px"><span>Si se pierde, motivo</span><input name="lost_reason" value="<?= h($deal['lost_reason'] ?? '') ?>"></label>
				</form>
			</div>
		</div>
		<div class="card" style="margin-bottom:16px">
			<div class="card-hd"><h2>Cotizaciones de este negocio</h2></div>
			<?php foreach ($quotes as $quote): ?>
				<a class="deal-card" href="<?= h(Http::url('/cotizaciones/' . $quote['id'])) ?>">
					<b><?= h($quote['number']) ?></b>
					<small><span class="badge <?= h($quote['status']) ?>"><?= h(Config::quoteStatuses()[$quote['status']]) ?></span> · <?= money((int) $quote['total']) ?> · <?= when($quote['sent_at'] ?: $quote['created_at'], 'd-m-Y') ?></small>
				</a>
			<?php endforeach; ?>
			<?php if (!$quotes): ?><p class="empty">Aún no hay presupuestos. Emite el primero.</p><?php endif; ?>
		</div>
		<div class="card">
			<div class="card-hd"><h2>Notas del equipo</h2></div>
			<div class="card-bd">
				<form class="form" method="post" action="<?= h(Http::url('/negocios/' . $deal['id'] . '/nota')) ?>">
					<?= Csrf::field() ?>
					<textarea name="message" rows="3" placeholder="Llamé al cliente, visita agendada, etc." required></textarea>
					<button class="btn-ghost" type="submit">Registrar nota</button>
				</form>
				<ul class="timeline">
					<?php foreach ($activity as $item): ?>
						<li><?= h($item['message']) ?><small><?= h($item['user_name'] ?: 'Sistema') ?> · <?= when($item['created_at']) ?></small></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
	<div>
		<div class="card" style="margin-bottom:16px">
			<div class="card-hd"><h2>Cliente</h2></div>
			<div class="card-bd">
				<p><b><?= h($client['name']) ?></b></p>
				<p><?= h($client['contact_name'] ?: '—') ?></p>
				<p><?= h($client['email'] ?: '—') ?></p>
				<p><?= h($client['phone'] ?: '—') ?></p>
				<p><?= h($client['city'] ?: '') ?> <?= h($client['rut'] ? '· RUT ' . $client['rut'] : '') ?></p>
			</div>
		</div>
		<?php
		$d = $deal;
		$d['client_name'] = $client['name'];
		$embed = true;
		require __DIR__ . '/form.php';
		?>
	</div>
</div>
