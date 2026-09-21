<?php
declare(strict_types=1);

namespace MizoCrm;

final class LeadIngest
{
	public static function fromContactForm(string $nombre, string $telefono, string $correo, string $servicio, string $mensaje): void
	{
		if (!Database::ready()) {
			return;
		}

		$clientId = Models\Client::findOrCreate($nombre, $correo, $telefono, 'web', $mensaje);
		$note = 'Consulta desde la web';
		if ($servicio !== '') {
			$note .= ' — ' . $servicio;
		}
		if ($mensaje !== '') {
			$note .= "\n" . $mensaje;
		}
		Models\Activity::log('lead', $note, null, $clientId);
	}
}
