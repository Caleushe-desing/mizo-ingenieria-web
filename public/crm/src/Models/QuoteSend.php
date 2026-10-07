<?php
declare(strict_types=1);

namespace MizoCrm\Models;

use MizoCrm\Record;

/** Bitácora de envíos/reenvíos de cotización (auditoría). */
final class QuoteSend extends Record
{
	protected static function table(): string
	{
		return 'quote_sends';
	}

	/**
	 * @param 'send'|'resend' $kind
	 */
	public static function record(
		int $quoteId,
		int $userId,
		string $toEmail,
		string $ccEmail = '',
		?int $contactId = null,
		string $kind = 'send',
		?int $mailId = null,
		bool $hadPdf = false,
	): int {
		return self::insert([
			'quote_id' => $quoteId,
			'user_id' => $userId,
			'contact_id' => $contactId,
			'to_email' => mb_strtolower(trim($toEmail)),
			'cc_email' => trim($ccEmail),
			'kind' => $kind === 'resend' ? 'resend' : 'send',
			'mail_id' => $mailId && $mailId > 0 ? $mailId : null,
			'had_pdf' => $hadPdf ? 1 : 0,
			'sent_at' => date('c'),
		]);
	}

	/** @return list<array<string,mixed>> */
	public static function forQuote(int $quoteId): array
	{
		$stmt = self::pdo()->prepare(
			'SELECT s.*,
				u.name AS user_name,
				u.email AS user_email,
				cc.name AS contact_name,
				cc.email AS contact_email
			 FROM quote_sends s
			 LEFT JOIN users u ON u.id = s.user_id
			 LEFT JOIN client_contacts cc ON cc.id = s.contact_id
			 WHERE s.quote_id = ?
			 ORDER BY s.id DESC'
		);
		$stmt->execute([$quoteId]);
		return $stmt->fetchAll();
	}
}
