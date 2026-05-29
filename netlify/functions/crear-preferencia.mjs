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

	// Despacho (Starken): se agrega como un ítem más de la preferencia.
	const shipCost = Math.round(Number(body.shipping?.cost) || 0);
	const d = body.delivery || {};
	const regionName = String(d.regionName || body.shipping?.region || '').slice(0, 80);
	const comuna = String(d.comuna || '').slice(0, 80);
	if (shipCost > 0) {
		const dest = [comuna, regionName].filter(Boolean).join(', ');
		items.push({
			id: 'envio',
			title: dest ? `Despacho Starken — ${dest}` : 'Despacho Starken',
			quantity: 1,
			unit_price: shipCost,
			currency_id: 'CLP',
		});
	}

	const nameParts = String(d.name || '').trim().split(/\s+/);
	const payer = {};
	if (nameParts.length) {
		payer.name = nameParts[0].slice(0, 80);
		if (nameParts.length > 1) payer.surname = nameParts.slice(1).join(' ').slice(0, 80);
	}
	if (d.email) payer.email = String(d.email).slice(0, 254);
	if (d.phone) {
		const digits = String(d.phone).replace(/\D/g, '');
		if (digits.length >= 8) {
			payer.phone = { area_code: digits.slice(0, 2), number: digits.slice(2) };
		}
	}
	if (d.address || d.comuna) {
		payer.address = {
			street_name: String(d.address || '').slice(0, 200),
			zip_code: String(d.comuna || '').slice(0, 20),
		};
	}

	const metadata = {};
	if (d.name) metadata.delivery_name = String(d.name).slice(0, 120);
	if (d.phone) metadata.delivery_phone = String(d.phone).slice(0, 40);
	if (d.email) metadata.delivery_email = String(d.email).slice(0, 120);
	if (regionName) metadata.delivery_region = regionName;
	if (comuna) metadata.delivery_comuna = comuna;
	if (d.address) metadata.delivery_address = String(d.address).slice(0, 200);
	if (d.reference) metadata.delivery_reference = String(d.reference).slice(0, 120);
	metadata.items_summary = rawItems
		.map((i) => `${String(i.title || 'Producto').slice(0, 60)} ×${i.quantity || 1}`)
		.join(' | ')
		.slice(0, 500);

	const site =
		process.env.URL ||
		process.env.DEPLOY_PRIME_URL ||
		(event.headers && `https://${event.headers.host}`) ||
		'';

	const preference = {
		items,
		...(Object.keys(payer).length ? { payer } : {}),
		...(Object.keys(metadata).length ? { metadata } : {}),
		...(site ? { notification_url: `${site}/.netlify/functions/mp-notificacion` } : {}),
		external_reference: `mizo-${Date.now()}`,
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
