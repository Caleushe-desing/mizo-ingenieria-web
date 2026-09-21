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
