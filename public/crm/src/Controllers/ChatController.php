<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\ChatMessage;
use MizoCrm\Models\User;
use MizoCrm\View;

final class ChatController
{
	public function index(): void
	{
		$user = Auth::requireUser();
		$peers = ChatMessage::peersFor((int) $user['id'], Auth::isAdmin());
		$peer = null;
		if (!Auth::isAdmin() && $peers) {
			Http::redirect('/chat/' . (int) $peers[0]['id']);
		}
		View::render('chat/index', [
			'title' => 'Chat interno',
			'peers' => $peers,
			'peer' => $peer,
			'messages' => [],
			'chat_unread' => ChatMessage::unreadCount((int) $user['id']),
		]);
	}

	public function show(string $id): void
	{
		$user = Auth::requireUser();
		$peer = User::find((int) $id);
		if (!$peer || !ChatMessage::canTalk($user, $peer)) {
			View::flash('error', 'No puedes chatear con esa persona.');
			Http::redirect('/chat');
		}
		ChatMessage::markSeen((int) $user['id'], (int) $peer['id']);
		View::render('chat/index', [
			'title' => 'Chat · ' . $peer['name'],
			'peers' => ChatMessage::peersFor((int) $user['id'], Auth::isAdmin()),
			'peer' => $peer,
			'messages' => ChatMessage::thread((int) $user['id'], (int) $peer['id']),
			'chat_unread' => ChatMessage::unreadCount((int) $user['id']),
		]);
	}

	public function send(string $id): void
	{
		Csrf::check();
		$user = Auth::requireUser();
		$peer = User::find((int) $id);
		if (!$peer || !ChatMessage::canTalk($user, $peer)) {
			View::flash('error', 'No puedes chatear con esa persona.');
			Http::redirect('/chat');
		}
		$body = trim(Http::text('body', 4000));
		if ($body === '') {
			View::flash('error', 'Escribe un mensaje.');
			Http::redirect('/chat/' . $id);
		}
		ChatMessage::send((int) $user['id'], (int) $peer['id'], $body);
		Http::redirect('/chat/' . $id);
	}

	public function poll(string $id): void
	{
		$user = Auth::user();
		if (!$user) {
			Http::json(['ok' => false], 401);
		}
		$peer = User::find((int) $id);
		if (!$peer || !ChatMessage::canTalk($user, $peer)) {
			Http::json(['ok' => false], 403);
		}
		$after = Http::int('despues');
		$messages = ChatMessage::thread((int) $user['id'], (int) $peer['id'], $after);
		if ($messages) {
			ChatMessage::markSeen((int) $user['id'], (int) $peer['id']);
		}
		$out = [];
		foreach ($messages as $row) {
			$out[] = [
				'id' => (int) $row['id'],
				'from_me' => (int) $row['from_user_id'] === (int) $user['id'],
				'from_name' => (string) $row['from_name'],
				'body' => (string) $row['body'],
				'created_at' => when($row['created_at'] ?? null),
			];
		}
		Http::json([
			'ok' => true,
			'messages' => $out,
			'chat_unread' => ChatMessage::unreadCount((int) $user['id']),
		]);
	}
}
