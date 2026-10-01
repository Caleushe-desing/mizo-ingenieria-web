<?php
declare(strict_types=1);

namespace MizoCrm;

final class Mailer
{
	/**
	 * Envía HTML. Con casilla conectada guarda copia en Enviados.
	 * @param list<array{filename:string,mime:string,content:string}> $attachments
	 * @return int|false id del mensaje en CRM (0 si salió por mail() sin casilla), false si falló
	 */
	public static function send(
		string $to,
		string $subject,
		string $html,
		string $replyTo = '',
		string $cc = '',
		array $attachments = [],
	): int|false {
		$user = Auth::user();
		if ($user && Models\Mailbox::forUser((int) $user['id'])) {
			try {
				$clientId = Models\MailMessage::clientIdFor((int) $user['id'], $to);
				if ($clientId === null && $cc !== '') {
					$clientId = Models\MailMessage::clientIdFor((int) $user['id'], $cc);
				}
				return Models\Mailbox::deliver((int) $user['id'], $user, $to, $subject, $html, '', $clientId, $cc, $attachments);
			} catch (\RuntimeException) {
				return false;
			}
		}
		$fromName = Models\User::mailFromName($user);
		$fromEmail = Config::EMAIL;
		if ($attachments !== []) {
			$rfc822 = Mail\Mime::build($fromName, $fromEmail, $to, $subject, $html, '', $cc, $attachments);
			$split = preg_split("/\r\n\r\n/", $rfc822, 2);
			if (!is_array($split) || count($split) < 2) {
				return false;
			}
			[$rawHeaders, $body] = $split;
			$headerLines = preg_split("/\r\n/", (string) $rawHeaders) ?: [];
			$pass = [];
			foreach ($headerLines as $line) {
				if (preg_match('/^(From|MIME-Version|Content-Type|Cc|Reply-To):/i', $line)) {
					$pass[] = $line;
				}
			}
			if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
				$pass[] = 'Reply-To: ' . $replyTo;
			}
			$encoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
			$ok = @mail($to, $encoded, (string) $body, implode("\r\n", $pass));
			return $ok ? 0 : false;
		}
		$from = $fromName . ' <' . $fromEmail . '>';
		$headers = [
			'MIME-Version: 1.0',
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from,
		];
		if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
			$headers[] = 'Reply-To: ' . $replyTo;
		}
		$ccList = Mail\Mime::emailsFromString($cc);
		if ($ccList !== []) {
			$headers[] = 'Cc: ' . implode(', ', $ccList);
		}
		$encoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
		$ok = @mail($to, $encoded, $html, implode("\r\n", $headers));
		return $ok ? 0 : false;
	}

	public static function quoteHtml(array $quote, array $items, array $client, string $publicUrl, ?array $user = null): string
	{
		$logo = App::origin() . '/mizo-logo.png';
		$downloadUrl = rtrim($publicUrl, '/') . '/pdf';
		$contact = (string) ($client['contact_name'] ?: $client['name']);
		$company = (string) ($client['name'] ?? '');
		$greeting = $contact !== '' ? $contact : 'estimado cliente';
		$reference = trim((string) ($quote['intro'] ?? $quote['deal_title'] ?? ''));
		$rows = '';
		$n = 1;
		foreach ($items as $item) {
			$qty = rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',');
			$label = Models\Quote::itemLabel($item);
			$detail = trim((string) ($item['description'] ?? ''));
			if ($detail !== '' && strcasecmp($detail, $label) === 0) {
				$detail = '';
			}
			$copy = '<div style="font-weight:600;color:#1a1a1a;">' . h($label) . '</div>';
			if ($detail !== '') {
				$copy .= '<div style="margin-top:4px;color:#555;font-size:12px;line-height:1.4;">' . nl2br(h($detail)) . '</div>';
			}
			$rows .= '<tr>
				<td style="padding:10px 8px;border-bottom:1px solid #e6e6e6;color:#666;font-size:12px;">' . $n . '</td>
				<td style="padding:10px 8px;border-bottom:1px solid #e6e6e6;color:#1a1a1a;">' . $copy . '</td>
				<td style="padding:10px 8px;border-bottom:1px solid #e6e6e6;text-align:right;white-space:nowrap;">' . h($qty) . ' ' . h((string) $item['unit']) . '</td>
				<td style="padding:10px 8px;border-bottom:1px solid #e6e6e6;text-align:right;white-space:nowrap;">' . money((int) $item['unit_price']) . '</td>
				<td style="padding:10px 8px;border-bottom:1px solid #e6e6e6;text-align:right;white-space:nowrap;font-weight:600;">' . money((int) $item['total']) . '</td>
			</tr>';
			$n++;
		}

		return '<div style="background:#efefef;padding:24px 12px;margin:0;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e0e0e0;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
			<tr>
				<td style="background:#ffffff;padding:22px 28px 18px;border-bottom:1px solid #ececec;">
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
						<tr>
							<td><img src="' . h($logo) . '" alt="Mizo" height="42" style="display:block;border:0;height:42px;width:auto;"></td>
							<td style="text-align:right;color:#161616;">
								<div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#f47b20;font-weight:bold;">Cotización</div>
								<div style="font-size:20px;font-weight:bold;margin-top:4px;">' . h($quote['number']) . '</div>
							</td>
						</tr>
					</table>
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;">
						<tr>
							<td align="center" style="padding:0;">
								<a href="' . h($downloadUrl) . '" style="display:inline-block;background:#f47b20;color:#ffffff;text-decoration:none;padding:14px 26px;font-weight:bold;font-size:15px;letter-spacing:.03em;border-radius:4px;">Descargar presupuesto</a>
							</td>
						</tr>
						<tr>
							<td align="center" style="padding:10px 0 0;">
								<a href="' . h($publicUrl) . '" style="color:#0b6ea8;font-size:13px;text-decoration:underline;">Ver en el navegador</a>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td style="padding:28px;">
					<p style="margin:0 0 16px;font-size:15px;line-height:1.5;">Estimado/a <strong>' . h($greeting) . '</strong>' . ($company !== '' && $company !== $contact ? ' · ' . h($company) : '') . ',</p>
					<p style="margin:0 0 20px;font-size:15px;line-height:1.5;color:#444;">Te enviamos la cotización <strong>' . h($quote['number']) . '</strong>' . ($reference !== '' ? ' para <strong>' . h($reference) . '</strong>' : '') . '. Puedes <strong>descargar el PDF</strong> con el botón de arriba (también va adjunto) o revisar el resumen aquí.</p>
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
						<tr style="background:#ffffff;color:#1c9bd8;border-bottom:2px solid #1c9bd8;">
							<th align="left" style="padding:9px 8px;font-weight:600;border-bottom:2px solid #1c9bd8;">#</th>
							<th align="left" style="padding:9px 8px;font-weight:600;border-bottom:2px solid #1c9bd8;">Descripción</th>
							<th align="right" style="padding:9px 8px;font-weight:600;border-bottom:2px solid #1c9bd8;">Cant.</th>
							<th align="right" style="padding:9px 8px;font-weight:600;border-bottom:2px solid #1c9bd8;">P. unitario</th>
							<th align="right" style="padding:9px 8px;font-weight:600;border-bottom:2px solid #1c9bd8;">Total neto</th>
						</tr>
						' . $rows . '
					</table>
					<table role="presentation" align="right" cellpadding="0" cellspacing="0" style="margin-top:16px;font-size:14px;min-width:240px;">
						<tr>
							<td style="padding:6px 0;color:#666;">Neto</td>
							<td style="padding:6px 0 6px 24px;text-align:right;">' . money((int) $quote['subtotal']) . '</td>
						</tr>
						<tr>
							<td style="padding:6px 0;color:#666;">IVA ' . (int) $quote['tax_rate'] . '%</td>
							<td style="padding:6px 0 6px 24px;text-align:right;">' . money((int) $quote['tax']) . '</td>
						</tr>
						<tr>
							<td style="padding:10px 0 0;border-top:2px solid #111;font-weight:bold;font-size:16px;">Total</td>
							<td style="padding:10px 0 0 24px;border-top:2px solid #111;text-align:right;font-weight:bold;font-size:16px;color:#f47b20;">' . money((int) $quote['total']) . '</td>
						</tr>
					</table>
					<div style="clear:both;"></div>
					<p style="margin:28px 0 0;text-align:center;">
						<a href="' . h($downloadUrl) . '" style="display:inline-block;background:#f47b20;color:#ffffff;text-decoration:none;padding:14px 22px;font-weight:bold;font-size:14px;letter-spacing:.03em;border-radius:4px;">Descargar presupuesto</a>
					</p>
					<p style="margin:12px 0 0;text-align:center;">
						<a href="' . h($publicUrl) . '" style="color:#0b6ea8;font-size:13px;text-decoration:underline;">Ver cotización completa en el navegador</a>
					</p>
					' . Models\User::signatureHtml($user) . '
					<p style="margin:18px 0 0;font-size:12px;color:#777;text-align:center;line-height:1.5;">Válida hasta ' . h(when($quote['valid_until'], 'd-m-Y')) . '. Precios en pesos chilenos, neto + IVA.<br>
					' . h(Config::PHONE) . ' · ' . h(Config::EMAIL) . ' · mizo.cl</p>
				</td>
			</tr>
		</table>
		</div>';
	}
}
