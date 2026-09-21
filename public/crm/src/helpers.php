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
			'mail_sent', 'mail_received' => 'Correo',
			'won' => 'Ganada',
			'lost' => 'Perdida',
			'assigned' => 'Asignación',
			'deal_created' => 'Caso nuevo',
			default => 'Registro',
		};
}

function mail_when(?string $value): string
{
	$time = strtotime((string) $value);
	if (!$time) {
		return '—';
	}
	$months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	if (date('Y-m-d', $time) === date('Y-m-d')) {
		return date('H:i', $time);
	}
	if (date('Y', $time) === date('Y')) {
		return date('j', $time) . ' ' . $months[(int) date('n', $time) - 1];
	}
	return date('d-m-Y', $time);
}

function mail_snippet(?string $html, ?string $text): string
{
	$raw = trim(strip_tags((string) (($text ?? '') !== '' ? $text : $html)));
	$raw = preg_replace('/\s+/u', ' ', $raw) ?? $raw;
	if ($raw === '') {
		return '';
	}
	if (function_exists('mb_strlen') && mb_strlen($raw, 'UTF-8') > 88) {
		return mb_substr($raw, 0, 88, 'UTF-8') . '…';
	}
	if (strlen($raw) > 88) {
		return substr($raw, 0, 88) . '…';
	}
	return $raw;
}

function mail_avatar_color(string $name): string
{
	$colors = ['#5f6368', '#1a73e8', '#188038', '#c5221f', '#e37400', '#7b1fa2', '#00897b'];
	return $colors[abs(crc32($name)) % count($colors)];
}
