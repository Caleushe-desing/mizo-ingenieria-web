<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Activity;
use MizoCrm\Models\ChatMessage;
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
				'reload' => true,
			];
		}

		foreach (Quote::recentChanges($userId, Auth::ownerScope()) as $quote) {
			$stamp = preg_replace('/\D+/', '', (string) $quote['updated_at']) ?: (string) $quote['id'];
			$alerts[] = [
				'id' => 'quote-edit-' . $quote['id'] . '-' . $stamp,
				'kind' => 'quote_edit',
				'title' => 'Cotización actualizada',
				'text' => trim(($quote['editor_name'] ?? 'Alguien') . ' · ' . $quote['client_name'] . ' · ' . $quote['number']),
				'href' => Http::url('/cotizaciones/' . $quote['id']),
				'quote_id' => (int) $quote['id'],
				'status' => $quote['status'],
				'label' => quote_status_label((string) $quote['status']),
				'reload' => true,
			];
		}

		foreach (Activity::recentNotices($userId, Auth::ownerScope()) as $note) {
			$title = match ((string) $note['type']) {
				'comentario' => 'Nuevo comentario',
				'quote_sent' => 'Cotización enviada',
				'assigned' => 'Cliente asignado',
				default => 'Actualización',
			};
			$snippet = trim((string) $note['message']);
			if (function_exists('mb_strlen') && mb_strlen($snippet, 'UTF-8') > 70) {
				$snippet = mb_substr($snippet, 0, 70, 'UTF-8') . '…';
			} elseif (strlen($snippet) > 70) {
				$snippet = substr($snippet, 0, 70) . '…';
			}
			$href = !empty($note['quote_id'])
				? Http::url('/cotizaciones/' . $note['quote_id'])
				: Http::url('/clientes/' . $note['client_id']);
			$alerts[] = [
				'id' => 'act-' . $note['id'],
				'kind' => 'change',
				'title' => $title,
				'text' => trim(($note['user_name'] ?? '') . ' · ' . ($note['client_name'] ?? '') . ($snippet !== '' ? ' · ' . $snippet : '')),
				'href' => $href,
				'quote_id' => !empty($note['quote_id']) ? (int) $note['quote_id'] : null,
				'reload' => true,
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
				'reload' => true,
			];
		}

		foreach (ChatMessage::unreadPeek($userId) as $chat) {
			$body = trim((string) $chat['body']);
			if (function_exists('mb_strlen') && mb_strlen($body, 'UTF-8') > 60) {
				$body = mb_substr($body, 0, 60, 'UTF-8') . '…';
			} elseif (strlen($body) > 60) {
				$body = substr($body, 0, 60) . '…';
			}
			$alerts[] = [
				'id' => 'chat-' . $chat['id'],
				'kind' => 'chat',
				'title' => 'Chat interno',
				'text' => ($chat['from_name'] ?? 'Equipo') . ($body !== '' ? ' · ' . $body : ''),
				'href' => Http::url('/chat/' . $chat['from_user_id']),
				'reload' => false,
			];
		}

		Http::json([
			'ok' => true,
			'unread' => MailMessage::unreadCount($userId),
			'chat_unread' => ChatMessage::unreadCount($userId),
			'alerts' => $alerts,
		]);
	}
}
