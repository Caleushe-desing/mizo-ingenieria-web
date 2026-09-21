<?php
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;

$d = $deal ?? [];
$action = $d ? Http::url('/negocios/' . $d['id']) : Http::url('/negocios');
$selectedClient = (int) ($d['client_id'] ?? $prefillClient ?? 0);
$embed = $embed ?? false;
?>
<?php if (!$embed): ?>
<p class="kicker">Negocio</p>
<h1><?= $d ? 'Editar negocio' : 'Nuevo negocio' ?></h1>
<?php endif; ?>
<form class="form card" method="post" action="<?= h($action) ?>" style="margin-top:20px;padding:20px">
	<?= Csrf::field() ?>
	<label>
		<span>Cliente</span>
		<?php if ($d): ?>
			<input value="<?= h($d['client_name'] ?? '') ?>" disabled>
		<?php else: ?>
			<select name="client_id" required>
				<option value="">Selecciona un cliente</option>
				<?php foreach ($clients as $client): ?>
					<option value="<?= (int) $client['id'] ?>" <?= $selectedClient === (int) $client['id'] ? 'selected' : '' ?>><?= h($client['name']) ?></option>
				<?php endforeach; ?>
			</select>
			<p><a href="<?= h(Http::url('/clientes/nuevo')) ?>">Crear cliente primero</a></p>
		<?php endif; ?>
	</label>
	<label><span>Título del negocio</span><input name="title" required value="<?= h($d['title'] ?? '') ?>" placeholder="Ej. Audio iglesia Frutillar"></label>
	<div class="form-row">
		<label>
			<span>Línea de servicio</span>
			<select name="service">
				<?php foreach (Config::services() as $key => $label): ?>
					<option value="<?= h($key) ?>" <?= (($d['service'] ?? '') === $key) ? 'selected' : '' ?>><?= h($label) ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span>Etapa</span>
			<select name="stage">
				<?php foreach (Config::stages() as $key => $label): ?>
					<option value="<?= h($key) ?>" <?= (($d['stage'] ?? 'nuevo') === $key) ? 'selected' : '' ?>><?= h($label) ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</div>
	<div class="form-row">
		<label><span>Monto estimado (CLP)</span><input name="amount" value="<?= h((string) ($d['amount'] ?? '')) ?>"></label>
		<label><span>Cierre estimado</span><input name="expected_close" type="date" value="<?= h($d['expected_close'] ?? '') ?>"></label>
	</div>
	<label><span>Notas internas</span><textarea name="notes" rows="4"><?= h($d['notes'] ?? '') ?></textarea></label>
	<button class="btn" type="submit">Guardar negocio</button>
</form>
