<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Activity;
use MizoCrm\Models\ClientContact;
use MizoCrm\Models\Deal;
use MizoCrm\Models\Quote;
use MizoCrm\View;

final class PublicQuoteController
{
	public function show(string $token): void
	{
		$quote = Quote::findByToken($token);
		if (!$quote || $quote['status'] === 'borrador') {
			http_response_code(404);
			echo 'Cotización no encontrada.';
			return;
		}
		if ($quote['status'] === 'enviada' && !$quote['viewed_at']) {
			Quote::update((int) $quote['id'], ['status' => 'vista', 'viewed_at' => date('c'), 'updated_at' => date('c')]);
			$quote['status'] = 'vista';
			Activity::log('quote_viewed', 'El cliente abrió la cotización ' . $quote['number'] . '.', null, (int) $quote['client_id'], (int) $quote['deal_id'], (int) $quote['id']);
		}
		View::render('quotes/public', [
			'title' => 'Cotización ' . $quote['number'],
			'quote' => ClientContact::applyToQuote($quote),
			'items' => Quote::items((int) $quote['id']),
		], 'public-layout');
	}

	public function respond(string $token): void
	{
		Csrf::check();
		$quote = Quote::findByToken($token);
		if (!$quote) {
			http_response_code(404);
			echo 'Cotización no encontrada.';
			return;
		}
		$decision = Http::string('decision', 20);
		if (!in_array($decision, ['aceptada', 'rechazada'], true)) {
			Http::redirect('/q/' . $token);
		}
		if (in_array($quote['status'], ['aceptada', 'rechazada', 'borrador'], true)) {
			Http::redirect('/q/' . $token);
		}
		Quote::update((int) $quote['id'], [
			'status' => $decision,
			'responded_at' => date('c'),
			'updated_at' => date('c'),
		]);
		if ($decision === 'aceptada') {
			\MizoCrm\Models\Pipeline::onQuoteAccepted((int) $quote['deal_id'], (int) $quote['total']);
			Activity::log('quote_accepted', 'El cliente aceptó ' . $quote['number'] . '.', null, (int) $quote['client_id'], (int) $quote['deal_id'], (int) $quote['id']);
		} else {
			Activity::log('quote_rejected', 'El cliente rechazó ' . $quote['number'] . '.', null, (int) $quote['client_id'], (int) $quote['deal_id'], (int) $quote['id']);
		}
		View::flash('ok', $decision === 'aceptada' ? 'Gracias. Un ingeniero Mizo te contactará para coordinar la instalación.' : 'Registramos tu respuesta. Si quieres ajustar el alcance, responde el correo.');
		Http::redirect('/q/' . $token);
	}
}
