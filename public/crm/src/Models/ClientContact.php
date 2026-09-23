<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class ClientContact extends Record
{
	protected static function table(): string
	{
		return 'client_contacts';
	}

	public static function forClient(int $clientId): array
	{
		$stmt = self::pdo()->prepare(
			'SELECT * FROM client_contacts WHERE client_id = ? ORDER BY is_primary DESC, id ASC'
		);
		$stmt->execute([$clientId]);
		return $stmt->fetchAll();
	}

	/** Pone en la cotización el nombre, cargo, correo y teléfono del contacto elegido. */
	public static function applyToQuote(array $quote): array
	{
		$clientId = (int) ($quote['client_id'] ?? 0);
		$contact = null;
		$id = (int) ($quote['contact_id'] ?? 0);
		if ($id > 0 && $clientId > 0) {
			$contact = self::owned($id, $clientId);
		}
		if (!$contact) {
			$dealId = (int) ($quote['deal_id'] ?? 0);
			if ($dealId > 0) {
				$assigned = Deal::contactsByDeal([$dealId])[$dealId] ?? [];
				if ($assigned) {
					$contact = $assigned[0];
				}
			}
		}
		if (!$contact && $clientId > 0 && !empty($quote['sent_to'])) {
			foreach (self::forClient($clientId) as $row) {
				if (strcasecmp((string) $row['email'], (string) $quote['sent_to']) === 0) {
					$contact = $row;
					break;
				}
			}
		}
		if (!$contact) {
			return $quote;
		}
		$quote['contact_name'] = (string) ($contact['name'] ?? '');
		$quote['contact_title'] = (string) ($contact['title'] ?? '');
		$email = trim((string) ($contact['email'] ?? ''));
		if ($email !== '') {
			$quote['client_email'] = $email;
			$quote['contact_email'] = $email;
		}
		if (trim((string) ($contact['phone'] ?? '')) !== '') {
			$quote['client_phone'] = (string) $contact['phone'];
		}
		return $quote;
	}

	public static function owned(int $id, int $clientId): ?array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM client_contacts WHERE id = ? AND client_id = ?');
		$stmt->execute([$id, $clientId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function findByEmail(string $email): ?array
	{
		$email = mb_strtolower(trim($email));
		if ($email === '') {
			return null;
		}
		$stmt = self::pdo()->prepare(
			'SELECT ct.*, c.owner_id, c.name AS client_name
			 FROM client_contacts ct
			 JOIN clients c ON c.id = ct.client_id
			 WHERE ct.email = ?
			 ORDER BY ct.id DESC LIMIT 1'
		);
		$stmt->execute([$email]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	/** @param list<array{name?:string,email?:string,phone?:string,title?:string,id?:int}> $rows */
	public static function replaceForClient(int $clientId, array $rows): void
	{
		$now = date('c');
		$keep = [];
		$primarySet = false;
		foreach ($rows as $i => $row) {
			$name = trim((string) ($row['name'] ?? ''));
			$email = mb_strtolower(trim((string) ($row['email'] ?? '')));
			$phone = trim((string) ($row['phone'] ?? ''));
			$title = trim((string) ($row['title'] ?? ''));
			if ($name === '' && $email === '' && $phone === '') {
				continue;
			}
			if ($name === '') {
				$name = $email !== '' ? $email : 'Contacto';
			}
			$isPrimary = !$primarySet ? 1 : 0;
			$primarySet = true;
			$id = (int) ($row['id'] ?? 0);
			if ($id > 0 && self::owned($id, $clientId)) {
				self::update($id, [
					'name' => $name,
					'email' => $email,
					'phone' => $phone,
					'title' => $title,
					'is_primary' => $isPrimary,
				]);
				$keep[] = $id;
			} else {
				$keep[] = self::insert([
					'client_id' => $clientId,
					'name' => $name,
					'email' => $email,
					'phone' => $phone,
					'title' => $title,
					'is_primary' => $isPrimary,
					'created_at' => $now,
				]);
			}
		}
		$existing = self::forClient($clientId);
		foreach ($existing as $row) {
			if (!in_array((int) $row['id'], $keep, true)) {
				self::delete((int) $row['id']);
			}
		}
		self::syncClientPrimary($clientId);
	}

	public static function syncClientPrimary(int $clientId): void
	{
		$contacts = self::forClient($clientId);
		$primary = $contacts[0] ?? null;
		Client::update($clientId, [
			'contact_name' => $primary['name'] ?? '',
			'email' => $primary['email'] ?? '',
			'phone' => $primary['phone'] ?? '',
			'updated_at' => date('c'),
		]);
	}

	/** Clientes del ejecutivo con sus contactos (para redactar). */
	public static function directory(?int $ownerId): array
	{
		$sql = 'SELECT c.id AS client_id, c.name AS client_name, c.rut,
			ct.id AS contact_id, ct.name AS contact_name, ct.email, ct.phone, ct.title, ct.is_primary
			FROM clients c
			LEFT JOIN client_contacts ct ON ct.client_id = c.id
			WHERE 1=1';
		$params = [];
		if ($ownerId) {
			$sql .= ' AND c.owner_id = ?';
			$params[] = $ownerId;
		}
		$sql .= ' ORDER BY c.name ASC, ct.is_primary DESC, ct.name ASC';
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		$grouped = [];
		foreach ($stmt->fetchAll() as $row) {
			$cid = (int) $row['client_id'];
			if (!isset($grouped[$cid])) {
				$grouped[$cid] = [
					'id' => $cid,
					'name' => (string) $row['client_name'],
					'rut' => (string) ($row['rut'] ?? ''),
					'contacts' => [],
				];
			}
			if (!empty($row['contact_id'])) {
				$grouped[$cid]['contacts'][] = [
					'id' => (int) $row['contact_id'],
					'name' => (string) $row['contact_name'],
					'email' => (string) $row['email'],
					'phone' => (string) $row['phone'],
					'title' => (string) $row['title'],
					'is_primary' => (int) $row['is_primary'],
				];
			}
		}
		return array_values($grouped);
	}
}
