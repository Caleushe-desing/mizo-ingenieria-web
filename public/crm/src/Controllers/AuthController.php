<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\User;
use MizoCrm\View;

final class AuthController
{
	public function show(): void
	{
		if (Auth::user()) {
			Http::redirect('/');
		}
		View::render('login', ['title' => 'Entrar al CRM'], 'auth-layout');
	}

	public function login(): void
	{
		Csrf::check();
		$email = Http::string('email', 160);
		$password = (string) ($_POST['password'] ?? '');
		$user = User::findByEmail($email);
		if (!$user || !(int) $user['active'] || !password_verify($password, $user['password_hash'])) {
			View::flash('error', 'Correo o clave incorrectos.');
			Http::redirect('/login');
		}
		Auth::login($user);
		Http::redirect('/');
	}

	public function logout(): void
	{
		Csrf::check();
		Auth::logout();
		Http::redirect('/login');
	}
}
