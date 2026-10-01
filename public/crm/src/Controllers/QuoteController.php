<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\App;
use MizoCrm\Auth;
use MizoCrm\Config;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mail\Mime;
use MizoCrm\Mailer;
use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\ClientContact;
use MizoCrm\Models\Deal;
use MizoCrm\Models\Product;
use MizoCrm\Models\Quote;
use MizoCrm\View;

final class QuoteController
{
	public function index(): void
	{
		Auth::user();
		$status = Http::string('estado', 20);
		$allowed = array_keys(Config::quoteStatuses());
		if ($status !== '' && !in_array($status, $allowed, true)) {
			$status = '';
		}
		$quotes = Quote::withRelations($status !== '' ? $status : null, Auth::ownerScope());
		$summary = [
			'sale_net' => 0,
			'cost_total_net' => 0,
			'cost_total_iva' => 0,
			'profit' => 0,
			'count' => count($quotes),
		];
		foreach ($quotes as $quote) {
			$summary['sale_net'] += (int) ($quote['sale_net'] ?? $quote['subtotal'] ?? 0);
			$summary['cost_total_net'] += (int) ($quote['cost_total_net'] ?? 0);
			$summary['cost_total_iva'] += (int) ($quote['cost_total_iva'] ?? 0);
			$summary['profit'] += (int) ($quote['profit'] ?? 0);
		}
		$summary['margin_real'] = $summary['cost_total_net'] > 0
			? round(($summary['profit'] / $summary['cost_total_net']) * 100, 1)
			: null;
		View::render('quotes/index', [
			'title' => 'Cotizaciones',
			'status' => $status,
			'quotes' => $quotes,
			'summary' => $summary,
		]);
	}

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
				'product_id' => 0,
				'name' => '',
				'description' => '',
				'quantity' => 1,
				'unit' => 'un',
				'cost_price' => 0,
				'margin_percent' => 0,
				'unit_price' => 0,
			]],
			'catalogProducts' => Product::forQuoting(),
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
				'product_id' => 0,
				'name' => '',
				'description' => '',
				'quantity' => 1,
				'unit' => 'un',
				'cost_price' => 0,
				'margin_percent' => 0,
				'unit_price' => 0,
			]],
			'catalogProducts' => Product::forQuoting(),
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
			Http::redirect('/cotizaciones/' . $quoteId . '/enviar');
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
			Http::redirect('/cotizaciones/' . $quoteId . '/enviar');
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
				'product_id' => 0,
				'name' => '',
				'description' => '',
				'quantity' => 1,
				'unit' => 'un',
				'cost_price' => 0,
				'margin_percent' => 0,
				'unit_price' => 0,
			]];
		}
		View::render('quotes/form', [
			'title' => $quote['number'],
			'client' => $client,
			'projects' => Client::deals((int) $client['id']),
			'project' => Deal::find((int) $quote['deal_id']),
			'quote' => $quote,
			'items' => $items,
			'catalogProducts' => Product::forQuoting(),
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
			Http::redirect('/cotizaciones/' . $id . '/enviar');
		}
		View::flash('ok', 'Cotización guardada.');
		Http::redirect('/cotizaciones/' . $id);
	}

	/** Pantalla previa: contactos, Cc y vista del correo antes de enviar. */
	public function prepareSend(string $id): void
	{
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $quote['client_id']));
		if (in_array((string) $quote['status'], ['aceptada', 'rechazada'], true)) {
			View::flash('error', 'Esa cotización ya fue respondida y no se puede enviar de nuevo.');
			Http::redirect('/cotizaciones/' . $id);
		}
		$items = Quote::items((int) $id);
		$hasItems = false;
		foreach ($items as $item) {
			if (Quote::itemLabel($item) !== '') {
				$hasItems = true;
				break;
			}
		}
		if (!$hasItems) {
			View::flash('error', 'Agrega al menos una partida antes de enviar.');
			Http::redirect('/cotizaciones/' . $id);
		}
		$contacts = ClientContact::forClient((int) $client['id']);
		$selectedContact = (int) ($_GET['contacto'] ?? ($quote['contact_id'] ?? 0));
		if ($selectedContact > 0) {
			$owned = ClientContact::owned($selectedContact, (int) $client['id']);
			if ($owned) {
				$quote['contact_id'] = $selectedContact;
				$quote['sent_to'] = trim((string) ($owned['email'] ?? ''));
			}
		}
		$quote = ClientContact::applyToQuote($quote);
		if (!empty($quote['contact_name'])) {
			$client['contact_name'] = $quote['contact_name'];
		}
		$quote['deal_title'] = $quote['intro'] !== '' ? $quote['intro'] : (Quote::itemLabel($items[0] ?? []) ?: 'Cotización');
		$url = App::absolute('/q/' . $quote['token']);
		$user = Auth::user();
		$html = Mailer::quoteHtml($quote, $items, $client, $url, $user);
		$version = trim((string) ($quote['revision'] ?? ''));
		$subject = 'Cotización ' . $quote['number'] . ($version !== '' ? ' ' . $version : '') . ' — Mizo';
		$alreadySent = !empty($quote['sent_at']) || in_array((string) $quote['status'], ['enviada', 'vista'], true);
		View::render('quotes/send', [
			'title' => 'Enviar ' . $quote['number'],
			'client' => $client,
			'quote' => $quote,
			'items' => $items,
			'contacts' => $contacts,
			'subject' => $subject,
			'emailHtml' => $html,
			'publicUrl' => $url,
			'alreadySent' => $alreadySent,
			'selectedContact' => (int) ($quote['contact_id'] ?? 0),
			'selectedCc' => Mime::emailsFromString((string) ($quote['sent_cc'] ?? '')),
		]);
	}

	public function send(string $id): void
	{
		Csrf::check();
		$this->deliver((int) $id);
	}

	/** Ver el último correo enviado de esta cotización. */
	public function email(string $id): void
	{
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $quote['client_id']));
		$html = trim((string) ($quote['last_email_html'] ?? ''));
		if ($html === '') {
			View::flash('error', 'Todavía no hay un correo guardado para esta cotización. Envíala primero.');
			Http::redirect('/cotizaciones/' . $id);
		}
		View::render('quotes/email', [
			'title' => 'Correo · ' . $quote['number'],
			'client' => $client,
			'quote' => $quote,
			'subject' => (string) ($quote['last_email_subject'] ?? ''),
			'emailHtml' => $html,
			'mailId' => (int) ($quote['last_mail_id'] ?? 0),
		]);
	}

	/** Elige proyecto destino antes de copiar. */
	public function prepareCopy(string $id): void
	{
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $quote['client_id']));
		$projects = Client::deals((int) $client['id']);
		if ($projects === []) {
			View::flash('error', 'Este cliente no tiene proyectos. Crea uno antes de copiar la cotización.');
			Http::redirect('/tablero/cliente/' . $client['id'] . '/ficha');
		}
		View::render('quotes/copy', [
			'title' => 'Copiar ' . $quote['number'],
			'client' => $client,
			'quote' => $quote,
			'project' => Deal::find((int) $quote['deal_id']),
			'projects' => $projects,
		]);
	}

	/** Copia con número correlativo nuevo y proyecto elegido. */
	public function duplicate(string $id): void
	{
		Csrf::check();
		$quote = Quote::find((int) $id);
		if (!$quote) {
			Http::redirect('/');
		}
		$client = Auth::requireClient(Client::find((int) $quote['client_id']));
		$dealId = (int) ($_POST['project_id'] ?? 0);
		if ($dealId < 1) {
			$dealId = (int) $quote['deal_id'];
		}
		$deal = Deal::find($dealId);
		if (!$deal || (int) $deal['client_id'] !== (int) $client['id']) {
			View::flash('error', 'Elige un proyecto de este cliente para la copia.');
			Http::redirect('/cotizaciones/' . $id . '/copiar');
		}
		$newId = Quote::duplicate((int) $id, Auth::id(), $dealId);
		if (!$newId) {
			View::flash('error', 'No se pudo copiar la cotización.');
			Http::redirect('/cotizaciones/' . $id . '/copiar');
		}
		$created = Quote::find($newId);
		$projectNote = ' → ' . (string) ($deal['title'] ?? 'proyecto');
		Activity::log(
			'quote_created',
			'Cotización ' . ($created['number'] ?? '') . ' copiada desde ' . ($quote['number'] ?? '') . $projectNote . '.',
			Auth::id(),
			(int) $client['id'],
			$dealId,
			$newId
		);
		Client::update((int) $client['id'], ['updated_at' => date('c')]);
		View::flash('ok', 'Copia creada como ' . ($created['number'] ?? '') . ' en «' . ($deal['title'] ?? 'proyecto') . '».');
		Http::redirect('/cotizaciones/' . $newId);
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
		$quote['client_rut'] = $client['rut'] ?? '';
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
		$termsText = trim((string) ($_POST['terms_text'] ?? ''));
		$aboutText = trim((string) ($_POST['about_text'] ?? ''));
		if ($termsText === '') {
			$termsText = Config::defaultQuoteTerms();
		}
		if ($aboutText === '') {
			$aboutText = Config::defaultQuoteAbout();
		}
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
				'terms_text' => $termsText,
				'about_text' => $aboutText,
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
			'terms_text' => $termsText,
			'about_text' => $aboutText,
			'valid_until' => $validUntil,
			'sent_to' => $recipient['email'],
			'contact_id' => $recipient['id'],
			...$totals,
			'updated_at' => $now,
			'updated_by' => Auth::id(),
		];
		$dealId = (int) $quote['deal_id'];
		// En borrador se puede reasignar a otro proyecto del mismo cliente.
		if ((string) ($quote['status'] ?? '') === 'borrador') {
			$postedDeal = (int) ($_POST['project_id'] ?? 0);
			if ($postedDeal > 0) {
				$target = Deal::find($postedDeal);
				if ($target && (int) $target['client_id'] === $clientId) {
					$dealId = $postedDeal;
					$fields['deal_id'] = $dealId;
				}
			}
		}
		if (!empty($quote['sent_at']) || in_array((string) $quote['status'], ['enviada', 'vista'], true)) {
			$fields['revision'] = $this->revisionLabel($quote);
		}
		Quote::update((int) $quote['id'], $fields);
		Deal::update($dealId, [
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
			$dealId,
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
		if (in_array((string) $quote['status'], ['aceptada', 'rechazada'], true)) {
			View::flash('error', 'Esa cotización ya fue respondida y no se puede enviar de nuevo.');
			Http::redirect('/cotizaciones/' . $quoteId);
		}

		$recipients = $this->recipientsFromSendPost((int) $client['id'], $quote);
		$to = $recipients['to'];
		$cc = $recipients['cc'];
		$contactId = $recipients['contact_id'];

		$fieldsPre = [
			'contact_id' => $contactId,
			'sent_to' => $to,
			'sent_cc' => $cc,
			'updated_at' => date('c'),
			'updated_by' => Auth::id(),
		];
		if ($alreadySent) {
			$postedRev = strtoupper(trim(Http::string('revision', 24)));
			$postedRev = preg_replace('/\s+/', '-', $postedRev) ?? '';
			if ($postedRev !== '' && preg_match('/^[A-Z0-9][A-Z0-9\-]{0,23}$/', $postedRev)) {
				$fieldsPre['revision'] = $postedRev;
			} elseif (trim((string) ($quote['revision'] ?? '')) === '') {
				View::flash('error', 'Indica la versión con letras y números, por ejemplo REV-01 o OC.');
				Http::redirect('/cotizaciones/' . $quoteId . '/enviar');
			}
		}
		Quote::update($quoteId, $fieldsPre);

		$quote = Quote::find($quoteId);
		if (!$quote) {
			Http::redirect('/');
		}
		$items = Quote::items((int) $quote['id']);
		$hasItems = false;
		foreach ($items as $item) {
			if (Quote::itemLabel($item) !== '') {
				$hasItems = true;
				break;
			}
		}
		if (!$hasItems) {
			View::flash('error', 'Agrega al menos una partida antes de enviar.');
			Http::redirect('/cotizaciones/' . $quoteId);
		}
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			View::flash('error', 'Elige un destinatario con correo válido.');
			Http::redirect('/cotizaciones/' . $quoteId . '/enviar');
		}

		$quote = ClientContact::applyToQuote($quote);
		if (!empty($quote['contact_name'])) {
			$client['contact_name'] = $quote['contact_name'];
		}
		$quote['deal_title'] = $quote['intro'] !== '' ? $quote['intro'] : (Quote::itemLabel($items[0] ?? []) ?: 'Cotización');
		$url = App::absolute('/q/' . $quote['token']);
		$user = Auth::user();
		$html = Mailer::quoteHtml($quote, $items, $client, $url, $user);
		$version = trim((string) ($quote['revision'] ?? ''));
		$subject = 'Cotización ' . $quote['number'] . ($version !== '' ? ' ' . $version : '') . ' — Mizo';
		$mailId = Mailer::send($to, $subject, $html, $user['email'] ?? '', $cc);
		if ($mailId === false) {
			$hint = \MizoCrm\Models\Mailbox::forUser(Auth::id())
				? 'Revisa la clave de tu casilla en Correo.'
				: 'Conecta tu casilla en Correo para que el cliente te responda ahí.';
			View::flash('error', 'No se pudo enviar el correo. ' . $hint . ' La cotización quedó guardada.');
			Http::redirect('/cotizaciones/' . $quoteId . '/enviar');
		}
		$fields = [
			'status' => 'enviada',
			'sent_at' => date('c'),
			'sent_to' => $to,
			'sent_cc' => $cc,
			'last_email_html' => $html,
			'last_email_subject' => $subject,
			'updated_at' => date('c'),
			'updated_by' => Auth::id(),
		];
		if ($mailId > 0) {
			$fields['last_mail_id'] = $mailId;
		}
		Quote::update((int) $quote['id'], $fields);
		// Siempre: si el primer envío no movió la tarjeta (roles rotos, etc.), el reenvío la corrige.
		\MizoCrm\Models\Pipeline::onQuoteSent((int) $quote['deal_id']);
		$ccNote = $cc !== '' ? ' (cc ' . $cc . ')' : '';
		Activity::log(
			'quote_sent',
			'Cotización ' . $quote['number'] . ($version !== '' ? ' ' . $version : '') . ' enviada a ' . $to . $ccNote . '.',
			Auth::id(),
			(int) $quote['client_id'],
			(int) $quote['deal_id'],
			(int) $quote['id']
		);
		Client::update((int) $quote['client_id'], ['updated_at' => date('c')]);
		View::flash('ok', $alreadySent
			? 'Versión ' . ($version !== '' ? $version : $quote['number']) . ' enviada a ' . $to . '.'
			: 'Cotización ' . $quote['number'] . ' enviada a ' . $to . '.');
		Http::redirect('/cotizaciones/' . $quoteId . '/correo');
	}

	/** @param array<string,mixed> $quote @return array{to: string, cc: string, contact_id: ?int} */
	private function recipientsFromSendPost(int $clientId, array $quote): array
	{
		$contactId = (int) ($_POST['contact_id'] ?? 0);
		$contact = $contactId > 0 ? ClientContact::owned($contactId, $clientId) : null;
		$to = $contact ? trim((string) ($contact['email'] ?? '')) : '';
		if ($to === '') {
			$to = trim(Http::string('sent_to', 160));
		}
		if ($to === '') {
			$to = trim((string) ($quote['sent_to'] ?? ''));
		}

		$ccEmails = [];
		$extra = $_POST['cc_contact_id'] ?? [];
		if (is_array($extra)) {
			foreach ($extra as $rawId) {
				$cid = (int) $rawId;
				if ($cid <= 0 || ($contact && $cid === (int) $contact['id'])) {
					continue;
				}
				$row = ClientContact::owned($cid, $clientId);
				$email = $row ? trim((string) ($row['email'] ?? '')) : '';
				if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && strcasecmp($email, $to) !== 0) {
					$ccEmails[] = mb_strtolower($email);
				}
			}
		}
		foreach (Mime::emailsFromString(Http::string('cc', 500)) as $email) {
			if (strcasecmp($email, $to) !== 0) {
				$ccEmails[] = $email;
			}
		}
		$ccEmails = array_values(array_unique($ccEmails));
		return [
			'to' => $to,
			'cc' => implode(', ', $ccEmails),
			'contact_id' => $contact ? (int) $contact['id'] : null,
		];
	}
}
