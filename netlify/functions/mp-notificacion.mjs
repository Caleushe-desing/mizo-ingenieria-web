// Webhook de Mercado Pago: recibe avisos de pago y envía correo a ventas/admin.
// Se registra automáticamente vía notification_url al crear cada preferencia.
//
// También acepta GET ?topic=payment&id=123 (formato IPN clásico de MP).

import { fetchMpPayment, paymentItems, sendPurchaseEmail } from './lib/email-compra.mjs';

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

		// Solo notificamos compras acreditadas.
		if (payment.status !== 'approved') {
			console.log(`Pago ${paymentId}: status=${payment.status}, sin correo.`);
			return ok('Ignorado');
		}

		const result = await sendPurchaseEmail({
			payment,
			items: paymentItems(payment),
		});

		if (!result.ok) {
			console.error(`Pago ${paymentId}: fallo correo —`, result.error);
			return err(500, result.error);
		}

		console.log(`Pago ${paymentId}: correo enviado (${result.id}).`);
		return ok('Notificado');
	} catch (e) {
		console.error('mp-notificacion error:', e);
		return err(500, String(e.message || e));
	}
};
