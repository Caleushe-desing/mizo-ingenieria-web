// products.ts
// Tipos y helpers compartidos del catálogo.
//
// El catálogo público ya no se renderiza desde JSON estático. La tienda consume
// exclusivamente productos publicados desde MySQL mediante /api-productos-publicados.php.

export const MARKUP = 0.2; // 20%

export type Category = 'sonido' | 'proyector' | 'camara';

export interface ProductSource {
	store: string;
	url: string;
	image: string;
	capturedAt: string;
}

export interface ProductSpec {
	label: string;
	value: string;
}

export interface RawProduct {
	id: string;
	sku: string;
	name: string;
	brand: string;
	category: Category;
	description: string;
	descriptionLong?: string;
	specs?: ProductSpec[];
	weightKg?: number;
	basePrice: number;
	stock?: number | null;
	available?: boolean;
	image: string;
	tag?: string;
	hidden?: boolean;
	source: ProductSource;
}

export type Product = Omit<RawProduct, 'source'> & {
	source: Pick<ProductSource, 'store' | 'url'>;
};

export const categories: { id: Category; label: string; icon: string; blurb: string }[] = [
	{ id: 'sonido', label: 'Parlantes', icon: '🔊', blurb: 'Cajas activas, line array y parlantes portátiles.' },
	{ id: 'proyector', label: 'Proyectores', icon: '📽️', blurb: 'Proyectores para salas, eventos y home cinema.' },
	{ id: 'camara', label: 'Cámaras', icon: '📹', blurb: 'Cámaras de seguridad y videovigilancia WiFi.' },
];

// Catálogo estático intencionalmente vacío: los productos vienen desde MySQL.
export const allProducts: Product[] = [];
export const products: Product[] = [];

export const productsById: Record<string, Product> = Object.fromEntries(
	allProducts.map((p) => [p.id, p]),
);

/** Selección destacada: intercala parlantes, proyectores y cámaras. */
export function featuredProducts(limit = 4): Product[] {
	const buckets = [
		products.filter((p) => p.category === 'sonido'),
		products.filter((p) => p.category === 'proyector'),
		products.filter((p) => p.category === 'camara'),
	];
	const mix: Product[] = [];
	const maxLen = Math.max(...buckets.map((b) => b.length));
	for (let i = 0; i < maxLen && mix.length < limit; i++) {
		for (const b of buckets) {
			if (b[i] && mix.length < limit) mix.push(b[i]);
		}
	}
	return mix.slice(0, limit);
}

/** Precio de venta publicado = precio base + 20%, redondeado a la decena. */
export function sellingPrice(basePrice: number): number {
	return Math.round((basePrice * (1 + MARKUP)) / 10) * 10;
}

/** Formatea un número como precio chileno: $1.234.567 */
export function formatCLP(value: number): string {
	return '$' + value.toLocaleString('es-CL');
}

export function providerKeyFromStore(store?: string): string {
	const normalizedStore = (store ?? '').toLowerCase();

	if (normalizedStore.includes('promusic')) return 'promusic';
	if (normalizedStore.includes('audio')) return 'audiomusica';
	if (normalizedStore.includes('casa')) return 'casaroyal';

	return '';
}
