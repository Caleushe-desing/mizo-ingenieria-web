<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\App;
use MizoCrm\Auth;
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mailer;
use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\Deal;
use MizoCrm\Models\Quote;
use MizoCrm\Models\User;
use MizoCrm\View;

final class WorkController
{
	public function inbox(): void
	{
		$filter = Http::string('ver', 20) ?: 'todas';
		if (!isset(Config::workStatuses()[$filter]) && !in_array($filter, ['todas', 'libre'], true)) {
			$filter = 'todas';
		}
		if ($filter === 'libre' && !Auth::isAdmin()) {
			$filter = 'todas';
		}
		$q = Http::string('q', 80);
		$ownerId = Auth::ownerScope();
		if (Auth::isAdmin()) {
			$picked = Http::int('ejecutivo');
			$ownerId = $picked > 0 ? $picked : null;
		}
		View::render('inbox', [
			'title' => 'Bandeja',
			'filter' => $filter,
			'q' => $q,
			'items' => Deal::inbox($filter, $q, $ownerId),
			'counts' => Deal::inboxCounts(Auth::ownerScope()),
			'team' => Auth::isAdmin() ? User::team() : [],
			'ejecutivo' => Auth::isAdmin() ? Http::int('ejecutivo') : 0,
		]);
	}

	public function create(): void
	{
		$service = Http::string('servicio', 20) ?: 'sonido';
		if (!isset(Config::services()[$service])) {
			$service = 'sonido';
		}
		View::render('work', [
			'title' => 'Nuevo caso',
			'deal' => null,
			'client' => null,
			'quote' => null,
			'items' => [[
				'description' => Config::defaultLine($service),
				'quantity' => 1,
				'unit' => 'un',
				'unit_price' => 0,
			]],
			'activity' => [],
			'status' => 'pendiente',
			'service' => $service,
			'publicUrl' => '',
		]);
	}

	public function store(): void
	{
		Csrf::check();
		[, $dealId] = $this->saveRecord(null);
		if (Http::string('intent', 20) === 'send') {
			$this->sendQuote($dealId);
			return;
		}
		View::flash('ok', 'Quedó en la bandeja. Puedes enviarla cuando esté lista.');
		Http::redirect('/t/' . $dealId);
	}

	public function home(): void
	{
		Http::redirect('/');
	}

	public function show(string $id): void
	{
		$deal = Auth::requireDeal(Deal::find((int) $id));
		$client = Client::find((int) $deal['client_id']);
		$quote = Deal::latestQuote((int) $id);
		$deal['quote_status'] = $quote['status'] ?? '';
		$items = $quote ? Quote::items((int) $quote['id']) : [];
		if ($items === []) {
			$items = [[
				'description' => Config::defaultLine((string) $deal['service']),
				'quantity' => 1,
				'unit' => 'un',
				'unit_price' => 0,
			]];
		}
		$owner = !empty($deal['owner_id']) ? User::find((int) $deal['owner_id']) : null;
		View::render('work', [
			'title' => $client['name'] ?? 'Caso',
			'deal' => $deal,
			'client' => $client,
			'quote' => $quote,
			'items' => $items,
			'activity' => Activity::forClient((int) $deal['client_id']),
			'history' => array_values(array_filter(
				Client::deals((int) $deal['client_id']),
				static fn(array $row): bool => (int) $row['id'] !== (int) $id
			)),
			'status' => \work_status($deal),
			'service' => $deal['service'],
			'publicUrl' => $quote ? App::absolute('/q/' . $quote['token']) : '',
			'owner' => $owner,
			'team' => Auth::isAdmin() ? User::team() : [],
		]);
	}

	public function update(string $id): void
	{
		Csrf::check();
		$deal = Auth::requireDeal(Deal::find((int) $id));
		$this->saveRecord($deal);
		if (Http::string('intent', 20) === 'send') {
			$this->sendQuote((int) $id);
			return;
		}
		View::flash('ok', 'Ficha actualizada.');
		Http::redirect('/t/' . $id);
	}

	public function send(string $id): void
	{
		Csrf::check();
		Auth::requireDeal(Deal::find((int) $id));
		$this->sendQuote((int) $id);
	}

