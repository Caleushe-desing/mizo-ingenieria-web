// Función serverless: cotiza el costo de envío con Starken (despacho a domicilio)
// desde Santiago (Región Metropolitana) hacia la región de destino del cliente.
//
// IMPORTANTE: Starken solo entrega cotización en tiempo real con una cuenta
// COMERCIAL (API Key + Código de Cliente, que se solicitan a un ejecutivo).
// Mientras no haya credenciales, usamos un TARIFARIO por región basado en los
// precios de despacho a domicilio de Starken desde la RM, escalado por peso.
//
// Cuando tengas la cuenta comercial, define en Netlify las variables:
//   STARKEN_API_KEY, STARKEN_CLIENT_CODE  (y opcional STARKEN_COST_CENTER)
// y reemplaza `cotizarTarifario` por la llamada real a la API de Starken.

const json = (statusCode, obj) => ({
	statusCode,
	headers: { 'Content-Type': 'application/json' },
	body: JSON.stringify(obj),
});

// Zonas de despacho (desde Santiago). base = hasta `baseKg` kilos; sobre eso se
// cobra `perKg` por kilo adicional. eta = días hábiles estimados.
const ZONES = {
	rm: { base: 3490, baseKg: 5, perKg: 390, eta: '1 a 2 días hábiles' },
	centro: { base: 5490, baseKg: 5, perKg: 690, eta: '2 a 4 días hábiles' },
	sur: { base: 6990, baseKg: 5, perKg: 890, eta: '3 a 5 días hábiles' },
	norte: { base: 7990, baseKg: 5, perKg: 1090, eta: '3 a 6 días hábiles' },
	norte_extremo: { base: 9490, baseKg: 5, perKg: 1390, eta: '4 a 7 días hábiles' },
	austral: { base: 13900, baseKg: 5, perKg: 2200, eta: '5 a 9 días hábiles' },
};

// Regiones de Chile -> zona de tarifa.
const REGIONS = {
	'arica-parinacota': { name: 'Arica y Parinacota', zone: 'norte_extremo' },
	tarapaca: { name: 'Tarapacá', zone: 'norte_extremo' },
	antofagasta: { name: 'Antofagasta', zone: 'norte' },
	atacama: { name: 'Atacama', zone: 'norte' },
	coquimbo: { name: 'Coquimbo', zone: 'centro' },
	valparaiso: { name: 'Valparaíso', zone: 'centro' },
	metropolitana: { name: 'Metropolitana de Santiago', zone: 'rm' },
	ohiggins: { name: "O'Higgins", zone: 'centro' },
	maule: { name: 'Maule', zone: 'centro' },
	nuble: { name: 'Ñuble', zone: 'centro' },
	biobio: { name: 'Biobío', zone: 'centro' },
	araucania: { name: 'La Araucanía', zone: 'sur' },
	'los-rios': { name: 'Los Ríos', zone: 'sur' },
	'los-lagos': { name: 'Los Lagos', zone: 'sur' },
	aysen: { name: 'Aysén', zone: 'austral' },
	magallanes: { name: 'Magallanes', zone: 'austral' },
};

const round100 = (n) => Math.round(n / 100) * 100;

function cotizarTarifario(regionId, weightKg) {
	const region = REGIONS[regionId];
	if (!region) return { error: 'Región no válida.' };
	const z = ZONES[region.zone];

	// Peso facturable: peso real + 1 kg de embalaje, mínimo 1 kg.
	const billable = Math.max(1, Math.ceil((Number(weightKg) || 0) + 1));
	const extra = Math.max(0, billable - z.baseKg);
	const cost = round100(z.base + extra * z.perKg);

	return {
		ok: true,
		regionId,
		regionName: region.name,
		zone: region.zone,
		weightKg: billable,
		cost,
		etaText: z.eta,
		courier: 'Starken',
		mode: 'domicilio',
		estimate: true,
	};
}

export const handler = async (event) => {
	if (event.httpMethod !== 'POST') {
		return json(405, { error: 'Método no permitido' });
	}

	let body;
	try {
		body = JSON.parse(event.body || '{}');
	} catch {
		return json(400, { error: 'Cuerpo JSON inválido.' });
	}

	const regionId = String(body.region || '').trim();
	const weightKg = Number(body.weightKg) || 0;

	if (!REGIONS[regionId]) {
		return json(400, { error: 'Selecciona una región válida.' });
	}

	const result = cotizarTarifario(regionId, weightKg);
	if (result.error) return json(400, result);
	return json(200, result);
};
