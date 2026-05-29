// Función PROGRAMADA de Netlify (Scheduled Function).
// Se ejecuta automáticamente todos los días a las 04:00 UTC (= 00:00 en Chile,
// horario de invierno UTC-4) y dispara un nuevo build del sitio mediante un
// "Build Hook". Cada build vuelve a ejecutar `npm run sync-catalogo`, por lo que
// los precios y el stock quedan actualizados a diario, con el recargo del 20%.
//
// Requiere la variable de entorno BUILD_HOOK_URL (la URL del Build Hook que se
// crea en Netlify: Site settings -> Build & deploy -> Build hooks).
//
// Nota: si Chile pasa a horario de verano (UTC-3), cambia el cron a "0 3 * * *".

export const config = {
	schedule: '0 4 * * *',
};

export const handler = async () => {
	const hook = process.env.BUILD_HOOK_URL;
	if (!hook) {
		console.error('Falta BUILD_HOOK_URL');
		return { statusCode: 500, body: 'Falta configurar BUILD_HOOK_URL.' };
	}
	try {
		const res = await fetch(hook, { method: 'POST' });
		if (!res.ok) throw new Error(`HTTP ${res.status}`);
		console.log('Build de actualización diaria disparado correctamente.');
		return { statusCode: 200, body: 'Build de actualización disparado.' };
	} catch (err) {
		console.error('No se pudo disparar el build:', err);
		return { statusCode: 500, body: String(err) };
	}
};
