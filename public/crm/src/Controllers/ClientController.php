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
		$rut = Http::string('rut', 20);
		$city = Http::string('city', 80);
		$comment = self::commentFromPost();
		$contacts = self::contactsFromPost();
		if ($name === '') {
			View::flash('error', 'Escribe el nombre del cliente.');
			Http::redirect('/clientes/nuevo');
		}
		$hasReach = false;
		foreach ($contacts as $c) {
			if (($c['email'] ?? '') !== '' || ($c['phone'] ?? '') !== '') {
				$hasReach = true;
				break;
			}
		}
		if (!$hasReach) {
			View::flash('error', 'Agrega al menos un contacto con correo o teléfono.');
			Http::redirect('/clientes/nuevo');
		}
		foreach ($contacts as $c) {
			$email = (string) ($c['email'] ?? '');
			if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				View::flash('error', 'Hay un correo de contacto inválido.');
				Http::redirect('/clientes/nuevo');
			}
			$phone = (string) ($c['phone'] ?? '');
			if (!Auth::isAdmin() && Client::conflictForUser($email, $phone, Auth::id())) {
				View::flash('error', 'Ese cliente/contacto ya lo lleva otro ejecutivo.');
				Http::redirect('/clientes/nuevo');
			}
		}

		$primary = $contacts[0] ?? ['name' => '', 'email' => '', 'phone' => ''];
		$now = date('c');
		$id = Client::insert([
			'name' => $name,
			'contact_name' => (string) ($primary['name'] ?? ''),
			'email' => (string) ($primary['email'] ?? ''),
			'phone' => (string) ($primary['phone'] ?? ''),
			'rut' => $rut,
			'city' => $city,
			'source' => 'otro',
			'notes' => $comment,
			'owner_id' => Auth::id(),
			'created_at' => $now,
			'updated_at' => $now,
		]);
		\MizoCrm\Models\ClientContact::replaceForClient($id, $contacts);
		if ($comment !== '') {
			Activity::log('comentario', $comment, Auth::id(), $id);
		}
		View::flash('ok', 'Cliente guardado con sus contactos.');
		Http::redirect('/clientes/' . $id);
	}

	public function show(string $id): void
	{
		$client = Auth::requireClient(Client::find((int) $id));
		View::render('clients/show', [
			'title' => 'Editar ' . $client['name'],
			'client' => $client,
			'contacts' => \MizoCrm\Models\ClientContact::forClient((int) $id),
			'team' => Auth::isAdmin() ? User::team() : [],
		]);
	}

	public function update(string $id): void
	{
		Csrf::check();
		$client = Auth::requireClient(Client::find((int) $id));
		$name = Http::string('name', 120);
		$rut = Http::string('rut', 20);
		$city = Http::string('city', 80);
		$contacts = self::contactsFromPost();
		$back = Http::string('volver', 20) === 'ficha'
			? '/tablero/cliente/' . $id . '/ficha'
			: '/clientes/' . $id;
		if ($name === '') {
			View::flash('error', 'El cliente no puede quedar vacío.');
			Http::redirect($back);
		}
		foreach ($contacts as $c) {
			$email = (string) ($c['email'] ?? '');
			if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				View::flash('error', 'Hay un correo de contacto inválido.');
				Http::redirect($back);
			}
		}
		$primary = $contacts[0] ?? ['name' => '', 'email' => '', 'phone' => ''];
		$payload = [
			'name' => $name,
			'rut' => $rut,
			'contact_name' => (string) ($primary['name'] ?? ''),
			'email' => (string) ($primary['email'] ?? ''),
			'phone' => (string) ($primary['phone'] ?? ''),
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
		\MizoCrm\Models\ClientContact::replaceForClient((int) $id, $contacts);
		View::flash('ok', 'Datos del cliente y contactos actualizados.');
		Http::redirect($back);
	}

	public function comment(string $id): void
	{
		Csrf::check();
		Auth::requireClient(Client::find((int) $id));
		$message = self::commentFromPost();
		if ($message === '') {
			View::flash('error', 'Escribe el comentario antes de guardar.');
			Http::redirect('/tablero/cliente/' . $id . '/ficha');
		}
		$kind = Http::string('kind', 20);
		$type = match ($kind) {
			'recordatorio' => 'recordatorio',
			'llamada' => 'llamada',
			default => 'comentario',
		};
		Activity::log($type, $message, Auth::id(), (int) $id);
		Client::update((int) $id, ['updated_at' => date('c')]);
		View::flash('ok', 'Comentario guardado.');
		Http::redirect('/tablero/cliente/' . $id . '/ficha');
	}

	public function fromDeal(string $id): void
	{
		$deal = Deal::find((int) $id);
		if (!$deal) {
			Http::redirect('/');
		}
		Auth::requireClient(Client::find((int) $deal['client_id']));
		Http::redirect('/tablero/cliente/' . $deal['client_id'] . '/ficha');
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
			Http::redirect('/tablero/cliente/' . $clientId . '/ficha');
		}
		Activity::delete((int) $commentId);
		Client::update((int) $clientId, ['updated_at' => date('c')]);
		View::flash('ok', 'Comentario eliminado.');
		Http::redirect('/tablero/cliente/' . $clientId . '/ficha');
	}

	private static function commentFromPost(): string
	{
		$message = trim((string) ($_POST['comment'] ?? ''));
		return function_exists('mb_substr') ? mb_substr($message, 0, 4000, 'UTF-8') : substr($message, 0, 4000);
	}

	/** @return list<array{id?:int,name:string,email:string,phone:string,title:string}> */
	private static function contactsFromPost(): array
	{
		$names = $_POST['contact_name'] ?? [];
		$emails = $_POST['contact_email'] ?? [];
		$phones = $_POST['contact_phone'] ?? [];
		$titles = $_POST['contact_title'] ?? [];
		$ids = $_POST['contact_id'] ?? [];
		if (!is_array($names)) {
			$names = [$names];
			$emails = [$emails];
			$phones = [$phones];
			$titles = [$titles];
			$ids = [$ids];
		}
		$out = [];
		$n = max(count($names), count($emails), count($phones));
		for ($i = 0; $i < $n; $i++) {
			$name = trim((string) ($names[$i] ?? ''));
			$email = mb_strtolower(trim((string) ($emails[$i] ?? '')));
			$phone = trim((string) ($phones[$i] ?? ''));
			$title = trim((string) ($titles[$i] ?? ''));
			$id = (int) ($ids[$i] ?? 0);
			if ($name === '' && $email === '' && $phone === '') {
				continue;
			}
			$row = [
				'name' => $name,
				'email' => $email,
				'phone' => $phone,
				'title' => $title,
			];
			if ($id > 0) {
				$row['id'] = $id;
			}
			$out[] = $row;
		}
		return $out;
	}
}
