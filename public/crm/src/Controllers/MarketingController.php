<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\App;
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mail\Mime;
use MizoCrm\Mailer;
use MizoCrm\Marketing\TemplateEngine;
use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\ClientContact;
use MizoCrm\Models\Deal;
use MizoCrm\Models\Mailbox;
use MizoCrm\Models\MarketingTemplate;
use MizoCrm\Models\Pipeline;
use MizoCrm\Models\Quote;
use MizoCrm\QuotePdf;
use MizoCrm\View;

final class MarketingController
{
	public function index(): void
	{
		Auth::requireUser();
		View::render('marketing/index', [
			'title' => 'Marketing',
			'templates' => MarketingTemplate::active(),
			'variables' => TemplateEngine::catalog(),
		]);
	}

	public function compose(): void
	{
		$user = Auth::requireUser();
		if (!Mailbox::forUser((int) $user['id'])) {
			View::flash('error', 'Conecta tu casilla en Correo antes de enviar correos comerciales.');
			Http::redirect('/correo/cuenta');
		}

		$client = null;
		$contacts = [];
		$quote = null;
		$deal = null;
		$to = Http::string('para', 400);
		$clientId = Http::int('cliente');
		$quoteId = Http::int('cotizacion');
		$templateId = Http::int('plantilla');

		if ($quoteId > 0) {
			$quote = Quote::find($quoteId);
			if ($quote) {
				$clientId = (int) $quote['client_id'];
				$deal = Deal::find((int) $quote['deal_id']);
			}
		}
		if ($clientId > 0) {
			$client = Auth::requireClient(Client::find($clientId));
			$contacts = ClientContact::forClient((int) $client['id']);
			if ($to === '') {
				$emails = [];
				foreach ($contacts as $c) {
					if (!empty($c['email'])) {
						$emails[] = (string) $c['email'];
					}
				}
				if ($emails === [] && !empty($client['email'])) {
					$emails[] = (string) $client['email'];
				}
				$to = implode(', ', $emails);
			}
		}

		$template = $templateId > 0 ? MarketingTemplate::findActive($templateId) : null;
		if (!$template) {
			$active = MarketingTemplate::active();
			$template = $active[0] ?? null;
			if ($quote && count($active) > 1) {
				foreach ($active as $row) {
					if (($row['slug'] ?? '') === 'seguimiento-cotizacion') {
						$template = $row;
						break;
					}
				}
			}
		}

		$contact = $contacts[0] ?? null;
		if ($quote && !empty($quote['contact_id'])) {
			foreach ($contacts as $c) {
				if ((int) ($c['id'] ?? 0) === (int) $quote['contact_id']) {
					$contact = $c;
					break;
				}
			}
		}

		$publicUrl = $quote ? App::absolute('/q/' . $quote['token']) : '';
		$vars = TemplateEngine::context($client, $contact, $quote, $user, $deal, $publicUrl);
		$subject = TemplateEngine::render((string) ($template['subject'] ?? ''), $vars);
		$body = TemplateEngine::render((string) ($template['body'] ?? ''), $vars);

		$quotes = $client ? Client::quotes((int) $client['id']) : [];

		View::render('marketing/compose', [
			'title' => 'Correo comercial',
			'templates' => MarketingTemplate::active(),
			'template' => $template,
			'variables' => TemplateEngine::catalog(),
			'client' => $client,
			'contacts' => $contacts,
			'directory' => ClientContact::directory(Auth::ownerScope()),
			'quote' => $quote,
			'quotes' => $quotes,
			'deal' => $deal,
			'to' => $to,
			'cc' => '',
			'subject' => $subject,
			'body' => $body,
			'publicUrl' => $publicUrl,
			'previewVars' => $vars,
		]);
	}

	public function send(): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		if (!Mailbox::forUser((int) $user['id'])) {
			View::flash('error', 'Conecta tu casilla en Correo antes de enviar.');
			Http::redirect('/correo/cuenta');
		}

