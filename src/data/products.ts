// products.ts
// Catálogo público de productos. Los datos viven en products.json (incluye el
// campo privado "source" con la tienda/URL de referencia del precio).
//
// IMPORTANTE: el precio de venta publicado aplica un recargo del 20% sobre el
// "basePrice" (valor de referencia del mercado chileno). El campo "source" NO se
// expone en el sitio: se elimina aquí antes de exportar a los componentes.

import rawProducts from './products.json';

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
	source: ProductSource;
}

// Producto público: sin la información de la fuente (source).
export type Product = Omit<RawProduct, 'source'>;

export const categories: { id: Category; label: string; icon: string; blurb: string }[] = [
	{ id: 'sonido', label: 'Parlantes', icon: '🔊', blurb: 'Cajas activas, line array y parlantes portátiles.' },
	{ id: 'proyector', label: 'Proyectores', icon: '📽️', blurb: 'Proyectores para salas, eventos y home cinema.' },
	{ id: 'camara', label: 'Cámaras', icon: '📹', blurb: 'Cámaras de seguridad y videovigilancia WiFi.' },
];

// Exportación pública: eliminamos `source` para que nunca llegue al HTML.
export const products: Product[] = (rawProducts as RawProduct[]).map(({ source, ...rest }) => rest);

// Acceso rápido por id (para la página de detalle y el carrito).
export const productsById: Record<string, Product> = Object.fromEntries(
	products.map((p) => [p.id, p]),
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
