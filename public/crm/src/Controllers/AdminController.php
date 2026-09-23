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
		$pipeline = AdminReport::pipeline();
		$openCount = 0;
		$openAmount = 0;
		$won = 0;
		$lost = 0;
		foreach ($pipeline as $row) {
			if (($row['kind'] ?? '') === 'won') {
				$won += $row['count'];
				continue;
			}
			if (($row['kind'] ?? '') === 'lost') {
				$lost += $row['count'];
				continue;
			}
			$openCount += $row['count'];
			$openAmount += $row['amount'];
		}
		View::render('admin/stats', [
			'title' => 'Estadísticas',
			'pipeline' => $pipeline,
			'executives' => AdminReport::executives(),
			'web' => AdminReport::web(),
			'trend' => AdminReport::trend(12),
			'openCount' => $openCount,
			'openAmount' => $openAmount,
			'won' => $won,
			'lost' => $lost,
		]);
	}

	private static function day(string $key): string
	{
		$value = (string) ($_GET[$key] ?? '');
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}
}
