<?php
declare(strict_types=1);

namespace MizoCrm;

final class Auth
{
	public static function user(): ?array
	{
		$id = (int) ($_SESSION['uid'] ?? 0);
		if ($id < 1) {
			return null;
		}
		static $cached = null;
		if (is_array($cached) && (int) $cached['id'] === $id) {
			return $cached;
		}
		$cached = Models\User::find($id);
		if (!$cached || !(int) $cached['active']) {
			self::logout();
			return null;
		}
		return $cached;
	}

	public static function id(): int
	{
		$user = self::user();
		return $user ? (int) $user['id'] : 0;
	}

	public static function isAdmin(): bool
	{
		$user = self::user();
		return $user !== null && $user['role'] === 'admin';
	}

	public static function ownerScope(): ?int
	{
		return self::isAdmin() ? null : self::id();
	}

	public static function canAccessClient(?array $client): bool
	{
		if (!$client) {
			return false;
		}
		if (self::isAdmin()) {
			return true;
		}
		return (int) ($client['owner_id'] ?? 0) === self::id();
	}

	public static function requireClient(?array $client): array
	{
		if (!self::canAccessClient($client)) {
			View::flash('error', 'Ese cliente no está a tu cargo.');
			Http::redirect('/');
		}
		return $client;
	}

	public static function canAccessDeal(?array $deal): bool
	{
		if (!$deal) {
			return false;
		}
		if (self::isAdmin()) {
			return true;
		}
		return (int) ($deal['owner_id'] ?? 0) === self::id();
	}

	public static function requireDeal(?array $deal): array
	{
		if (!self::canAccessDeal($deal)) {
			View::flash('error', 'Ese cliente no está a tu cargo.');
			Http::redirect('/');
		}
		return $deal;
	}

	public static function requireUser(): array
	{
		$user = self::user();
		if (!$user) {
			Http::redirect('/login');
		}
		return $user;
	}

	public static function requireAdmin(): array
	{
		$user = self::requireUser();
		if ($user['role'] !== 'admin') {
			Http::redirect('/');
		}
		return $user;
	}

	public static function login(array $user): void
	{
		session_regenerate_id(true);
		$_SESSION['uid'] = (int) $user['id'];
	}

	public static function logout(): void
	{
		$_SESSION = [];
		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
		}
		session_destroy();
	}

	public static function needsSetup(): bool
	{
		return Models\User::count() === 0;
	}
}
