// API de pedidos para el panel /admin (protegida con ADMIN_PASSWORD).

import { listOrders, updateOrder, ORDER_STATUSES } from './lib/pedidos.mjs';

const json = (statusCode, obj) => ({
	statusCode,
	headers: { 'Content-Type': 'application/json' },
	body: JSON.stringify(obj),
});

function checkAuth(body) {
	const expected = process.env.ADMIN_PASSWORD;
	if (!expected) return 'Falta ADMIN_PASSWORD en Netlify.';
	if (!body?.password || body.password !== expected) return 'Clave incorrecta.';
	return null;
}

export const handler = async (event) => {
	if (event.httpMethod !== 'POST') {
		return json(405, { error: 'Método no permitido' });
	}

	let body;
	try {
		body = JSON.parse(event.body || '{}');
	} catch {
		return json(400, { error: 'JSON inválido' });
	}

	const authErr = checkAuth(body);
	if (authErr) return json(401, { error: authErr });

	try {
		if (body.action === 'list') {
			const orders = await listOrders();
			return json(200, { ok: true, orders, statuses: ORDER_STATUSES });
		}

		if (body.action === 'update') {
			const id = String(body.id || '').trim();
			if (!id) return json(400, { error: 'Falta id del pedido' });

			const patch = {};
			if (body.status !== undefined) {
				if (!ORDER_STATUSES[body.status]) return json(400, { error: 'Estado inválido' });
				patch.status = body.status;
			}
			if (body.adminNotes !== undefined) patch.adminNotes = String(body.adminNotes).slice(0, 500);
			if (body.tracking !== undefined) patch.tracking = String(body.tracking).slice(0, 120);

			const order = await updateOrder(id, patch);
			if (!order) return json(404, { error: 'Pedido no encontrado' });
			return json(200, { ok: true, order });
		}

		return json(400, { error: 'Acción no válida' });
	} catch (e) {
		console.error('pedidos-api error:', e);
		return json(500, { error: String(e.message || e) });
	}
};
