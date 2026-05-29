// Pedidos: verificación Mercado Pago + almacenamiento en Netlify Blobs (sin servicios externos).

import { getStore } from '@netlify/blobs';

const STORE = 'mizo-pedidos';

export async function fetchMpPayment(paymentId) {
	const token = process.env.MP_ACCESS_TOKEN;
	if (!token) throw new Error('Falta MP_ACCESS_TOKEN');

	const res = await fetch(`https://api.mercadopago.com/v1/payments/${paymentId}`, {
		headers: { Authorization: `Bearer ${token}` },
	});
	const data = await res.json();
	if (!res.ok) throw new Error(data.message || `MP HTTP ${res.status}`);
	return data;
}

export function paymentItems(payment) {
	const raw = payment.additional_info?.items;
	if (Array.isArray(raw) && raw.length) {
		return raw.map((i) => ({
			title: i.title || i.description || 'Producto',
			quantity: Number(i.quantity) || 1,
			unit_price: Math.round(Number(i.unit_price) || 0),
		}));
	}
	const md = payment.metadata || {};
	if (md.items_summary) {
		return [{ title: md.items_summary, quantity: 1, unit_price: payment.transaction_amount || 0 }];
	}
	return [];
}

export function buildOrder(payment) {
	const md = payment.metadata || {};
	const payer = payment.payer || {};
	const items = paymentItems(payment);
	const productTotal = items
		.filter((i) => !/despacho|env[ií]o|starken/i.test(i.title))
		.reduce((s, i) => s + i.unit_price * i.quantity, 0);
	const shippingItem = items.find((i) => /despacho|env[ií]o|starken/i.test(i.title));
	const now = new Date().toISOString();

	return {
		id: String(payment.id),
		mpStatus: payment.status,
		status: 'nuevo',
		createdAt: payment.date_approved || payment.date_created || now,
		updatedAt: now,
		total: Math.round(payment.transaction_amount || 0),
		productTotal,
		shippingCost: shippingItem ? shippingItem.unit_price : 0,
		currency: payment.currency_id || 'CLP',
		customer: {
			name: md.delivery_name || [payer.first_name, payer.last_name].filter(Boolean).join(' ') || '—',
			email: md.delivery_email || payer.email || '',
			phone: md.delivery_phone || '',
		},
		delivery: {
			region: md.delivery_region || '',
			comuna: md.delivery_comuna || '',
			address: md.delivery_address || '',
			reference: md.delivery_reference || '',
		},
		items,
		adminNotes: '',
		tracking: '',
		mpLink: `https://www.mercadopago.cl/activities/payments/${payment.id}`,
	};
}

function store() {
	return getStore(STORE);
}

export async function registerOrderFromPayment(payment) {
	if (payment.status !== 'approved') {
		return { ok: false, status: payment.status, message: 'Pago no aprobado' };
	}

	const order = buildOrder(payment);
	const key = `pedido-${order.id}`;
	const s = store();
	const existing = await s.get(key, { type: 'json' });
	if (existing) return { ok: true, duplicate: true, order: existing };

	await s.setJSON(key, order);
	return { ok: true, duplicate: false, order };
}

export async function listOrders() {
	const s = store();
	const { blobs } = await s.list({ prefix: 'pedido-' });
	const orders = await Promise.all(
		blobs.map(async (b) => s.get(b.key, { type: 'json' }))
	);
	return orders
		.filter(Boolean)
		.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
}

export async function updateOrder(id, patch) {
	const key = `pedido-${id}`;
	const s = store();
	const order = await s.get(key, { type: 'json' });
	if (!order) return null;

	const allowed = ['status', 'adminNotes', 'tracking'];
	const next = { ...order, updatedAt: new Date().toISOString() };
	for (const k of allowed) {
		if (patch[k] !== undefined) next[k] = patch[k];
	}
	await s.setJSON(key, next);
	return next;
}

export const ORDER_STATUSES = {
	nuevo: 'Nuevo — verificar en MP',
	verificado: 'Pago verificado',
	preparando: 'Preparando envío',
	enviado: 'Enviado',
	entregado: 'Entregado',
	cancelado: 'Cancelado',
};