		$toList = Mime::emailsFromString((string) ($_POST['to'] ?? ''));
		$ccList = Mime::emailsFromString((string) ($_POST['cc'] ?? ''));
		$subject = Http::string('subject', 180);
		$body = Http::text('body', 20000);
		$clientId = Http::int('client_id');
		$quoteId = Http::int('quote_id');
		$attachPdf = !empty($_POST['attach_pdf']);
		$syncBoard = !empty($_POST['sync_board']);

		$back = '/marketing/nuevo';
		if ($clientId > 0) {
			$back .= '?cliente=' . $clientId;
		}
		if ($quoteId > 0) {
			$back .= ($clientId > 0 ? '&' : '?') . 'cotizacion=' . $quoteId;
		}

		if ($toList === []) {
			View::flash('error', 'Elige al menos un destinatario válido.');
			Http::redirect($back);
		}
		if (trim($subject) === '') {
			View::flash('error', 'Escribe un asunto.');
			Http::redirect($back);
		}
		if (trim($body) === '') {
			View::flash('error', 'Escribe el mensaje.');
			Http::redirect($back);
		}

		$client = null;
		if ($clientId > 0) {
			$client = Auth::requireClient(Client::find($clientId));
			$clientId = (int) $client['id'];
		} else {
			$clientId = 0;
		}

		$quote = null;
		$deal = null;
		if ($quoteId > 0) {
			$quote = Quote::find($quoteId);
			if (!$quote) {
				View::flash('error', 'No se encontró la cotización.');
				Http::redirect($back);
			}
			$qClient = Auth::requireClient(Client::find((int) $quote['client_id']));
			$client = $qClient;
			$clientId = (int) $client['id'];
			$deal = Deal::find((int) $quote['deal_id']);
		}

		$attachments = [];
		if ($attachPdf && $quote) {
			$items = Quote::items((int) $quote['id']);
			$pdf = QuotePdf::attachment($quote, $items);
			if ($pdf !== null) {
				$attachments[] = $pdf;
			}
		}

		$html = self::htmlFromText($body, $user);
		$to = implode(', ', $toList);
		$cc = implode(', ', $ccList);
		$mailId = Mailer::send($to, $subject, $html, (string) ($user['email'] ?? ''), $cc, $attachments);
		if ($mailId === false) {
			View::flash('error', 'No se pudo enviar el correo. Revisa tu casilla en Correo.');
			Http::redirect($back);
		}

		if ($clientId > 0) {
			$note = 'Correo comercial enviado a ' . $to . ': ' . $subject;
			if ($quote) {
				$rev = trim((string) ($quote['revision'] ?? ''));
				$note .= ' (cotización ' . $quote['number'] . ($rev !== '' ? ' ' . $rev : '') . ')';
			}
			if ($attachments !== []) {
				$note .= ' · PDF adjunto';
			}
			Activity::log(
				'mail_sent',
				$note,
				(int) $user['id'],
				$clientId,
				$deal ? (int) $deal['id'] : ($quote ? (int) $quote['deal_id'] : null),
				$quote ? (int) $quote['id'] : null
			);
			Client::update($clientId, ['updated_at' => date('c')]);
		}

		if ($quote && $syncBoard) {
			$fields = [
				'updated_at' => date('c'),
				'updated_by' => (int) $user['id'],
			];
			$status = (string) ($quote['status'] ?? '');
			if (!in_array($status, ['aceptada', 'rechazada', 'enviada', 'vista'], true)) {
				$fields['status'] = 'enviada';
				$fields['sent_at'] = date('c');
				$fields['sent_to'] = $to;
				if ($cc !== '') {
					$fields['sent_cc'] = $cc;
				}
			} elseif (empty($quote['sent_at'])) {
				$fields['sent_at'] = date('c');
				$fields['sent_to'] = $to;
			}
			if ($mailId > 0) {
				$fields['last_mail_id'] = $mailId;
			}
			Quote::update((int) $quote['id'], $fields);
			if ((int) ($quote['deal_id'] ?? 0) > 0) {
				Pipeline::onQuoteSent((int) $quote['deal_id']);
			}
			$rev = trim((string) ($quote['revision'] ?? ''));
			Activity::log(
				'quote_sent',
				'Cotización ' . $quote['number'] . ($rev !== '' ? ' ' . $rev : '') . ' enviada por correo comercial a ' . $to . '.',
				(int) $user['id'],
				(int) $quote['client_id'],
				(int) $quote['deal_id'],
				(int) $quote['id']
			);
		}

