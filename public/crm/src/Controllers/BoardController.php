<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\Deal;
use MizoCrm\Models\User;
use MizoCrm\View;

final class BoardController
{
	public function index(): void
	{
		View::render('board/index', [
			'title' => 'Tablero',
			'stages' => \MizoCrm\Models\Stage::labels(),
			'stageColors' => \MizoCrm\Models\Stage::colors(),
			'stageRows' => \MizoCrm\Models\Stage::rows(),
			'stageCounts' => Auth::isAdmin() ? \MizoCrm\Models\Stage::dealCounts() : [],
			'services' => Config::services(),
			'cards' => Deal::board(Auth::ownerScope()),
			'team' => Auth::isAdmin() ? User::team() : [],
			'ownerFilter' => 0,
		]);
	}

	public function file(string $id): void
	{
		$client = Auth::requireClient(Client::find((int) $id));
		$deals = Client::deals((int) $client['id']);
		$byDeal = Deal::contactsByDeal(array_map(static fn(array $row): int => (int) $row['id'], $deals));
		$projects = [];
		foreach ($deals as $row) {
			$projects[] = [
				'id' => (int) $row['id'],
				'title' => (string) $row['title'],
				'service' => Config::services()[$row['service']] ?? (string) $row['service'],
				'stage_label' => Config::stages()[$row['stage']] ?? (string) $row['stage'],
				'contacts' => $byDeal[(int) $row['id']] ?? [],
			];
		}
		View::render('board/file', [
			'title' => $client['name'],
			'client' => $client,
			'contacts' => \MizoCrm\Models\ClientContact::forClient((int) $id),
			'comments' => Activity::notesForClient((int) $id),
			'quotes' => Client::quotes((int) $id),
			'mails' => \MizoCrm\Models\MailMessage::forClient(Auth::id(), (int) $id),
			'projects' => $projects,
			'services' => Config::services(),
			'team' => Auth::isAdmin() ? User::team() : [],
			'audit' => Auth::isAdmin() ? \MizoCrm\Models\AdminReport::client((int) $client['id']) : null,
		]);
	}

	public function storeProject(string $id): void
	{
		Csrf::check();
		$client = Auth::requireClient(Client::find((int) $id));
		$title = Http::string('title', 120);
		$service = Http::string('service', 30);
		if (!isset(Config::services()[$service])) {
			$service = 'otro';
		}
		if ($title === '') {
			View::flash('error', 'Escribe el nombre del proyecto.');
			Http::redirect('/tablero/cliente/' . $id . '/ficha');
		}
		$now = date('c');
		$dealId = Deal::insert([
			'client_id' => (int) $client['id'],
			'title' => $title,
			'service' => $service,
			'stage' => \MizoCrm\Models\Stage::firstSlug(),
			'amount' => 0,
			'expected_close' => null,
			'lost_reason' => null,
			'notes' => '',
			'owner_id' => (int) ($client['owner_id'] ?: Auth::id()),
			'job_status' => 'consulta',
			'site_address' => null,
			'visit_at' => null,
			'created_at' => $now,
			'updated_at' => $now,
		]);
		Deal::setContacts($dealId, (int) $client['id'], self::contactIdsFromPost());
		Client::update((int) $client['id'], ['updated_at' => $now]);
		View::flash('ok', 'Proyecto creado. Ya aparece en el tablero.');
		Http::redirect('/tablero/cliente/' . $id . '/ficha');
	}

