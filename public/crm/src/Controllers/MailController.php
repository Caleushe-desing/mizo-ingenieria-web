<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mail\Mime;
use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\ClientContact;
use MizoCrm\Models\Mailbox;
use MizoCrm\Models\MailAttachment;
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
		$contacts = [];
		$to = Http::string('para', 400);
		$clientId = Http::int('cliente');
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
		View::render('mail/compose', [
			'title' => 'Nuevo correo',
			'mailbox' => Mailbox::forUser((int) $user['id']),
			'client' => $client,
			'contacts' => $contacts,
			'directory' => ClientContact::directory(Auth::ownerScope()),
			'to' => $to,
			'cc' => '',
			'subject' => Http::string('asunto', 180),
			'body' => '',
			'unread' => MailMessage::unreadCount((int) $user['id']),
		]);
	}

	public function send(): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		$toRaw = (string) ($_POST['to'] ?? '');
		$ccRaw = (string) ($_POST['cc'] ?? '');
		$toList = Mime::emailsFromString($toRaw);
		$ccList = Mime::emailsFromString($ccRaw);
		$subject = Http::string('subject', 180);
		$body = Http::text('body', 20000);
		$clientId = Http::int('client_id') ?: null;
		$replyId = Http::string('in_reply_to', 200);
		if ($toList === []) {
			$this->finishMail(false, 'Elige al menos un destinatario válido.', '/correo/nuevo');
		}
		if ($subject === '') {
			$subject = '(sin asunto)';
		}
		if ($body === '') {
			$this->finishMail(false, 'Escribe el mensaje.', '/correo/nuevo');
		}
		if ($clientId) {
			$client = Auth::requireClient(Client::find($clientId));
			$clientId = (int) $client['id'];
		} else {
			$clientId = MailMessage::clientIdFor((int) $user['id'], $toList[0]);
		}
		$html = self::htmlFromText($body, $user);
		$to = implode(', ', $toList);
		$cc = implode(', ', $ccList);
		$attachments = $this->attachmentsFromPost('/correo/nuevo');
		try {
			$id = Mailbox::deliver((int) $user['id'], $user, $to, $subject, $html, $replyId, $clientId, $cc, $attachments);
		} catch (RuntimeException $e) {
			$this->finishMail(false, $e->getMessage(), '/correo/nuevo');
		}
		if ($clientId) {
			Activity::log('mail_sent', 'Correo enviado a ' . $to . ': ' . $subject, (int) $user['id'], $clientId);
			Client::update($clientId, ['updated_at' => date('c')]);
		}
		$this->finishMail(true, 'Correo enviado. Si responden, te llega a Correo.', '/correo/' . $id);
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
		$sort = Http::string('orden', 20) ?: 'fecha';
		$filter = Http::string('filtro', 20) ?: 'todos';
		$attachments = MailAttachment::ensureForMessage((int) $user['id'], $row);
		if ($attachments !== []) {
			$row['has_attachments'] = 1;
		}
		View::render('mail/inbox', [
			'title' => $row['subject'] ?: 'Correo',
			'folder' => $folder,
			'mailbox' => $box,
			'messages' => MailMessage::list((int) $user['id'], $folder, null, $query, $sort, $filter),
			'message' => $row,
			'attachments' => $attachments,
			'client' => $client,
			'unread' => MailMessage::unreadCount((int) $user['id']),
			'query' => $query,
			'sort' => $sort,
			'filter' => $filter,
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
		$mode = Http::string('mode', 20);
		if ($mode === 'forward') {
			$this->forward($user, $row, $body);
			return;
		}
		if ($body === '') {
			View::flash('error', 'Escribe la respuesta.');
			Http::redirect('/correo/' . $id);
		}
		$myEmail = mb_strtolower((string) (Mailbox::forUser((int) $user['id'])['email'] ?? $user['email']));
		$to = $row['folder'] === 'inbox' ? (string) $row['from_email'] : (string) $row['to_email'];
		$cc = '';
		if ($mode === 'all') {
			$pool = array_merge(
				Mime::emailsFromString((string) ($row['to_email'] ?? '')),
				Mime::emailsFromString((string) ($row['cc_email'] ?? '')),
				Mime::emailsFromString((string) ($row['from_email'] ?? ''))
			);
			$ccList = [];
			foreach ($pool as $email) {
				if ($email === $myEmail || $email === mb_strtolower($to)) {
					continue;
				}
				$ccList[] = $email;
			}
			$cc = implode(', ', array_values(array_unique($ccList)));
		}
		$subject = (string) $row['subject'];
		if (!str_starts_with(mb_strtolower($subject), 're:')) {
			$subject = 'Re: ' . $subject;
		}
		$clientId = !empty($row['client_id']) ? (int) $row['client_id'] : MailMessage::clientIdFor((int) $user['id'], $to);
		$html = self::htmlFromText($body, $user);
		$attachments = $this->attachmentsFromPost('/correo/' . $id);
		try {
			$newId = Mailbox::deliver((int) $user['id'], $user, $to, $subject, $html, (string) $row['message_id'], $clientId, $cc, $attachments);
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
			Http::redirect('/correo/' . $id);
		}
		if ($clientId) {
			Activity::log('mail_sent', 'Respuesta enviada a ' . $to . ': ' . $subject, (int) $user['id'], $clientId);
			Client::update($clientId, ['updated_at' => date('c')]);
		}
		View::flash('ok', $mode === 'all' ? 'Respuesta a todos enviada.' : 'Respuesta enviada.');
		Http::redirect('/correo/' . $newId);
	}

	public function destroy(string $id): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		$row = MailMessage::owned((int) $user['id'], (int) $id);
		if ($row && MailMessage::apply([$row], 'delete') === 0) {
			View::flash('error', 'No se pudo borrar el correo en el servidor. Sigue en tu casilla.');
			Http::redirect($row['folder'] === 'sent' ? '/correo/enviados' : '/correo');
		}
		View::flash('ok', 'Correo eliminado de tu casilla y del servidor.');
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

	public function bulk(): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		$action = Http::string('action', 20);
		$ids = $_POST['ids'] ?? [];
		if (!is_array($ids)) {
			$ids = [];
		}
		$rows = MailMessage::bulkOwned((int) $user['id'], $ids);
		$remote = match ($action) {
			'read' => 'seen',
			'unread' => 'unseen',
			'important' => 'flag',
			'unimportant' => 'unflag',
			'delete' => 'delete',
			default => '',
		};
		$n = $remote === '' || $rows === [] ? 0 : MailMessage::apply($rows, $remote);
		if ($rows === []) {
			View::flash('ok', 'No seleccionaste correos.');
		} elseif ($action === 'delete' && $n < count($rows)) {
			View::flash('error', $n === 0
				? 'No se pudieron borrar en el servidor de correo.'
				: "Se borraron {$n} correos. El resto sigue en el servidor.");
		} elseif ($n === 0) {
			View::flash('error', 'No se pudo actualizar el servidor de correo.');
		} else {
			View::flash('ok', "Listo: {$n} correo(s) quedaron igual en el servidor.");
		}
		$back = trim((string) ($_POST['back'] ?? '/correo'));
		if ($back === '' || !str_starts_with($back, '/correo')) {
			$back = '/correo';
		}
		Http::redirect($back);
	}

	public function attachment(string $id): void
	{
		$user = Auth::requireUser();
		$row = MailAttachment::owned((int) $user['id'], (int) $id);
		if (!$row) {
			http_response_code(404);
			echo 'Adjunto no encontrado.';
			exit;
		}
		$path = MailAttachment::absolutePath($row);
		if (!is_file($path)) {
			http_response_code(404);
			echo 'Archivo no disponible.';
			exit;
		}
		$mime = (string) ($row['mime'] ?: 'application/octet-stream');
		$mime = strtolower(trim(explode(';', $mime)[0]));
		if ($mime === '') {
			$mime = 'application/octet-stream';
		}
		$filename = (string) $row['filename'];
		$inline = MailAttachment::isPreviewable($mime, $filename) && Http::string('dl', 4) !== '1';
		header('Content-Type: ' . $mime);
		header('Content-Length: ' . (string) filesize($path));
		$safeName = str_replace(['"', "\r", "\n"], '', $filename);
		header(
			($inline ? 'Content-Disposition: inline' : 'Content-Disposition: attachment')
			. '; filename="' . $safeName . '"'
		);
		header('X-Content-Type-Options: nosniff');
		readfile($path);
		exit;
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
		$forceSync = Http::string('sync', 8) === '1';
		try {
			Mailbox::sync((int) $user['id'], $forceSync, !$forceSync);
		} catch (\Throwable $e) {
			$error = $e->getMessage() !== ''
				? $e->getMessage()
				: 'No se pudo actualizar el correo ahora. Intenta de nuevo en un momento.';
		}
		if ($error !== '') {
			View::flash('error', $error);
		}
		$query = Http::string('q', 80);
		$sort = Http::string('orden', 20) ?: 'fecha';
		$filter = Http::string('filtro', 20) ?: 'todos';
		$messages = MailMessage::list((int) $user['id'], $folder, null, $query, $sort, $filter);
		View::render('mail/inbox', [
			'title' => $folder === 'sent' ? 'Enviados' : 'Bandeja de entrada',
			'folder' => $folder,
			'mailbox' => $box,
			'messages' => $messages,
			'unread' => MailMessage::unreadCount((int) $user['id']),
			'query' => $query,
			'sort' => $sort,
			'filter' => $filter,
		]);
	}

	private function finishMail(bool $ok, string $message, string $redirect): void
	{
		if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
			Http::json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
		}
		View::flash($ok ? 'ok' : 'error', $message);
		Http::redirect($redirect);
	}

	/** @return list<array{filename:string,mime:string,content:string}> */
	private function attachmentsFromPost(string $failRedirect): array
	{
		$bag = $_FILES['adjuntos'] ?? null;
		if (!is_array($bag) || !isset($bag['name'])) {
			return [];
		}
		$names = $bag['name'];
		$tmp = $bag['tmp_name'] ?? [];
		$errors = $bag['error'] ?? [];
		$sizes = $bag['size'] ?? [];
		if (!is_array($names)) {
			$names = [$names];
			$tmp = [$tmp];
			$errors = [$errors];
			$sizes = [$sizes];
		}
		$out = [];
		$total = 0;
		foreach ($names as $i => $original) {
			$err = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
			if ($err === UPLOAD_ERR_NO_FILE || (string) $original === '') {
				continue;
			}
			if ($err !== UPLOAD_ERR_OK) {
				$this->finishMail(false, 'No se pudo leer un adjunto. Prueba con un archivo más liviano.', $failRedirect);
			}
			if (count($out) >= 5) {
				$this->finishMail(false, 'Puedes adjuntar hasta 5 archivos.', $failRedirect);
			}
			$size = (int) ($sizes[$i] ?? 0);
			if ($size <= 0 || $size > 8 * 1024 * 1024) {
				$this->finishMail(false, 'Cada adjunto puede pesar hasta 8 MB.', $failRedirect);
			}
			$total += $size;
			if ($total > 15 * 1024 * 1024) {
				$this->finishMail(false, 'Los adjuntos juntos superan 15 MB.', $failRedirect);
			}
			$path = (string) ($tmp[$i] ?? '');
			$binary = is_file($path) ? (string) file_get_contents($path) : '';
			if ($binary === '') {
				$this->finishMail(false, 'No se pudo leer un adjunto.', $failRedirect);
			}
			$ext = self::uploadExtension((string) $original);
			$types = self::uploadTypes();
			if (!isset($types[$ext])) {
				$this->finishMail(false, 'Puedes adjuntar PDF, imágenes, XML, Excel, Word, PowerPoint, TXT, CSV o ZIP.', $failRedirect);
			}
			$mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary));
			if (self::blockedUpload($mime, $binary)) {
				$this->finishMail(false, 'Ese archivo no se puede adjuntar.', $failRedirect);
			}
			if (in_array($mime, ['application/octet-stream', 'text/plain', 'application/zip', 'application/x-empty'], true)) {
				$mime = $types[$ext];
			}
			$out[] = [
				'filename' => self::safeUploadName((string) $original, $ext),
				'mime' => $mime !== '' ? $mime : $types[$ext],
				'content' => $binary,
			];
		}
		return $out;
	}

	/** @return array<string,string> */
	private static function uploadTypes(): array
	{
		return [
			'pdf' => 'application/pdf',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			'xml' => 'application/xml',
			'txt' => 'text/plain',
			'csv' => 'text/csv',
			'doc' => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls' => 'application/vnd.ms-excel',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'ppt' => 'application/vnd.ms-powerpoint',
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'zip' => 'application/zip',
			'rtf' => 'application/rtf',
		];
	}

	private static function uploadExtension(string $name): string
	{
		$name = basename(str_replace('\\', '/', $name));
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		return preg_match('/^[a-z0-9]{1,8}$/', $ext) ? $ext : '';
	}

	private static function blockedUpload(string $mime, string $binary): bool
	{
		$blocked = [
			'text/html',
			'application/javascript',
			'text/javascript',
			'application/x-httpd-php',
			'application/x-php',
			'application/x-executable',
			'application/x-dosexec',
			'application/x-msdownload',
			'application/x-sh',
		];
		if (in_array($mime, $blocked, true)) {
			return true;
		}
		$head = ltrim(substr($binary, 0, 64));
		return str_starts_with(strtolower($head), '<?php');
	}

	private static function safeUploadName(string $name, string $ext): string
	{
		$name = basename(str_replace('\\', '/', $name));
		$name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '', $name) ?? '';
		$name = trim((string) $name, '. ');
		if ($name === '') {
			$name = 'archivo.' . $ext;
		} elseif (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $name)) {
			$name .= '.' . $ext;
		}
		if (strlen($name) > 120) {
			$name = substr($name, -120);
		}
		return $name;
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $row */
	private function forward(array $user, array $row, string $body): void
	{
		$id = (int) $row['id'];
		$recipients = Mime::emailsFromString(Http::string('forward_to', 500));
		if ($recipients === []) {
			View::flash('error', 'Indica el correo de quien debe recibir el reenvío.');
			Http::redirect('/correo/' . $id);
		}
		$subject = (string) $row['subject'];
		$lower = mb_strtolower($subject);
		if (!str_starts_with($lower, 'fwd:') && !str_starts_with($lower, 'fw:')) {
			$subject = 'Fwd: ' . $subject;
		}
		$to = implode(', ', $recipients);
		$clientId = MailMessage::clientIdFor((int) $user['id'], $recipients[0]);
		$html = self::forwardHtml($body, $user, $row);
		$attachments = array_merge($this->storedAttachments($row), $this->attachmentsFromPost('/correo/' . $id));
		if (count($attachments) > 5) {
			View::flash('error', 'El reenvío junto con los archivos nuevos supera 5 adjuntos.');
			Http::redirect('/correo/' . $id);
		}
		$total = 0;
		foreach ($attachments as $file) {
			$total += strlen((string) ($file['content'] ?? ''));
		}
		if ($total > 15 * 1024 * 1024) {
			View::flash('error', 'Los adjuntos del reenvío superan 15 MB.');
			Http::redirect('/correo/' . $id);
		}
		try {
			$newId = Mailbox::deliver((int) $user['id'], $user, $to, $subject, $html, '', $clientId, '', $attachments);
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
			Http::redirect('/correo/' . $id);
		}
		if ($clientId) {
			Activity::log('mail_sent', 'Reenvío enviado a ' . $to . ': ' . $subject, (int) $user['id'], $clientId);
			Client::update($clientId, ['updated_at' => date('c')]);
		}
		View::flash('ok', 'Correo reenviado.');
		Http::redirect('/correo/' . $newId);
	}

	/** @param array<string,mixed> $row @return list<array{filename:string,mime:string,content:string}> */
	private function storedAttachments(array $row): array
	{
		$out = [];
		foreach (MailAttachment::forMessage((int) $row['id']) as $att) {
			$path = MailAttachment::absolutePath($att);
			if (!is_file($path)) {
				continue;
			}
			$content = (string) file_get_contents($path);
			if ($content === '') {
				continue;
			}
			$out[] = [
				'filename' => (string) $att['filename'],
				'mime' => (string) ($att['mime'] ?: 'application/octet-stream'),
				'content' => $content,
			];
		}
		return $out;
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $row */
	private static function forwardHtml(string $note, array $user, array $row): string
	{
		$fromName = trim((string) ($row['from_name'] ?? ''));
		$fromEmail = (string) ($row['from_email'] ?? '');
		$who = $fromName !== '' ? h($fromName) . ' &lt;' . h($fromEmail) . '&gt;' : h($fromEmail);
		$original = trim((string) ($row['body_html'] ?? ''));
		$original = $original !== '' ? Mime::safeHtml($original) : nl2br(h((string) ($row['body_text'] ?? '')), false);
		$noteHtml = trim($note) !== '' ? nl2br(h($note), false) . '<br><br>' : '';
		return '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.5;color:#222;">'
			. $noteHtml
			. \MizoCrm\Models\User::signatureHtml($user)
			. '<div style="margin-top:16px;border-left:3px solid #dadce0;padding-left:12px;color:#444;">'
			. '<p><strong>Mensaje reenviado</strong><br>De: ' . $who
			. '<br>Fecha: ' . h(when((string) ($row['sent_at'] ?? ''), 'd-m-Y H:i'))
			. '<br>Asunto: ' . h((string) ($row['subject'] ?? ''))
			. '<br>Para: ' . h((string) ($row['to_email'] ?? ''))
			. '</p>' . $original . '</div></div>';
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
