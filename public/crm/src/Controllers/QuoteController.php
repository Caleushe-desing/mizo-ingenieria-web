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
use MizoCrm\Models\ClientContact;
use MizoCrm\Models\Deal;
use MizoCrm\Models\Quote;
use MizoCrm\View;

final class QuoteController
{
	public function create(string $clientId): void
	{
		$client = Auth::requireClient(Client::find((int) $clientId));
		$projects = Client::deals((int) $client['id']);
		if ($projects === []) {
			View::flash('error', 'Crea un proyecto antes de hacer una cotización.');
			Http::redirect('/tablero/cliente/' . $client['id'] . '/ficha');
		}
		View::render('quotes/form', [
			'title' => 'Nueva cotización',
			'client' => $client,
			'projects' => $projects,
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

	public function createForDeal(string $dealId): void
	{
		$deal = Deal::find((int) $dealId);
		if (!$deal) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $deal['client_id']));
		View::render('quotes/form', [
			'title' => 'Nueva cotización',
			'client' => $client,
			'projects' => Client::deals((int) $client['id']),
			'project' => $deal,
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

	public function storeForDeal(string $dealId): void
	{
		Csrf::check();
		$deal = Deal::find((int) $dealId);
		if (!$deal) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $deal['client_id']));
		$quoteId = $this->saveQuote($client, null, (int) $deal['id']);
		if (Http::string('intent', 20) === 'send') {
			$this->deliver($quoteId);
			return;
		}
		View::flash('ok', 'Cotización guardada en este proyecto.');
		Http::redirect('/cotizaciones/' . $quoteId);
	}

	public function store(string $clientId): void
	{
		Csrf::check();
		$client = Auth::requireClient(Client::find((int) $clientId));
		$dealId = (int) ($_POST['project_id'] ?? 0);
		$deal = $dealId > 0 ? Deal::find($dealId) : null;
		if (!$deal || (int) $deal['client_id'] !== (int) $client['id']) {
			View::flash('error', 'Elige un proyecto de este cliente para asociar la cotización.');
			Http::redirect('/tablero/cliente/' . $client['id'] . '/ficha');
		}
		$quoteId = $this->saveQuote($client, null, (int) $deal['id']);
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
			'project' => Deal::find((int) $quote['deal_id']),
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
		$locked = in_array((string) $quote['status'], ['aceptada', 'rechazada'], true);
		if ($locked) {
			View::flash('error', 'Esa cotización ya fue respondida y no se puede modificar.');
			Http::redirect('/cotizaciones/' . $id);
		}
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
		Http::redirect('/tablero/cliente/' . $client['id'] . '/ficha');
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
		$quote = ClientContact::applyToQuote($quote);
		View::render('quotes/public', [
			'title' => 'Vista previa ' . $quote['number'],
			'quote' => $quote,
			'items' => Quote::items((int) $quote['id']),
			'preview' => true,
		], 'public-layout');
	}

	private function saveQuote(array $client, ?array $quote, ?int $attachDealId = null): int
	{
		$items = Quote::itemsFromPost();
		$intro = trim((string) ($_POST['intro'] ?? ''));
		$notes = trim((string) ($_POST['notes'] ?? ''));
		$validUntil = Http::string('valid_until', 20) ?: date('Y-m-d', strtotime('+15 days'));
		$now = date('c');
		$clientId = (int) $client['id'];
		$recipient = $this->recipientFromPost($clientId);

		if (!$quote) {
			$existing = $attachDealId ? Deal::find($attachDealId) : null;
			if (!$existing || (int) $existing['client_id'] !== $clientId) {
				View::flash('error', 'Crea un proyecto y elige a cuál va esta cotización.');
				Http::redirect('/tablero/cliente/' . $clientId . '/ficha');
			}
			$dealId = (int) $existing['id'];
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
				'sent_to' => $recipient['email'],
				'contact_id' => $recipient['id'],
				'created_by' => Auth::id(),
				'updated_by' => Auth::id(),
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$totals = Quote::saveItems($id, $items);
			Quote::update($id, [...$totals, 'updated_at' => $now, 'updated_by' => Auth::id()]);
			Deal::update($dealId, ['amount' => $totals['total'], 'updated_at' => $now]);
			$created = Quote::find($id);
			Activity::log('quote_created', 'Cotización ' . ($created['number'] ?? '') . ' creada.', Auth::id(), $clientId, $dealId, $id);
			Client::update($clientId, ['updated_at' => $now]);
			return $id;
		}

		if (in_array($quote['status'], ['aceptada', 'rechazada'], true)) {
			return (int) $quote['id'];
		}

		$totals = Quote::saveItems((int) $quote['id'], $items);
		$fields = [
			'intro' => $intro,
			'notes' => $notes,
			'valid_until' => $validUntil,
			'sent_to' => $recipient['email'],
			'contact_id' => $recipient['id'],
			...$totals,
			'updated_at' => $now,
			'updated_by' => Auth::id(),
		];
		if (!empty($quote['sent_at']) || in_array((string) $quote['status'], ['enviada', 'vista'], true)) {
			$fields['revision'] = $this->revisionLabel($quote);
		}
		Quote::update((int) $quote['id'], $fields);
		Deal::update((int) $quote['deal_id'], [
			'amount' => $totals['total'],
			'updated_at' => $now,
		]);
		Client::update($clientId, ['updated_at' => $now]);
		$versionNote = !empty($fields['revision']) ? ' Versión ' . $fields['revision'] . '.' : '';
		Activity::log(
			'quote_updated',
			'Cotización ' . ($quote['number'] ?? '') . ' actualizada.' . $versionNote,
			Auth::id(),
			$clientId,
			(int) $quote['deal_id'],
			(int) $quote['id']
		);
		return (int) $quote['id'];
	}

	/** @param array<string,mixed> $quote */
	private function revisionLabel(array $quote): string
	{
		$raw = strtoupper(trim(Http::string('revision', 24)));
		$raw = preg_replace('/\s+/', '-', $raw) ?? '';
		if ($raw !== '' && preg_match('/^[A-Z0-9][A-Z0-9\-]{0,23}$/', $raw)) {
			return $raw;
		}
		View::flash('error', 'Indica la versión con letras y números, por ejemplo REV-01 o OC.');
		Http::redirect('/cotizaciones/' . (int) $quote['id']);
	}

	/** @return array{id: ?int, email: string} */
	private function recipientFromPost(int $clientId): array
	{
		$id = (int) ($_POST['contact_id'] ?? 0);
		$contact = $id > 0 ? ClientContact::owned($id, $clientId) : null;
		$email = $contact ? trim((string) ($contact['email'] ?? '')) : '';
		if ($email === '') {
			$email = Http::string('sent_to', 160);
		}
		return [
			'id' => $contact ? (int) $contact['id'] : null,
			'email' => $email,
		];
	}

	private function deliver(int $quoteId): void
	{
		$quote = Quote::find($quoteId);
		if (!$quote) {
			Http::redirect('/');
		}
		$alreadySent = !empty($quote['sent_at']) || in_array((string) $quote['status'], ['enviada', 'vista'], true);
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
		$quote = ClientContact::applyToQuote($quote);
		if (!empty($quote['contact_name'])) {
			$client['contact_name'] = $quote['contact_name'];
		}
		$to = (string) ($quote['sent_to'] ?? '');
		if ($to === '') {
			$to = Http::string('sent_to', 160) ?: (string) ($quote['client_email'] ?? $client['email'] ?? '');
		}
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			View::flash('error', 'Ese contacto no tiene un correo válido. Agrégalo en la ficha del cliente y vuelve a enviar.');
			Http::redirect('/cotizaciones/' . $quoteId);
		}
		$quote['deal_title'] = $quote['intro'] !== '' ? $quote['intro'] : ($items[0]['description'] ?? 'Cotización');
		$url = App::absolute('/q/' . $quote['token']);
		$user = Auth::user();
		$html = Mailer::quoteHtml($quote, $items, $client, $url, $user);
		$version = trim((string) ($quote['revision'] ?? ''));
		$subject = 'Cotización ' . $quote['number'] . ($version !== '' ? ' ' . $version : '') . ' — Mizo';
		$ok = Mailer::send($to, $subject, $html, $user['email'] ?? '');
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
			'updated_by' => Auth::id(),
		]);
		if (!$alreadySent) {
			\MizoCrm\Models\Pipeline::onQuoteSent((int) $quote['deal_id']);
		}
		Activity::log(
			'quote_sent',
			'Cotización ' . $quote['number'] . ($version !== '' ? ' ' . $version : '') . ' enviada a ' . $to . '.',
			Auth::id(),
			(int) $quote['client_id'],
			(int) $quote['deal_id'],
			(int) $quote['id']
		);
		Client::update((int) $quote['client_id'], ['updated_at' => date('c')]);
		View::flash('ok', $alreadySent
			? 'Versión ' . ($version !== '' ? $version : $quote['number']) . ' enviada. La tarjeta sigue en la misma columna.'
			: 'Cotización ' . $quote['number'] . ' enviada a ' . $to . '. Si responde, te llega a Correo.');
		Http::redirect('/tablero/cliente/' . $quote['client_id'] . '/ficha');
	}
}
