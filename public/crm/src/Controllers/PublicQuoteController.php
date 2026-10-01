<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Activity;
use MizoCrm\Models\ClientContact;
use MizoCrm\Models\Quote;
use MizoCrm\QuotePdf;
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

	/** Descarga directa del PDF (enlace del correo). */
	public function pdf(string $token): void
	{
		$quote = Quote::findByToken($token);
		if (!$quote || $quote['status'] === 'borrador') {
			http_response_code(404);
			header('Content-Type: text/plain; charset=UTF-8');
			echo 'Cotización no encontrada.';
			return;
		}
		$quote = ClientContact::applyToQuote($quote);
		$items = Quote::items((int) $quote['id']);
		$pdf = QuotePdf::attachment($quote, $items);
		if ($pdf === null) {
			http_response_code(500);
			header('Content-Type: text/plain; charset=UTF-8');
			echo 'No se pudo generar el PDF.';
			return;
		}
		if ($quote['status'] === 'enviada' && empty($quote['viewed_at'])) {
			Quote::update((int) $quote['id'], ['status' => 'vista', 'viewed_at' => date('c'), 'updated_at' => date('c')]);
			Activity::log('quote_viewed', 'El cliente descargó el PDF de ' . $quote['number'] . '.', null, (int) $quote['client_id'], (int) $quote['deal_id'], (int) $quote['id']);
		}
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="' . str_replace('"', '', $pdf['filename']) . '"');
		header('Content-Length: ' . (string) strlen($pdf['content']));
		header('Cache-Control: private, max-age=0, must-revalidate');
		echo $pdf['content'];
	}

	/** Pantalla de confirmación desde el botón del PDF. */
	public function confirmAccept(string $token): void
	{
		$this->confirm($token, 'aceptada');
	}

	public function confirmReject(string $token): void
	{
		$this->confirm($token, 'rechazada');
	}

	private function confirm(string $token, string $decision): void
	{
		$quote = Quote::findByToken($token);
		if (!$quote || $quote['status'] === 'borrador') {
			http_response_code(404);
			echo 'Cotización no encontrada.';
			return;
		}
		if (in_array((string) $quote['status'], ['aceptada', 'rechazada'], true)) {
			View::flash('ok', 'Esta cotización ya fue respondida.');
			Http::redirect('/q/' . $token);
		}
		View::render('quotes/confirm', [
			'title' => ($decision === 'aceptada' ? 'Aceptar' : 'Rechazar') . ' ' . $quote['number'],
			'quote' => ClientContact::applyToQuote($quote),
			'decision' => $decision,
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
			\MizoCrm\Models\Pipeline::onQuoteRejected((int) $quote['deal_id']);
			Activity::log('quote_rejected', 'El cliente rechazó ' . $quote['number'] . '.', null, (int) $quote['client_id'], (int) $quote['deal_id'], (int) $quote['id']);
		}
		View::flash('ok', $decision === 'aceptada'
			? 'Gracias. Un ingeniero Mizo te contactará para coordinar la instalación.'
			: 'Registramos tu respuesta. Si quieres ajustar el alcance, responde el correo.');
		Http::redirect('/q/' . $token);
	}
}
