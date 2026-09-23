<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Config;
use MizoCrm\Models\AdminReport;
use MizoCrm\Models\User;
use MizoCrm\View;

final class AdminController
{
	public function index(): void
	{
		Auth::requireAdmin();
		$owner = max(0, (int) ($_GET['ejecutivo'] ?? 0));
		$from = self::day('desde');
		$to = self::day('hasta');
		$stage = (string) ($_GET['estado'] ?? '');
		if (!isset(Config::stages()[$stage])) {
			$stage = '';
		}
		View::render('admin/index', [
			'title' => 'Auditoría',
			'feed' => AdminReport::feed($owner, $from, $to, $stage),
			'team' => User::team(),
			'stages' => Config::stages(),
			'filters' => [
				'ejecutivo' => $owner,
				'desde' => $from,
				'hasta' => $to,
				'estado' => $stage,
			],
		]);
	}

	public function stats(): void
	{
		Auth::requireAdmin();
		$pipeline = [];
		foreach (AdminReport::pipeline() as $row) {
			$pipeline[] = [
				'label' => $row['label'],
				'kind' => $row['kind'],
				'count' => $row['count'],
			];
		}
		View::render('admin/stats', [
			'title' => 'Estadísticas',
			'pipeline' => $pipeline,
			'desk' => AdminReport::desk(),
			'web' => AdminReport::web(),
			'trend' => AdminReport::trend(12),
		]);
	}

	private static function day(string $key): string
	{
		$value = (string) ($_GET[$key] ?? '');
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}
}
