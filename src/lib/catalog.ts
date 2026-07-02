import catalogRaw from '../../public/catalogo-productos.json';

export interface CatalogProduct {
	id: string;
	sku: string;
	name: string;
	brand: string;
	category: string;
	categoryLabel: string;
	description: string;
	descriptionLong: string;
	engineeringCategory: string;
	image: string;
	sourceImage: string;
}

const CATEGORY_LABELS: Record<string, string> = {
	'captacion-mezcla': 'Sistemas de Captación y Mezcla',
	'rack-potencia': 'Procesamiento en Rack y Potencia',
	'proyeccion-video': 'Sistemas de Proyección y Video',
	sonido: 'Audio Profesional',
	proyector: 'Proyectores',
	camara: 'Cámaras',
	microfonos: 'Micrófonos',
	'consolas-mixers': 'Consolas y Mixers',
	parlantes: 'Parlantes',
	'cajas-acusticas': 'Cajas Acústicas',
	amplificadores: 'Amplificadores',
	procesadores: 'Procesadores',
	'matrices-video': 'Matrices de Video',
};

function categoryLabel(category: string): string {
	const key = String(category || '').trim();
	if (!key) return 'Producto audiovisual';
	return CATEGORY_LABELS[key] ?? key.replace(/[-_]/g, ' ').replace(/\b\w/g, (ch) => ch.toUpperCase());
}

function normalizeProduct(raw: Record<string, unknown>): CatalogProduct | null {
	const id = String(raw.id || '').trim();
	const name = String(raw.name || '').trim();
	if (!id || !name) return null;

	const source = (raw.source && typeof raw.source === 'object' ? raw.source : {}) as Record<string, unknown>;
	const image = String(raw.image || '').trim();
	const sourceImage = String(source.image || raw.source_image || raw.sourceImage || '').trim();

	return {
		id,
		sku: String(raw.sku || '').trim(),
		name,
		brand: String(raw.brand || '').trim(),
		category: String(raw.category || '').trim(),
		categoryLabel: String(raw.categoryLabel || raw.category_label || categoryLabel(String(raw.category || ''))),
		description: String(raw.description || '').trim(),
		descriptionLong: String(raw.descriptionLong || raw.description_long || raw.description || '').trim(),
		engineeringCategory: String(raw.engineeringCategory || raw.engineering_category || '').trim(),
		image: image || sourceImage || '/mizo-logo.png',
		sourceImage: sourceImage || image || '/mizo-logo.png',
	};
}

export function loadCatalogProducts(): CatalogProduct[] {
	if (!Array.isArray(catalogRaw)) return [];
	return catalogRaw
		.map((item) => normalizeProduct(item as Record<string, unknown>))
		.filter((item): item is CatalogProduct => Boolean(item));
}

export function getProductById(id: string): CatalogProduct | undefined {
	const needle = String(id || '').trim().toLowerCase();
	return loadCatalogProducts().find(
		(product) => product.id.toLowerCase() === needle || product.sku.toLowerCase() === needle,
	);
}

export function groupCategories(products: CatalogProduct[]): Record<string, string> {
	const categories: Record<string, string> = {};
	for (const product of products) {
		if (product.category) {
			categories[product.category] = product.categoryLabel || categoryLabel(product.category);
		}
	}
	return Object.fromEntries(
		Object.entries(categories).sort((a, b) => a[1].localeCompare(b[1], 'es')),
	);
}

export function getRelatedProducts(product: CatalogProduct, catalog = loadCatalogProducts(), limit = 4): CatalogProduct[] {
	const sameCategory = catalog.filter(
		(item) => item.id !== product.id && item.category && item.category === product.category,
	);
	const sameBrand = catalog.filter(
		(item) =>
			item.id !== product.id &&
			item.brand &&
			product.brand &&
			item.brand.toLowerCase() === product.brand.toLowerCase() &&
			!sameCategory.some((entry) => entry.id === item.id),
	);
	const pool = [...sameCategory, ...sameBrand];
	const unique = new Map<string, CatalogProduct>();
	for (const item of pool) {
		if (!unique.has(item.id)) unique.set(item.id, item);
	}
	return Array.from(unique.values()).slice(0, limit);
}