	public function assignContacts(string $id): void
	{
		Csrf::check();
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $deal['client_id']));
		Deal::setContacts((int) $deal['id'], (int) $client['id'], self::contactIdsFromPost());
		View::flash('ok', 'Contactos del proyecto actualizados.');
		if (Http::string('volver', 20) === 'tablero') {
			Http::redirect('/');
		}
		Http::redirect('/tablero/cliente/' . $client['id'] . '/ficha');
	}

	public function destroyProject(string $id): void
	{
		Csrf::check();
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $deal['client_id']));
		$title = (string) $deal['title'];
		Deal::purge((int) $deal['id']);
		View::flash('ok', 'Se eliminó el proyecto ' . $title . '. El cliente sigue en la lista.');
		if (Http::string('volver', 20) === 'tablero') {
			Http::redirect('/');
		}
		Http::redirect('/tablero/cliente/' . $client['id'] . '/ficha');
	}

	public function project(string $id): void
	{
		Auth::requireUser();
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::json(['ok' => false, 'error' => 'Ese proyecto no existe.'], 404);
		}
		$client = Client::find((int) $deal['client_id']);
		if (!$client || !Auth::canAccessClient($client)) {
			Http::json(['ok' => false, 'error' => 'Ese proyecto no está a tu cargo.'], 403);
		}
		$service = (string) ($deal['service'] ?? 'otro');
		$notes = [];
		foreach (Activity::notesForDeal((int) $deal['id']) as $note) {
			$notes[] = [
				'message' => (string) $note['message'],
				'who' => (string) ($note['user_name'] ?: 'Sistema'),
				'when' => when($note['created_at'] ?? null),
				'type' => (string) $note['type'],
			];
		}
		$assigned = Deal::contactsByDeal([(int) $deal['id']])[(int) $deal['id']] ?? [];
		$people = [];
		foreach (\MizoCrm\Models\ClientContact::forClient((int) $client['id']) as $contact) {
			$people[] = [
				'id' => (int) $contact['id'],
				'name' => (string) ($contact['name'] ?: 'Contacto'),
				'title' => (string) ($contact['title'] ?? ''),
				'email' => (string) ($contact['email'] ?? ''),
				'phone' => (string) ($contact['phone'] ?? ''),
				'on' => in_array((int) $contact['id'], array_map(static fn(array $row): int => (int) $row['id'], $assigned), true),
			];
		}
		$quotes = [];
		foreach (Deal::quotes((int) $deal['id']) as $quote) {
			$quotes[] = [
				'id' => (int) $quote['id'],
				'number' => (string) $quote['number'],
				'status' => quote_status_label((string) $quote['status']),
				'total' => (int) $quote['total'],
			];
		}
		Http::json([
			'ok' => true,
			'client' => [
				'id' => (int) $client['id'],
				'name' => (string) $client['name'],
			],
			'deal' => [
				'id' => (int) $deal['id'],
				'stage' => (string) $deal['stage'],
				'stage_label' => Config::stages()[$deal['stage']] ?? $deal['stage'],
				'service' => $service,
				'service_label' => Config::services()[$service] ?? $service,
				'amount' => (int) $deal['amount'],
				'title' => (string) $deal['title'],
			],
			'notes' => $notes,
			'quotes' => $quotes,
			'contacts' => $people,
		]);
	}

	public function show(string $id): void
	{
		$user = Auth::requireUser();
		$client = Client::find((int) $id);
		if (!$client || !Auth::canAccessClient($client)) {
			Http::json(['ok' => false, 'error' => 'Ese cliente no está a tu cargo.'], 403);
		}
		$deal = Deal::latestForClient((int) $client['id']);
		$service = (string) ($deal['service'] ?? 'otro');
		$notes = Activity::commentsForClient((int) $client['id']);
		$out = [];
		foreach (array_slice($notes, 0, 12) as $note) {
			$out[] = [
				'message' => (string) $note['message'],
				'who' => (string) ($note['user_name'] ?: 'Sistema'),
				'when' => when($note['created_at'] ?? null),
			];
		}
		$quoteId = 0;
		$quoteNumber = '';
		$quoteTotal = 0;
		if ($deal) {
			$quotes = Deal::quotes((int) $deal['id']);
			if ($quotes) {
				$quoteId = (int) $quotes[0]['id'];
				$quoteNumber = (string) $quotes[0]['number'];
				$quoteTotal = (int) $quotes[0]['total'];
			}
		}
		Http::json([
			'ok' => true,
			'client' => [
				'id' => (int) $client['id'],
				'name' => (string) $client['name'],
				'rut' => (string) ($client['rut'] ?? ''),
				'city' => (string) ($client['city'] ?? ''),
			],
			'deal' => $deal ? [
				'id' => (int) $deal['id'],
				'stage' => (string) $deal['stage'],
				'stage_label' => Config::stages()[$deal['stage']] ?? $deal['stage'],
				'service' => $service,
				'service_label' => Config::services()[$service] ?? $service,
				'amount' => (int) $deal['amount'],
				'title' => (string) $deal['title'],
			] : null,
			'playbook' => Config::playbook($service),
			'notes' => $out,
			'quote' => $quoteId > 0 ? [
				'id' => $quoteId,
				'number' => $quoteNumber,
				'total' => $quoteTotal,
			] : null,
			'viewer' => (string) $user['name'],
		]);
	}

	public function move(): void
	{
		Csrf::check();
		$deal = Deal::find(Http::int('deal_id'));
		if (!$deal) {
			Http::json(['ok' => false, 'error' => 'Ese proyecto no existe.'], 404);
		}
		$client = Client::find((int) $deal['client_id']);
		if (!$client || !Auth::canAccessClient($client)) {
			Http::json(['ok' => false, 'error' => 'Ese proyecto no está a tu cargo.'], 403);
		}
		$stage = Http::string('stage', 30);
		if (!isset(Config::stages()[$stage])) {
			Http::json(['ok' => false, 'error' => 'Esa etapa no existe.'], 422);
		}
		$now = date('c');
		Deal::update((int) $deal['id'], [
			'stage' => $stage,
			'updated_at' => $now,
		]);
		$fromLabel = Config::stages()[$deal['stage']] ?? (string) $deal['stage'];
		$toLabel = Config::stages()[$stage];
		if ((string) $deal['stage'] !== $stage) {
			Activity::log(
				'stage',
				'Movió «' . $deal['title'] . '» de «' . $fromLabel . '» a «' . $toLabel . '»',
				Auth::id(),
				(int) $client['id'],
				(int) $deal['id']
			);
		}
		Client::update((int) $client['id'], ['updated_at' => $now]);
		Http::json([
			'ok' => true,
			'deal_id' => (int) $deal['id'],
			'stage' => $stage,
			'label' => Config::stages()[$stage],
		]);
	}

	public function projectNote(string $id): void
	{
		Csrf::check();
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::json(['ok' => false, 'error' => 'Ese proyecto no existe.'], 404);
		}
		$client = Client::find((int) $deal['client_id']);
		if (!$client || !Auth::canAccessClient($client)) {
			Http::json(['ok' => false, 'error' => 'Ese proyecto no está a tu cargo.'], 403);
		}
		$message = Http::text('message', 2000);
		if ($message === '') {
			Http::json(['ok' => false, 'error' => 'Escribe la nota.'], 422);
		}
		Activity::log('comentario', $message, Auth::id(), (int) $client['id'], (int) $deal['id']);
		Deal::update((int) $deal['id'], ['updated_at' => date('c')]);
		Http::json([
			'ok' => true,
			'note' => [
				'message' => $message,
				'who' => (string) (Auth::user()['name'] ?? 'Tú'),
				'when' => when(date('c')),
			],
		]);
	}

	public function note(string $id): void
	{
		Csrf::check();
		$client = Client::find((int) $id);
		if (!$client || !Auth::canAccessClient($client)) {
			Http::json(['ok' => false, 'error' => 'Ese cliente no está a tu cargo.'], 403);
		}
		$message = Http::text('message', 2000);
		if ($message === '') {
			Http::json(['ok' => false, 'error' => 'Escribe la nota.'], 422);
		}
		$deal = Deal::latestForClient((int) $client['id']);
		Activity::log('comentario', $message, Auth::id(), (int) $client['id'], $deal ? (int) $deal['id'] : null);
		Client::update((int) $client['id'], ['updated_at' => date('c')]);
		Http::json([
			'ok' => true,
			'note' => [
				'message' => $message,
				'who' => (string) (Auth::user()['name'] ?? 'Tú'),
				'when' => when(date('c')),
			],
		]);
	}


	public function columnsStore(): void
	{
		Csrf::check();
		Auth::requireAdmin();
		try {
			$slug = \MizoCrm\Models\Stage::create((string) ($_POST['label'] ?? ''), (string) ($_POST['color'] ?? ''), (string) ($_POST['kind'] ?? 'open'));
			$label = \MizoCrm\Models\Stage::labels()[$slug] ?? $slug;
			Activity::log('stage', 'Creó la columna «' . $label . '».', Auth::id());
			View::flash('ok', 'Columna agregada al tablero.');
		} catch (\RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/');
	}

	public function columnsUpdate(string $slug): void
	{
		Csrf::check();
		Auth::requireAdmin();
		try {
			\MizoCrm\Models\Stage::save($slug, (string) ($_POST['label'] ?? ''), (string) ($_POST['color'] ?? ''), (string) ($_POST['kind'] ?? 'open'));
			View::flash('ok', 'Columna actualizada.');
		} catch (\RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/');
	}

	public function columnsShift(string $slug): void
	{
		Csrf::check();
		Auth::requireAdmin();
		\MizoCrm\Models\Stage::shift($slug, (($_POST['dir'] ?? '') === 'up') ? -1 : 1);
		Http::redirect('/');
	}

	public function columnsDelete(string $slug): void
	{
		Csrf::check();
		Auth::requireAdmin();
		try {
			$labels = \MizoCrm\Models\Stage::labels();
			$from = $labels[$slug] ?? $slug;
			$moveTo = (string) ($_POST['move_to'] ?? '');
			$n = \MizoCrm\Models\Stage::remove($slug, $moveTo);
			$to = \MizoCrm\Models\Stage::labels()[$moveTo] ?? $moveTo;
			$message = 'Eliminó la columna «' . $from . '».';
			if ($n > 0) {
				$message .= ' Movió ' . $n . ' tarjetas a «' . $to . '».';
			}
			Activity::log('stage', $message, Auth::id());
			View::flash('ok', $n > 0 ? 'Columna eliminada y tarjetas reubicadas.' : 'Columna eliminada.');
		} catch (\RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/');
	}
	/** @return list<int> */
	private static function contactIdsFromPost(): array
	{
		$raw = $_POST['contact_id'] ?? [];
		if (!is_array($raw)) {
			$raw = [$raw];
		}
		$ids = [];
		foreach ($raw as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		return $ids;
	}
}
