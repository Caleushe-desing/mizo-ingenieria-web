// Envía correo de notificación de compra vía Resend.
// Variables de entorno en Netlify:
//   RESEND_API_KEY   -> API key de https://resend.com
//   MAIL_FROM        -> Remitente verificado (ej. "Mizo <notificaciones@mizo.cl>")
//   NOTIFY_EMAILS    -> Destinatarios separados por coma (default: ventas@mizo.cl,admin@mizo.cl)

const fmtCLP = (n) =>
	'$' +
	Number(n || 0).toLocaleString('es-CL', { maximumFractionDigits: 0 });

export function notifyRecipients() {
	const raw = process.env.NOTIFY_EMAILS || 'ventas@mizo.cl,admin@mizo.cl';
	return raw
		.split(',')
		.map((e) => e.trim())
		.filter(Boolean);
}

export function buildPurchaseEmail({ payment, items = [] }) {
	const md = payment.metadata || {};
	const amount = payment.transaction_amount || 0;
	const id = payment.id;
	const status = payment.status;
	const payer = payment.payer || {};

	const customerName = md.delivery_name || [payer.first_name, payer.last_name].filter(Boolean).join(' ') || '—';
	const customerEmail = md.delivery_email || payer.email || '—';
	const customerPhone = md.delivery_phone || '—';
	const address = [
		md.delivery_address,
		md.delivery_reference,
		md.delivery_comuna,
		md.delivery_region,
	]
		.filter(Boolean)
		.join(', ');

	const itemLines =
		items.length > 0
			? items.map((i) => `<li>${escapeHtml(i.title)} × ${i.quantity} — ${fmtCLP(i.unit_price * i.quantity)}</li>`).join('')
			: md.items_summary
				? `<li>${escapeHtml(md.items_summary)}</li>`
				: '<li>(ver detalle en Mercado Pago)</li>';

	const html = `
<!DOCTYPE html>
<html lang="es">
<body style="font-family:system-ui,sans-serif;color:#111;line-height:1.5;max-width:560px">
  <h2 style="color:#15616d">Nueva compra en mizo.cl</h2>
  <p>Se registró un pago <strong>${escapeHtml(status)}</strong> en Mercado Pago.</p>
  <table style="width:100%;border-collapse:collapse;margin:16px 0">
    <tr><td style="padding:4px 0;color:#666">ID pago</td><td><strong>${id}</strong></td></tr>
    <tr><td style="padding:4px 0;color:#666">Total</td><td><strong>${fmtCLP(amount)}</strong></td></tr>
    <tr><td style="padding:4px 0;color:#666">Cliente</td><td>${escapeHtml(customerName)}</td></tr>
    <tr><td style="padding:4px 0;color:#666">Correo</td><td>${escapeHtml(customerEmail)}</td></tr>
    <tr><td style="padding:4px 0;color:#666">Teléfono</td><td>${escapeHtml(customerPhone)}</td></tr>
    <tr><td style="padding:4px 0;color:#666">Despacho</td><td>${escapeHtml(address || '—')}</td></tr>
  </table>
  <h3 style="font-size:15px;margin-bottom:8px">Productos</h3>
  <ul style="padding-left:20px;margin:0">${itemLines}</ul>
  <p style="margin-top:24px;font-size:13px;color:#666">
    Revisa el pago en tu panel de Mercado Pago y coordina el despacho con Starken.
  </p>
</body>
</html>`;

	const text = [
		'Nueva compra en mizo.cl',
		`Estado: ${status}`,
		`ID pago: ${id}`,
		`Total: ${fmtCLP(amount)}`,
		`Cliente: ${customerName}`,
		`Correo: ${customerEmail}`,
		`Teléfono: ${customerPhone}`,
		`Despacho: ${address || '—'}`,
		md.items_summary ? `Productos: ${md.items_summary}` : '',
	].filter(Boolean).join('\n');

	return {
		subject: `Nueva compra mizo.cl — ${fmtCLP(amount)} (${customerName})`,
		html,
		text,
	};
}

function escapeHtml(s = '') {
	return String(s)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;');
}

export async function sendPurchaseEmail(payload) {
	const apiKey = process.env.RESEND_API_KEY;
	const to = notifyRecipients();
	if (!apiKey) {
		console.error('Falta RESEND_API_KEY: no se envió el correo de compra.');
		return { ok: false, error: 'Falta RESEND_API_KEY' };
	}
	if (!to.length) {
		return { ok: false, error: 'Sin destinatarios NOTIFY_EMAILS' };
	}

	const from = process.env.MAIL_FROM || 'Mizo <notificaciones@mizo.cl>';
	const { subject, html, text } = buildPurchaseEmail(payload);

	const res = await fetch('https://api.resend.com/emails', {
		method: 'POST',
		headers: {
			Authorization: `Bearer ${apiKey}`,
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({ from, to, subject, html, text }),
	});

	const data = await res.json().catch(() => ({}));
	if (!res.ok) {
		console.error('Resend error:', data);
		return { ok: false, error: data.message || `HTTP ${res.status}` };
	}
	return { ok: true, id: data.id };
}

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
	if (!Array.isArray(raw)) return [];
	return raw.map((i) => ({
		title: i.title || i.description || 'Producto',
		quantity: Number(i.quantity) || 1,
		unit_price: Number(i.unit_price) || 0,
	}));
}
