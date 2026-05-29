// Webhook Mercado Pago: verifica el pago y registra el pedido en el admin.

import { fetchMpPayment, registerOrderFromPayment } from './lib/pedidos.mjs';

const ok = (body = 'OK') => ({ statusCode: 200, body });
const err = (code, body) => ({ statusCode: code, body });

function extractPaymentId(event) {
	if (event.httpMethod === 'GET') {
		const q = event.queryStringParameters || {};
		if (q.topic === 'payment' && q.id) return String(q.id);
		if (q.type === 'payment' && q['data.id']) return String(q['data.id']);
		return null;
	}
	if (event.httpMethod !== 'POST') return null;
	try {
		const body = JSON.parse(event.body || '{}');
		if (body.type === 'payment' && body.data?.id) return String(body.data.id);
		if (body.topic === 'payment' && body.id) return String(body.id);
	} catch {
		return null;
	}
	return null;
}

export const handler = async (event) => {
	const paymentId = extractPaymentId(event);
	if (!paymentId) return ok('Sin ID de pago');

	try {
		const payment = await fetchMpPayment(paymentId);
		const result = await registerOrderFromPayment(payment);

		if (!result.ok) {
			console.log(`Pago ${paymentId}: ${result.message || result.status}`);
			return ok('Ignorado');
		}

		console.log(`Pago ${paymentId}: pedido registrado${result.duplicate ? ' (duplicado)' : ''}.`);
		return ok('Registrado');
	} catch (e) {
		console.error('mp-notificacion error:', e);
		return err(500, String(e.message || e));
	}
};
