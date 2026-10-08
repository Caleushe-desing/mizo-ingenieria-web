<?php
use MizoCrm\Csrf;
use MizoCrm\Http;

$report = $report ?? [];
$days = (int) ($days ?? 7);
$views = (int) ($report['views'] ?? 0);

$bar = static function (array $rows): void {
	$max = 1;
	foreach ($rows as $row) {
		$max = max($max, (int) ($row['count'] ?? 0));
	}
	if ($rows === []) {
		echo '<p class="muted">Todavía no hay visitas en este período.</p>';
		return;
	}
	echo '<ul class="visit-bars">';
	foreach ($rows as $row) {
		$count = (int) ($row['count'] ?? 0);
		$width = (int) round($count * 100 / $max);
		echo '<li><span>' . h((string) ($row['label'] ?? '')) . '</span><b style="width:' . $width . '%"></b><em>' . $count . '</em></li>';
	}
	echo '</ul>';
};
?>
<div class="admin-page">
	<div class="page-head">
		<div>
			<p class="file-kicker">Sitio</p>
			<h1>Visitas</h1>
			<p>Quién entra a mizo.cl, desde dónde llega y qué páginas mira. El registro parte desde ahora; no incluye visitas anteriores.</p>
		</div>
		<div class="page-head-actions">
			<?php foreach ([1 => 'Hoy', 7 => '7 días', 30 => '30 días', 90 => '90 días'] as $value => $label): ?>
				<a class="btn<?= $days === $value ? ' btn-word' : '' ?>" href="<?= h(Http::url('/visitas?dias=' . $value)) ?>"><?= h($label) ?></a>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="admin-grid" id="vivo-panel" data-vivo="<?= h(Http::url('/visitas/vivo')) ?>" data-reply="<?= h(Http::url('/visitas/chat')) ?>" data-csrf="<?= h(Csrf::token()) ?>">
		<section class="admin-card">
			<h2>En el sitio ahora <span id="vivo-count">0</span></h2>
			<ul class="vivo-list" id="vivo-list"><li class="muted">Nadie en este momento.</li></ul>
		</section>
		<section class="admin-card">
			<h2>Chat del sitio</h2>
			<div class="vivo-chat">
				<ul class="vivo-chats" id="vivo-chats"><li class="muted">Sin conversaciones.</li></ul>
				<div>
					<div class="vivo-thread" id="vivo-thread"><p class="muted">Elige una conversación para responder.</p></div>
					<form id="vivo-reply">
						<input type="hidden" name="chat_id" id="vivo-chat-id" value="">
						<textarea name="body" rows="2" placeholder="Escribe la respuesta…" required></textarea>
						<button class="btn btn-word" type="submit">Enviar</button>
					</form>
				</div>
			</div>
		</section>
	</div>

	<div class="admin-kpis">
		<article class="admin-kpi is-orange">
			<strong class="stat-num"><?= $views ?></strong>
			<span>Páginas vistas</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($report['people'] ?? 0) ?></strong>
			<span>Visitantes</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($report['sessions'] ?? 0) ?></strong>
			<span>Sesiones</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= h((string) ($report['pages'] ?? 0)) ?></strong>
			<span>Páginas por visita</span>
		</article>
		<article class="admin-kpi">
			<strong class="stat-num"><?= (int) ($report['bounce'] ?? 0) ?>%</strong>
			<span>Salieron en la primera página</span>
		</article>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Visitas por día</h2>
			<?php $bar($report['days'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>De dónde llegan</h2>
			<?php $bar($report['sources'] ?? []); ?>
		</section>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Páginas más vistas</h2>
			<?php $bar($report['pages_top'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>Página de entrada</h2>
			<?php $bar($report['landings'] ?? []); ?>
		</section>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Campañas (utm)</h2>
			<?php $bar($report['campaigns'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>Horario (Chile)</h2>
			<?php $bar($report['hours'] ?? []); ?>
		</section>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Dispositivo</h2>
			<?php $bar($report['devices'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>Navegador</h2>
			<?php $bar($report['browsers'] ?? []); ?>
		</section>
	</div>

	<div class="admin-grid">
		<section class="admin-card">
			<h2>Sistema</h2>
			<?php $bar($report['systems'] ?? []); ?>
		</section>
		<section class="admin-card">
			<h2>Idioma y país</h2>
			<?php
			$geo = array_merge($report['countries'] ?? [], $report['languages'] ?? []);
			$bar($geo);
			?>
		</section>
	</div>

	<section class="admin-card">
		<h2>Últimas visitas</h2>
		<div class="table-wrap">
			<table class="sheet">
				<thead>
					<tr>
						<th>Hora</th>
						<th>Página</th>
						<th>Origen</th>
						<th>Campaña</th>
						<th>Equipo</th>
						<th>Idioma</th>
						<th>País</th>
						<th>IP</th>
					</tr>
				</thead>
				<tbody>
				<?php if (($report['recent'] ?? []) === []): ?>
					<tr><td colspan="8">Cuando alguien entre al sitio, la visita aparece aquí.</td></tr>
				<?php else: ?>
					<?php foreach ($report['recent'] as $row): ?>
						<tr>
							<td><?= h(substr((string) $row['visited_at'], 11, 5)) ?> · <?= h(substr((string) $row['visited_at'], 8, 2)) ?>/<?= h(substr((string) $row['visited_at'], 5, 2)) ?></td>
							<td>
								<a href="<?= h((string) $row['path']) ?>" target="_blank" rel="noopener"><?= h((string) $row['path']) ?></a>
								<?php if (!empty($row['title'])): ?><br><span class="muted"><?= h((string) $row['title']) ?></span><?php endif; ?>
							</td>
							<td>
								<?= h((string) $row['source']) ?>
								<?php if (!empty($row['referrer']) && (string) $row['source'] !== 'Directo'): ?><br><span class="muted"><?= h((string) $row['referrer']) ?></span><?php endif; ?>
							</td>
							<td><?= h((string) ($row['utm_campaign'] ?: $row['utm_medium'])) ?></td>
							<td><?= h((string) $row['device']) ?> · <?= h((string) $row['browser']) ?> · <?= h((string) $row['os']) ?><?php if (!empty($row['screen'])): ?><br><span class="muted"><?= h((string) $row['screen']) ?></span><?php endif; ?></td>
							<td><?= h((string) $row['language']) ?></td>
							<td><?= h((string) ($row['country'] ?: '—')) ?></td>
							<td><?= h((string) $row['ip']) ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
<style>
.visit-bars { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.visit-bars li { display: grid; grid-template-columns: minmax(88px, 180px) 1fr 36px; gap: 8px; align-items: center; font-size: 13px; }
.visit-bars span, .visit-bars em { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.visit-bars em { font-style: normal; text-align: right; color: #605e5c; }
.visit-bars b { display: block; height: 8px; background: #1c9bd8; border-radius: 99px; min-width: 2px; }
.vivo-list, .vivo-chats { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; max-height: 560px; overflow: auto; }
.vivo-list li, .vivo-chats button { border: 1px solid #d6e4f0; background: #fff; border-radius: 8px; padding: 10px 12px; text-align: left; }
.vivo-chats button { width: 100%; cursor: pointer; font: inherit; }
.vivo-chats button.is-on { border-color: #1c9bd8; background: #f4f8fc; }
#vivo-panel { grid-template-columns: minmax(240px, 320px) 1fr; }
.vivo-chat { display: grid; grid-template-columns: 260px 1fr; gap: 12px; min-height: 520px; }
.vivo-thread { min-height: 420px; max-height: 62vh; overflow: auto; display: grid; gap: 8px; align-content: start; font-size: 15px; }
.vivo-msg { padding: 8px 10px; border-radius: 8px; background: #f4f8fc; }
.vivo-msg.is-mizo { background: #fff4ea; }
.vivo-msg small { display: block; color: #605e5c; }
#vivo-reply { display: grid; gap: 8px; margin-top: 8px; }
#vivo-reply textarea { width: 100%; min-height: 84px; font-size: 16px; }
@media (max-width: 720px) { .vivo-chat { grid-template-columns: 1fr; } }
</style>
<script>
(function () {
	var panel = document.getElementById('vivo-panel');
	if (!panel) return;
	var chatId = 0;
	function text(value) {
		return String(value || '').replace(/[&<>"']/g, function (c) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
		});
	}
	function paint(data) {
		var online = data.online || [];
		document.getElementById('vivo-count').textContent = String(online.length);
		var list = document.getElementById('vivo-list');
		list.innerHTML = online.length ? online.map(function (row) {
			return '<li><strong>' + text(row.code || '') + '</strong> · ' + text(row.path) + '<br><span class="muted">' + text(row.device || 'Visitante') + (row.referrer ? ' · ' + text(row.referrer) : ' · Directo') + '</span></li>';
		}).join('') : '<li class="muted">Nadie en este momento.</li>';
		var chats = data.chats || [];
		document.getElementById('vivo-chats').innerHTML = chats.length ? chats.map(function (row) {
			return '<li><button type="button" data-chat="' + row.id + '" class="' + (Number(row.id) === chatId ? 'is-on' : '') + '"><strong>' + text(row.code || '') + ' · ' + text(row.visitor_name || 'Visitante') + (Number(row.unread) > 0 ? ' · nuevo' : '') + '</strong><br><span class="muted">' + text(row.preview || row.page || '') + '</span></button></li>';
		}).join('') : '<li class="muted">Sin conversaciones.</li>';
		if (chatId && Array.isArray(data.thread)) {
			var thread = document.getElementById('vivo-thread');
			thread.innerHTML = data.thread.length ? data.thread.map(function (msg) {
				return '<div class="vivo-msg' + (msg.author === 'mizo' ? ' is-mizo' : '') + '"><small>' + (msg.author === 'mizo' ? 'Mizo' : 'Visitante') + ' · ' + text(String(msg.created_at || '').slice(11, 16)) + '</small>' + text(msg.body) + '</div>';
			}).join('') : '<p class="muted">Sin mensajes.</p>';
			thread.scrollTop = thread.scrollHeight;
		}
	}
	function load() {
		var url = panel.getAttribute('data-vivo') + (chatId ? '?chat=' + chatId : '');
		fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(paint)
			.catch(function () {});
	}
	document.getElementById('vivo-chats').addEventListener('click', function (event) {
		var button = event.target.closest('[data-chat]');
		if (!button) return;
		chatId = Number(button.getAttribute('data-chat')) || 0;
		document.getElementById('vivo-chat-id').value = String(chatId);
		load();
	});
	document.getElementById('vivo-reply').addEventListener('submit', function (event) {
		event.preventDefault();
		if (!chatId) return;
		var form = event.currentTarget;
		var body = new FormData(form);
		body.append('_csrf', panel.getAttribute('data-csrf') || '');
		body.set('chat_id', String(chatId));
		fetch(panel.getAttribute('data-reply'), { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (res) { return res.json(); })
			.then(function () { form.body.value = ''; load(); })
			.catch(function () {});
	});
	load();
	setInterval(load, 4000);
})();
</script>