	public function close(string $id): void
	{
		Csrf::check();
		$deal = Auth::requireDeal(Deal::find((int) $id));
		$outcome = Http::string('outcome', 20);
		$now = date('c');
		if ($outcome === 'ganada') {
			Deal::update((int) $id, [
				'stage' => 'ganado',
				'job_status' => 'ganado',
				'lost_reason' => null,
				'updated_at' => $now,
			]);
			Activity::log('won', 'Trabajo aprobado / ganada.', Auth::id(), (int) $deal['client_id'], (int) $id);
			View::flash('ok', 'Quedó ganada. Sigue el trabajo en la ficha.');
		} elseif ($outcome === 'perdida') {
			$reason = Http::string('lost_reason', 200);
			Deal::update((int) $id, [
				'stage' => 'perdido',
				'job_status' => 'perdido',
				'lost_reason' => $reason,
				'updated_at' => $now,
			]);
			Activity::log('lost', 'Marcada como perdida' . ($reason !== '' ? ': ' . $reason : '.'), Auth::id(), (int) $deal['client_id'], (int) $id);
			View::flash('ok', 'Quedó como perdida.');
		} elseif ($outcome === 'entregado') {
			Deal::update((int) $id, [
				'stage' => 'ganado',
				'job_status' => 'entregado',
				'updated_at' => $now,
			]);
			Activity::log('instalacion', 'Trabajo marcado como entregado.', Auth::id(), (int) $deal['client_id'], (int) $id);
			View::flash('ok', 'Quedó entregado en el historial.');
		}
		Http::redirect('/t/' . $id);
	}

	public function note(string $id): void
	{
		Csrf::check();
		$deal = Auth::requireDeal(Deal::find((int) $id));
		$message = trim((string) ($_POST['note'] ?? ''));
		$kind = Http::string('kind', 20);
		if (!isset(Config::activityKinds()[$kind])) {
			$kind = 'nota';
		}
		if ($message !== '') {
			$label = Config::activityKinds()[$kind];
			Activity::log($kind, $label . ': ' . $message, Auth::id(), (int) $deal['client_id'], (int) $id);
			Deal::update((int) $id, ['updated_at' => date('c')]);
			View::flash('ok', 'Quedó en el historial del cliente.');
		}
		Http::redirect('/t/' . $id);
	}

