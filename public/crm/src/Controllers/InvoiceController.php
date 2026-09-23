<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Invoice;
use MizoCrm\View;
use RuntimeException;

final class InvoiceController
{
	public function index(): void
	{
		Auth::requireAdmin();
		View::render('invoices/index', [
			'title' => 'Ingreso de facturas',
			'book' => Invoice::board(),
		]);
	}

	public function storeSale(): void
	{
		Csrf::check();
		$user = Auth::requireAdmin();
		try {
			$data = self::moneyRow(['net', 'tax', 'total']);
			$number = Http::string('number', 40);
			$issued = self::day('issued_on');
			if ($number === '' || $issued === '') {
				throw new RuntimeException('Indica el número y la fecha de la factura.');
			}
			if ($data['total'] < $data['net'] + $data['tax']) {
				$data['total'] = $data['net'] + $data['tax'];
			}
			Invoice::addSale([
				'number' => $number,
				'client_id' => Http::int('client_id'),
				'deal_id' => Http::int('deal_id'),
				'quote_id' => Http::int('quote_id'),
				'net' => $data['net'],
				'tax' => $data['tax'],
				'total' => $data['total'],
				'issued_on' => $issued,
				'created_by' => (int) $user['id'],
			]);
			View::flash('ok', 'Factura de venta registrada. Quedó como tarjeta en el tablero.');
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/facturas');
	}

	public function storePurchase(): void
	{
		Csrf::check();
		$user = Auth::requireAdmin();
		try {
			$supplier = Http::string('supplier', 120);
			$number = Http::string('number', 40);
			$issued = self::day('issued_on');
			if ($supplier === '' || $number === '' || $issued === '') {
				throw new RuntimeException('Indica proveedor, número y fecha.');
			}
			$data = self::moneyRow(['net', 'tax', 'travel', 'operations', 'other_costs']);
			Invoice::addPurchase([
				'supplier' => $supplier,
				'number' => $number,
				'deal_id' => Http::int('deal_id'),
				'net' => $data['net'],
				'tax' => $data['tax'],
				'travel' => $data['travel'],
				'operations' => $data['operations'],
				'other_costs' => $data['other_costs'],
				'issued_on' => $issued,
				'created_by' => (int) $user['id'],
			]);
			View::flash('ok', 'Factura de compra registrada para el margen.');
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/facturas');
	}

	public function pay(string $id): void
	{
		Csrf::check();
		$user = Auth::requireAdmin();
		try {
			Invoice::markPaid((int) $id, (int) $user['id']);
			View::flash('ok', 'Factura marcada como pagada.');
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
		}
		Http::redirect('/facturas');
	}

	/** @param list<string> $keys @return array<string,int> */
	private static function moneyRow(array $keys): array
	{
		$out = [];
		foreach ($keys as $key) {
			$out[$key] = max(0, Http::money($key));
		}
		return $out;
	}

	private static function day(string $key): string
	{
		$value = Http::string($key, 10);
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}
}
