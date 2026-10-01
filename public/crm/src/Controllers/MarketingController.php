<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\App;
use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Mail\Mime;
use MizoCrm\Mailer;
use MizoCrm\Marketing\FlyerStudio;
use MizoCrm\Marketing\TemplateEngine;
use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\ClientContact;
use MizoCrm\Models\Deal;
use MizoCrm\Models\Mailbox;
use MizoCrm\Models\MarketingResource;
use MizoCrm\Models\MarketingTemplate;
use MizoCrm\Models\Pipeline;
use MizoCrm\Models\Quote;
use MizoCrm\QuotePdf;
use MizoCrm\View;
use RuntimeException;

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

	public function resources(): void
	{
		Auth::requireUser();
		$category = Http::string('categoria', 80);
		$visual = MarketingResource::visual();
		$documents = MarketingResource::documents();
		if ($category !== '') {
			$visual = array_values(array_filter(
				$visual,
				static fn(array $row): bool => (string) ($row['category'] ?? '') === $category
			));
			$documents = array_values(array_filter(
				$documents,
				static fn(array $row): bool => (string) ($row['category'] ?? '') === $category
			));
		}
		View::render('marketing/resources', [
			'title' => 'Recursos y material comercial',
			'visual' => $visual,
			'documents' => $documents,
			'categories' => MarketingResource::categories(),
			'kinds' => MarketingResource::kinds(),
			'filterCategory' => $category,
			'canManage' => Auth::isAdmin(),
			'studioTemplates' => FlyerStudio::templates(),
			'studioBackgrounds' => FlyerStudio::backgrounds(),
			'studioBrand' => FlyerStudio::brand(),
			'csrf' => Csrf::token(),
			'saveDesignUrl' => Http::url('/marketing/recursos/diseno'),
			'libraryUploadUrl' => Http::url('/marketing/recursos/biblioteca'),
		]);
	}

	/** Guarda un flyer generado en el canvas (JPG/PNG/PDF) como recurso descargable. */
	public function saveDesign(): void
	{
		Csrf::check();
		$user = Auth::requireUser();

		$title = Http::string('title', 160);
		$description = Http::string('description', 500);
		$category = Http::string('category', 80);
		$format = strtolower(Http::string('format', 10));
		$imageData = (string) ($_POST['image'] ?? '');
		$allowedCategories = MarketingResource::categories();
		if ($title === '' || !in_array($category, $allowedCategories, true)) {
			Http::json(['ok' => false, 'error' => 'Indica título y categoría válidos.'], 422);
		}
		if (!in_array($format, ['png', 'jpg', 'jpeg', 'pdf'], true)) {
			$format = 'png';
		}
		if ($format === 'jpeg') {
			$format = 'jpg';
		}
		if (!preg_match('#^data:image/(png|jpeg);base64,#i', $imageData, $m)) {
			Http::json(['ok' => false, 'error' => 'La imagen del diseño no es válida.'], 422);
		}
		$binary = base64_decode(substr($imageData, strpos($imageData, ',') + 1), true);
		if ($binary === false || strlen($binary) < 100) {
			Http::json(['ok' => false, 'error' => 'No se pudo leer el diseño exportado.'], 422);
		}
		if (strlen($binary) > 18 * 1024 * 1024) {
			Http::json(['ok' => false, 'error' => 'El diseño es demasiado pesado. Prueba JPG.'], 422);
		}

		$ext = $format === 'pdf' ? 'png' : $format;
		$mimeMap = ['png' => 'image/png', 'jpg' => 'image/jpeg'];
		$mime = $mimeMap[$ext] ?? 'image/png';
		$safe = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? 'flyer-mizo'));
		$safe = trim($safe, '-') ?: 'flyer-mizo';
		$original = $safe . '.' . ($format === 'pdf' ? 'pdf' : $ext);

		$id = MarketingResource::insert([
			'kind' => 'flyer',
			'category' => $category,
			'title' => $title,
			'description' => $description !== '' ? $description : 'Flyer generado en el estudio Mizo.',
			'file_path' => '',
			'thumb_path' => '',
			'original_name' => $original,
			'mime' => $format === 'pdf' ? 'application/pdf' : $mime,
			'file_size' => 0,
			'active' => 1,
			'position' => MarketingResource::nextPosition(),
			'created_by' => (int) $user['id'],
			'created_at' => date('c'),
			'updated_at' => date('c'),
		]);

		$dir = MarketingResource::storageRoot() . '/' . $id;
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			MarketingResource::delete($id);
			Http::json(['ok' => false, 'error' => 'No se pudo crear la carpeta del recurso.'], 500);
		}

		$imageName = $safe . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
		$imagePath = $dir . '/' . $imageName;
		if (@file_put_contents($imagePath, $binary) === false) {
			MarketingResource::delete($id);
			Http::json(['ok' => false, 'error' => 'No se pudo guardar el archivo.'], 500);
		}

		$finalName = $imageName;
		$finalMime = $mime;
		$fileSize = (int) filesize($imagePath);
		if ($format === 'pdf') {
			try {
				$pdfBinary = $this->designToPdf($imagePath);
				$pdfName = $safe . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.pdf';
				$pdfPath = $dir . '/' . $pdfName;
				if (@file_put_contents($pdfPath, $pdfBinary) === false) {
					throw new RuntimeException('No se pudo escribir el PDF.');
				}
				$finalName = $pdfName;
				$finalMime = 'application/pdf';
				$fileSize = (int) filesize($pdfPath);
			} catch (\Throwable $e) {
				// Si falla PDF, dejamos el PNG como recurso usable.
				$finalName = $imageName;
				$finalMime = $mime;
				$original = $safe . '.png';
			}
		}

		$filePath = '/crm/uploads/marketing/' . $id . '/' . $finalName;
		$thumbPath = '';
		$thumbName = $this->makeImageThumb($imagePath, $dir);
		if ($thumbName !== null) {
			$thumbPath = '/crm/uploads/marketing/' . $id . '/' . $thumbName;
		} elseif ($finalMime !== 'application/pdf') {
			$thumbPath = $filePath;
		} else {
			$thumbPath = '/crm/uploads/marketing/' . $id . '/' . $imageName;
		}

		MarketingResource::update($id, [
			'file_path' => $filePath,
			'thumb_path' => $thumbPath,
			'original_name' => $original,
			'mime' => $finalMime,
			'file_size' => $fileSize,
			'updated_at' => date('c'),
		]);

		Http::json([
			'ok' => true,
			'id' => $id,
			'download' => Http::url('/marketing/recursos/' . $id . '/descargar'),
			'message' => 'Diseño guardado en Recursos y listo para descargar.',
		]);
	}

	/** Sube una imagen corporativa a la biblioteca del estudio (admin). */
	public function storeLibrary(): void
	{
		Csrf::check();
		Auth::requireUser();
		if (!Auth::isAdmin()) {
			View::flash('error', 'Solo administración puede ampliar la biblioteca.');
			Http::redirect('/marketing/recursos');
		}
		$file = $_FILES['archivo'] ?? null;
		if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			View::flash('error', 'Sube una imagen JPG, PNG o WEBP.');
			Http::redirect('/marketing/recursos#estudio');
		}
		$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
		if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
			View::flash('error', 'Formato de biblioteca no permitido.');
			Http::redirect('/marketing/recursos#estudio');
		}
		if ((int) ($file['size'] ?? 0) > 12 * 1024 * 1024) {
			View::flash('error', 'La imagen de biblioteca debe pesar máximo 12 MB.');
			Http::redirect('/marketing/recursos#estudio');
		}
		$dir = FlyerStudio::libraryRoot();
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			View::flash('error', 'No se pudo crear la biblioteca.');
			Http::redirect('/marketing/recursos#estudio');
		}
		$base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', pathinfo((string) $file['name'], PATHINFO_FILENAME)) ?? 'fondo'));
		$base = trim($base, '-') ?: 'fondo';
		$name = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
		$dest = $dir . '/' . $name;
		if (!@move_uploaded_file((string) $file['tmp_name'], $dest)) {
			View::flash('error', 'No se pudo guardar la imagen en la biblioteca.');
			Http::redirect('/marketing/recursos#estudio');
		}
		View::flash('ok', 'Imagen agregada a la biblioteca del estudio.');
		Http::redirect('/marketing/recursos#estudio');
	}

	private function designToPdf(string $imagePath): string
	{
		$autoload = dirname(__DIR__, 2) . '/lib/dompdf-src/dompdf/autoload.inc.php';
		if (!is_file($autoload)) {
			throw new RuntimeException('Dompdf no está instalado.');
		}
		require_once $autoload;
		$crmRoot = realpath(dirname(__DIR__, 2));
		if ($crmRoot === false) {
			throw new RuntimeException('No se encontró la carpeta CRM.');
		}
		$abs = realpath($imagePath);
		if ($abs === false || !str_starts_with($abs, $crmRoot)) {
			throw new RuntimeException('Ruta de imagen inválida.');
		}
		$src = ltrim(str_replace('\\', '/', substr($abs, strlen($crmRoot))), '/');
		$html = '<!doctype html><html><head><meta charset="UTF-8"><style>
			@page { margin: 0; }
			html, body { margin: 0; padding: 0; }
			img { width: 100%; height: auto; display: block; }
		</style></head><body><img src="' . h($src) . '" alt="Flyer Mizo"></body></html>';

		$options = new \Dompdf\Options();
		$options->set('isRemoteEnabled', false);
		$options->set('isHtml5ParserEnabled', true);
		$options->setChroot($crmRoot);
		$dompdf = new \Dompdf\Dompdf($options);
		$dompdf->loadHtml($html, 'UTF-8');
		// Proporción 1080x1350 ≈ 8.5 x 10.625 in
		$dompdf->setPaper([0.0, 0.0, 612.0, 765.0]);
		$dompdf->render();
		$out = $dompdf->output();
		if (!is_string($out) || $out === '') {
			throw new RuntimeException('PDF vacío.');
		}
		return $out;
	}

	public function storeResource(): void
	{
		Csrf::check();
		Auth::requireUser();
		if (!Auth::isAdmin()) {
			View::flash('error', 'Solo administración puede subir material oficial.');
			Http::redirect('/marketing/recursos');
		}

		$title = Http::string('title', 160);
		$description = Http::string('description', 500);
		$category = Http::string('category', 80);
		$kind = Http::string('kind', 20);
		$allowedKinds = array_keys(MarketingResource::kinds());
		$allowedCategories = MarketingResource::categories();
		if ($title === '' || !in_array($kind, $allowedKinds, true) || !in_array($category, $allowedCategories, true)) {
			View::flash('error', 'Completa título, tipo y categoría válidos.');
			Http::redirect('/marketing/recursos');
		}

		try {
			$stored = $this->storeUploadedResource();
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
			Http::redirect('/marketing/recursos');
		}

		$id = MarketingResource::insert([
			'kind' => $kind,
			'category' => $category,
			'title' => $title,
			'description' => $description,
			'file_path' => '',
			'thumb_path' => '',
			'original_name' => $stored['original'],
			'mime' => $stored['mime'],
			'file_size' => $stored['size'],
			'active' => 1,
			'position' => MarketingResource::nextPosition(),
			'created_by' => Auth::id(),
			'created_at' => date('c'),
			'updated_at' => date('c'),
		]);

		$dir = MarketingResource::storageRoot() . '/' . $id;
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			MarketingResource::delete($id);
			View::flash('error', 'No se pudo crear la carpeta del recurso.');
			Http::redirect('/marketing/recursos');
		}

		$dest = $dir . '/' . $stored['filename'];
		if (!@rename($stored['tmp'], $dest) && !@move_uploaded_file($stored['tmp'], $dest)) {
			if (!@copy($stored['tmp'], $dest)) {
				MarketingResource::delete($id);
				@unlink($stored['tmp']);
				View::flash('error', 'No se pudo guardar el archivo.');
				Http::redirect('/marketing/recursos');
			}
			@unlink($stored['tmp']);
		}

		$filePath = '/crm/uploads/marketing/' . $id . '/' . $stored['filename'];
		$thumbPath = '';
		if (str_starts_with($stored['mime'], 'image/')) {
			$thumbName = $this->makeImageThumb($dest, $dir);
			if ($thumbName !== null) {
				$thumbPath = '/crm/uploads/marketing/' . $id . '/' . $thumbName;
			}
		}

		MarketingResource::update($id, [
			'file_path' => $filePath,
			'thumb_path' => $thumbPath,
			'updated_at' => date('c'),
		]);

		View::flash('ok', 'Recurso publicado en el centro de material comercial.');
		Http::redirect('/marketing/recursos');
	}

	public function destroyResource(string $id): void
	{
		Csrf::check();
		Auth::requireUser();
		if (!Auth::isAdmin()) {
			View::flash('error', 'Solo administración puede ocultar material oficial.');
			Http::redirect('/marketing/recursos');
		}
		$row = MarketingResource::find((int) $id);
		if (!$row) {
			View::flash('error', 'Recurso no encontrado.');
			Http::redirect('/marketing/recursos');
		}
		MarketingResource::update((int) $id, [
			'active' => 0,
			'updated_at' => date('c'),
		]);
		View::flash('ok', 'Recurso ocultado del centro de material.');
		Http::redirect('/marketing/recursos');
	}

	public function downloadResource(string $id): void
	{
		Auth::requireUser();
		$row = MarketingResource::find((int) $id);
		if (!$row || empty($row['active'])) {
			http_response_code(404);
			echo 'Recurso no disponible.';
			exit;
		}
		$path = MarketingResource::absolutePath($row);
		if ($path === null) {
			http_response_code(404);
			echo 'Archivo no encontrado.';
			exit;
		}
		$mime = trim((string) ($row['mime'] ?? '')) ?: 'application/octet-stream';
		$filename = trim((string) ($row['original_name'] ?? '')) ?: basename($path);
		$filename = preg_replace('/[\r\n"]+/', '', $filename) ?: 'recurso-mizo';
		header('Content-Type: ' . $mime);
		header('Content-Length: ' . (string) filesize($path));
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: private, no-store');
		readfile($path);
		exit;
	}

	/**
	 * @return array{tmp:string,filename:string,original:string,mime:string,size:int}
	 */
	private function storeUploadedResource(): array
	{
		$file = $_FILES['archivo'] ?? null;
		if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			throw new RuntimeException('Sube un archivo de imagen o PDF.');
		}
		$size = (int) ($file['size'] ?? 0);
		if ($size <= 0 || $size > 25 * 1024 * 1024) {
			throw new RuntimeException('El archivo debe pesar como máximo 25 MB.');
		}
		$original = (string) ($file['name'] ?? 'archivo');
		$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
		$allowed = [
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
			'gif' => 'image/gif',
			'pdf' => 'application/pdf',
		];
		if (!isset($allowed[$ext])) {
			throw new RuntimeException('Formato no permitido. Usa JPG, PNG, WEBP, GIF o PDF.');
		}
		$tmp = (string) ($file['tmp_name'] ?? '');
		if ($tmp === '' || !is_uploaded_file($tmp)) {
			throw new RuntimeException('La subida del archivo falló.');
		}
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$detected = (string) ($finfo->file($tmp) ?: '');
		$mime = $allowed[$ext];
		if ($detected !== '' && $detected !== $mime && !($ext === 'jpg' && $detected === 'image/jpeg')) {
			// Algunos hostings reportan application/octet-stream; confiamos en la extensión validada.
			if ($detected !== 'application/octet-stream') {
				throw new RuntimeException('El contenido del archivo no coincide con su extensión.');
			}
		}
		$safeBase = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', pathinfo($original, PATHINFO_FILENAME)) ?? 'recurso'));
		$safeBase = trim($safeBase, '-') ?: 'recurso';
		$filename = $safeBase . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
		$staging = sys_get_temp_dir() . '/mizo-mkt-' . $filename;
		if (!@move_uploaded_file($tmp, $staging)) {
			throw new RuntimeException('No se pudo preparar el archivo subido.');
		}
		return [
			'tmp' => $staging,
			'filename' => $filename,
			'original' => $original,
			'mime' => $mime,
			'size' => $size,
		];
	}

	private function makeImageThumb(string $source, string $dir): ?string
	{
		if (!function_exists('imagecreatetruecolor') || !function_exists('imagecreatefromstring')) {
			return null;
		}
		$raw = @file_get_contents($source);
		if ($raw === false || $raw === '') {
			return null;
		}
		$src = @imagecreatefromstring($raw);
		if ($src === false) {
			return null;
		}
		$w = imagesx($src);
		$h = imagesy($src);
		if ($w < 1 || $h < 1) {
			imagedestroy($src);
			return null;
		}
		$max = 640;
		$scale = min(1, $max / max($w, $h));
		$tw = max(1, (int) round($w * $scale));
		$th = max(1, (int) round($h * $scale));
		$dst = imagecreatetruecolor($tw, $th);
		imagealphablending($dst, false);
		imagesavealpha($dst, true);
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
		$name = 'thumb.webp';
		$path = $dir . '/' . $name;
		$ok = false;
		if (function_exists('imagewebp')) {
			$ok = imagewebp($dst, $path, 82);
		}
		if (!$ok) {
			$name = 'thumb.jpg';
			$path = $dir . '/' . $name;
			$ok = imagejpeg($dst, $path, 85);
		}
		imagedestroy($dst);
		imagedestroy($src);
		return $ok ? $name : null;
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
