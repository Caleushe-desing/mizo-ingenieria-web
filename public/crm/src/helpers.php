<?php
declare(strict_types=1);

function h(mixed $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int|float|string|null $value): string
{
	return '$' . number_format((int) $value, 0, ',', '.');
}

function when(?string $value, string $format = 'd-m-Y H:i'): string
{
	if (!$value) {
		return '—';
	}
	$time = strtotime($value);
	return $time ? date($format, $time) : $value;
}

function initials(string $name): string
{
	$parts = preg_split('/\s+/', trim($name)) ?: [];
	$letters = '';
	foreach (array_slice($parts, 0, 2) as $part) {
		$letters .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
	}
	return $letters !== '' ? $letters : 'M';
}

function work_status(array $row): string
{
	$stage = (string) ($row['stage'] ?? '');
	$quoteStatus = (string) ($row['quote_status'] ?? '');
	if ($stage === 'ganado' || $quoteStatus === 'aceptada') {
		return 'ganada';
	}
	if ($stage === 'perdido') {
		return 'perdida';
	}
	if (in_array($quoteStatus, ['enviada', 'vista'], true)) {
		return 'enviada';
	}
	return 'pendiente';
}

function work_status_label(string $status): string
{
	return \MizoCrm\Config::workStatuses()[$status] ?? 'Por enviar';
}

function quote_status_label(string $status): string
{
	return \MizoCrm\Config::quoteStatuses()[$status] ?? $status;
}

function activity_label(string $type): string
{
	return \MizoCrm\Config::activityKinds()[$type]
		?? match ($type) {
			'lead' => 'Sitio web',
			'quote_created', 'quote_sent', 'quote_viewed', 'quote_accepted', 'quote_rejected' => 'Cotización',
			'won' => 'Ganada',
			'lost' => 'Perdida',
			'assigned' => 'Asignación',
			'deal_created' => 'Caso nuevo',
			default => 'Registro',
		};
}
