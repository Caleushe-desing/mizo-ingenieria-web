<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Models\Activity;
use MizoCrm\Models\Client;
use MizoCrm\Models\Deal;
use MizoCrm\Models\Quote;
use MizoCrm\View;

final class DashboardController
{
	public function index(): void
	{
		View::render('dashboard', [
			'title' => 'Tablero',
			'clients' => Client::count(),
			'dealsOpen' => count(array_filter(Deal::all(), static fn($d) => !in_array($d['stage'], ['ganado', 'perdido'], true))),
			'quotesSent' => Quote::sentCount(),
			'pipelineValue' => Deal::openValue(),
			'pipeline' => Deal::pipeline(),
			'activity' => Activity::recent(10),
			'quotes' => array_slice(Quote::withRelations(), 0, 6),
		]);
	}
}
