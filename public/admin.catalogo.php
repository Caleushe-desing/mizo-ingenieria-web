<!doctype html>
<html lang="es">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta name="robots" content="noindex, nofollow" />
		<link rel="icon" type="image/png" href="/favicon.png" />
		<title>Panel privado | Mizo</title>
		<script>
			window.tailwind = window.tailwind || {};
			window.tailwind.config = {
				theme: {
					extend: {
						colors: {
							'accent-main': '#1877f2',
							'accent-hover': '#166fe5',
							'accent-dark': '#0d5bd7',
							'accent-light': '#4d9cf7',
							'accent-soft': '#e7f0ff',
						},
					},
				},
			};
		</script>
		<script src="https://cdn.tailwindcss.com"></script>

		<style>
			.tab-active { border-bottom: 2px solid #15616d; color: #15616d; font-weight: 600; }
			.badge-nuevo { background: #f59e0b; color: white; font-size: 0.65rem; padding: 2px 6px; border-radius: 999px; margin-left: 6px; }
			.status-nuevo { background: #fef3c7; color: #92400e; }
			.status-verificado { background: #dbeafe; color: #1e40af; }
			.status-preparando { background: #e0e7ff; color: #3730a3; }
			.status-enviado { background: #d1fae5; color: #065f46; }
			.status-entregado { background: #ecfdf5; color: #047857; }
			.status-cancelado { background: #fee2e2; color: #991b1b; }
		</style>
	</head>
	<body class="bg-gray-50 text-gray-900 min-h-screen">
		<div class="max-w-7xl mx-auto px-4 py-10">
			<header class="mb-8 flex items-center justify-between">
				<a href="/" class="text-xl font-extrabold text-gray-900">Mizo <span class="text-sm font-medium text-gray-400">/ panel privado</span></a>
			</header>

			<div id="login" class="max-w-md mx-auto bg-white border border-gray-200 rounded-2xl shadow-sm p-8 mt-10">
				<h1 class="text-2xl font-extrabold text-gray-900 mb-1">Acceso privado</h1>
				<p class="text-gray-500 text-sm mb-6">Ingresa tu clave para gestionar productos y pedidos.</p>
				<form id="login-form" class="space-y-4">
					<input type="password" id="password" autocomplete="current-password" placeholder="Clave" class="block w-full border border-gray-300 rounded-md p-3 focus:ring-accent-main focus:border-accent-main" required />
					<button type="submit" class="w-full py-3 rounded-md font-semibold text-white bg-accent-main hover:bg-accent-hover transition">Entrar</button>
					<p id="error" class="text-sm text-red-600 hidden">Clave incorrecta. Inténtalo de nuevo.</p>
				</form>
			</div>

			<div id="panel" class="hidden">
				<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
					<div>
						<h1 class="text-2xl font-extrabold text-gray-900">Panel Mizo</h1>
						<p id="meta" class="text-gray-500 text-sm"></p>
					</div>
					<button id="logout" class="self-start text-sm font-semibold text-accent-main hover:text-accent-hover">Cerrar sesión</button>
				</div>

				<nav class="flex gap-6 border-b border-gray-200 mb-6">
					<button type="button" id="tab-productos" class="tab-btn pb-3 text-sm text-gray-500 tab-active">Productos y costos</button>
					<button type="button" id="tab-instalaciones" class="tab-btn pb-3 text-sm text-gray-500">Gestión de Productos e Instalaciones</button>
					<button type="button" id="tab-pedidos" class="tab-btn pb-3 text-sm text-gray-500">
						Pedidos <span id="badge-nuevos" class="badge-nuevo hidden">0</span>
					</button>
				</nav>

				<!-- PRODUCTOS -->
				<div id="view-productos">
					<div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-900">
						<strong>Producto de prueba (oculto):</strong>
						<a href="/productos/producto-prueba-mizo" target="_blank" rel="noopener" class="text-accent-main font-semibold hover:underline ml-1">/productos/producto-prueba-mizo</a>
						— precio $1.000 · SKU MZ-TEST-0001 · no aparece en la tienda pública.
					</div>
					<div id="cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6"></div>
					<section class="mb-6 overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
						<div class="flex flex-col gap-2 border-b border-amber-100 bg-amber-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
							<div>
								<p class="text-xs font-extrabold uppercase tracking-[0.18em] text-amber-700">Catálogo Maestro · Control de calidad</p>
								<h2 class="mt-1 text-xl font-extrabold text-gray-950">⚠️ Auditoría del Catálogo Maestro</h2>
								<p class="mt-1 text-sm font-semibold text-amber-900">Productos bajo revisión manual por diferencias de stock o precio frente al mayorista.</p>
							</div>
							<button id="refresh-quality-review" type="button" class="self-start rounded-full border border-amber-300 bg-white px-4 py-2 text-xs font-extrabold text-amber-800 transition hover:bg-amber-100">
								Actualizar reporte
							</button>
						</div>
						<div id="quality-review-panel" class="p-5">
							<p class="text-sm text-gray-500">Cargando reporte de discrepancias...</p>
						</div>
					</section>
					<section id="cotizaciones" class="mb-6 scroll-mt-8 overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm">
						<div class="border-b border-blue-100 bg-blue-50 px-5 py-4">
							<p class="text-xs font-extrabold uppercase tracking-[0.18em] text-accent-main">Catálogo Maestro · Cotizaciones</p>
							<div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
								<div>
									<h2 class="text-xl font-extrabold text-gray-950">Módulo profesional de cotizaciones</h2>
									<p class="mt-1 text-sm font-semibold text-blue-900">Correlativo automático, ítems del catálogo o manuales, condiciones comerciales, PDF y correo.</p>
								</div>
								<span id="quote-next-number" class="inline-flex self-start rounded-full bg-white px-4 py-2 text-xs font-black text-accent-main shadow-sm">Presupuesto: M1001</span>
							</div>
						</div>
						<div class="grid grid-cols-1 gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_360px]">
							<div class="space-y-5">
								<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
									<div class="rounded-2xl border border-gray-200 p-4">
										<p class="text-sm font-extrabold text-gray-950">Datos de la compañía</p>
										<div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
											<input id="quote-company-name" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Nombre empresa" />
											<input id="quote-company-phone" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Teléfono" />
											<input id="quote-company-email" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Email ventas" />
											<input id="quote-company-address" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Dirección" />
											<input id="quote-company-website" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Sitio web" />
											<input id="quote-company-logo" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="URL/Base64 logo" />
										</div>
										<button id="save-quote-config" type="button" class="mt-3 rounded-xl border border-accent-main px-4 py-2 text-xs font-black text-accent-main hover:bg-accent-soft">Guardar datos de compañía</button>
									</div>
									<div class="rounded-2xl border border-gray-200 p-4">
										<p class="text-sm font-extrabold text-gray-950">Datos del cliente</p>
										<div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
											<input id="quote-client-name" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Nombre cliente" />
											<input id="quote-client-email" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Email" />
											<input id="quote-client-phone" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Teléfono" />
											<input id="quote-client-rut" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="RUT / Código" />
											<input id="quote-client-address" class="rounded-xl border border-gray-300 px-3 py-2 text-sm sm:col-span-2" placeholder="Dirección / comuna" />
										</div>
									</div>
								</div>
								<div class="rounded-2xl border border-gray-200 p-4">
									<p class="text-sm font-extrabold text-gray-950">Agregar ítems</p>
									<div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1.3fr)_90px_130px_auto]">
										<select id="quote-catalog-product" class="rounded-xl border border-gray-300 px-3 py-2 text-sm">
											<option value="">Importar producto del catálogo Mizo</option>
										</select>
										<input id="quote-catalog-qty" type="number" min="1" value="1" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Cant." />
										<input id="quote-catalog-price" inputmode="numeric" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Precio opcional" />
										<button id="add-catalog-quote-item" type="button" class="rounded-xl bg-gray-950 px-4 py-2 text-xs font-black text-white">Agregar catálogo</button>
									</div>
									<div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[1fr_120px_90px_130px_auto]">
										<input id="quote-manual-name" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Ítem manual / servicio" />
										<input id="quote-manual-sku" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Código" />
										<input id="quote-manual-qty" type="number" min="1" value="1" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Cant." />
										<input id="quote-manual-price" inputmode="numeric" class="rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Precio unit." />
										<button id="add-manual-quote-item" type="button" class="rounded-xl bg-accent-main px-4 py-2 text-xs font-black text-white">Agregar manual</button>
									</div>
								</div>
								<div class="rounded-2xl border border-gray-200 p-4">
									<div class="flex items-center justify-between gap-3">
										<p class="text-sm font-extrabold text-gray-950">Detalle de cotización</p>
										<button id="clear-quote-items" type="button" class="text-xs font-bold text-gray-500 hover:text-red-600">Limpiar ítems</button>
									</div>
									<div id="quote-items-list" class="mt-3 space-y-2"></div>
								</div>
							</div>
							<aside class="space-y-4">
								<div class="rounded-2xl bg-gray-950 p-5 text-white">
									<p class="text-xs font-extrabold uppercase tracking-[0.18em] text-blue-200">Total presupuesto</p>
									<p id="quote-total" class="mt-2 text-3xl font-black">$0</p>
									<p id="quote-saved-link" class="mt-3 text-xs font-semibold text-white/70"></p>
								</div>
								<div class="rounded-2xl border border-gray-200 p-4">
									<p class="text-sm font-extrabold text-gray-950">Condiciones comerciales</p>
									<textarea id="quote-conditions" rows="6" class="mt-3 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Una condición por línea"></textarea>
									<textarea id="quote-notes" rows="3" class="mt-3 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Notas internas / alcance"></textarea>
								</div>
								<div class="rounded-2xl border border-gray-200 p-4">
									<p class="text-sm font-extrabold text-gray-950">Generar y enviar</p>
									<input id="quote-email-to" class="mt-3 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Enviar a email" />
									<textarea id="quote-email-message" rows="3" class="mt-3 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Mensaje del correo"></textarea>
									<div class="mt-3 grid grid-cols-1 gap-2">
										<button id="save-quote" type="button" class="rounded-xl bg-accent-main px-4 py-3 text-sm font-black text-white hover:bg-accent-hover">Guardar cotización y generar PDF</button>
										<button id="send-quote" type="button" class="rounded-xl border border-accent-main px-4 py-3 text-sm font-black text-accent-main hover:bg-accent-soft">Enviar PDF por email</button>
									</div>
									<p id="quote-message" class="mt-3 text-sm font-semibold text-gray-500"></p>
								</div>
								<div class="rounded-2xl border border-gray-200 p-4">
									<div class="flex items-center justify-between">
										<p class="text-sm font-extrabold text-gray-950">Últimas cotizaciones</p>
										<button id="refresh-quotes" type="button" class="text-xs font-bold text-accent-main">Actualizar</button>
									</div>
									<div id="quotes-history" class="mt-3 space-y-2 text-sm"></div>
								</div>
							</aside>
						</div>
					</section>
					<div class="relative mb-4 max-w-xl">
						<input id="admin-search" type="search" placeholder="Buscar por SKU, nombre, marca o tienda..." class="w-full pl-11 pr-4 py-3 rounded-md border border-gray-300 focus:ring-accent-main focus:border-accent-main focus:outline-none" />
						<svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
					</div>
					<p id="search-count" class="text-sm text-gray-500 mb-3"></p>
					<div id="rows" class="grid grid-cols-1 gap-4 xl:grid-cols-2"></div>
				</div>

				<!-- GESTIÓN DE PRODUCTOS E INSTALACIONES -->
				<div id="view-instalaciones" class="hidden">
					<div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6">
						<section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
							<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
								<div>
									<p class="text-xs font-extrabold uppercase tracking-[0.18em] text-accent-main">Home · 8 cuadros destacados</p>
									<h2 class="mt-2 text-2xl font-extrabold text-gray-900">Gestión de Productos e Instalaciones</h2>
									<p class="mt-2 text-sm text-gray-500 max-w-2xl">
										Selecciona libremente qué producto ocupa cada uno de los 8 cuadros destacados de la Home y configura su lógica de instalación.
									</p>
								</div>
								<span class="inline-flex self-start rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">
									Instalación como prioridad comercial
								</span>
							</div>

							<form id="installation-form" class="space-y-6">
								<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
									<label class="block">
										<span class="block text-sm font-bold text-gray-700 mb-1">Seleccionar Cuadro de la Home</span>
										<select id="featured-slot" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm bg-white focus:ring-accent-main focus:border-accent-main">
											<option value="">Selecciona un cuadro de la Home</option>
										</select>
									</label>

									<label class="block">
										<span class="block text-sm font-bold text-gray-700 mb-1">Seleccionar Producto a Mostrar</span>
										<select id="featured-product" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm bg-white focus:ring-accent-main focus:border-accent-main">
											<option value="">Selecciona producto del catálogo</option>
										</select>
									</label>
								</div>

								<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
									<label class="block">
										<span class="block text-sm font-bold text-gray-700 mb-1">Precio Base de Instalación (1ra Unidad)</span>
										<input id="install-base-price" type="text" inputmode="numeric" placeholder="Ej. 189990" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-accent-main focus:border-accent-main" />
									</label>

									<label class="block">
										<span class="block text-sm font-bold text-gray-700 mb-1">Recargo por Unidad Adicional</span>
										<input id="additional-unit-price" type="text" inputmode="numeric" placeholder="Ej. 45000" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-accent-main focus:border-accent-main" />
									</label>
								</div>

								<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
									<label class="block">
										<span class="block text-sm font-bold text-gray-700 mb-1">Región de Cobertura Base</span>
										<select id="base-region" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm bg-white focus:ring-accent-main focus:border-accent-main">
											<option value="">Selecciona región base</option>
										</select>
									</label>

									<label class="block">
										<span class="block text-sm font-bold text-gray-700 mb-1">Recargo Geográfico</span>
										<select id="geo-surcharge" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm bg-white focus:ring-accent-main focus:border-accent-main">
											<option value="0">Sin Recargo</option>
											<option value="30000">+$30.000</option>
											<option value="50000">+$50.000</option>
											<option value="100000">+$100.000</option>
										</select>
									</label>
								</div>

								<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
									<label class="block">
										<span class="block text-sm font-bold text-gray-700 mb-1">Cantidad para Simular</span>
										<select id="install-quantity" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm bg-white focus:ring-accent-main focus:border-accent-main">
											<option value="1">1 unidad</option>
											<option value="2">2 unidades</option>
											<option value="3">3 unidades</option>
											<option value="4">4 unidades</option>
											<option value="5">5 unidades</option>
											<option value="6">6 unidades</option>
											<option value="8">8 unidades</option>
											<option value="10">10 unidades</option>
										</select>
									</label>
								</div>

								<div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
									<p class="text-sm font-extrabold text-gray-900">Fórmula aplicada en checkout</p>
									<p class="mt-1 text-sm text-gray-700">
										Total Instalación = Precio Base Instalación + ([Cantidad - 1] × Recargo Unidad Adicional) + Recargo Geográfico.
									</p>
									<div id="install-preview" class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm"></div>
								</div>

								<div class="flex flex-col sm:flex-row sm:items-center gap-3">
									<button type="submit" class="inline-flex justify-center rounded-xl bg-accent-main px-6 py-3 text-sm font-extrabold text-white hover:bg-accent-hover transition">
										Guardar configuración
									</button>
									<button id="reset-installation-form" type="button" class="inline-flex justify-center rounded-xl border border-gray-300 px-6 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
										Limpiar formulario
									</button>
									<p id="installation-msg" class="text-sm font-semibold text-green-700 hidden"></p>
								</div>
							</form>
						</section>

						<aside class="space-y-4">
							<div class="bg-gray-950 text-white rounded-2xl shadow-sm p-6">
								<p class="text-xs font-extrabold uppercase tracking-[0.18em] text-amber-300">Vista administrativa</p>
								<h3 class="mt-2 text-xl font-extrabold">Configuraciones guardadas</h3>
								<p class="mt-2 text-sm text-white/70">Estas reglas quedan listas para alimentar la lógica dinámica del carrito/checkout.</p>
							</div>
							<div id="installation-config-list" class="space-y-3"></div>
							<p id="installation-empty" class="text-sm text-gray-400 bg-white border border-gray-200 rounded-xl p-5">Aún no hay configuraciones guardadas.</p>
						</aside>
					</div>
				</div>

				<!-- PEDIDOS -->
				<div id="view-pedidos" class="hidden">
					<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
						<p class="text-sm text-gray-600">Compras verificadas en Mercado Pago. Revisa el pago en MP y gestiona el envío.</p>
						<div class="flex flex-wrap gap-2">
							<select id="pedidos-filter" class="text-sm border border-gray-300 rounded-md px-3 py-2 bg-white">
								<option value="">Todos los estados</option>
								<option value="nuevo">Solo nuevos</option>
								<option value="verificado">Pago verificado</option>
								<option value="preparando">Preparando envío</option>
								<option value="enviado">Enviados</option>
								<option value="entregado">Entregados</option>
								<option value="cancelado">Cancelados</option>
							</select>
							<button id="export-pedidos" type="button" class="text-sm font-semibold text-gray-700 border border-gray-300 px-4 py-2 rounded-md hover:bg-gray-50">Exportar CSV</button>
							<button id="refresh-pedidos" type="button" class="text-sm font-semibold text-accent-main border border-accent-main px-4 py-2 rounded-md hover:bg-accent-soft">Actualizar</button>
						</div>
					</div>
					<p id="pedidos-count" class="text-sm text-gray-500 mb-3"></p>
					<div id="pedidos-list" class="space-y-4"></div>
					<p id="pedidos-empty" class="hidden text-center text-gray-400 py-16 bg-white border border-gray-200 rounded-xl">Aún no hay pedidos registrados.</p>
				</div>
			</div>
		</div>

		<script>
			const fmt = new Intl.NumberFormat('es-CL');
			const clp = (v) => '$' + fmt.format(v);
			const catLabel = { sonido: 'Parlantes', proyector: 'Proyectores', camara: 'Cámaras' };

			let adminPassword = '';
			let allItems = [];
			let allOrders = [];
			let orderStatuses = {};
			let pedidosFilter = '';
			const INSTALL_CONFIG_KEY = 'mizo-admin-installation-configs';
			const HOME_SLOT_COUNT = 8;
			let homeFeaturedConfigs = {};
			let quoteConfig = null;
			let quoteItems = [];
			let currentQuote = null;

			const chileRegions = [
				'Arica y Parinacota',
				'Tarapacá',
				'Antofagasta',
				'Atacama',
				'Coquimbo',
				'Valparaíso',
				'Metropolitana de Santiago',
				"Libertador General Bernardo O'Higgins",
				'Maule',
				'Ñuble',
				'Biobío',
				'La Araucanía',
				'Los Ríos',
				'Los Lagos',
				'Aysén del General Carlos Ibáñez del Campo',
				'Magallanes y de la Antártica Chilena',
			];

			function moneyNumber(value) {
				return Number(String(value ?? '').replace(/[^\d-]/g, '')) || 0;
			}

			function resolveGeographicSurcharge(surchargeValue, baseInstallationPrice) {
				const value = String(surchargeValue ?? '0').trim();
				if (value.endsWith('%')) {
					return Math.round(baseInstallationPrice * (Number(value.replace('%', '')) || 0) / 100);
				}
				return moneyNumber(value);
			}

			/**
			 * Fórmula de checkout:
			 * Total Instalación = [Precio Base Instalación]
			 * + ([Cantidad - 1] * [Recargo Unidad Adicional])
			 * + [Recargo Geográfico Seleccionado por el Usuario].
			 */
			function calculateInstallationTotal(config, quantity, selectedGeographicSurcharge) {
				const qty = Math.max(1, Number(quantity) || 1);
				const baseInstallationPrice = moneyNumber(config.baseInstallationPrice);
				const additionalUnitSurcharge = moneyNumber(config.additionalUnitSurcharge);
				const geographicSurcharge = resolveGeographicSurcharge(
					selectedGeographicSurcharge ?? config.geographicSurcharge,
					baseInstallationPrice
				);

				return baseInstallationPrice + ((qty - 1) * additionalUnitSurcharge) + geographicSurcharge;
			}

			window.MizoInstallationPricing = {
				calculateInstallationTotal,
				resolveGeographicSurcharge,
			};

			function filteredOrders() {
				if (!pedidosFilter) return allOrders;
				return allOrders.filter((o) => o.status === pedidosFilter);
			}

			function exportPedidosCsv() {
				const orders = filteredOrders();
				if (!orders.length) {
					alert('No hay pedidos para exportar con el filtro actual.');
					return;
				}
				const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
				const header = ['ID pago', 'Fecha', 'Estado', 'Total', 'Cliente', 'Email', 'Teléfono', 'Región', 'Comuna', 'Dirección', 'Referencia', 'Envío', 'Productos', 'Seguimiento', 'Notas'];
				const rows = orders.map((o) => [
					o.id,
					formatDate(o.createdAt),
					orderStatuses[o.status] || o.status,
					o.total,
					o.customer?.name,
					o.customer?.email,
					o.customer?.phone,
					o.delivery?.region,
					o.delivery?.comuna,
					o.delivery?.address,
					o.delivery?.reference,
					o.shippingCost || 0,
					(o.items || []).map((i) => `${i.title} x${i.quantity}`).join(' | '),
					o.tracking,
					o.adminNotes,
				]);
				const csv = [header, ...rows].map((r) => r.map(esc).join(',')).join('\n');
				const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
				const a = document.createElement('a');
				a.href = URL.createObjectURL(blob);
				a.download = `pedidos-mizo-${new Date().toISOString().slice(0, 10)}.csv`;
				a.click();
				URL.revokeObjectURL(a.href);
			}

			function b64ToBytes(b64) {
				return Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
			}

			async function decryptData(password) {
				const res = await fetch('/datos-privados.json', { cache: 'no-store' });
				if (!res.ok) throw new Error('no-data');
				const data = await res.json();
				const enc = new TextEncoder();
				const baseKey = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']);
				const key = await crypto.subtle.deriveKey(
					{ name: 'PBKDF2', salt: b64ToBytes(data.salt), iterations: data.iterations, hash: 'SHA-256' },
					baseKey,
					{ name: 'AES-GCM', length: 256 },
					false,
					['decrypt']
				);
				const ptBuf = await crypto.subtle.decrypt(
					{ name: 'AES-GCM', iv: b64ToBytes(data.iv) },
					key,
					b64ToBytes(data.ciphertext)
				);
				return JSON.parse(new TextDecoder().decode(ptBuf));
			}

			async function apiPedidos(action, extra) {
				const res = await fetch('/.netlify/functions/pedidos-api', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ password: adminPassword, action, ...extra }),
				});
				const data = await res.json();
				if (!res.ok) throw new Error(data.error || 'Error API pedidos');
				return data;
			}

			function showTab(name) {
				document.getElementById('view-productos').classList.toggle('hidden', name !== 'productos');
				document.getElementById('view-instalaciones').classList.toggle('hidden', name !== 'instalaciones');
				document.getElementById('view-pedidos').classList.toggle('hidden', name !== 'pedidos');
				document.getElementById('tab-productos').classList.toggle('tab-active', name === 'productos');
				document.getElementById('tab-instalaciones').classList.toggle('tab-active', name === 'instalaciones');
				document.getElementById('tab-pedidos').classList.toggle('tab-active', name === 'pedidos');
				if (name === 'pedidos') loadPedidos();
			}

			function renderProducts(payload) {
				const items = payload.products;
				const totalCompra = items.reduce((a, p) => a + p.basePrice, 0);
				const totalVenta = items.reduce((a, p) => a + p.sellingPrice, 0);

				document.getElementById('meta').textContent =
					`${items.length} productos · recargo ${Math.round(payload.markup * 100)}% · datos al ${payload.generatedAt}`;

				document.getElementById('cards').innerHTML = [
					['Productos', items.length],
					['Costo total compra', clp(totalCompra)],
					['Total publicado', clp(totalVenta)],
					['Ganancia total', clp(totalVenta - totalCompra)],
				].map(([l, v]) => `<div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm"><p class="text-xs text-gray-500">${l}</p><p class="text-xl font-extrabold mt-1">${v}</p></div>`).join('');

				allItems = items;
				initInstallationManager(items);
				initQuotesModule(items);
				renderRows(items);
			}

			function renderRows(items) {
				const list = document.getElementById('rows');
				if (!items.length) {
					list.innerHTML = '<div class="rounded-2xl border border-gray-200 bg-white px-6 py-12 text-center text-gray-400">Sin resultados.</div>';
				} else {
					list.innerHTML = items.map((p) => {
						const g = p.sellingPrice - p.basePrice;
						const stock = p.stock ?? null;
						const hasStock = stock !== null && stock !== undefined;
						const stockClass = hasStock && Number(stock) > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700';
						return `<article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
							<div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-[6.5rem_minmax(0,1fr)] lg:grid-cols-[7rem_minmax(0,1fr)]">
								<div class="flex items-center gap-3 sm:block">
									<div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 p-2 sm:h-28 sm:w-full">
										<img src="${escapeHtml(p.image || '/mizo-logo.svg')}" alt="${escapeHtml(p.name)}" class="max-h-full max-w-full object-contain" loading="lazy" onerror="this.src='/mizo-logo.svg';" />
									</div>
									<div class="min-w-0 sm:hidden">
										<p class="font-mono text-xs font-black text-accent-main">${escapeHtml(p.sku || '—')}</p>
										<h3 class="mt-1 line-clamp-2 text-base font-black leading-tight text-gray-950">${escapeHtml(p.name)}</h3>
										<p class="mt-1 text-xs font-bold uppercase tracking-wide text-gray-500">${escapeHtml(p.brand || '')}</p>
									</div>
								</div>

								<div class="min-w-0">
									<div class="hidden sm:block">
										<p class="font-mono text-xs font-black text-accent-main">${escapeHtml(p.sku || '—')}</p>
										<h3 class="mt-1 line-clamp-2 text-lg font-black leading-tight text-gray-950">${escapeHtml(p.name)}</h3>
										<p class="mt-1 text-xs font-bold uppercase tracking-wide text-gray-500">${escapeHtml(p.brand || '')}</p>
									</div>

									<div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
										<div class="rounded-2xl bg-slate-50 p-3">
											<p class="text-[0.65rem] font-black uppercase tracking-wide text-gray-400">Datos técnicos</p>
											<p class="mt-2 text-sm font-black text-accent-main">${escapeHtml(p.store || 'Proveedor')}</p>
											<p class="mt-2 text-sm font-bold text-gray-800">${escapeHtml(catLabel[p.category] || p.category || 'Producto')}</p>
											<p class="mt-3 inline-flex rounded-full px-2.5 py-1 text-xs font-black ${stockClass}">Stock ${hasStock ? escapeHtml(stock) : '—'}</p>
										</div>

										<div class="rounded-2xl bg-slate-50 p-3 md:col-span-2">
											<p class="text-[0.65rem] font-black uppercase tracking-wide text-gray-400">Datos financieros</p>
											<div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
												<div>
													<p class="text-xs text-gray-500">Costo base</p>
													<p class="text-sm font-black text-gray-950">${clp(p.basePrice)}</p>
												</div>
												<div>
													<p class="text-xs text-gray-500">Margen aplicado</p>
													<p class="text-sm font-black text-accent-main">20,00%</p>
												</div>
												<div>
													<p class="text-xs text-gray-500">Venta Mizo</p>
													<p class="text-sm font-black text-gray-950">${clp(p.sellingPrice)}</p>
												</div>
											</div>
											<p class="mt-3 text-xs font-semibold text-emerald-700">Ganancia estimada: ${clp(g)}</p>
										</div>
									</div>

									<div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
										<a href="${escapeHtml(p.url || '#')}" target="_blank" rel="noopener" class="inline-flex justify-center rounded-xl border border-accent-main px-4 py-2 text-sm font-black text-accent-main transition hover:bg-accent-soft">Ver origen</a>
										<span class="inline-flex justify-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">Publicado</span>
									</div>
								</div>
							</div>
						</article>`;
					}).join('');
				}
				document.getElementById('search-count').textContent = `${items.length} de ${allItems.length} productos`;
			}

			function escapeHtml(value) {
				return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#39;',
				}[ch]));
			}

			async function apiQuotes(action, payload = {}) {
				const response = await fetch('/api/quotes.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
					cache: 'no-store',
					body: JSON.stringify({ action, password: adminPassword, ...payload }),
				});
				const data = await response.json();
				if (!response.ok || !data?.ok) throw new Error(data?.error || 'No se pudo operar con cotizaciones.');
				return data;
			}

			function quoteMessage(text, type = 'info') {
				const el = document.getElementById('quote-message');
				if (!el) return;
				el.textContent = text;
				el.className = 'mt-3 text-sm font-semibold ' + (type === 'error' ? 'text-red-600' : type === 'success' ? 'text-green-700' : 'text-gray-500');
			}

			function quoteCompanyFromForm() {
				return {
					companyName: document.getElementById('quote-company-name').value.trim(),
					phone: document.getElementById('quote-company-phone').value.trim(),
					email: document.getElementById('quote-company-email').value.trim(),
					address: document.getElementById('quote-company-address').value.trim(),
					website: document.getElementById('quote-company-website').value.trim(),
					logo: document.getElementById('quote-company-logo').value.trim(),
				};
			}

			function fillQuoteCompanyForm(config) {
				quoteConfig = config || {};
				document.getElementById('quote-company-name').value = quoteConfig.companyName || 'Mizo';
				document.getElementById('quote-company-phone').value = quoteConfig.phone || '';
				document.getElementById('quote-company-email').value = quoteConfig.email || 'ventas@mizo.cl';
				document.getElementById('quote-company-address').value = quoteConfig.address || '';
				document.getElementById('quote-company-website').value = quoteConfig.website || 'https://mizo.cl';
				document.getElementById('quote-company-logo').value = quoteConfig.logo || '/mizo-logo.png';
				document.getElementById('quote-conditions').value = (quoteConfig.defaultConditions || []).join('\n');
			}

			function quoteClientFromForm() {
				return {
					name: document.getElementById('quote-client-name').value.trim(),
					email: document.getElementById('quote-client-email').value.trim(),
					phone: document.getElementById('quote-client-phone').value.trim(),
					rut: document.getElementById('quote-client-rut').value.trim(),
					address: document.getElementById('quote-client-address').value.trim(),
				};
			}

			function quoteConditionsFromForm() {
				return document.getElementById('quote-conditions').value
					.split('\n')
					.map((line) => line.trim())
					.filter(Boolean);
			}

			function renderQuoteCatalogOptions(items) {
				const select = document.getElementById('quote-catalog-product');
				select.innerHTML = '<option value="">Importar producto del catálogo Mizo</option>' + items.map((product) => {
					const price = Number(product.sellingPrice || product.basePrice || 0);
					return `<option value="${escapeHtml(product.id)}">${escapeHtml(product.sku || product.id)} · ${escapeHtml(product.brand || '')} ${escapeHtml(product.name)} · ${clp(price)}</option>`;
				}).join('');
			}

			function quoteTotal() {
				return quoteItems.reduce((sum, item) => sum + (Number(item.quantity) * Number(item.unitPrice)), 0);
			}

			function renderQuoteItems() {
				const list = document.getElementById('quote-items-list');
				const total = quoteTotal();
				document.getElementById('quote-total').textContent = clp(total);
				if (!quoteItems.length) {
					list.innerHTML = '<div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm font-semibold text-gray-400">Aún no hay ítems en esta cotización.</div>';
					return;
				}
				list.innerHTML = quoteItems.map((item, index) => `
					<article class="rounded-xl border border-gray-200 bg-gray-50 p-3">
						<div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
							<div class="min-w-0">
								<p class="font-mono text-[0.68rem] font-black uppercase text-accent-main">${escapeHtml(item.sku || 'Manual')}</p>
								<h4 class="mt-1 text-sm font-black text-gray-950">${escapeHtml(item.name)}</h4>
								<p class="mt-1 text-xs font-semibold text-gray-500">${escapeHtml(item.source === 'catalog' ? 'Catálogo Mizo' : 'Ítem manual')}</p>
							</div>
							<button type="button" class="remove-quote-item text-xs font-black text-red-600" data-index="${index}">Eliminar</button>
						</div>
						<div class="mt-3 grid grid-cols-3 gap-2 text-sm">
							<div><p class="text-xs text-gray-500">Cantidad</p><p class="font-black">${Number(item.quantity)}</p></div>
							<div><p class="text-xs text-gray-500">Unitario</p><p class="font-black">${clp(Number(item.unitPrice))}</p></div>
							<div><p class="text-xs text-gray-500">Total</p><p class="font-black">${clp(Number(item.quantity) * Number(item.unitPrice))}</p></div>
						</div>
					</article>
				`).join('');
				list.querySelectorAll('.remove-quote-item').forEach((button) => {
					button.onclick = () => {
						quoteItems.splice(Number(button.dataset.index), 1);
						renderQuoteItems();
					};
				});
			}

			function addQuoteItem(item) {
				quoteItems.push({
					source: item.source || 'manual',
					sku: item.sku || '',
					name: item.name,
					quantity: Math.max(1, Number(item.quantity) || 1),
					unitPrice: Math.max(0, moneyNumber(item.unitPrice)),
				});
				currentQuote = null;
				document.getElementById('quote-saved-link').innerHTML = '';
				renderQuoteItems();
			}

			function addCatalogQuoteItem() {
				const productId = document.getElementById('quote-catalog-product').value;
				const product = allItems.find((item) => item.id === productId);
				if (!product) {
					quoteMessage('Selecciona un producto del catálogo para importarlo.', 'error');
					return;
				}
				const overridePrice = moneyNumber(document.getElementById('quote-catalog-price').value);
				addQuoteItem({
					source: 'catalog',
					sku: product.sku || product.id,
					name: `${product.brand || ''} ${product.name}`.trim(),
					quantity: document.getElementById('quote-catalog-qty').value,
					unitPrice: overridePrice || Number(product.sellingPrice || product.basePrice || 0),
				});
				document.getElementById('quote-catalog-product').value = '';
				document.getElementById('quote-catalog-price').value = '';
				document.getElementById('quote-catalog-qty').value = '1';
				quoteMessage('Producto agregado a la cotización.', 'success');
			}

			function addManualQuoteItem() {
				const name = document.getElementById('quote-manual-name').value.trim();
				const price = moneyNumber(document.getElementById('quote-manual-price').value);
				if (!name || !price) {
					quoteMessage('Completa nombre y precio unitario del ítem manual.', 'error');
					return;
				}
				addQuoteItem({
					source: 'manual',
					sku: document.getElementById('quote-manual-sku').value.trim() || 'MANUAL',
					name,
					quantity: document.getElementById('quote-manual-qty').value,
					unitPrice: price,
				});
				['quote-manual-name', 'quote-manual-sku', 'quote-manual-price'].forEach((id) => document.getElementById(id).value = '');
				document.getElementById('quote-manual-qty').value = '1';
				quoteMessage('Ítem manual agregado.', 'success');
			}

			async function loadQuotesHistory() {
				const container = document.getElementById('quotes-history');
				container.innerHTML = '<p class="text-gray-400">Cargando cotizaciones...</p>';
				try {
					const data = await apiQuotes('list');
					const quotes = data.quotes || [];
					if (!quotes.length) {
						container.innerHTML = '<p class="text-gray-400">Aún no hay cotizaciones guardadas.</p>';
						return;
					}
					container.innerHTML = quotes.slice(0, 8).map((quote) => `
						<a class="block rounded-xl border border-gray-200 p-3 hover:bg-accent-soft" href="${escapeHtml(quote.pdfUrl)}" target="_blank" rel="noopener">
							<span class="font-black text-gray-950">${escapeHtml(quote.number)}</span>
							<span class="block text-xs text-gray-500">${escapeHtml(quote.client || 'Cliente')} · ${clp(Number(quote.total) || 0)}</span>
						</a>
					`).join('');
				} catch (error) {
					container.innerHTML = `<p class="text-red-600">${escapeHtml(error.message)}</p>`;
				}
			}

			async function bootstrapQuotes() {
				try {
					const data = await apiQuotes('bootstrap');
					document.getElementById('quote-next-number').textContent = `Presupuesto: ${data.nextNumber || 'M1001'}`;
					fillQuoteCompanyForm(data.config || {});
					renderQuoteItems();
					await loadQuotesHistory();
				} catch (error) {
					quoteMessage(error.message, 'error');
				}
			}

			function initQuotesModule(items) {
				renderQuoteCatalogOptions(items);
				if (!quoteConfig) bootstrapQuotes();
			}

			async function saveQuoteConfig() {
				try {
					const config = {
						...quoteCompanyFromForm(),
						defaultConditions: quoteConditionsFromForm(),
					};
					const data = await apiQuotes('save_config', { config });
					fillQuoteCompanyForm(data.config || config);
					quoteMessage('Datos de compañía guardados para futuras cotizaciones.', 'success');
				} catch (error) {
					quoteMessage(error.message, 'error');
				}
			}

			async function saveQuote() {
				if (!quoteItems.length) {
					quoteMessage('Agrega al menos un ítem antes de guardar.', 'error');
					return;
				}
				try {
					quoteMessage('Generando cotización y PDF...', 'info');
					const data = await apiQuotes('create_quote', {
						company: quoteCompanyFromForm(),
						client: quoteClientFromForm(),
						items: quoteItems,
						conditions: quoteConditionsFromForm(),
						notes: document.getElementById('quote-notes').value.trim(),
					});
					currentQuote = data.quote;
					document.getElementById('quote-next-number').textContent = `Presupuesto: ${data.nextNumber || ''}`;
					document.getElementById('quote-saved-link').innerHTML = `<a class="text-blue-200 underline" href="${escapeHtml(currentQuote.pdfUrl)}" target="_blank" rel="noopener">Descargar ${escapeHtml(currentQuote.number)}.pdf</a>`;
					document.getElementById('quote-email-to').value = currentQuote.client?.email || document.getElementById('quote-client-email').value;
					quoteMessage(`Cotización ${currentQuote.number} guardada correctamente.`, 'success');
					await loadQuotesHistory();
					return currentQuote;
				} catch (error) {
					quoteMessage(error.message, 'error');
					return null;
				}
			}

			async function sendQuote() {
				const quote = currentQuote || await saveQuote();
				if (!quote) return;
				try {
					quoteMessage('Enviando PDF por correo...', 'info');
					const data = await apiQuotes('send_quote', {
						number: quote.number,
						to: document.getElementById('quote-email-to').value.trim(),
						message: document.getElementById('quote-email-message').value.trim(),
					});
					quoteMessage(data.sent ? `Cotización ${quote.number} enviada por email.` : data.error || 'El servidor no confirmó el envío.', data.sent ? 'success' : 'error');
				} catch (error) {
					quoteMessage(error.message, 'error');
				}
			}

			function reviewValue(value) {
				return value === null || value === undefined || value === '' ? '—' : escapeHtml(value);
			}

			function renderQualityReview(products) {
				const panel = document.getElementById('quality-review-panel');
				if (!panel) return;
				if (!products.length) {
					panel.innerHTML = `
						<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
							<p class="text-base font-extrabold">✅ Inventario 100% cuadrado con los mayoristas</p>
							<p class="mt-1 text-sm text-emerald-800">No hay discrepancias de stock o precio pendientes de revisión manual.</p>
						</div>
					`;
					return;
				}

				panel.innerHTML = `
					<p class="mb-4 text-sm font-semibold text-amber-900">
						${products.length} producto(s) requieren revisión manual porque el valor escrito en Mizo no coincide con el proveedor.
					</p>
					<div class="overflow-x-auto rounded-xl border border-gray-200">
						<table class="w-full text-left text-sm">
							<thead class="bg-gray-100 text-xs uppercase text-gray-600">
								<tr>
									<th class="px-4 py-3">SKU</th>
									<th class="px-4 py-3 text-right">Stock Proveedor</th>
									<th class="px-4 py-3 text-right">Stock Mizo</th>
									<th class="px-4 py-3 text-right">Precio Proveedor</th>
									<th class="px-4 py-3 text-right">Precio Mizo</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100 bg-white">
								${products.map((product) => `
									<tr class="hover:bg-amber-50/60">
										<td class="px-4 py-3 font-mono text-xs font-bold text-gray-900">${escapeHtml(product.sku)}</td>
										<td class="px-4 py-3 text-right font-semibold text-gray-700">${reviewValue(product.stock_proveedor)}</td>
										<td class="px-4 py-3 text-right font-semibold text-gray-700">${reviewValue(product.stock_mizo)}</td>
										<td class="px-4 py-3 text-right font-semibold text-gray-700">${product.precio_proveedor === null || product.precio_proveedor === undefined ? '—' : clp(Number(product.precio_proveedor) || 0)}</td>
										<td class="px-4 py-3 text-right font-semibold text-gray-700">${product.precio_mizo === null || product.precio_mizo === undefined ? '—' : clp(Number(product.precio_mizo) || 0)}</td>
									</tr>
								`).join('')}
							</tbody>
						</table>
					</div>
				`;
			}

			async function loadQualityReview() {
				const panel = document.getElementById('quality-review-panel');
				if (panel) panel.innerHTML = '<p class="text-sm text-gray-500">Cargando reporte de discrepancias...</p>';
				try {
					const response = await fetch('/api-productos-en-revision.php?ts=' + Date.now(), {
						headers: { Accept: 'application/json' },
						cache: 'no-store',
					});
					const payload = await response.json();
					if (!response.ok || !payload?.ok) throw new Error(payload?.error || 'No se pudo leer el reporte.');
					renderQualityReview(Array.isArray(payload.products) ? payload.products : []);
				} catch (error) {
					console.warn('No se pudo cargar productos bajo revisión.', error);
					renderQualityReview([]);
				}
			}

			function formatDate(iso) {
				try {
					return new Date(iso).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' });
				} catch (e) { return iso; }
			}

			function readInstallationConfigs() {
				try {
					return homeFeaturedConfigs || JSON.parse(localStorage.getItem(INSTALL_CONFIG_KEY)) || {};
				} catch (e) {
					return {};
				}
			}

			function writeInstallationConfigs(configs) {
				homeFeaturedConfigs = configs;
				localStorage.setItem(INSTALL_CONFIG_KEY, JSON.stringify(configs));
			}

			function slotsToConfigs(slots = []) {
				const configs = {};
				slots.forEach((item) => {
					configs[String(item.slot)] = {
						slot: String(item.slot),
						slotLabel: `Cuadro ${Number(item.slot) + 1}`,
						productId: item.product?.id || '',
						productName: item.product?.name || '',
						productBrand: item.product?.brand || '',
						productCategory: item.product?.category || '',
						productPrice: Number(item.product?.price) || 0,
						product: item.product,
						baseInstallationPrice: Number(item.installation?.baseInstallationPrice || item.installation?.salePrice || 0),
						additionalUnitSurcharge: Number(item.installation?.additionalUnitSurcharge) || 0,
						baseRegion: item.installation?.baseRegion || '',
						geographicSurcharge: item.installation?.geographicSurcharge || '0',
					};
				});
				return configs;
			}

			async function apiHomeFeatured(method, payload = {}) {
				const url = method === 'POST' ? '/api/guardar-destacados.php' : `/api/obtener-destacados.php?ts=${Date.now()}`;
				const response = await fetch(url, {
					method,
					headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
					cache: 'no-store',
					body: method === 'POST'
						? JSON.stringify({ password: adminPassword, slots: payload.slots || [] })
						: undefined,
				});
				const data = await response.json();
				if (!response.ok || !data?.ok) throw new Error(data?.error || 'No se pudo sincronizar destacados de Home.');
				return data;
			}

			async function loadRemoteHomeFeatured() {
				try {
					const payload = await apiHomeFeatured('GET');
					const slots = Array.isArray(payload.slots) ? payload.slots : [];
					homeFeaturedConfigs = slotsToConfigs(slots);
					localStorage.setItem(INSTALL_CONFIG_KEY, JSON.stringify(homeFeaturedConfigs));
				} catch (error) {
					console.warn('Usando configuración local de destacados Home.', error);
					homeFeaturedConfigs = JSON.parse(localStorage.getItem(INSTALL_CONFIG_KEY) || '{}');
				}
			}

			function currentInstallationFormConfig() {
				const slot = document.getElementById('featured-slot').value;
				const productId = document.getElementById('featured-product').value;
				const product = allItems.find((p) => p.id === productId);
				return {
					slot,
					slotLabel: slot !== '' ? `Cuadro ${Number(slot) + 1}` : '',
					productId,
					productName: product?.name || '',
					productBrand: product?.brand || '',
					productCategory: product?.category || '',
					productPrice: Number(product?.sellingPrice || product?.basePrice || 0),
					product: product ? {
						id: product.id,
						sku: product.sku,
						name: product.name,
						brand: product.brand,
						category: product.category,
						description: product.description,
						image: product.image,
						weightKg: product.weightKg || 0,
						price: Number(product.sellingPrice || product.basePrice || 0),
					} : null,
					baseInstallationPrice: moneyNumber(document.getElementById('install-base-price').value),
					additionalUnitSurcharge: moneyNumber(document.getElementById('additional-unit-price').value),
					baseRegion: document.getElementById('base-region').value,
					geographicSurcharge: document.getElementById('geo-surcharge').value,
				};
			}

			function defaultInstallationByCategory(category) {
				if (category === 'proyector') return { baseInstallationPrice: 189990, additionalUnitSurcharge: 49990 };
				if (category === 'sonido') return { baseInstallationPrice: 149990, additionalUnitSurcharge: 39990 };
				return { baseInstallationPrice: 79990, additionalUnitSurcharge: 24990 };
			}

			function fillInstallationForm(config) {
				document.getElementById('install-base-price').value = config?.baseInstallationPrice ? clp(config.baseInstallationPrice) : '';
				document.getElementById('additional-unit-price').value = config?.additionalUnitSurcharge ? clp(config.additionalUnitSurcharge) : '';
				document.getElementById('base-region').value = config?.baseRegion || 'Los Lagos';
				document.getElementById('geo-surcharge').value = config?.geographicSurcharge || '0';
				updateInstallationPreview();
			}

			function loadInstallationSlot(slot) {
				const configs = readInstallationConfigs();
				if (slot === '') {
					document.getElementById('featured-product').value = '';
					fillInstallationForm(null);
					return;
				}
				const saved = configs[slot];
				const product = allItems.find((p) => p.id === saved?.productId) || allItems[Number(slot)] || allItems[0];
				document.getElementById('featured-product').value = product?.id || '';
				if (!product) {
					fillInstallationForm(null);
					return;
				}
				const defaults = defaultInstallationByCategory(product.category);
				fillInstallationForm({
					baseRegion: 'Los Lagos',
					geographicSurcharge: '0',
					...defaults,
					...saved,
				});
			}

			function loadSelectedProductDefaults() {
				const product = allItems.find((p) => p.id === document.getElementById('featured-product').value);
				if (!product) {
					updateInstallationPreview();
					return;
				}
				const config = currentInstallationFormConfig();
				if (!config.baseInstallationPrice && !config.additionalUnitSurcharge) {
					fillInstallationForm({
						baseRegion: document.getElementById('base-region').value || 'Los Lagos',
						geographicSurcharge: document.getElementById('geo-surcharge').value || '0',
						...defaultInstallationByCategory(product.category),
					});
				}
				updateInstallationPreview();
			}

			function updateInstallationPreview() {
				const preview = document.getElementById('install-preview');
				const slot = document.getElementById('featured-slot').value;
				const productId = document.getElementById('featured-product').value;
				if (slot === '' || !productId) {
					preview.innerHTML = '<div class="sm:col-span-3 text-gray-500">Selecciona un cuadro de la Home y un producto del catálogo para simular el cálculo.</div>';
					return;
				}

				const config = currentInstallationFormConfig();
				const quantity = Number(document.getElementById('install-quantity').value) || 1;
				const geographicSurcharge = resolveGeographicSurcharge(config.geographicSurcharge, config.baseInstallationPrice);
				const installationTotal = calculateInstallationTotal(config, quantity);
				const productTotal = config.productPrice * quantity;

				preview.innerHTML = [
					['Producto × cantidad', clp(productTotal)],
					['Instalación calculada', clp(installationTotal)],
					['Total referencial', clp(productTotal + installationTotal)],
				].map(([label, value]) => `<div class="rounded-xl bg-white p-4 border border-amber-200"><p class="text-xs font-bold uppercase tracking-wide text-gray-500">${label}</p><p class="mt-1 text-xl font-extrabold text-gray-950">${value}</p></div>`).join('')
					+ `<div class="sm:col-span-3 text-xs text-gray-600">Detalle: ${clp(config.baseInstallationPrice)} + (${quantity - 1} × ${clp(config.additionalUnitSurcharge)}) + ${clp(geographicSurcharge)} de recargo geográfico.</div>`;
			}

			function renderInstallationConfigs() {
				const list = document.getElementById('installation-config-list');
				const empty = document.getElementById('installation-empty');
				const configs = readInstallationConfigs();
				const values = Array.from({ length: HOME_SLOT_COUNT }, (_, slot) => configs[String(slot)]).filter(Boolean);
				empty.classList.toggle('hidden', values.length > 0);
				list.innerHTML = values.map((config) => {
					const totalOne = calculateInstallationTotal(config, 1, config.geographicSurcharge);
					return `<article class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
						<p class="text-xs font-extrabold uppercase tracking-wide text-accent-main">${config.slotLabel || 'Cuadro Home'}</p>
						<h4 class="mt-1 text-sm font-extrabold text-gray-900">${config.productBrand || ''} ${config.productName || ''}</h4>
						<p class="mt-2 text-xs text-gray-500">Región base: ${config.baseRegion || '—'}</p>
						<p class="mt-3 text-lg font-extrabold text-gray-950">${clp(totalOne)}</p>
						<p class="text-xs text-gray-500">Instalación para 1 unidad, antes de cantidad adicional.</p>
						<button type="button" class="edit-install-config mt-3 text-sm font-bold text-accent-main hover:underline" data-slot="${config.slot}">Editar configuración</button>
					</article>`;
				}).join('');

				list.querySelectorAll('.edit-install-config').forEach((btn) => {
					btn.onclick = () => {
						document.getElementById('featured-slot').value = btn.dataset.slot;
						loadInstallationSlot(btn.dataset.slot);
					};
				});
			}

			async function initInstallationManager(items) {
				const slotSelect = document.getElementById('featured-slot');
				const productSelect = document.getElementById('featured-product');
				const regionSelect = document.getElementById('base-region');

				slotSelect.innerHTML = '<option value="">Selecciona un cuadro de la Home</option>' + Array.from({ length: HOME_SLOT_COUNT }, (_, index) =>
					`<option value="${index}">Cuadro ${index + 1}</option>`
				).join('');
				productSelect.innerHTML = '<option value="">Selecciona producto del catálogo</option>' + items.map((p) =>
					`<option value="${p.id}">${p.brand} · ${p.name}</option>`
				).join('');
				regionSelect.innerHTML = '<option value="">Selecciona región base</option>' + chileRegions.map((region) =>
					`<option value="${region}">${region}</option>`
				).join('');

				if (!regionSelect.value) regionSelect.value = 'Los Lagos';
				await loadRemoteHomeFeatured();
				renderInstallationConfigs();
				updateInstallationPreview();
			}

			function renderPedidos() {
				const list = document.getElementById('pedidos-list');
				const empty = document.getElementById('pedidos-empty');
				const visible = filteredOrders();
				const nuevos = allOrders.filter((o) => o.status === 'nuevo').length;
				const badge = document.getElementById('badge-nuevos');
				if (nuevos > 0) { badge.textContent = nuevos; badge.classList.remove('hidden'); }
				else badge.classList.add('hidden');

				document.getElementById('pedidos-count').textContent =
					pedidosFilter ? `${visible.length} de ${allOrders.length} pedido(s)` : `${allOrders.length} pedido(s)`;

				if (!visible.length) {
					list.innerHTML = '';
					empty.classList.remove('hidden');
					empty.textContent = pedidosFilter ? 'No hay pedidos con ese estado.' : 'Aún no hay pedidos registrados.';
					return;
				}
				empty.classList.add('hidden');

				list.innerHTML = visible.map((o) => {
					const itemsHtml = (o.items || []).map((i) =>
						`<li class="text-sm text-gray-600">${i.title} × ${i.quantity} — ${clp(i.unit_price * i.quantity)}</li>`
					).join('');
					const statusOpts = Object.entries(orderStatuses).map(([k, label]) =>
						`<option value="${k}" ${o.status === k ? 'selected' : ''}>${label}</option>`
					).join('');
					const addr = [o.delivery?.address, o.delivery?.reference, o.delivery?.comuna, o.delivery?.region].filter(Boolean).join(', ');

					return `<article class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" data-order-id="${o.id}">
						<div class="flex flex-wrap items-start justify-between gap-3 mb-3">
							<div>
								<p class="text-xs text-gray-400">Pedido #${o.id} · ${formatDate(o.createdAt)}</p>
								<p class="text-lg font-bold text-gray-900">${clp(o.total)}</p>
								<span class="inline-block mt-1 text-xs font-semibold px-2 py-0.5 rounded status-${o.status}">${orderStatuses[o.status] || o.status}</span>
							</div>
							<a href="${o.mpLink}" target="_blank" rel="noopener" class="text-sm font-semibold text-accent-main border border-accent-main px-3 py-1.5 rounded-md hover:bg-accent-soft">Verificar en Mercado Pago ↗</a>
						</div>
						<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 text-sm">
							<div>
								<p class="font-semibold text-gray-700 mb-1">Cliente</p>
								<p>${o.customer?.name || '—'}</p>
								<p class="text-gray-500">${o.customer?.email || ''}</p>
								<p class="text-gray-500">${o.customer?.phone || ''}</p>
							</div>
							<div>
								<p class="font-semibold text-gray-700 mb-1">Despacho</p>
								<p class="text-gray-600">${addr || '—'}</p>
								${o.shippingCost ? `<p class="text-gray-500 mt-1">Envío: ${clp(o.shippingCost)}</p>` : ''}
							</div>
						</div>
						<ul class="mb-4 pl-4 list-disc">${itemsHtml}</ul>
						<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-gray-100">
							<label class="text-xs text-gray-500">Estado
								<select class="order-status mt-1 w-full border border-gray-300 rounded-md p-2 text-sm" data-id="${o.id}">${statusOpts}</select>
							</label>
							<label class="text-xs text-gray-500">Seguimiento / guía
								<input type="text" class="order-tracking mt-1 w-full border border-gray-300 rounded-md p-2 text-sm" data-id="${o.id}" value="${o.tracking || ''}" placeholder="Ej. Starken #123" />
							</label>
							<label class="text-xs text-gray-500 sm:col-span-1">Notas internas
								<input type="text" class="order-notes mt-1 w-full border border-gray-300 rounded-md p-2 text-sm" data-id="${o.id}" value="${o.adminNotes || ''}" placeholder="Observaciones" />
							</label>
						</div>
						<button type="button" class="order-save mt-3 text-sm font-semibold text-white bg-accent-main hover:bg-accent-hover px-4 py-2 rounded-md" data-id="${o.id}">Guardar cambios</button>
						<p class="order-msg mt-2 text-xs hidden" data-id="${o.id}"></p>
					</article>`;
				}).join('');

				list.querySelectorAll('.order-save').forEach((btn) => {
					btn.onclick = async () => {
						const id = btn.dataset.id;
						const card = btn.closest('[data-order-id]');
						const msg = list.querySelector(`.order-msg[data-id="${id}"]`);
						btn.disabled = true;
						try {
							const data = await apiPedidos('update', {
								id,
								status: card.querySelector('.order-status').value,
								tracking: card.querySelector('.order-tracking').value,
								adminNotes: card.querySelector('.order-notes').value,
							});
							const idx = allOrders.findIndex((x) => x.id === id);
							if (idx >= 0) allOrders[idx] = data.order;
							msg.textContent = 'Guardado ✓';
							msg.className = 'order-msg mt-2 text-xs text-green-600';
							msg.classList.remove('hidden');
							renderPedidos();
						} catch (e) {
							msg.textContent = e.message;
							msg.className = 'order-msg mt-2 text-xs text-red-500';
							msg.classList.remove('hidden');
						} finally {
							btn.disabled = false;
						}
					};
				});
			}

			async function loadPedidos() {
				try {
					const data = await apiPedidos('list');
					allOrders = data.orders || [];
					orderStatuses = data.statuses || {};
					renderPedidos();
				} catch (e) {
					const msg = e.message || 'Error desconocido';
					const help = msg.includes('ADMIN_PASSWORD')
						? `<p class="mt-3 text-gray-600 text-sm leading-relaxed">
							En <strong>Netlify → Site configuration → Environment variables</strong> agrega
							<code class="bg-gray-100 px-1 rounded">ADMIN_PASSWORD</code> con la <strong>misma clave</strong>
							que usas para entrar a este panel. Luego haz un nuevo deploy (o espera 1 min) y pulsa Actualizar.
						</p>`
						: '';
					document.getElementById('pedidos-list').innerHTML =
						`<div class="text-red-600 text-sm p-4 bg-white border border-red-200 rounded-xl"><p class="font-semibold">${msg}</p>${help}</div>`;
				}
			}

			document.getElementById('login-form').addEventListener('submit', async (e) => {
				e.preventDefault();
				const err = document.getElementById('error');
				err.classList.add('hidden');
				const pwd = document.getElementById('password').value;
				try {
					const payload = await decryptData(pwd);
					adminPassword = pwd;
					document.getElementById('login').classList.add('hidden');
					document.getElementById('panel').classList.remove('hidden');
					renderProducts(payload);
					loadQualityReview();
					loadPedidos();
					if (location.hash === '#cotizaciones') {
						setTimeout(() => document.getElementById('cotizaciones')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
					}
				} catch (_) {
					err.classList.remove('hidden');
				}
			});

			document.getElementById('logout').addEventListener('click', () => location.reload());
			document.getElementById('tab-productos').onclick = () => showTab('productos');
			document.getElementById('tab-instalaciones').onclick = () => showTab('instalaciones');
			document.getElementById('tab-pedidos').onclick = () => showTab('pedidos');
			document.getElementById('refresh-quality-review').onclick = () => loadQualityReview();
			document.getElementById('refresh-pedidos').onclick = () => loadPedidos();
			document.getElementById('pedidos-filter').onchange = (e) => {
				pedidosFilter = e.target.value;
				renderPedidos();
			};
			document.getElementById('export-pedidos').onclick = () => exportPedidosCsv();
			document.getElementById('save-quote-config').onclick = () => saveQuoteConfig();
			document.getElementById('add-catalog-quote-item').onclick = () => addCatalogQuoteItem();
			document.getElementById('add-manual-quote-item').onclick = () => addManualQuoteItem();
			document.getElementById('clear-quote-items').onclick = () => {
				quoteItems = [];
				currentQuote = null;
				document.getElementById('quote-saved-link').innerHTML = '';
				renderQuoteItems();
			};
			document.getElementById('save-quote').onclick = () => saveQuote();
			document.getElementById('send-quote').onclick = () => sendQuote();
			document.getElementById('refresh-quotes').onclick = () => loadQuotesHistory();
			['quote-catalog-price', 'quote-manual-price'].forEach((id) => {
				document.getElementById(id).addEventListener('blur', (event) => {
					const value = moneyNumber(event.target.value);
					if (value) event.target.value = clp(value);
				});
			});
			document.getElementById('quote-client-email').addEventListener('input', (event) => {
				document.getElementById('quote-email-to').value = event.target.value;
			});
			document.getElementById('featured-slot').onchange = (e) => loadInstallationSlot(e.target.value);
			document.getElementById('featured-product').onchange = () => loadSelectedProductDefaults();
			['install-base-price', 'additional-unit-price'].forEach((id) => {
				document.getElementById(id).addEventListener('input', updateInstallationPreview);
				document.getElementById(id).addEventListener('blur', (e) => {
					const value = moneyNumber(e.target.value);
					if (value) e.target.value = clp(value);
					updateInstallationPreview();
				});
			});
			['base-region', 'geo-surcharge', 'install-quantity'].forEach((id) => {
				document.getElementById(id).addEventListener('change', updateInstallationPreview);
			});
			document.getElementById('reset-installation-form').onclick = () => {
				document.getElementById('installation-form').reset();
				document.getElementById('base-region').value = 'Los Lagos';
				updateInstallationPreview();
			};
			document.getElementById('installation-form').addEventListener('submit', async (e) => {
				e.preventDefault();
				const config = currentInstallationFormConfig();
				const msg = document.getElementById('installation-msg');
				if (config.slot === '' || !config.productId) {
					msg.textContent = 'Selecciona un cuadro de la Home y un producto antes de guardar.';
					msg.className = 'text-sm font-semibold text-red-600';
					msg.classList.remove('hidden');
					return;
				}
				const configs = readInstallationConfigs();
				configs[config.slot] = config;
				try {
					writeInstallationConfigs(configs);
					const slots = Array.from({ length: HOME_SLOT_COUNT }, (_, slot) => configs[String(slot)]).filter(Boolean).map((item) => ({
						slot: Number(item.slot),
						product: {
							...item.product,
							showInstallation: Number(item.baseInstallationPrice) > 0,
							installation: {
								salePrice: item.baseInstallationPrice,
								baseInstallationPrice: item.baseInstallationPrice,
								additionalUnitSurcharge: item.additionalUnitSurcharge,
								baseRegion: item.baseRegion,
								geographicSurcharge: item.geographicSurcharge,
							},
						},
						installation: {
							salePrice: item.baseInstallationPrice,
							baseInstallationPrice: item.baseInstallationPrice,
							additionalUnitSurcharge: item.additionalUnitSurcharge,
							baseRegion: item.baseRegion,
							geographicSurcharge: item.geographicSurcharge,
						},
					}));
					await apiHomeFeatured('POST', { slots });
					renderInstallationConfigs();
					updateInstallationPreview();
					msg.textContent = 'Configuración publicada en Home y destacados.';
					msg.className = 'text-sm font-semibold text-green-700';
					msg.classList.remove('hidden');
				} catch (err) {
					msg.textContent = err.message || 'No se pudo guardar la configuración.';
					msg.className = 'text-sm font-semibold text-red-600';
					msg.classList.remove('hidden');
				}
			});
			document.getElementById('admin-search').addEventListener('input', (e) => {
				const q = e.target.value.trim().toLowerCase();
				const filtered = !q ? allItems : allItems.filter((p) =>
					[p.sku, p.name, p.brand, p.store, catLabel[p.category]].filter(Boolean).some((v) => String(v).toLowerCase().includes(q))
				);
				renderRows(filtered);
			});
		</script>
	</body>
</html>
