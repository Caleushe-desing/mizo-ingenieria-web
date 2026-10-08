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
		]);
	}
}
