// products.ts
// Catálogo público de productos. Los datos viven en products.json (incluye el
// campo privado "source" con la tienda/URL de referencia del precio).
//
// IMPORTANTE: el precio de venta publicado aplica un recargo del 20% sobre el
// "basePrice" (valor de referencia del mercado chileno). El campo "source" NO se
// expone en el sitio: se elimina aquí antes de exportar a los componentes.

import rawProducts from './products.json';

export const MARKUP = 0.2; // 20%

export type Category = 'sonido' | 'video';

export interface ProductSource {
	store: string;
	url: string;
	image: string;
	capturedAt: string;
}

export interface RawProduct {
	id: string;
	name: string;
	brand: string;
	category: Category;
	description: string;
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
	{ id: 'sonido', label: 'Audio', icon: '🔊', blurb: 'Parlantes, amplificadores y sistemas de audio profesional.' },
	{ id: 'video', label: 'Video', icon: '📽️', blurb: 'Proyectores, cámaras y barras de sonido.' },
];

// Exportación pública: eliminamos `source` para que nunca llegue al HTML.
export const products: Product[] = (rawProducts as RawProduct[]).map(({ source, ...rest }) => rest);

// Acceso rápido por id (para la página de detalle y el carrito).
export const productsById: Record<string, Product> = Object.fromEntries(
	products.map((p) => [p.id, p]),
);

/** Selección destacada: equilibra audio y video, robusto ante cambios del catálogo. */
export function featuredProducts(limit = 4): Product[] {
	const audio = products.filter((p) => p.category === 'sonido');
	const video = products.filter((p) => p.category === 'video');
	const mix: Product[] = [];
	for (let i = 0; mix.length < limit && (i < audio.length || i < video.length); i++) {
		if (audio[i]) mix.push(audio[i]);
		if (video[i] && mix.length < limit) mix.push(video[i]);
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
