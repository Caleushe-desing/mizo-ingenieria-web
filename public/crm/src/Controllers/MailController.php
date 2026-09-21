<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\Mailbox;
use MizoCrm\Models\MailMessage;
use MizoCrm\View;
use RuntimeException;

final class MailController
{
	public function inbox(): void
	{
		$this->folder('inbox');
	}

	public function sent(): void
	{
		$this->folder('sent');
	}

	public function compose(): void
	{
		$user = Auth::requireUser();
		if (!Mailbox::forUser((int) $user['id'])) {
			View::flash('error', 'Conecta tu casilla antes de escribir.');
			Http::redirect('/correo/cuenta');
		}
		$client = null;
		$to = Http::string('para', 160);
		$clientId = Http::int('cliente');
		if ($clientId > 0) {
			$client = Auth::requireClient(Client::find($clientId));
			if ($to === '' && !empty($client['email'])) {
				$to = (string) $client['email'];
			}
		}
		View::render('mail/compose', [
			'title' => 'Nuevo correo',
			'mailbox' => Mailbox::forUser((int) $user['id']),
			'client' => $client,
			'to' => $to,
			'subject' => Http::string('asunto', 180),
			'body' => '',
			'unread' => MailMessage::unreadCount((int) $user['id']),
		]);
	}

