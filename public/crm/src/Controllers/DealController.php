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
use MizoCrm\View;

final class DealController
{
	public function index(): void
	{
		$stage = Http::string('etapa', 30);
		$mine = Http::string('mios') === '1';
		View::render('deals/index', [
			'title' => 'Negocios',
			'stage' => $stage,
			'mine' => $mine,
			'deals' => Deal::withClient($stage !== '' ? $stage : null, $mine ? Auth::id() : null),
		]);
	}

	public function create(): void
	{
		View::render('deals/form', [
			'title' => 'Nuevo negocio',
			'deal' => null,
			'clients' => Client::all('name ASC'),
			'prefillClient' => Http::int('cliente'),
		]);
	}

	public function store(): void
	{
		Csrf::check();
		$clientId = Http::int('client_id');
		if (!Client::find($clientId)) {
			View::flash('error', 'Elige un cliente.');
			Http::redirect('/negocios/nuevo');
		}
		$id = Deal::insert($this->payload($clientId));
		Activity::log('created', 'Negocio creado.', Auth::id(), $clientId, $id);
		View::flash('ok', 'Negocio abierto.');
		Http::redirect('/negocios/' . $id);
	}

	public function show(string $id): void
	{
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::redirect('/negocios');
		}
		$client = Client::find((int) $deal['client_id']);
		View::render('deals/show', [
			'title' => $deal['title'],
			'deal' => $deal,
			'client' => $client,
			'quotes' => Deal::quotes((int) $id),
			'activity' => Activity::forDeal((int) $id),
		]);
	}

	public function update(string $id): void
	{
		Csrf::check();
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::redirect('/negocios');
		}
		$data = $this->payload((int) $deal['client_id']);
		unset($data['created_at'], $data['client_id']);
		Deal::update((int) $id, $data);
		Activity::log('updated', 'Negocio actualizado.', Auth::id(), (int) $deal['client_id'], (int) $id);
		View::flash('ok', 'Negocio guardado.');
		Http::redirect('/negocios/' . $id);
	}

	public function stage(string $id): void
	{
		Csrf::check();
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::redirect('/negocios');
		}
		$stage = Http::string('stage', 30);
		if (!isset(Config::stages()[$stage])) {
			Http::redirect('/negocios/' . $id);
		}
		$lost = $stage === 'perdido' ? Http::string('lost_reason', 200) : null;
		Deal::update((int) $id, [
			'stage' => $stage,
			'lost_reason' => $lost,
			'updated_at' => date('c'),
		]);
		$label = Config::stages()[$stage];
		Activity::log('stage', 'Etapa: ' . $label . ($lost ? ' — ' . $lost : ''), Auth::id(), (int) $deal['client_id'], (int) $id);
		View::flash('ok', 'Etapa actualizada a ' . $label . '.');
		Http::redirect('/negocios/' . $id);
	}

	public function note(string $id): void
	{
		Csrf::check();
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::redirect('/negocios');
		}
		$message = trim((string) ($_POST['message'] ?? ''));
		if ($message !== '') {
			Activity::log('note', $message, Auth::id(), (int) $deal['client_id'], (int) $id);
		}
		Http::redirect('/negocios/' . $id);
	}

	private function payload(int $clientId): array
	{
		$now = date('c');
		return [
			'client_id' => $clientId,
			'title' => Http::string('title', 180) ?: 'Nuevo negocio',
			'service' => Http::string('service', 40) ?: 'otro',
			'stage' => Http::string('stage', 30) ?: 'nuevo',
			'amount' => Http::money('amount'),
			'expected_close' => Http::string('expected_close', 20) ?: null,
			'notes' => trim((string) ($_POST['notes'] ?? '')),
			'owner_id' => Auth::id(),
			'updated_at' => $now,
			'created_at' => $now,
		];
	}
}
