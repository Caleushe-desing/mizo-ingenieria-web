<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\App;
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mailer;
use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\Deal;
use MizoCrm\Models\Quote;
use MizoCrm\View;

final class QuoteController
{
	public function create(string $clientId): void
	{
		$client = Auth::requireClient(Client::find((int) $clientId));
		View::render('quotes/form', [
			'title' => 'Nueva cotización',
			'client' => $client,
			'quote' => null,
			'items' => [[
				'description' => '',
				'quantity' => 1,
				'unit' => 'un',
				'unit_price' => 0,
			]],
			'publicUrl' => '',
		]);
	}

	public function store(string $clientId): void
	{
		Csrf::check();
		$client = Auth::requireClient(Client::find((int) $clientId));
		$quoteId = $this->saveQuote($client, null);
		if (Http::string('intent', 20) === 'send') {
			$this->deliver($quoteId);
			return;
		}
		View::flash('ok', 'Cotización guardada. Puedes enviarla cuando esté lista.');
		Http::redirect('/cotizaciones/' . $quoteId);
	}

	public function show(string $id): void
	{
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $quote['client_id']));
		$items = Quote::items((int) $id);
		if ($items === []) {
			$items = [[
				'description' => '',
				'quantity' => 1,
				'unit' => 'un',
				'unit_price' => 0,
			]];
		}
		View::render('quotes/form', [
			'title' => $quote['number'],
			'client' => $client,
			'quote' => $quote,
			'items' => $items,
			'publicUrl' => App::absolute('/q/' . $quote['token']),
		]);
	}

	public function update(string $id): void
	{
		Csrf::check();
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $quote['client_id']));
		$this->saveQuote($client, $quote);
		if (Http::string('intent', 20) === 'send') {
			$this->deliver((int) $id);
			return;
		}
		View::flash('ok', 'Cotización guardada.');
		Http::redirect('/cotizaciones/' . $id);
	}

	public function send(string $id): void
	{
		Csrf::check();
		$this->deliver((int) $id);
	}

	public function destroy(string $id): void
	{
		Csrf::check();
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $quote['client_id']));
		$number = (string) $quote['number'];
		Quote::purge((int) $id);
		View::flash('ok', 'Se eliminó la cotización ' . $number . '.');
		Http::redirect('/clientes/' . $client['id']);
	}

	public function preview(string $id): void
	{
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $quote['client_id']));
		$quote['deal_title'] = $quote['intro'] !== '' ? $quote['intro'] : 'Propuesta técnica';
		$quote['client_name'] = $client['name'] ?? '';
		$quote['contact_name'] = $client['contact_name'] ?? '';
		$quote['client_email'] = $client['email'] ?? '';
		$quote['client_phone'] = $client['phone'] ?? '';
		$quote['client_city'] = $client['city'] ?? '';
		View::render('quotes/public', [
			'title' => 'Vista previa ' . $quote['number'],
			'quote' => $quote,
			'items' => Quote::items((int) $quote['id']),
			'preview' => true,
		], 'public-layout');
	}

	private function saveQuote(array $client, ?array $quote): int
	{
		$items = Quote::itemsFromPost();
		$intro = trim((string) ($_POST['intro'] ?? ''));
		$notes = trim((string) ($_POST['notes'] ?? ''));
		$validUntil = Http::string('valid_until', 20) ?: date('Y-m-d', strtotime('+15 days'));
		$now = date('c');
		$clientId = (int) $client['id'];
		$title = $intro !== '' ? $intro : 'Cotización';
		foreach ($items as $item) {
			$description = trim((string) ($item['description'] ?? ''));
			if ($description !== '') {
				$title = function_exists('mb_substr') ? mb_substr($description, 0, 80, 'UTF-8') : substr($description, 0, 80);
				break;
			}
		}

		if (!$quote) {
			$dealId = Deal::insert([
				'client_id' => $clientId,
				'title' => $title,
				'service' => 'otro',
				'stage' => 'nuevo',
				'amount' => 0,
				'expected_close' => null,
				'lost_reason' => null,
				'notes' => $intro,
				'owner_id' => !empty($client['owner_id']) ? (int) $client['owner_id'] : Auth::id(),
				'job_status' => 'consulta',
				'site_address' => null,
				'visit_at' => null,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$id = Quote::insert([
				'number' => Quote::nextNumber(),
				'deal_id' => $dealId,
				'client_id' => $clientId,
				'status' => 'borrador',
				'intro' => $intro,
				'notes' => $notes !== '' ? $notes : 'Validez 15 días. Precios en pesos chilenos, neto + IVA.',
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
			Deal::update($dealId, ['amount' => $totals['total'], 'title' => $title, 'updated_at' => $now]);
			$created = Quote::find($id);
			Activity::log('quote_created', 'Cotización ' . ($created['number'] ?? '') . ' creada.', Auth::id(), $clientId, $dealId, $id);
			Client::update($clientId, ['updated_at' => $now]);
			return $id;
		}

		if (in_array($quote['status'], ['aceptada', 'rechazada'], true)) {
			return (int) $quote['id'];
		}

		$totals = Quote::saveItems((int) $quote['id'], $items);
		Quote::update((int) $quote['id'], [
			'intro' => $intro,
			'notes' => $notes,
			'valid_until' => $validUntil,
			...$totals,
			'updated_at' => $now,
		]);
		Deal::update((int) $quote['deal_id'], [
			'title' => $title,
			'amount' => $totals['total'],
			'updated_at' => $now,
		]);
		Client::update($clientId, ['updated_at' => $now]);
		return (int) $quote['id'];
	}

	private function deliver(int $quoteId): void
	{
		$quote = Quote::find($quoteId);
		if (!$quote) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $quote['client_id']));
		$this->saveQuote($client, $quote);
		$quote = Quote::find($quoteId);
		if (!$quote) {
			Http::redirect('/');
		}
		$items = Quote::items((int) $quote['id']);
		$hasItems = false;
		foreach ($items as $item) {
			if (trim((string) ($item['description'] ?? '')) !== '') {
				$hasItems = true;
				break;
			}
		}
		if (!$hasItems) {
			View::flash('error', 'Agrega al menos una partida antes de enviar.');
			Http::redirect('/cotizaciones/' . $quoteId);
		}
		$to = Http::string('sent_to', 160) ?: (string) ($client['email'] ?? '');
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			View::flash('error', 'El cliente no tiene un correo válido. Escríbelo arriba y vuelve a enviar.');
			Http::redirect('/cotizaciones/' . $quoteId);
		}
		$quote['deal_title'] = $quote['intro'] !== '' ? $quote['intro'] : ($items[0]['description'] ?? 'Cotización');
		$url = App::absolute('/q/' . $quote['token']);
		$html = Mailer::quoteHtml($quote, $items, $client, $url);
		$user = Auth::user();
		$ok = Mailer::send($to, 'Cotización ' . $quote['number'] . ' — Mizo', $html, $user['email'] ?? '');
		if (!$ok) {
			$hint = \MizoCrm\Models\Mailbox::forUser(Auth::id())
				? 'Revisa la clave de tu casilla en Correo.'
				: 'Conecta tu casilla en Correo para que el cliente te responda ahí.';
			View::flash('error', 'No se pudo enviar el correo. ' . $hint . ' La cotización quedó guardada.');
			Http::redirect('/cotizaciones/' . $quoteId);
		}
		Quote::update((int) $quote['id'], [
			'status' => 'enviada',
			'sent_at' => date('c'),
			'sent_to' => $to,
			'updated_at' => date('c'),
		]);
		Deal::update((int) $quote['deal_id'], [
			'stage' => 'propuesta',
			'job_status' => 'cotizado',
			'updated_at' => date('c'),
		]);
		Activity::log(
			'quote_sent',
			'Cotización ' . $quote['number'] . ' enviada a ' . $to . '.',
			Auth::id(),
			(int) $quote['client_id'],
			(int) $quote['deal_id'],
			(int) $quote['id']
		);
		Client::update((int) $quote['client_id'], ['updated_at' => date('c')]);
		View::flash('ok', 'Cotización enviada a ' . $to . '. Si responde, te llega a Correo.');
		Http::redirect('/clientes/' . $quote['client_id']);
	}
}
