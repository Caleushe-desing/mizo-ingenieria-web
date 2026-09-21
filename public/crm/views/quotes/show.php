<?php
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;
?>
<p class="kicker">Cotización <?= h($quote['number']) ?></p>
<h1><?= h($deal['title'] ?? '') ?></h1>
<p><?= h($client['name']) ?> · <?= h(Config::quoteStatuses()[$quote['status']]) ?> · Total <?= money((int) $quote['total']) ?></p>

<div class="grid-2" style="margin-top:20px">
	<div class="card">
		<table class="table">
			<thead><tr><th>Partida</th><th>Cant.</th><th>Total</th></tr></thead>
			<tbody>
			<?php foreach ($items as $item): ?>
				<tr>
					<td><?= h($item['description']) ?></td>
					<td><?= h((string) $item['quantity']) ?> <?= h($item['unit']) ?></td>
					<td><?= money((int) $item['total']) ?></td>
				</tr>
			<?php endforeach; ?>
			<tr><td colspan="2">Neto</td><td><?= money((int) $quote['subtotal']) ?></td></tr>
			<tr><td colspan="2">IVA <?= (int) $quote['tax_rate'] ?>%</td><td><?= money((int) $quote['tax']) ?></td></tr>
			<tr><td colspan="2"><b>Total</b></td><td><b><?= money((int) $quote['total']) ?></b></td></tr>
			</tbody>
		</table>
		<?php if ($quote['notes']): ?><div class="card-bd"><p><?= nl2br(h($quote['notes'])) ?></p></div><?php endif; ?>
	</div>
	<div>
		<div class="card" style="margin-bottom:16px">
			<div class="card-hd"><h2>Envío y registro</h2></div>
			<div class="card-bd">
				<p>Enviada: <?= $quote['sent_at'] ? when($quote['sent_at']) : '—' ?></p>
				<p>Destino: <?= h($quote['sent_to'] ?: $client['email'] ?: 'Sin correo') ?></p>
				<p>Vista: <?= $quote['viewed_at'] ? when($quote['viewed_at']) : '—' ?></p>
				<p>Respuesta: <?= $quote['responded_at'] ? when($quote['responded_at']) : '—' ?></p>
				<p><a href="<?= h($publicUrl) ?>" target="_blank" rel="noopener">Abrir enlace del cliente</a></p>
				<p><a href="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/preview')) ?>">Ver como cliente</a></p>
				<?php if (!in_array($quote['status'], ['aceptada', 'rechazada'], true)): ?>
					<form class="form" method="post" action="<?= h(Http::url('/cotizaciones/' . $quote['id'] . '/enviar')) ?>">
						<?= Csrf::field() ?>
						<label><span>Enviar a</span><input name="sent_to" type="email" value="<?= h($quote['sent_to'] ?: $client['email']) ?>" required></label>
						<button class="btn" type="submit">Enviar cotización</button>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<div class="card">
			<div class="card-hd"><h2>Cliente</h2></div>
			<div class="card-bd">
				<p><a href="<?= h(Http::url('/clientes/' . $client['id'])) ?>"><?= h($client['name']) ?></a></p>
				<p><?= h($client['email'] ?: '—') ?></p>
				<p><?= h($client['phone'] ?: '—') ?></p>
				<p><a href="<?= h(Http::url('/negocios/' . $deal['id'])) ?>">Ir al negocio</a></p>
			</div>
		</div>
	</div>
</div>
