<?php
declare(strict_types=1);

namespace MizoCrm;

final class Config
{
	public const COMPANY = 'Mizo';
	/** RUT de la empresa emisora (aparece en la cotización PDF). */
	public const RUT = '77.589.163-7';
	public const EMAIL = 'ventas@mizo.cl';
	public const PHONE = '+56 9 9439 0870';
	public const ADDRESS = 'Frutillar y Santiago, Chile';
	public const TAX_RATE = 19.0;

	public static function services(): array
	{
		return [
			'sonido' => 'Sistemas de Sonido',
			'video' => 'Videoproyección y Audiovisuales',
			'cctv' => 'Cámaras de Seguridad (CCTV)',
			'ti' => 'Soporte TI e Infraestructura',
			'otro' => 'Otro / mixto',
		];
	}

	public static function stages(): array
	{
		return Models\Stage::labels();
	}

	/** Guion corto para leer en la llamada, según el servicio del negocio. */
	public static function playbook(string $service): array
	{
		$all = [
			'sonido' => [
				'pregunta' => '¿En qué momento del día se nota más que el audio no se entiende?',
				'beneficio' => 'Que el mensaje se escuche parejo en todo el recinto, sin gritar ni saturar.',
				'cierre' => 'Te dejo una propuesta con zonas, potencia y calibración, para que compares con lo que tienes hoy.',
			],
			'video' => [
				'pregunta' => '¿La imagen se usa para presentar, para operar o para que el público vea desde lejos?',
				'beneficio' => 'Una imagen brillante y estable, del tamaño correcto, sin cables a la vista.',
				'cierre' => 'Armamos la cotización con pantalla, montaje e instalación lista para usar.',
			],
			'cctv' => [
				'pregunta' => '¿Qué zona te preocupa más: acceso, perímetro o interior?',
				'beneficio' => 'Ver qué pasó, desde el celular, con grabación ordenada y cámaras donde sí importan.',
				'cierre' => 'Te cotizo cámaras, grabador y cableado según los puntos que marcamos.',
			],
			'ti' => [
				'pregunta' => '¿Lo que más se cae es internet, los equipos o el soporte cuando algo falla?',
				'beneficio' => 'Una red estable y alguien que responde cuando el local no puede parar.',
				'cierre' => 'Te propongo un alcance claro: qué queda cubierto y en qué plazo.',
			],
			'otro' => [
				'pregunta' => '¿Qué resultado necesitas en el local en las próximas semanas?',
				'beneficio' => 'Una solución a la medida, instalada y explicada, sin dejar el trabajo a medias.',
				'cierre' => 'Con el requerimiento claro, la cotización sale con precio y plazo.',
			],
		];
		return $all[$service] ?? $all['otro'];
	}

	public static function openStages(): array
	{
		return Models\Stage::openKeys();
	}

	public static function quoteStatuses(): array
	{
		return [
			'borrador' => 'Borrador',
			'enviada' => 'Enviada',
			'vista' => 'Vista por el cliente',
			'aceptada' => 'Aceptada',
			'rechazada' => 'Rechazada',
		];
	}

	/** Texto por defecto: página 2 del PDF — condiciones y pago. */
	public static function defaultQuoteTerms(): string
	{
		return "Condiciones generales\n"
			. "• Validez de la cotización: 15 días corridos desde la fecha de emisión.\n"
			. "• Precios en pesos chilenos (CLP), expresados en valores netos. El IVA se indica por separado en el total.\n"
			. "• La instalación y puesta en marcha quedan sujetas a coordinación de visita técnica y acceso al recinto.\n"
			. "• Cualquier cambio de alcance, marca o especificación puede modificar plazos y valores.\n"
			. "• Los equipos quedan garantizados según la política del fabricante y la instalación ejecutada por Mizo.\n\n"
			. "Modo de pago\n"
			. "• 50% al aceptar la propuesta, para reserva de equipos e inicio de obra.\n"
			. "• 50% contra entrega e instalación conforme, o según hitos acordados por escrito.\n"
			. "• Transferencia bancaria a nombre de Mizo. Datos de pago se envían al confirmar la OC.\n"
			. "• Facturación electrónica según normativa vigente.";
	}

	/** Texto por defecto: página 2 del PDF — presentación institucional. */
	public static function defaultQuoteAbout(): string
	{
		return "Sobre Mizo\n"
			. "Somos una empresa de ingeniería audiovisual e integración tecnológica. Diseñamos, instalamos y damos soporte a sistemas de sonido, video, CCTV, redes y automatización para empresas, instituciones y recintos en Frutillar, Santiago y el sur de Chile.\n\n"
			. "Nuestras fortalezas\n"
			. "• Ingeniería aplicada: cada propuesta se dimensiona al uso real del espacio, no a un catálogo genérico.\n"
			. "• Integración completa: audio, video, seguridad y TI conversan en un solo sistema operable.\n"
			. "• Instalación profesional: cableado, montaje, calibración y entrega documentada.\n"
			. "• Soporte cercano: acompañamos la operación después de la puesta en marcha.\n"
			. "• Marcas con respaldo local y garantía oficial en el territorio nacional.\n\n"
			. "Trabajamos para que la tecnología se note en el resultado — claridad, estabilidad y continuidad — y no en la complejidad del día a día.";
	}

	public static function sources(): array
	{
		return [
			'web' => 'Sitio web',
			'whatsapp' => 'WhatsApp',
			'telefono' => 'Teléfono',
			'referido' => 'Referido',
			'terreno' => 'Visita en terreno',
			'otro' => 'Otro',
		];
	}

	public static function workStatuses(): array
	{
		return [
			'pendiente' => 'Por enviar',
			'enviada' => 'Enviada',
			'ganada' => 'Ganada',
			'perdida' => 'Perdida',
		];
	}

	public static function defaultLine(string $service): string
	{
		return match ($service) {
			'sonido' => 'Instalación y calibración de sistema de sonido',
			'video' => 'Integración de videoproyección y audiovisuales',
			'cctv' => 'Instalación de cámaras de seguridad, NVR y cableado',
			'ti' => 'Soporte TI, red e infraestructura',
			default => '',
		};
	}

	public static function jobStatuses(): array
	{
		return [
			'consulta' => 'Consulta',
			'cotizado' => 'Cotizado',
			'ganado' => 'Aprobado / en trabajo',
			'en_terreno' => 'Instalación en terreno',
			'entregado' => 'Entregado',
			'perdido' => 'Perdido',
		];
	}

	public static function activityKinds(): array
	{
		return [
			'llamada' => 'Llamada',
			'whatsapp' => 'WhatsApp',
			'visita' => 'Visita',
			'comentario' => 'Comentario',
			'nota' => 'Comentario',
		];
	}
}
