<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Mail\Imap;
use MizoCrm\Mail\Mime;
use MizoCrm\Mail\Smtp;
use MizoCrm\Record;
use MizoCrm\Secret;
use RuntimeException;

final class Mailbox extends Record
{
	protected static function table(): string
	{
		return 'mailboxes';
	}

	public static function forUser(int $userId): ?array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM mailboxes WHERE user_id = ?');
		$stmt->execute([$userId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function defaults(string $email): array
	{
		$domain = strtolower((string) substr(strrchr($email, '@') ?: '@mizo.cl', 1));
		if ($domain === '') {
			$domain = 'mizo.cl';
		}
		return [
			'email' => mb_strtolower(trim($email)),
			'smtp_host' => 'smtp.hostinger.com',
			'smtp_port' => 465,
			'imap_host' => 'imap.hostinger.com',
			'imap_port' => 993,
			'alt_host' => 'mail.' . $domain,
		];
	}

	public static function connect(int $userId, string $email, string $password): void
	{
		$email = mb_strtolower(trim($email));
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new RuntimeException('Escribe un correo válido.');
		}
		if (strlen($password) < 4) {
			throw new RuntimeException('Escribe la clave de la casilla de correo.');
		}
		$defaults = self::defaults($email);
		$attempts = [
			['smtp_host' => $defaults['smtp_host'], 'smtp_port' => 465, 'imap_host' => $defaults['imap_host'], 'imap_port' => 993],
			['smtp_host' => $defaults['alt_host'], 'smtp_port' => 465, 'imap_host' => $defaults['alt_host'], 'imap_port' => 993],
		];
		$last = 'No se pudo conectar a la casilla.';
		foreach ($attempts as $hosts) {
			$box = [
				'email' => $email,
				'password' => $password,
				...$hosts,
			];
			try {
				Smtp::probe($box);
				$sentFolder = Imap::probe($box);
				self::saveBox($userId, $box, $sentFolder);
				return;
			} catch (RuntimeException $e) {
				$last = $e->getMessage();
			}
		}
		throw new RuntimeException($last);
	}

	public static function open(int $userId): array
	{
		$row = self::forUser($userId);
		if (!$row) {
			throw new RuntimeException('Conecta tu casilla de correo para enviar y recibir.');
		}
		$row['password'] = Secret::open((string) $row['password_enc']);
		return $row;
	}

	public static function disconnect(int $userId): void
	{
		self::pdo()->prepare('DELETE FROM mail_messages WHERE user_id = ?')->execute([$userId]);
		self::pdo()->prepare('DELETE FROM mailboxes WHERE user_id = ?')->execute([$userId]);
	}

	public static function sync(int $userId, bool $force = false, bool $inboxOnly = false): void
	{
		$box = self::open($userId);
		$last = strtotime((string) ($box['last_sync'] ?? '')) ?: 0;
		if (!$force && $last > time() - 40) {
			return;
		}
		$imap = new Imap($box);
		$notify = !empty($box['last_sync']);
		try {
			self::syncFolder($userId, $imap, 'INBOX', 'inbox', $notify);
			if (!$inboxOnly) {
				$sent = (string) ($box['sent_folder'] ?: 'Sent');
				try {
					self::syncFolder($userId, $imap, $sent, 'sent', false);
				} catch (RuntimeException) {
					$sent = $imap->findSentFolder();
					if ($sent !== (string) $box['sent_folder']) {
						self::pdo()->prepare('UPDATE mailboxes SET sent_folder = ? WHERE user_id = ?')->execute([$sent, $userId]);
						self::syncFolder($userId, $imap, $sent, 'sent', false);
					}
				}
			}
		} finally {
			$imap->close();
		}
		self::pdo()->prepare('UPDATE mailboxes SET last_sync = ? WHERE user_id = ?')->execute([date('c'), $userId]);
	}

	public static function deliver(int $userId, array $user, string $to, string $subject, string $html, string $replyToMessageId = '', ?int $clientId = null): int
	{
		$box = self::open($userId);
		$fromName = User::mailFromName($user);
		$rfc822 = Mime::build($fromName, (string) $box['email'], $to, $subject, $html, $replyToMessageId);
		Smtp::send($box, $to, $rfc822);
		try {
			$imap = new Imap($box);
			try {
				$imap->append((string) ($box['sent_folder'] ?: 'Sent'), $rfc822);
			} finally {
				$imap->close();
			}
		} catch (RuntimeException) {
			// El envío ya salió; si no se guarda en Enviados igual queda en el CRM.
		}

		$parsed = Mime::parse($rfc822);
		return MailMessage::store($userId, 'sent', [
			'uid' => null,
			'message_id' => $parsed['message_id'],
			'in_reply_to' => $parsed['in_reply_to'],
			'from_email' => $box['email'],
			'from_name' => $fromName,
			'to_email' => mb_strtolower($to),
			'subject' => $subject,
			'body_text' => $parsed['body_text'],
			'body_html' => $html,
			'sent_at' => date('c'),
			'seen' => 1,
			'client_id' => $clientId,
		]);
	}

	private static function saveBox(int $userId, array $box, string $sentFolder): void
	{
		$payload = [
			'user_id' => $userId,
			'email' => $box['email'],
			'password_enc' => Secret::seal((string) $box['password']),
			'smtp_host' => $box['smtp_host'],
			'smtp_port' => $box['smtp_port'],
			'imap_host' => $box['imap_host'],
			'imap_port' => $box['imap_port'],
			'sent_folder' => $sentFolder,
			'last_sync' => null,
		];
		$existing = self::forUser($userId);
		if ($existing) {
			$stmt = self::pdo()->prepare(
				'UPDATE mailboxes SET email = ?, password_enc = ?, smtp_host = ?, smtp_port = ?, imap_host = ?, imap_port = ?, sent_folder = ?, last_sync = NULL WHERE user_id = ?'
			);
			$stmt->execute([
				$payload['email'],
				$payload['password_enc'],
				$payload['smtp_host'],
				$payload['smtp_port'],
				$payload['imap_host'],
				$payload['imap_port'],
				$payload['sent_folder'],
				$userId,
			]);
			return;
		}
		$stmt = self::pdo()->prepare(
			'INSERT INTO mailboxes (user_id, email, password_enc, smtp_host, smtp_port, imap_host, imap_port, sent_folder, last_sync)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
		);
		$stmt->execute([
			$userId,
			$payload['email'],
			$payload['password_enc'],
			$payload['smtp_host'],
			$payload['smtp_port'],
			$payload['imap_host'],
			$payload['imap_port'],
			$payload['sent_folder'],
			null,
		]);
	}

	private static function syncFolder(int $userId, Imap $imap, string $remote, string $folder, bool $notify = false): void
	{
		$uids = $imap->uids($remote);
		$known = MailMessage::uidsFor($userId, $folder);
		$missing = [];
		foreach ($uids as $uid) {
			if (!isset($known[$uid])) {
				$missing[] = $uid;
			}
		}
		foreach ($missing as $uid) {
			$parsed = $imap->fetch($remote, $uid);
			$peer = $folder === 'inbox' ? $parsed['from_email'] : $parsed['to_email'];
			$clientId = MailMessage::clientIdFor($userId, $peer);
			MailMessage::store($userId, $folder, [
				'uid' => $uid,
				'message_id' => $parsed['message_id'],
				'in_reply_to' => $parsed['in_reply_to'],
				'from_email' => $parsed['from_email'],
				'from_name' => $parsed['from_name'],
				'to_email' => $parsed['to_email'],
				'subject' => $parsed['subject'],
				'body_text' => $parsed['body_text'],
				'body_html' => $parsed['body_html'],
				'sent_at' => $parsed['sent_at'],
				'seen' => $parsed['seen'] ? 1 : 0,
				'important' => !empty($parsed['flagged']) ? 1 : 0,
				'client_id' => $clientId,
			]);
			if ($notify && $folder === 'inbox' && $clientId) {
				Activity::log(
					'mail_received',
					'El cliente respondió: ' . ($parsed['subject'] ?: '(sin asunto)'),
					$userId,
					$clientId
				);
				Client::update($clientId, ['updated_at' => date('c')]);
			}
		}
		$flags = $imap->flags($remote, $uids);
		foreach ($flags as $uid => $state) {
			$seen = !empty($state['seen']) ? 1 : 0;
			$important = !empty($state['flagged']) ? 1 : 0;
			self::pdo()->prepare('UPDATE mail_messages SET seen = ?, important = ? WHERE user_id = ? AND folder = ? AND uid = ?')
				->execute([$seen, $important, $userId, $folder, $uid]);
		}
	}
}