		View::flash('ok', 'Correo comercial enviado a ' . $to . '.');
		if ($mailId > 0) {
			Http::redirect('/correo/' . $mailId);
		}
		if ($clientId > 0) {
			Http::redirect('/tablero/cliente/' . $clientId . '/ficha');
		}
		Http::redirect('/marketing');
	}

	public function templates(): void
	{
		Auth::requireUser();
		View::render('marketing/templates', [
			'title' => 'Plantillas de correo',
			'templates' => MarketingTemplate::allOrdered(),
			'variables' => TemplateEngine::catalog(),
		]);
	}

	public function storeTemplate(): void
	{
		Csrf::check();
		Auth::requireUser();
		$name = Http::string('name', 120);
		$subject = Http::string('subject', 180);
		$body = Http::text('body', 20000);
		if ($name === '' || $subject === '' || trim($body) === '') {
			View::flash('error', 'Completa nombre, asunto y cuerpo de la plantilla.');
			Http::redirect('/marketing/plantillas');
		}
		$slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? 'plantilla'));
		$slug = trim($slug, '-') ?: ('plantilla-' . time());
		MarketingTemplate::insert([
			'slug' => $slug . '-' . substr((string) time(), -4),
			'name' => $name,
			'subject' => $subject,
			'body' => $body,
			'active' => 1,
			'position' => MarketingTemplate::nextPosition(),
			'created_at' => date('c'),
			'updated_at' => date('c'),
		]);
		View::flash('ok', 'Plantilla creada.');
		Http::redirect('/marketing/plantillas');
	}

	public function updateTemplate(string $id): void
	{
		Csrf::check();
		Auth::requireUser();
		$row = MarketingTemplate::find((int) $id);
		if (!$row) {
			View::flash('error', 'Plantilla no encontrada.');
			Http::redirect('/marketing/plantillas');
		}
		$name = Http::string('name', 120);
		$subject = Http::string('subject', 180);
		$body = Http::text('body', 20000);
		$active = !empty($_POST['active']) ? 1 : 0;
		if ($name === '' || $subject === '' || trim($body) === '') {
			View::flash('error', 'Completa nombre, asunto y cuerpo.');
			Http::redirect('/marketing/plantillas');
		}
		MarketingTemplate::update((int) $id, [
			'name' => $name,
			'subject' => $subject,
			'body' => $body,
			'active' => $active,
			'updated_at' => date('c'),
		]);
		View::flash('ok', 'Plantilla actualizada.');
		Http::redirect('/marketing/plantillas');
	}

	public function destroyTemplate(string $id): void
	{
		Csrf::check();
		Auth::requireUser();
		$row = MarketingTemplate::find((int) $id);
		if (!$row) {
			View::flash('error', 'Plantilla no encontrada.');
			Http::redirect('/marketing/plantillas');
		}
		// Soft-delete: no borramos seeds del sistema; las ocultamos.
		MarketingTemplate::update((int) $id, [
			'active' => 0,
			'updated_at' => date('c'),
		]);
		View::flash('ok', 'Plantilla ocultada.');
		Http::redirect('/marketing/plantillas');
	}

	/** @param array<string, mixed> $user */
	private static function htmlFromText(string $text, array $user): string
	{
		$body = nl2br(h($text), false);
		return '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.5;color:#222;">'
			. $body
			. \MizoCrm\Models\User::signatureHtml($user)
			. '</div>';
	}
}
