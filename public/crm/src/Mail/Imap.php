<?php
declare(strict_types=1);

namespace MizoCrm\Mail;

use RuntimeException;

final class Imap
{
	private $fp;
	private int $n = 0;

	public function __construct(array $mailbox)
	{
		$host = (string) $mailbox['imap_host'];
		$port = (int) $mailbox['imap_port'];
		$this->fp = Socket::open('ssl://' . $host . ':' . $port);
		$this->readLine();
		try {
			$this->command('LOGIN ' . self::quote((string) $mailbox['email']) . ' ' . self::quote((string) $mailbox['password']));
		} catch (RuntimeException $e) {
			$this->close();
			throw new RuntimeException('No se pudo entrar a la casilla. Revisa el correo y la clave (es la del correo, no la del CRM).');
		}
	}

	public static function probe(array $mailbox): string
	{
		$imap = new self($mailbox);
		try {
			return $imap->findSentFolder();
		} finally {
			$imap->close();
		}
	}

	public function findSentFolder(): string
	{
		$lines = $this->command('LIST "" "*"');
		$fallback = 'Sent';
		foreach ($lines as $line) {
			if (!preg_match('/^\* LIST \((.*)\) ".*" (.+)$/', $line, $matches)) {
				continue;
			}
			$attrs = strtoupper($matches[1]);
			$name = trim($matches[2], '"');
			if (str_contains($attrs, '\\SENT') || preg_match('/sent|enviad/i', $name)) {
				return $name;
			}
			if (strcasecmp($name, 'Sent') === 0) {
				$fallback = $name;
			}
		}
		return $fallback;
	}

	public function uids(string $folder, int $limit = 80): array
	{
		$this->select($folder);
		$lines = $this->command('UID SEARCH ALL');
		$uids = [];
		foreach ($lines as $line) {
			if (!preg_match('/^\* SEARCH\b(.*)$/i', $line, $matches)) {
				continue;
			}
			foreach (preg_split('/\s+/', trim($matches[1])) ?: [] as $uid) {
				if (ctype_digit($uid)) {
					$uids[] = (int) $uid;
				}
			}
		}
		sort($uids, SORT_NUMERIC);
		if ($limit > 0 && count($uids) > $limit) {
			$uids = array_slice($uids, -$limit);
		}
		return $uids;
	}

	public function flags(string $folder, array $uids): array
	{
		if ($uids === []) {
			return [];
		}
		$this->select($folder);
		$set = implode(',', $uids);
		$lines = $this->command('UID FETCH ' . $set . ' (UID FLAGS)');
		$out = [];
		$buffer = implode("\n", $lines);
		if (preg_match_all('/UID (\d+).*?FLAGS \(([^)]*)\)|FLAGS \(([^)]*)\).*?UID (\d+)/s', $buffer, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $row) {
				$uid = (int) ($row[1] !== '' ? $row[1] : $row[4]);
				$flags = strtoupper($row[2] !== '' ? $row[2] : $row[3]);
				$out[$uid] = [
					'seen' => str_contains($flags, '\\SEEN'),
					'flagged' => str_contains($flags, '\\FLAGGED'),
				];
			}
		}
		return $out;
	}

	public function fetch(string $folder, int $uid): array
	{
		$this->select($folder);
		try {
			return $this->fetchRaw($uid, 'FLAGS BODY.PEEK[]');
		} catch (RuntimeException) {
			// Algunos servidores IMAP no aceptan BODY.PEEK[]; RFC822 puede marcar \Seen.
			return $this->fetchRaw($uid, 'FLAGS RFC822');
		}
	}

	private function fetchRaw(int $uid, string $items): array
	{
		$tag = $this->tag();
		$this->write($tag . ' UID FETCH ' . $uid . ' (' . $items . ')');
		$raw = '';
		$seen = false;
		$flagged = false;
		while (true) {
			$line = $this->readLine();
			if (str_starts_with($line, $tag . ' ')) {
				if (!str_contains($line, ' OK')) {
					throw new RuntimeException('No se pudo leer el correo.');
				}
				break;
			}
			$upper = strtoupper($line);
			if (str_contains($upper, '\\SEEN')) {
				$seen = true;
			}
			if (str_contains($upper, '\\FLAGGED')) {
				$flagged = true;
			}
			if (preg_match('/\{(\d+)\}\s*$/', $line, $matches)) {
				$n = (int) $matches[1];
				$chunk = $this->readBytes($n);
				// Quedarse con el literal más grande (el cuerpo RFC822 / BODY[]).
				if ($n >= strlen($raw)) {
					$raw = $chunk;
				}
				$this->readLine();
			}
		}
		if (trim($raw) === '') {
			throw new RuntimeException('El correo llegó vacío desde el servidor.');
		}
		$parsed = Mime::parse($raw);
		$parsed['seen'] = $seen;
		$parsed['flagged'] = $flagged;
		$parsed['uid'] = $uid;
		$parsed['raw'] = $raw;
		return $parsed;
	}