	public function assign(string $id): void
	{
		Csrf::check();
		Auth::requireAdmin();
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::redirect('/');
		}
		$ownerId = Http::int('owner_id');
		$owner = $ownerId ? User::find($ownerId) : null;
		Deal::update((int) $id, [
			'owner_id' => $owner ? $ownerId : null,
			'updated_at' => date('c'),
		]);
		Client::update((int) $deal['client_id'], [
			'owner_id' => $owner ? $ownerId : null,
			'updated_at' => date('c'),
		]);
		Activity::log(
			'assigned',
			$owner ? 'Asignado a ' . $owner['name'] . '.' : 'Quedó sin ejecutivo.',
			Auth::id(),
			(int) $deal['client_id'],
			(int) $id
		);
		View::flash('ok', $owner ? 'El caso quedó a cargo de ' . $owner['name'] . '.' : 'El caso quedó sin asignar.');
		Http::redirect('/t/' . $id);
	}

	public function preview(string $id): void
	{
		$deal = Auth::requireDeal(Deal::find((int) $id));
		$quote = Deal::latestQuote((int) $id);
		if (!$quote) {
			View::flash('error', 'Guarda la cotización antes de previsualizarla.');
			Http::redirect('/t/' . $id);
		}
		$client = Client::find((int) $deal['client_id']);
		$quote['deal_title'] = $deal['title'] ?? '';
		$quote['client_name'] = $client['name'] ?? '';
		$quote['contact_name'] = $client['contact_name'] ?? '';
		View::render('quotes/public', [
			'title' => 'Vista previa ' . $quote['number'],
			'quote' => $quote,
			'items' => Quote::items((int) $quote['id']),
			'preview' => true,
		], 'public-layout');
	}

	public function fromQuote(string $id): void
	{
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		Auth::requireDeal(Deal::find((int) $quote['deal_id']));
		Http::redirect('/t/' . $quote['deal_id']);
	}

	public function fromQuotePreview(string $id): void
	{
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		Auth::requireDeal(Deal::find((int) $quote['deal_id']));
		Http::redirect('/t/' . $quote['deal_id'] . '/preview');
	}

	public function fromClient(string $id): void
	{
		$deals = Client::deals((int) $id);
		foreach ($deals as $deal) {
			if (Auth::canAccessDeal($deal)) {
				Http::redirect('/t/' . $deal['id']);
			}
		}
		Http::redirect('/');
	}

	/** @return array{0:int,1:int} */
	private function saveRecord(?array $deal): array
	{
		$name = Http::string('name', 120);
		$email = Http::string('email', 160);
		$phone = Http::string('phone', 40);
		$service = Http::string('service', 20);
		if (!isset(Config::services()[$service])) {
			$service = 'otro';
		}
		$title = Http::string('title', 160);
		$need = trim((string) ($_POST['need'] ?? ''));
		if ($title === '') {
			$title = $need !== '' ? (function_exists('mb_substr') ? mb_substr($need, 0, 80, 'UTF-8') : substr($need, 0, 80)) : Config::services()[$service];
		}
		$city = Http::string('city', 80);
		$rut = Http::string('rut', 20);
		$site = trim((string) ($_POST['site_address'] ?? ''));
		$visitAt = Http::string('visit_at', 20);
		$jobStatus = Http::string('job_status', 30);
		if (!isset(Config::jobStatuses()[$jobStatus])) {
			$jobStatus = $deal['job_status'] ?? 'consulta';
		}
		$back = $deal ? '/t/' . $deal['id'] : '/nueva';
		if ($name === '' || ($email === '' && $phone === '')) {
			View::flash('error', 'Indica el nombre y un correo o teléfono.');
			Http::redirect($back);
		}
		if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			View::flash('error', 'El correo no es válido.');
			Http::redirect($back);
		}
		if (!Auth::isAdmin() && ($conflict = Client::conflictForUser($email, $phone, Auth::id()))) {
			View::flash('error', 'Ese cliente ya lo lleva otro ejecutivo. Pide al administrador que te lo asigne.');
			Http::redirect($back);
		}

		$now = date('c');
		$clientId = Client::findOrCreate($name, $email, $phone, 'otro', $need, Auth::id());
		$clientPayload = ['updated_at' => $now];
		if (isset($_POST['city'])) {
			$clientPayload['city'] = $city;
		}
		if (isset($_POST['rut'])) {
			$clientPayload['rut'] = $rut;
		}
		Client::update($clientId, $clientPayload);
		if ($deal) {
			$dealId = (int) $deal['id'];
			$payload = [
				'title' => $title,
				'service' => $service,
				'notes' => $need,
				'updated_at' => $now,
			];
			if (isset($_POST['site_address'])) {
				$payload['site_address'] = $site;
			}
			if (isset($_POST['visit_at'])) {
				$payload['visit_at'] = $visitAt !== '' ? $visitAt : null;
			}
			if (isset($_POST['job_status']) && isset(Config::jobStatuses()[$jobStatus])) {
				$payload['job_status'] = $jobStatus;
			}
			if (!in_array($deal['stage'], ['perdido'], true)) {
				$payload['client_id'] = $clientId;
			}
			Deal::update($dealId, $payload);
		} else {
			$dealId = Deal::insert([
				'client_id' => $clientId,
				'title' => $title,
				'service' => $service,
				'stage' => \MizoCrm\Models\Stage::firstSlug(),
				'amount' => 0,
				'expected_close' => null,
				'lost_reason' => null,
				'notes' => $need,
				'owner_id' => Auth::id(),
				'job_status' => $jobStatus !== '' ? $jobStatus : 'consulta',
				'site_address' => $site,
				'visit_at' => $visitAt !== '' ? $visitAt : null,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			Activity::log('deal_created', 'Caso creado para ' . $name . '.', Auth::id(), $clientId, $dealId);
		}

		$this->persistQuote($dealId, $clientId);
		return [$clientId, $dealId];
	}

	private function persistQuote(int $dealId, int $clientId): ?array
	{
		if (!isset($_POST['item_description'])) {
			return Deal::latestQuote($dealId);
		}

		$quote = Deal::latestQuote($dealId);
		$now = date('c');
		$intro = trim((string) ($_POST['intro'] ?? ''));
		$notes = trim((string) ($_POST['notes'] ?? ''));
		$validUntil = Http::string('valid_until', 20) ?: date('Y-m-d', strtotime('+15 days'));
		$items = Quote::itemsFromPost();
		$hasItems = false;
		foreach ($items as $item) {
			if (trim((string) ($item['description'] ?? '')) !== '') {
				$hasItems = true;
				break;
			}
		}

		if (!$quote) {
			if (!$hasItems) {
				return null;
			}
			$id = Quote::insert([
				'number' => Quote::nextNumber(),
				'deal_id' => $dealId,
				'client_id' => $clientId,
				'status' => 'borrador',
				'intro' => $intro,
				'notes' => $notes !== '' ? $notes : 'Validez 15 días. Precios en pesos chilenos, neto + IVA. Instalación sujeta a visita técnica.',
				'valid_until' => $validUntil,
				'tax_rate' => 19,
				'subtotal' => 0,
				'tax' => 0,
				'total' => 0,
				'token' => bin2hex(random_bytes(16)),
				'created_by' => Auth::id(),
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$totals = Quote::saveItems($id, $items);
			Quote::update($id, [...$totals, 'updated_at' => $now]);
			Deal::update($dealId, ['amount' => $totals['total'], 'updated_at' => $now]);
			$created = Quote::find($id);
			Activity::log('quote_created', 'Cotización ' . ($created['number'] ?? '') . ' creada.', Auth::id(), $clientId, $dealId, $id);
			return $created;
		}

		if ($quote['status'] === 'aceptada') {
			return $quote;
		}
		$totals = Quote::saveItems((int) $quote['id'], $items);
		Quote::update((int) $quote['id'], [
			'intro' => $intro,
			'notes' => $notes,
			'valid_until' => $validUntil,
			...$totals,
			'updated_at' => $now,
		]);
		Deal::update($dealId, ['amount' => $totals['total'], 'updated_at' => $now]);
		return Quote::find((int) $quote['id']);
	}

	private function sendQuote(int $dealId): void
	{
		$deal = Auth::requireDeal(Deal::find($dealId));
		$client = Client::find((int) $deal['client_id']);
		$quote = $this->persistQuote($dealId, (int) $deal['client_id']);
		if (!$quote) {
			View::flash('error', 'Agrega al menos una partida antes de enviar.');
			Http::redirect('/t/' . $dealId);
		}
		$to = Http::string('sent_to', 160) ?: (string) ($client['email'] ?? '');
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			View::flash('error', 'El cliente no tiene un correo válido.');
			Http::redirect('/t/' . $dealId);
		}
		$items = Quote::items((int) $quote['id']);
		if ($items === []) {
			View::flash('error', 'Agrega al menos una partida antes de enviar.');
			Http::redirect('/t/' . $dealId);
		}
		$quote['deal_title'] = $deal['title'] ?? '';
		$url = App::absolute('/q/' . $quote['token']);
		$html = Mailer::quoteHtml($quote, $items, $client ?? ['name' => '', 'contact_name' => ''], $url);
		$user = Auth::user();
		$ok = Mailer::send($to, 'Cotización ' . $quote['number'] . ' — Mizo', $html, $user['email'] ?? '');
		if (!$ok) {
			View::flash('error', 'No se pudo enviar el correo desde este equipo. En el servidor sí sale. La cotización quedó guardada.');
			Http::redirect('/t/' . $dealId);
		}
		Quote::update((int) $quote['id'], [
			'status' => 'enviada',
			'sent_at' => date('c'),
			'sent_to' => $to,
			'updated_at' => date('c'),
		]);
		\MizoCrm\Models\Pipeline::onQuoteSent($dealId);
		Activity::log(
			'quote_sent',
			'Cotización ' . $quote['number'] . ' enviada a ' . $to . '.',
			Auth::id(),
			(int) $quote['client_id'],
			$dealId,
			(int) $quote['id']
		);
		View::flash('ok', 'Cotización enviada a ' . $to . '. Quedó registrada.');
		Http::redirect('/t/' . $dealId);
	}
}
