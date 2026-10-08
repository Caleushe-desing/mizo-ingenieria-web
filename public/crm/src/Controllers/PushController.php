<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\WebPush;

final class PushController
{
	public function subscribe(): void
	{
		$user = Auth::user();
		if (!$user) {
			Http::json(['ok' => false], 401);
		}
		$raw = file_get_contents('php://input');
		$data = is_string($raw) ? json_decode($raw, true) : null;
		if (!is_array($data)) {
			Http::json(['ok' => false], 400);
		}
		$sent = (string) ($data['_csrf'] ?? '');
		if ($sent === '' || !hash_equals(Csrf::token(), $sent)) {
			Http::json(['ok' => false, 'error' => 'Sesión expirada'], 419);
		}
		$subscription = $data['subscription'] ?? null;
		if (!is_array($subscription)) {
			Http::json(['ok' => false], 400);
		}
		WebPush::save((int) $user['id'], $subscription);
		Http::json(['ok' => true]);
	}
}
