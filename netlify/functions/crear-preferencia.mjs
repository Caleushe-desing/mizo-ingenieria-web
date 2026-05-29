// Función serverless de Netlify: crea una "preferencia" de pago en Mercado Pago
// (Checkout Pro) a partir de los items del carrito y devuelve el init_point para
// redirigir al cliente al pago seguro.
//
// Requiere la variable de entorno MP_ACCESS_TOKEN (Access Token de Mercado Pago)
// configurada en Netlify (Site settings -> Environment variables). NUNCA se expone
// al navegador: vive solo en el servidor.

const json = (statusCode, obj) => ({
	statusCode,
	headers: { 'Content-Type': 'application/json' },
	body: JSON.stringify(obj),
});

export const handler = async (event) => {
	if (event.httpMethod !== 'POST') {
		return json(405, { error: 'Método no permitido' });
	}

	const token = process.env.MP_ACCESS_TOKEN;
	if (!token) {
		return json(500, { error: 'Falta configurar MP_ACCESS_TOKEN en Netlify.' });
	}

	let body;
	try {
		body = JSON.parse(event.body || '{}');
	} catch {
		return json(400, { error: 'Cuerpo JSON inválido.' });
	}

	const rawItems = Array.isArray(body.items) ? body.items : [];
	if (!rawItems.length) {
		return json(400, { error: 'El carrito está vacío.' });
	}

	const items = rawItems.map((i) => ({
		id: String(i.id || ''),
		title: String(i.title || 'Producto').slice(0, 250),
		quantity: Math.max(1, parseInt(i.quantity, 10) || 1),
		unit_price: Math.round(Number(i.unit_price) || 0),
		currency_id: 'CLP',
	}));

	if (items.some((i) => i.unit_price <= 0)) {
		return json(400, { error: 'Precios inválidos en el carrito.' });
	}

	const site =
		process.env.URL ||
		process.env.DEPLOY_PRIME_URL ||
		(event.headers && `https://${event.headers.host}`) ||
		'';

	const preference = {
		items,
		back_urls: {
			success: `${site}/gracias`,
			failure: `${site}/carrito`,
			pending: `${site}/carrito`,
		},
		auto_return: 'approved',
		statement_descriptor: 'MIZO',
		binary_mode: false,
	};

	try {
		const res = await fetch('https://api.mercadopago.com/checkout/preferences', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Authorization: `Bearer ${token}`,
			},
			body: JSON.stringify(preference),
		});
		const data = await res.json();
		if (!res.ok) {
			return json(502, { error: 'Mercado Pago rechazó la solicitud.', detail: data });
		}
		return json(200, { id: data.id, init_point: data.init_point });
	} catch (err) {
		return json(500, { error: 'Error al conectar con Mercado Pago.', detail: String(err) });
	}
};
