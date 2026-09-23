<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Accounting;
use MizoCrm\View;
use RuntimeException;

final class AccountingController
{
	public function index(): void
	{
		Auth::requireAdmin();
		$year = self::year();
		View::render('accounting/index', [
			'title' => 'Contabilidad',
			'report' => Accounting::report($year),
		]);
	}

	public function saveRate(): void
	{
		Csrf::check();
		Auth::requireAdmin();
		$year = self::year();
		try {
			Accounting::saveTaxRate(self::percent($_POST['income_tax_rate'] ?? '27'));
			View::flash('ok', 'Quedó guardado el porcentaje de Impuesto de Primera Categoría.');
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/contabilidad?anio=' . $year);
	}

	public function saveCommissions(): void
	{
		Csrf::check();
		Auth::requireAdmin();
		$year = self::year();
		$raw = $_POST['rate'] ?? [];
		$rates = [];
		try {
			if (is_array($raw)) {
				foreach ($raw as $id => $value) {
					$text = trim((string) $value);
					$rates[(int) $id] = $text === '' ? 0.0 : self::percent($text);
				}
			}
			Accounting::saveCommissions($rates);
			View::flash('ok', 'Quedaron guardados los porcentajes de comisión.');
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/contabilidad?anio=' . $year);
	}

	public function storeObligation(): void
	{
		Csrf::check();
		$user = Auth::requireAdmin();
		$year = self::year();
		try {
			Accounting::addObligation(self::obligation((int) $user['id']));
			View::flash('ok', 'Quedó registrado el movimiento.');
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/contabilidad?anio=' . $year);
	}

	public function updateObligation(string $id): void
	{
		Csrf::check();
		Auth::requireAdmin();
		$year = self::year();
		try {
			Accounting::updateObligation((int) $id, self::obligation(null));
			View::flash('ok', 'Quedó actualizado el movimiento.');
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/contabilidad?anio=' . $year);
	}

	public function deleteObligation(string $id): void
	{
		Csrf::check();
		Auth::requireAdmin();
		$year = self::year();
		$row = Accounting::find((int) $id);
		if (!$row) {
			View::flash('error', 'Ese registro no existe.');
		} else {
			Accounting::delete((int) $id);
			View::flash('ok', 'Se eliminó «' . $row['concept'] . '».');
		}
		Http::redirect('/contabilidad?anio=' . $year);
	}

	private static function year(): int
	{
		$year = (int) ($_GET['anio'] ?? $_POST['anio'] ?? date('Y'));
		if ($year < 2000 || $year > 2100) {
			return (int) date('Y');
		}
		return $year;
	}

	private static function percent(mixed $value): float
	{
		$text = str_replace(',', '.', trim((string) $value));
		if ($text === '' || !is_numeric($text)) {
			throw new RuntimeException('El porcentaje tiene que ser un número entre 0 y 100.');
		}
		$rate = (float) $text;
		if ($rate < 0 || $rate > 100) {
			throw new RuntimeException('El porcentaje tiene que estar entre 0 y 100.');
		}
		return round($rate, 2);
	}

	/** @return array<string,mixed> */
	private static function obligation(?int $userId): array
	{
		$kind = (string) ($_POST['kind'] ?? '');
		if (!in_array($kind, ['gasto', 'deuda', 'compromiso'], true)) {
			throw new RuntimeException('Elige si es gasto, deuda o compromiso.');
		}
		$concept = Http::string('concept', 160);
		if ($concept === '') {
			throw new RuntimeException('Indica el concepto.');
		}
		$issued = self::day('issued_on');
		if ($issued === '') {
			throw new RuntimeException('Indica la fecha.');
		}
		$due = self::day('due_on');
		$status = (string) ($_POST['status'] ?? '') === 'paid' ? 'paid' : 'pending';
		$net = max(0, Http::money('net'));
		$tax = max(0, Http::money('tax'));
		$total = max(0, Http::money('total'));
		if ($total < $net + $tax) {
			$total = $net + $tax;
		}
		$data = [
			'kind' => $kind,
			'concept' => $concept,
			'notes' => Http::string('notes', 500),
			'net' => $net,
			'tax' => $tax,
			'total' => $total,
			'status' => $status,
			'issued_on' => $issued,
			'due_on' => $due !== '' ? $due : null,
		];
		if ($userId !== null) {
			$data['created_by'] = $userId;
		}
		return $data;
	}

	private static function day(string $key): string
	{
		$value = trim((string) ($_POST[$key] ?? ''));
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}
}
