// Registra un pedido verificado en Mercado Pago (llamado desde /gracias tras el pago).

import { fetchMpPayment, registerOrderFromPayment } from './lib/pedidos.mjs';

const json = (statusCode, obj) => ({
	statusCode,
	headers: { 'Content-Type': 'application/json' },
	body: JSON.stringify(obj),
});

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

	const paymentId = String(body.payment_id || body.collection_id || '').trim();
	if (!paymentId) return json(400, { error: 'Falta payment_id' });

	try {
		const payment = await fetchMpPayment(paymentId);
		const result = await registerOrderFromPayment(payment);
		if (!result.ok) return json(200, result);
		return json(200, { ok: true, duplicate: result.duplicate, orderId: result.order.id });
	} catch (e) {
		console.error('confirmar-compra error:', e);
		return json(500, { error: String(e.message || e) });
	}
};