	public function send(): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		$to = mb_strtolower(Http::string('to', 160));
		$subject = Http::string('subject', 180);
		$body = Http::text('body', 20000);
		$clientId = Http::int('client_id') ?: null;
		$replyId = Http::string('in_reply_to', 200);
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			View::flash('error', 'Escribe un correo de destino válido.');
			Http::redirect('/correo/nuevo');
		}
		if ($subject === '') {
			$subject = '(sin asunto)';
		}
		if ($body === '') {
			View::flash('error', 'Escribe el mensaje.');
			Http::redirect('/correo/nuevo');
		}
		if ($clientId) {
			$client = Auth::requireClient(Client::find($clientId));
			$clientId = (int) $client['id'];
		} else {
			$clientId = MailMessage::clientIdFor((int) $user['id'], $to);
		}
		$html = self::htmlFromText($body, $user);
		try {
			$id = Mailbox::deliver((int) $user['id'], $user, $to, $subject, $html, $replyId, $clientId);
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
			Http::redirect('/correo/nuevo');
		}
		if ($clientId) {
			Activity::log('mail_sent', 'Correo enviado a ' . $to . ': ' . $subject, (int) $user['id'], $clientId);
			Client::update($clientId, ['updated_at' => date('c')]);
		}
		View::flash('ok', 'Correo enviado a ' . $to . '. Si responde, te llega aquí a Bandeja.');
		Http::redirect('/correo/' . $id);
	}

	public function show(string $id): void
	{
		$user = Auth::requireUser();
		$row = MailMessage::owned((int) $user['id'], (int) $id);
		if (!$row) {
			View::flash('error', 'Ese correo no está en tu casilla.');
			Http::redirect('/correo');
		}
		MailMessage::markRead($row);
		$row['seen'] = 1;
		$client = !empty($row['client_id']) ? Client::find((int) $row['client_id']) : null;
		if ($client && !Auth::canAccessClient($client)) {
			$client = null;
		}
		$folder = ($row['folder'] ?? '') === 'sent' ? 'sent' : 'inbox';
		$box = Mailbox::forUser((int) $user['id']);
		if (!$box) {
			Http::redirect('/correo/cuenta');
		}
		$query = Http::string('q', 80);
		View::render('mail/inbox', [
			'title' => $row['subject'] ?: 'Correo',
			'folder' => $folder,
			'mailbox' => $box,
			'messages' => MailMessage::list((int) $user['id'], $folder, null, $query),
			'message' => $row,
			'client' => $client,
			'unread' => MailMessage::unreadCount((int) $user['id']),
			'query' => $query,
		]);
	}

	public function reply(string $id): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		$row = MailMessage::owned((int) $user['id'], (int) $id);
		if (!$row) {
			Http::redirect('/correo');
		}
		$body = Http::text('body', 20000);
		if ($body === '') {
			View::flash('error', 'Escribe la respuesta.');
			Http::redirect('/correo/' . $id);
		}
		$to = $row['folder'] === 'inbox' ? (string) $row['from_email'] : (string) $row['to_email'];
		$subject = (string) $row['subject'];
		if (!str_starts_with(mb_strtolower($subject), 're:')) {
			$subject = 'Re: ' . $subject;
		}
		$clientId = !empty($row['client_id']) ? (int) $row['client_id'] : MailMessage::clientIdFor((int) $user['id'], $to);
		$html = self::htmlFromText($body, $user);
		try {
			$newId = Mailbox::deliver((int) $user['id'], $user, $to, $subject, $html, (string) $row['message_id'], $clientId);
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
			Http::redirect('/correo/' . $id);
		}
		if ($clientId) {
			Activity::log('mail_sent', 'Respuesta enviada a ' . $to . ': ' . $subject, (int) $user['id'], $clientId);
			Client::update($clientId, ['updated_at' => date('c')]);
		}
		View::flash('ok', 'Respuesta enviada.');
		Http::redirect('/correo/' . $newId);
	}

	public function destroy(string $id): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		$row = MailMessage::owned((int) $user['id'], (int) $id);
		if ($row) {
			MailMessage::delete((int) $row['id']);
		}
		View::flash('ok', 'Correo quitado de esta lista. Sigue en tu casilla de correo.');
		Http::redirect($row && $row['folder'] === 'sent' ? '/correo/enviados' : '/correo');
	}

	public function status(string $id): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		$row = MailMessage::owned((int) $user['id'], (int) $id);
		if (!$row) {
			View::flash('error', 'Ese correo no está en tu casilla.');
			Http::redirect('/correo');
		}
		$action = Http::string('action', 20);
		match ($action) {
			'read' => MailMessage::setSeen($row, true),
			'unread' => MailMessage::setSeen($row, false),
			'important' => MailMessage::setImportant($row, true),
			'unimportant' => MailMessage::setImportant($row, false),
			default => null,
		};
		$back = trim((string) ($_POST['back'] ?? ''));
		if ($back === '' || !str_starts_with($back, '/correo')) {
			$back = '/correo/' . (int) $row['id'];
		}
		Http::redirect($back);
	}

	public function account(): void
	{
		$user = Auth::requireUser();
		View::render('mail/account', [
			'title' => 'Mi casilla',
			'mailbox' => Mailbox::forUser((int) $user['id']),
			'email' => $user['email'],
			'unread' => MailMessage::unreadCount((int) $user['id']),
		]);
	}

	public function connect(): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		$email = Http::string('email', 160) ?: (string) $user['email'];
		$password = (string) ($_POST['password'] ?? '');
		try {
			Mailbox::connect((int) $user['id'], $email, $password);
			Mailbox::sync((int) $user['id'], true);
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
			Http::redirect('/correo/cuenta');
		}
		View::flash('ok', 'Casilla conectada. Ya puedes enviar y recibir desde el CRM.');
		Http::redirect('/correo');
	}

	public function disconnect(): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		Mailbox::disconnect((int) $user['id']);
		View::flash('ok', 'Se desconectó la casilla de este CRM.');
		Http::redirect('/correo/cuenta');
	}

	private function folder(string $folder): void
	{
		$user = Auth::requireUser();
		$box = Mailbox::forUser((int) $user['id']);
		if (!$box) {
			View::render('mail/account', [
				'title' => 'Correo',
				'mailbox' => null,
				'email' => $user['email'],
				'unread' => 0,
			]);
			return;
		}
		$error = '';
		try {
			Mailbox::sync((int) $user['id'], Http::string('sync', 8) === '1');
		} catch (RuntimeException $e) {
			$error = $e->getMessage();
		}
		if ($error !== '') {
			View::flash('error', $error);
		}
		$query = Http::string('q', 80);
		View::render('mail/inbox', [
			'title' => $folder === 'sent' ? 'Enviados' : 'Bandeja de entrada',
			'folder' => $folder,
			'mailbox' => $box,
			'messages' => MailMessage::list((int) $user['id'], $folder, null, $query),
			'unread' => MailMessage::unreadCount((int) $user['id']),
			'query' => $query,
		]);
	}

	private static function htmlFromText(string $text, array $user): string
	{
		$body = nl2br(h($text), false);
		return '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.5;color:#222;">'
			. $body
			. \MizoCrm\Models\User::signatureHtml($user)
			. '</div>';
	}
}
