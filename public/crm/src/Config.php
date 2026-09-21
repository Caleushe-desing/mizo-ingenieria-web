<?php
declare(strict_types=1);

namespace MizoCrm;

final class Config
{
	public const COMPANY = 'Mizo';
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
		return [
			'nuevo' => 'Nuevo',
			'contactado' => 'Contactado',
			'propuesta' => 'Propuesta',
			'negociacion' => 'Negociación',
			'ganado' => 'Ganado',
			'perdido' => 'Perdido',
		];
	}

	public static function openStages(): array
	{
		return ['nuevo', 'contactado', 'propuesta', 'negociacion'];
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
