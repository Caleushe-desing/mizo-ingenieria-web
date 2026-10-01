<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

final class MarketingTemplate extends Record
{
	protected static function table(): string
	{
		return 'marketing_templates';
	}

	/** @return list<array<string, mixed>> */
	public static function active(): array
	{
		return self::pdo()
			->query('SELECT * FROM marketing_templates WHERE active = 1 ORDER BY position ASC, id ASC')
			->fetchAll() ?: [];
	}

	/** @return list<array<string, mixed>> */
	public static function allOrdered(): array
	{
		return self::pdo()
			->query('SELECT * FROM marketing_templates ORDER BY position ASC, id ASC')
			->fetchAll() ?: [];
	}

	public static function findActive(int $id): ?array
	{
		$stmt = self::pdo()->prepare('SELECT * FROM marketing_templates WHERE id = ? AND active = 1');
		$stmt->execute([$id]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public static function nextPosition(): int
	{
		return (int) self::pdo()->query('SELECT COALESCE(MAX(position), 0) FROM marketing_templates')->fetchColumn() + 1;
	}

	/** @return list<array{slug:string,name:string,subject:string,body:string}> */
	public static function defaults(): array
	{
		return [
			[
				'slug' => 'prospeccion-inicial',
				'name' => 'Prospección inicial',
				'subject' => 'Propuesta de audio y video para {nombre_empresa}',
				'body' => "Hola {nombre_cliente},\n\nSoy {nombre_vendedor} de Mizo Ingeniería. Vimos que {nombre_empresa} podría beneficiarse de una solución profesional de audio y video.\n\n¿Te parece bien una breve llamada esta semana para entender el recinto y proponerte opciones concretas?\n\nQuedo atento.\n{nombre_vendedor}",
			],
			[
				'slug' => 'seguimiento-cotizacion',
				'name' => 'Seguimiento de cotización',
				'subject' => 'Seguimiento cotización {numero_cotizacion} — {nombre_empresa}',
				'body' => "Hola {nombre_cliente},\n\nTe escribo para hacer seguimiento de la cotización {numero_cotizacion} de {nombre_empresa}"
					. " (total {total_cotizacion}).\n\nPuedes revisarla aquí:\n{enlace_cotizacion}\n\n"
					. "Si quieres ajustar partidas, plazos o una visita técnica, avísame y lo coordinamos.\n\nSaludos,\n{nombre_vendedor}",
			],
			[
				'slug' => 'presentacion-servicios',
				'name' => 'Presentación de servicios',
				'subject' => 'Servicios Mizo para {nombre_empresa}',
				'body' => "Hola {nombre_cliente},\n\nEn Mizo diseñamos e instalamos sistemas de audio, video y control a medida para recintos como el de {nombre_empresa}"
					. " ({ciudad}).\n\nTrabajamos con hardware de grado profesional, cotización clara y acompañamiento en terreno.\n\n"
					. "Si te interesa, te preparo una propuesta según el espacio y el uso.\n\nSaludos,\n{nombre_vendedor}",
			],
		];
	}
}
