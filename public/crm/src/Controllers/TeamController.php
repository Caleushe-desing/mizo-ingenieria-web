<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\User;
use MizoCrm\View;

final class TeamController
{
	public function index(): void
	{
		Auth::requireAdmin();
		View::render('team', [
			'title' => 'Equipo',
			'users' => User::team(),
		]);
	}

	public function store(): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$name = Http::string('name', 80);
		$email = Http::string('email', 160);
		$password = (string) ($_POST['password'] ?? '');
		$role = Http::string('role', 20) === 'admin' ? 'admin' : 'vendedor';
		if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
			View::flash('error', 'Nombre, correo válido y clave de 8+ caracteres.');
			Http::redirect('/equipo');
		}
		if (User::findByEmail($email)) {
			View::flash('error', 'Ese correo ya está en el equipo.');
			Http::redirect('/equipo');
		}
		User::create($name, $email, $password, $role);
		View::flash('ok', $name . ' ya puede entrar al CRM.');
		Http::redirect('/equipo');
	}

	public function signature(string $id): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$member = User::find((int) $id);
		if (!$member) {
			Http::redirect('/equipo');
		}
		$html = User::sanitizeSignature(Http::text('signature', 80000));
		$plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		if ($plain === '' && !str_contains(strtolower($html), '<img')) {
			View::flash('error', 'Escribe una firma o pega una imagen.');
			Http::redirect('/equipo');
		}
		User::update((int) $id, ['signature' => $html]);
		View::flash('ok', 'Firma de ' . $member['name'] . ' guardada. Se usará en sus correos y cotizaciones.');
		Http::redirect('/equipo');
	}

	public function signatureImage(): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$file = $_FILES['image'] ?? null;
		if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			Http::json(['ok' => false, 'error' => 'No se pudo leer la imagen.'], 400);
		}
		$binary = (string) file_get_contents((string) $file['tmp_name']);
		$mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
		$url = User::storeSignatureImage($binary, $mime);
		if (!$url) {
			Http::json(['ok' => false, 'error' => 'Usa PNG, JPG, GIF o WebP de hasta 1,5 MB.'], 400);
		}
		Http::json(['ok' => true, 'url' => $url]);
	}

	public function destroy(string $id): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$member = User::find((int) $id);
		if (!$member) {
			Http::redirect('/equipo');
		}
		if ((int) $member['id'] === Auth::id()) {
			View::flash('error', 'No puedes quitarte el acceso a ti mismo.');
			Http::redirect('/equipo');
		}
		if ($member['role'] === 'admin' && User::adminCount() <= 1) {
			View::flash('error', 'Debe quedar al menos un administrador.');
			Http::redirect('/equipo');
		}
		User::deactivate((int) $id);
		View::flash('ok', $member['name'] . ' ya no puede entrar al CRM.');
		Http::redirect('/equipo');
	}
}
