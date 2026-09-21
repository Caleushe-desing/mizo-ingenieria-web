<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Http;
use MizoCrm\Models\Mailbox;
use MizoCrm\Models\MailMessage;
use MizoCrm\Models\Quote;
use RuntimeException;

final class AlertController
{
	public function ping(): void
	{
		$user = Auth::user();
		if (!$user) {
			Http::json(['ok' => false], 401);
		}

		$userId = (int) $user['id'];
		if (Mailbox::forUser($userId)) {
			try {
				Mailbox::sync($userId, false, true);
			} catch (RuntimeException) {
			}
		}

		$alerts = [];
		foreach (Quote::recentResponses(Auth::ownerScope()) as $quote) {
			$accepted = $quote['status'] === 'aceptada';
			$alerts[] = [
				'id' => 'quote-' . $quote['id'],
				'kind' => $accepted ? 'quote_ok' : 'quote_no',
				'title' => $accepted ? 'Cotización aceptada' : 'Cotización no aceptada',
				'text' => $quote['client_name'] . ' · ' . $quote['number'],
				'href' => Http::url('/clientes/' . $quote['client_id']),
				'quote_id' => (int) $quote['id'],
				'status' => $quote['status'],
				'label' => quote_status_label((string) $quote['status']),
			];
		}
		foreach (MailMessage::unreadPeek($userId) as $mail) {
			$who = trim((string) ($mail['from_name'] ?: $mail['from_email']));
			$alerts[] = [
				'id' => 'mail-' . $mail['id'],
				'kind' => 'mail',
				'title' => 'Correo nuevo',
				'text' => $who . ($mail['subject'] !== '' ? ' · ' . $mail['subject'] : ''),
				'href' => Http::url('/correo/' . $mail['id']),
			];
		}

		Http::json([
			'ok' => true,
			'unread' => MailMessage::unreadCount($userId),
			'alerts' => $alerts,
		]);
	}
}
