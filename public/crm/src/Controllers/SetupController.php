<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\User;
use MizoCrm\View;

final class SetupController
{
	public function show(): void
	{
		View::render('setup', ['title' => 'Activar CRM'], 'auth-layout');
	}

	public function store(): void
	{
		Csrf::check();
		$name = Http::string('name', 80);
		$email = Http::string('email', 160);
		$password = (string) ($_POST['password'] ?? '');
		if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
			View::flash('error', 'Completa nombre, correo válido y una clave de al menos 8 caracteres.');
			Http::redirect('/setup');
		}
		$id = User::create($name, $email, $password, 'admin');
		Auth::login(['id' => $id, 'active' => 1, 'role' => 'admin']);
		View::flash('ok', 'CRM listo. Empieza agregando un cliente.');
		Http::redirect('/');
	}
}
