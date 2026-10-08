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
		$labels = Product::landings();
		$onLanding = [];
		foreach (Product::landingAssignments() as $slug => $ids) {
			foreach ($ids as $id) {
				$onLanding[$id][] = $labels[$slug] ?? $slug;
			}
		}
		View::render('products/index', [
			'title' => 'Catálogo',
			'products' => Product::catalog($query),
			'q' => $query,
			'landings' => $labels,
			'onLanding' => $onLanding,
		]);
	}

	public function import(): void
	{
		Auth::requireAdmin();
		Csrf::check();
		try {
			$_SESSION['_product_draft'] = ProductImporter::fromUrl(Http::string('url', 800));
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
			if (isset($draft['imagenes']) && is_array($draft['imagenes'])) {
				$product['imagenes'] = $draft['imagenes'];
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
		$images = $this->postedImages();
		$error = $this->validate($data, null);
		if ($error !== null) {
			View::render('products/form', [
				'title' => 'Nuevo producto',
				'product' => $data + ['imagenes' => $images],
				'error' => $error,
				'landings' => Product::landings(),
				'landingSlugs' => $this->postedLandings(),
			]);
			return;
		}
		$now = date('c');
		$extra = ['created_at' => $now, 'updated_at' => $now];
		if ($images !== []) {
			$extra['imagenes'] = json_encode($images, JSON_UNESCAPED_SLASHES);
		}
		$id = Product::insert($data + $extra);
		Product::setLandings($id, $this->postedLandings());
		View::flash('ok', 'Producto ' . $data['sku'] . ' agregado al catálogo.');
		Http::redirect('/catalogo');
	}

	public function features(): void
	{
		Auth::requireAdmin();
		View::render('products/features', [
			'title' => 'Productos destacados',
			'landings' => Product::landings(),
			'products' => array_values(array_filter(
				Product::catalog(),
				static fn(array $product): bool => empty($product['servicio_profesional'])
			)),
			'assigned' => Product::landingAssignments(),
		]);
	}

	public function addFeatures(): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$slug = Http::string('landing', 80);
		$ids = $_POST['ids'] ?? [];
		if (!is_array($ids)) {
			$ids = [];
		}
		if (!isset(Product::landings()[$slug])) {
			View::flash('error', 'Elige la landing donde quieres publicar los productos.');
			Http::redirect('/catalogo');
		}
		$added = Product::addToLanding($slug, $ids);
		$label = Product::landings()[$slug];
		View::flash('ok', $added > 0
			? $added . ' producto' . ($added === 1 ? '' : 's') . ' agregado' . ($added === 1 ? '' : 's') . ' a ' . $label . '.'
			: 'Esos productos ya estaban en ' . $label . '.');
		$back = Http::string('volver', 80);
		Http::redirect($back === 'destacados' ? '/catalogo/destacados' : '/catalogo');
	}

	public function removeFeature(): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$slug = Http::string('landing', 80);
		Product::removeFromLanding($slug, Http::int('product_id'));
		View::flash('ok', 'Producto quitado de la landing.');
		Http::redirect('/catalogo/destacados');
	}

	public function saveFeatures(): void
	{
		Auth::requireAdmin();
		Csrf::check();
		$posted = $_POST['landings'] ?? [];
		$byLanding = [];
		foreach (Product::landings() as $slug => $_label) {
			$ids = is_array($posted[$slug] ?? null) ? $posted[$slug] : [];
			$byLanding[$slug] = array_map(static fn($id): int => (int) $id, $ids);
		}
		Product::saveLandingAssignments($byLanding);
		View::flash('ok', 'Los productos destacados de las landings quedaron actualizados.');
		Http::redirect('/catalogo/destacados');
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
			'quoteUsage' => Product::quoteAppearances((int) $product['id']),
			'landings' => Product::landings(),
			'landingSlugs' => Product::landingSlugsFor((int) $product['id']),
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
				'quoteUsage' => Product::quoteAppearances((int) $product['id']),
				'landings' => Product::landings(),
				'landingSlugs' => $this->postedLandings(),
			]);
			return;
		}
		Product::update((int) $product['id'], $data + ['updated_at' => date('c')]);
		Product::setLandings((int) $product['id'], $this->postedLandings());
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
		if (Product::isProfessionalService($product)) {
			Product::update((int) $product['id'], [
				'activo' => 0,
				'updated_at' => date('c'),
			]);
			View::flash('error', 'Servicio profesional es interno del CRM y no se publica en la web.');
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
		if ($product && Product::isProfessionalService($product)) {
			View::flash('error', 'Servicio profesional no se puede eliminar: se usa en el cotizador.');
			Http::redirect('/catalogo');
		}
		if ($product) {
			Product::setLandings((int) $product['id'], []);
			Product::delete((int) $product['id']);
			View::flash('ok', 'Producto ' . $product['sku'] . ' eliminado del catálogo.');
		}
		Http::redirect('/catalogo');
	}

	/** JSON para el cotizador: productos del catálogo con precio de compra c/IVA. */
	public function quoteSearch(): void
	{
		Auth::requireUser();
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode(
			['products' => Product::forQuoting(Http::string('q', 80))],
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/** Importa ficha desde URL (misma regla que el catálogo). */
	public function quoteImport(): void
	{
		$this->jsonStart();
		if (!$this->jsonGuard()) {
			return;
		}
		try {
			$draft = ProductImporter::fromUrl(Http::string('url', 800));
			$payload = json_encode(
				['ok' => true, 'draft' => $draft],
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
			);
			if ($payload === false) {
				throw new RuntimeException('Los datos importados no se pudieron procesar. Prueba otra URL.');
			}
			echo $payload;
		} catch (\Throwable $e) {
			http_response_code(422);
			echo json_encode(
				['ok' => false, 'error' => $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo importar esa URL.'],
				JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
			);
		}
	}

	/** Alta rápida desde el cotizador (ejecutivo autenticado). */
	public function quoteCreate(): void
	{
		$this->jsonStart();
		if (!$this->jsonGuard()) {
			return;
		}
		$data = $this->input();
		$data['activo'] = 1;
		$images = $this->postedImages();
		$error = $this->validate($data, null);
		if ($error !== null) {
			http_response_code(422);
			echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
			return;
		}
		try {
			$now = date('c');
			$extra = ['created_at' => $now, 'updated_at' => $now];
			if ($images !== []) {
				$extra['imagenes'] = json_encode($images, JSON_UNESCAPED_SLASHES);
			}
			$id = Product::insert($data + $extra);
			$product = [
				'id' => $id,
				'sku' => $data['sku'],
				'nombre' => $data['nombre'],
				'descripcion' => $data['descripcion'],
				'categoria' => $data['categoria'],
				'precio_compra_iva' => (int) $data['precio_compra_iva'],
				'proveedor_link' => $data['proveedor_link'],
				'proveedor_empresa' => $data['proveedor_empresa'],
			];
			echo json_encode(['ok' => true, 'product' => $product], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		} catch (\Throwable $e) {
			http_response_code(500);
			echo json_encode(
				['ok' => false, 'error' => 'No se pudo guardar el producto. Inténtalo de nuevo.'],
				JSON_UNESCAPED_UNICODE
			);
		}
	}

	private function jsonStart(): void
	{
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
	}

	private function jsonGuard(): bool
	{
		if (!Auth::user()) {
			http_response_code(401);
			echo json_encode(['ok' => false, 'error' => 'Sesión expirada. Vuelve a iniciar sesión.'], JSON_UNESCAPED_UNICODE);
			return false;
		}
		$sent = (string) ($_POST['_csrf'] ?? '');
		if ($sent === '' || !hash_equals(Csrf::token(), $sent)) {
			http_response_code(419);
			echo json_encode(['ok' => false, 'error' => 'Sesión expirada. Recarga la cotización e inténtalo de nuevo.'], JSON_UNESCAPED_UNICODE);
			return false;
		}
		return true;
	}

	/** @return list<string> */
	private function postedLandings(): array
	{
		$posted = $_POST['landings'] ?? [];
		if (!is_array($posted)) {
			return [];
		}
		$slugs = [];
		foreach ($posted as $slug) {
			if (is_string($slug)) {
				$slugs[] = $slug;
			}
		}
		return $slugs;
	}

	private function postedImages(): array
	{
		$decoded = json_decode((string) ($_POST['imagenes'] ?? ''), true);
		if (!is_array($decoded)) {
			return [];
		}
		$root = dirname(__DIR__, 2);
		$saved = [];
		foreach ($decoded as $path) {
			if (!is_string($path) || !preg_match('#^/crm/uploads/productos/[a-z0-9-]+/\d+\.(jpg|png|webp|gif)$#', $path)) {
				continue;
			}
			$file = $root . substr($path, 4);
			if (is_file($file)) {
				$saved[] = $path;
			}
		}
		return $saved;
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
			'precio_compra_iva' => 0,
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
			'precio_compra_iva' => max(0, Http::money('precio_compra_iva')),
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
