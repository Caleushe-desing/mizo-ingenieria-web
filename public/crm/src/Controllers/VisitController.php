<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Models\VisitReport;
use MizoCrm\View;

final class VisitController
{
	public function index(): void
	{
		Auth::requireAdmin();
		$days = (int) ($_GET['dias'] ?? 7);
		if (!in_array($days, [1, 7, 30, 90], true)) {
			$days = 7;
		}
		View::render('visits/index', [
			'title' => 'Visitas del sitio',
			'days' => $days,
			'report' => VisitReport::summary($days),
			'live' => \MizoCrm\Models\SiteLive::snapshot(),
		]);
	}

	public function live(): void
	{
		Auth::requireAdmin();
		$chatId = max(0, (int) ($_GET['chat'] ?? 0));
		\MizoCrm\Http::json(\MizoCrm\Models\SiteLive::snapshot($chatId));
	}

	public function reply(): void
	{
		\MizoCrm\Csrf::check();
		Auth::requireAdmin();
		$chatId = (int) ($_POST['chat_id'] ?? 0);
		$body = trim(\MizoCrm\Http::text('body', 2000));
		$ok = \MizoCrm\Models\SiteLive::reply($chatId, $body);
		\MizoCrm\Http::json(['ok' => $ok, 'error' => $ok ? '' : 'No se pudo enviar.']);
	}
}
