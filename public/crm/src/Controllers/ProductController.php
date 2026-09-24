<?php
declare(strict_types=1);

namespace MizoCrm\Controllers;

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Http;
use MizoCrm\Models\Product;
use MizoCrm\ProductImporter;
use MizoCrm\View;
use RuntimeException;

final class ProductController
{
	public function index(): void
	{
		Auth::requireAdmin();
		$query = Http::string('q', 80);
		View::render('products/index', [
			'title' => 'Catálogo',
			'products' => Product::catalog($query),
			'q' => $query,
		]);
	}

	public function import(): void
	{
		Auth::requireAdmin();
		Csrf::check();
		try {
			$_SESSION['_product_draft'] = ProductImporter::fromUrl(Http::string('url', 500));
		} catch (RuntimeException $e) {
			View::flash('error', $e->getMessage());
			Http::redirect('/catalogo');
		}
		View::flash('ok', 'Datos leídos. Asigna el SKU, revisa la ficha y guarda.');
		Http::redirect('/catalogo/nuevo');
	}

	public function create(): void
	{
		Auth::requireAdmin();
		$draft = $_SESSION['_product_draft'] ?? null;
		unset($_SESSION['_product_draft']);
		$product = $this->blank();
		$imported = false;
		if (is_array($draft)) {
			$imported = true;
			foreach (['nombre', 'descripcion', 'proveedor_empresa', 'proveedor_link'] as $key) {
				if (isset($draft[$key]) && is_string($draft[$key])) {
					$product[$key] = $draft[$key];
				}
			}
		}
		View::render('products/form', [
			'title' => 'Nuevo producto',
			'product' => $product,
			'imported' => $imported,
		]);
	}

	public function store(): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$data = $this->input();
		$error = $this->validate($data, null);
		if ($error !== null) {
			View::render('products/form', [
				'title' => 'Nuevo producto',
				'product' => $data,
				'error' => $error,
			]);
			return;
		}
		$now = date('c');
		Product::insert($data + ['created_at' => $now, 'updated_at' => $now]);
		View::flash('ok', 'Producto ' . $data['sku'] . ' agregado al catálogo.');
		Http::redirect('/catalogo');
	}

	public function edit(string $id): void
	{
		Auth::requireAdmin();
		$product = Product::find((int) $id);
		if (!$product) {
			Http::redirect('/catalogo');
		}
		View::render('products/form', [
			'title' => 'Editar producto',
			'product' => $product,
		]);
	}

	public function update(string $id): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$product = Product::find((int) $id);
		if (!$product) {
			Http::redirect('/catalogo');
		}
		$data = $this->input();
		$error = $this->validate($data, (int) $product['id']);
		if ($error !== null) {
			View::render('products/form', [
				'title' => 'Editar producto',
				'product' => $data + ['id' => (int) $product['id']],
				'error' => $error,
			]);
			return;
		}
		Product::update((int) $product['id'], $data + ['updated_at' => date('c')]);
		View::flash('ok', 'Producto ' . $data['sku'] . ' actualizado.');
		Http::redirect('/catalogo');
	}

	public function visibility(string $id): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$product = Product::find((int) $id);
		if (!$product) {
			Http::redirect('/catalogo');
		}
		$visible = (int) $product['activo'] === 1 ? 0 : 1;
		Product::update((int) $product['id'], [
			'activo' => $visible,
			'updated_at' => date('c'),
		]);
		View::flash('ok', $visible === 1 ? 'El producto queda visible.' : 'El producto queda oculto.');
		Http::redirect('/catalogo');
	}

	public function destroy(string $id): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$product = Product::find((int) $id);
		if ($product) {
			Product::delete((int) $product['id']);
			View::flash('ok', 'Producto ' . $product['sku'] . ' eliminado del catálogo.');
		}
		Http::redirect('/catalogo');
	}

	private function blank(): array
	{
		return [
			'sku' => '',
			'nombre' => '',
			'descripcion' => '',
			'categoria' => '',
			'proveedor_empresa' => '',
			'proveedor_link' => '',
			'activo' => 1,
		];
	}

	private function input(): array
	{
		return [
			'sku' => Http::string('sku', 80),
			'nombre' => Http::string('nombre', 180),
			'descripcion' => Http::text('descripcion', 8000),
			'categoria' => Http::string('categoria', 80),
			'proveedor_empresa' => Http::string('proveedor_empresa', 160),
			'proveedor_link' => Http::string('proveedor_link', 500),
			'activo' => isset($_POST['activo']) ? 1 : 0,
		];
	}

	private function validate(array $data, ?int $exceptId): ?string
	{
		if ($data['sku'] === '' || $data['nombre'] === '' || $data['descripcion'] === '' || $data['categoria'] === '' || $data['proveedor_empresa'] === '' || $data['proveedor_link'] === '') {
			return 'Completa SKU, nombre, descripción, categoría, proveedor y el enlace.';
		}
		if (Product::findBySku($data['sku'], $exceptId)) {
			return 'Ese SKU ya está en el catálogo.';
		}
		if (!$this->validLink($data['proveedor_link'])) {
			return 'El enlace del proveedor debe ser una URL http o https.';
		}
		return null;
	}

	private function validLink(string $url): bool
	{
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return false;
		}
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		return $scheme === 'http' || $scheme === 'https';
	}
}
