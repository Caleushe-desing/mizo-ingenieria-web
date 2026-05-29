// Respaldo: confirma una compra aprobada y envía el correo (llamado desde /gracias).
// Valida el pago directamente con la API de Mercado Pago.

import { fetchMpPayment, paymentItems, sendPurchaseEmail } from './lib/email-compra.mjs';

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

		if (payment.status !== 'approved') {
			return json(200, { ok: false, status: payment.status, message: 'Pago no aprobado aún' });
		}

		const result = await sendPurchaseEmail({
			payment,
			items: paymentItems(payment),
		});

		if (!result.ok) return json(500, { ok: false, error: result.error });
		return json(200, { ok: true, emailId: result.id });
	} catch (e) {
		console.error('confirmar-compra error:', e);
		return json(500, { error: String(e.message || e) });
	}
};
