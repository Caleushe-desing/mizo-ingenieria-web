<?php
declare(strict_types=1);

namespace MizoCrm\Marketing;

/**
 * Configuración del estudio de flyers: plantillas base y biblioteca de fondos.
 */
final class FlyerStudio
{
	public const WIDTH = 1080;
	public const HEIGHT = 1350;

	/** @return list<array<string, mixed>> */
	public static function templates(): array
	{
		return [
			[
				'id' => 'domotica-residencial',
				'name' => 'Domótica Residencial',
				'category' => 'Domótica',
				'description' => 'Plantilla cálida para homes y departamentos.',
				'background' => 'grad-warm',
				'accent' => '#f47b20',
				'fields' => [
					'title' => 'Domótica que se siente natural',
					'description' => 'Control de iluminación, audio y clima en una sola app. Diseñado para tu hogar.',
					'cta' => 'Cotiza tu proyecto',
				],
			],
			[
				'id' => 'audio-profesional',
				'name' => 'Audio Profesional',
				'category' => 'Audio Comercial',
				'description' => 'Para locales, iglesias y recintos comerciales.',
				'background' => 'grad-navy',
				'accent' => '#1c9bd8',
				'fields' => [
					'title' => 'Audio de grado profesional',
					'description' => 'Sistemas claros, potentes y listos para operar todos los días.',
					'cta' => 'Agenda una visita técnica',
				],
			],
			[
				'id' => 'proyectos-corporativos',
				'name' => 'Proyectos Corporativos',
				'category' => 'Proyectos Especiales',
				'description' => 'Look institucional para propuestas B2B.',
				'background' => 'grad-corporate',
				'accent' => '#0b6ea8',
				'fields' => [
					'title' => 'Integración AV corporativa',
					'description' => 'Salas de reunión, auditorios y experiencia de marca con ingeniería Mizo.',
					'cta' => 'Solicita propuesta',
				],
			],
			[
				'id' => 'automatizacion-residencial',
				'name' => 'Automatización Residencial',
				'category' => 'Automatización Residencial',
				'description' => 'Pieza limpia para stories y WhatsApp.',
				'background' => 'grad-slate',
				'accent' => '#f47b20',
				'fields' => [
					'title' => 'Tu casa, más inteligente',
					'description' => 'Automatizamos escenas, seguridad y entretenimiento sin complicaciones.',
					'cta' => 'Habla con un especialista',
				],
			],
		];
	}

	/** Fondos procedurales + archivos de la biblioteca. @return list<array<string, mixed>> */
	public static function backgrounds(): array
	{
		$presets = [
			['id' => 'grad-navy', 'name' => 'Azul Mizo', 'type' => 'preset'],
			['id' => 'grad-warm', 'name' => 'Naranja técnico', 'type' => 'preset'],
			['id' => 'grad-corporate', 'name' => 'Corporativo', 'type' => 'preset'],
			['id' => 'grad-slate', 'name' => 'Pizarra', 'type' => 'preset'],
			['id' => 'grad-light', 'name' => 'Claro institucional', 'type' => 'preset'],
		];
		foreach (self::libraryFiles() as $file) {
			$presets[] = [
				'id' => 'file:' . $file['name'],
				'name' => $file['label'],
				'type' => 'image',
				'url' => $file['url'],
			];
		}
		return $presets;
	}

	/** @return list<array{name:string,label:string,url:string}> */
	public static function libraryFiles(): array
	{
		$dir = self::libraryRoot();
		if (!is_dir($dir)) {
			return [];
		}
		$out = [];
		$files = @scandir($dir) ?: [];
		natcasesort($files);
		foreach ($files as $name) {
			if ($name === '.' || $name === '..') {
				continue;
			}
			if (!preg_match('/\.(jpe?g|png|webp|gif)$/i', $name)) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $name;
			if (!is_file($path)) {
				continue;
			}
			$out[] = [
				'name' => $name,
				'label' => pathinfo($name, PATHINFO_FILENAME),
				'url' => '/crm/uploads/marketing/library/' . rawurlencode($name),
			];
		}
		return $out;
	}

	public static function libraryRoot(): string
	{
		return dirname(__DIR__, 2) . '/uploads/marketing/library';
	}

	/** @return array{logo:string,logoFallback:string,width:int,height:int} */
	public static function brand(): array
	{
		return [
			'logo' => '/mizo-logo-footer.png',
			'logoFallback' => '/mizo-logo.svg',
			'width' => self::WIDTH,
			'height' => self::HEIGHT,
		];
	}
}