	public function markSeen(string $folder, int $uid): void
	{
		$this->select($folder);
		$this->command('UID STORE ' . $uid . ' +FLAGS (\\Seen)');
	}

	public function markUnseen(string $folder, int $uid): void
	{
		$this->select($folder);
		$this->command('UID STORE ' . $uid . ' -FLAGS (\\Seen)');
	}

	public function markFlagged(string $folder, int $uid, bool $flagged): void
	{
		$this->select($folder);
		$op = $flagged ? '+FLAGS' : '-FLAGS';
		$this->command('UID STORE ' . $uid . ' ' . $op . ' (\\Flagged)');
	}

	/** Borra los mensajes en el servidor (papelera IMAP) y los expurga de la carpeta. */
	public function remove(string $folder, array $uids): void
	{
		$uids = array_values(array_filter(array_map('intval', $uids), static fn(int $uid): bool => $uid > 0));
		if ($uids === []) {
			return;
		}
		$this->select($folder);
		$set = implode(',', $uids);
		$this->command('UID STORE ' . $set . ' +FLAGS (\\Deleted)');
		try {
			$this->command('UID EXPUNGE ' . $set);
		} catch (RuntimeException) {
			$this->command('EXPUNGE');
		}
	}

	public function append(string $folder, string $rfc822): void
	{
		$tag = $this->tag();
		$this->write($tag . ' APPEND ' . self::quote($folder) . ' (\\Seen) {' . strlen($rfc822) . '}');
		$line = $this->readLine();
		if (!str_starts_with($line, '+')) {
			throw new RuntimeException('No se pudo guardar el correo enviado en la casilla.');
		}
		fwrite($this->fp, $rfc822 . "\r\n");
		while (true) {
			$done = $this->readLine();
			if (str_starts_with($done, $tag . ' ')) {
				if (!str_contains($done, ' OK')) {
					throw new RuntimeException('No se pudo guardar el correo enviado en la casilla.');
				}
				return;
			}
		}
	}

	public function close(): void
	{
		if ($this->fp === null) {
			return;
		}
		if (!is_resource($this->fp) && !is_object($this->fp)) {
			$this->fp = null;
			return;
		}
		try {
			$this->write($this->tag() . ' LOGOUT');
		} catch (\Throwable) {
		}
		try {
			fclose($this->fp);
		} catch (\Throwable) {
		}
		$this->fp = null;
	}

	private function select(string $folder): void
	{
		$this->command('SELECT ' . self::quote($folder));
	}

	private function command(string $cmd): array
	{
		$tag = $this->tag();
		$this->write($tag . ' ' . $cmd);
		$lines = [];
		while (true) {
			$line = $this->readLine();
			if (str_starts_with($line, $tag . ' ')) {
				if (!str_contains($line, ' OK')) {
					throw new RuntimeException('El servidor de correo rechazó la operación.');
				}
				return $lines;
			}
			$lines[] = $line;
		}
	}

	private function tag(): string
	{
		$this->n++;
		return 'A' . $this->n;
	}

	private function write(string $line): void
	{
		if (fwrite($this->fp, $line . "\r\n") === false) {
			throw new RuntimeException('Se perdió la conexión con el correo.');
		}
	}

	private function readLine(): string
	{
		$line = fgets($this->fp, 8192);
		if ($line === false) {
			throw new RuntimeException('El servidor de correo no respondió.');
		}
		return rtrim($line, "\r\n");
	}

	private function readBytes(int $n): string
	{
		$data = '';
		while (strlen($data) < $n) {
			$chunk = fread($this->fp, $n - strlen($data));
			if ($chunk === false || $chunk === '') {
				throw new RuntimeException('No se pudo leer el correo completo.');
			}
			$data .= $chunk;
		}
		return $data;
	}

	private static function quote(string $value): string
	{
		return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
	}
}
