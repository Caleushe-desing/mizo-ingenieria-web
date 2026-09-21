<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\Deal;
use MizoCrm\Models\User;
use MizoCrm\View;

final class ClientController
{
	public function index(): void
	{
		$q = Http::string('q', 80);
		View::render('clients/index', [
			'title' => 'Clientes',
			'q' => $q,
			'clients' => Client::search($q !== '' ? $q : null, Auth::ownerScope()),
		]);
	}

	public function create(): void
	{
		View::render('clients/form', [
			'title' => 'Nuevo cliente',
		]);
	}

	public function store(): void
	{
		Csrf::check();
		$name = Http::string('name', 120);
		$contact = Http::string('contact_name', 120);
		$email = Http::string('email', 160);
		$phone = Http::string('phone', 40);
		$city = Http::string('city', 80);
		$comment = self::commentFromPost();
		if ($name === '') {
			View::flash('error', 'Escribe el nombre del cliente.');
			Http::redirect('/clientes/nuevo');
		}
		if ($email === '' && $phone === '') {
			View::flash('error', 'Indica un correo o un teléfono.');
			Http::redirect('/clientes/nuevo');
		}
		if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			View::flash('error', 'El correo no es válido.');
			Http::redirect('/clientes/nuevo');
		}
		if (!Auth::isAdmin() && Client::conflictForUser($email, $phone, Auth::id())) {
			View::flash('error', 'Ese cliente ya lo lleva otro ejecutivo. Pide al administrador que te lo asigne.');
			Http::redirect('/clientes/nuevo');
		}

		$now = date('c');
		$id = Client::insert([
			'name' => $name,
			'contact_name' => $contact,
			'email' => $email !== '' ? mb_strtolower($email) : '',
			'phone' => $phone,
			'rut' => '',
			'city' => $city,
			'source' => 'otro',
			'notes' => $comment,
			'owner_id' => Auth::id(),
			'created_at' => $now,
			'updated_at' => $now,
		]);
		if ($comment !== '') {
			Activity::log('comentario', $comment, Auth::id(), $id);
		}
		View::flash('ok', 'Cliente guardado. Ya puedes dejar comentarios o armar una cotización.');
		Http::redirect('/clientes/' . $id);
	}

	public function show(string $id): void
	{
		$client = Auth::requireClient(Client::find((int) $id));
		$owner = !empty($client['owner_id']) ? User::find((int) $client['owner_id']) : null;
		View::render('clients/show', [
			'title' => $client['name'],
			'client' => $client,
			'comments' => Activity::commentsForClient((int) $id),
			'quotes' => Client::quotes((int) $id),
			'mails' => \MizoCrm\Models\MailMessage::forClient(Auth::id(), (int) $id),
			'owner' => $owner,
			'team' => Auth::isAdmin() ? User::team() : [],
		]);
	}

	public function update(string $id): void
	{
		Csrf::check();
		$client = Auth::requireClient(Client::find((int) $id));
		$name = Http::string('name', 120);
		$contact = Http::string('contact_name', 120);
		$email = Http::string('email', 160);
		$phone = Http::string('phone', 40);
		$city = Http::string('city', 80);
		if ($name === '') {
			View::flash('error', 'El cliente no puede quedar vacío.');
			Http::redirect('/clientes/' . $id);
		}
		if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			View::flash('error', 'El correo no es válido.');
			Http::redirect('/clientes/' . $id);
		}
		$payload = [
			'name' => $name,
			'contact_name' => $contact,
			'email' => $email !== '' ? mb_strtolower($email) : '',
			'phone' => $phone,
			'city' => $city,
			'updated_at' => date('c'),
		];
		if (Auth::isAdmin() && isset($_POST['owner_id'])) {
			$ownerId = Http::int('owner_id');
			$payload['owner_id'] = $ownerId > 0 ? $ownerId : null;
			$prevOwner = (int) ($client['owner_id'] ?? 0);
			if ($payload['owner_id'] && $payload['owner_id'] !== $prevOwner) {
				Activity::log('assigned', 'Cliente asignado a un ejecutivo.', Auth::id(), (int) $id);
			}
		}
		Client::update((int) $id, $payload);
		View::flash('ok', 'Datos del cliente actualizados.');
		Http::redirect('/clientes/' . $id);
	}

	public function comment(string $id): void
	{
		Csrf::check();
		Auth::requireClient(Client::find((int) $id));
		$message = self::commentFromPost();
		if ($message === '') {
			View::flash('error', 'Escribe el comentario antes de guardar.');
			Http::redirect('/clientes/' . $id);
		}
		Activity::log('comentario', $message, Auth::id(), (int) $id);
		Client::update((int) $id, ['updated_at' => date('c')]);
		View::flash('ok', 'Comentario guardado.');
		Http::redirect('/clientes/' . $id);
	}

	public function fromDeal(string $id): void
	{
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::redirect('/');
		}
		Auth::requireClient(Client::find((int) $deal['client_id']));
		Http::redirect('/clientes/' . $deal['client_id']);
	}

	public function destroy(string $id): void
	{
		Csrf::check();
		$client = Auth::requireClient(Client::find((int) $id));
		$name = (string) $client['name'];
		Client::purge((int) $id);
		View::flash('ok', 'Se eliminó a ' . $name . ' y todo lo que tenía.');
		Http::redirect('/');
	}

	public function destroyComment(string $clientId, string $commentId): void
	{
		Csrf::check();
		Auth::requireClient(Client::find((int) $clientId));
		$note = Activity::find((int) $commentId);
		if (!$note || (int) $note['client_id'] !== (int) $clientId) {
			View::flash('error', 'Ese comentario ya no está.');
			Http::redirect('/clientes/' . $clientId);
		}
		Activity::delete((int) $commentId);
		Client::update((int) $clientId, ['updated_at' => date('c')]);
		View::flash('ok', 'Comentario eliminado.');
		Http::redirect('/clientes/' . $clientId);
	}

	private static function commentFromPost(): string
	{
		$message = trim((string) ($_POST['comment'] ?? ''));
		return function_exists('mb_substr') ? mb_substr($message, 0, 4000, 'UTF-8') : substr($message, 0, 4000);
	}
}
