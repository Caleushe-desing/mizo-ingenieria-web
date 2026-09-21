<?php
declare(strict_types=1);

namespace MizoCrm;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
	private static ?PDO $pdo = null;

	public static function path(): string
	{
		return dirname(__DIR__, 2) . '/crm-data/crm.sqlite';
	}

	public static function pdo(): PDO
	{
		if (self::$pdo instanceof PDO) {
			return self::$pdo;
		}

		$dir = dirname(self::path());
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new RuntimeException('No se pudo crear la carpeta de datos del CRM.');
		}

		$pdo = new PDO('sqlite:' . self::path(), null, null, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		]);
		$pdo->exec('PRAGMA foreign_keys = ON');
		try {
			$pdo->exec('PRAGMA journal_mode = WAL');
		} catch (\PDOException) {
			// Algunos hostings no permiten WAL.
		}
		self::$pdo = $pdo;
		self::migrate($pdo);
		return $pdo;
	}

	public static function ready(): bool
	{
		try {
			self::pdo();
			return true;
		} catch (PDOException | RuntimeException) {
			return false;
		}
	}

	private static function migrate(PDO $pdo): void
	{
		$version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
		if ($version < 1) {
			$pdo->exec(
				<<<'SQL'
				CREATE TABLE IF NOT EXISTS users (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					name TEXT NOT NULL,
					email TEXT NOT NULL UNIQUE,
					password_hash TEXT NOT NULL,
					role TEXT NOT NULL DEFAULT 'vendedor',
					active INTEGER NOT NULL DEFAULT 1,
					created_at TEXT NOT NULL
				);

				CREATE TABLE IF NOT EXISTS clients (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					name TEXT NOT NULL,
					contact_name TEXT,
					email TEXT,
					phone TEXT,
					rut TEXT,
					city TEXT,
					source TEXT NOT NULL DEFAULT 'otro',
					notes TEXT,
					owner_id INTEGER,
					created_at TEXT NOT NULL,
					updated_at TEXT NOT NULL,
					FOREIGN KEY (owner_id) REFERENCES users(id)
				);

				CREATE TABLE IF NOT EXISTS deals (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					client_id INTEGER NOT NULL,
					title TEXT NOT NULL,
					service TEXT NOT NULL DEFAULT 'otro',
					stage TEXT NOT NULL DEFAULT 'nuevo',
					amount INTEGER NOT NULL DEFAULT 0,
					expected_close TEXT,
					lost_reason TEXT,
					notes TEXT,
					owner_id INTEGER,
					created_at TEXT NOT NULL,
					updated_at TEXT NOT NULL,
					FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
					FOREIGN KEY (owner_id) REFERENCES users(id)
				);

				CREATE TABLE IF NOT EXISTS quotes (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					number TEXT NOT NULL UNIQUE,
					deal_id INTEGER NOT NULL,
					client_id INTEGER NOT NULL,
					status TEXT NOT NULL DEFAULT 'borrador',
					intro TEXT,
					notes TEXT,
					valid_until TEXT,
					tax_rate REAL NOT NULL DEFAULT 19,
					subtotal INTEGER NOT NULL DEFAULT 0,
					tax INTEGER NOT NULL DEFAULT 0,
					total INTEGER NOT NULL DEFAULT 0,
					token TEXT NOT NULL UNIQUE,
					sent_at TEXT,
					sent_to TEXT,
					viewed_at TEXT,
					responded_at TEXT,
					created_by INTEGER,
					created_at TEXT NOT NULL,
					updated_at TEXT NOT NULL,
					FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE,
					FOREIGN KEY (client_id) REFERENCES clients(id),
					FOREIGN KEY (created_by) REFERENCES users(id)
				);

				CREATE TABLE IF NOT EXISTS quote_items (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					quote_id INTEGER NOT NULL,
					position INTEGER NOT NULL DEFAULT 0,
					description TEXT NOT NULL,
					quantity REAL NOT NULL DEFAULT 1,
					unit TEXT NOT NULL DEFAULT 'un',
					unit_price INTEGER NOT NULL DEFAULT 0,
					total INTEGER NOT NULL DEFAULT 0,
					FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
				);

				CREATE TABLE IF NOT EXISTS activities (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					client_id INTEGER,
					deal_id INTEGER,
					quote_id INTEGER,
					user_id INTEGER,
					type TEXT NOT NULL,
					message TEXT NOT NULL,
					created_at TEXT NOT NULL
				);

				CREATE INDEX IF NOT EXISTS idx_deals_stage ON deals(stage);
				CREATE INDEX IF NOT EXISTS idx_deals_client ON deals(client_id);
				CREATE INDEX IF NOT EXISTS idx_quotes_deal ON quotes(deal_id);
				CREATE INDEX IF NOT EXISTS idx_quotes_token ON quotes(token);
				CREATE INDEX IF NOT EXISTS idx_activities_deal ON activities(deal_id);
				SQL
			);
			$pdo->exec('PRAGMA user_version = 1');
			$version = 1;
		}

		if ($version < 2) {
			$cols = $pdo->query('PRAGMA table_info(deals)')->fetchAll();
			$names = array_column($cols, 'name');
			if (!in_array('job_status', $names, true)) {
				$pdo->exec("ALTER TABLE deals ADD COLUMN job_status TEXT NOT NULL DEFAULT 'consulta'");
			}
			if (!in_array('site_address', $names, true)) {
				$pdo->exec('ALTER TABLE deals ADD COLUMN site_address TEXT');
			}
			if (!in_array('visit_at', $names, true)) {
				$pdo->exec('ALTER TABLE deals ADD COLUMN visit_at TEXT');
			}
			$pdo->exec('PRAGMA user_version = 2');
			$version = 2;
		}

		if ($version < 3) {
			$pdo->exec(
				<<<'SQL'
				CREATE TABLE IF NOT EXISTS mailboxes (
					user_id INTEGER PRIMARY KEY,
					email TEXT NOT NULL,
					password_enc TEXT NOT NULL,
					smtp_host TEXT NOT NULL,
					smtp_port INTEGER NOT NULL DEFAULT 465,
					imap_host TEXT NOT NULL,
					imap_port INTEGER NOT NULL DEFAULT 993,
					sent_folder TEXT NOT NULL DEFAULT 'Sent',
					last_sync TEXT,
					FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
				);

				CREATE TABLE IF NOT EXISTS mail_messages (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					user_id INTEGER NOT NULL,
					folder TEXT NOT NULL,
					uid INTEGER,
					message_id TEXT,
					in_reply_to TEXT,
					from_email TEXT,
					from_name TEXT,
					to_email TEXT,
					subject TEXT,
					body_text TEXT,
					body_html TEXT,
					sent_at TEXT,
					seen INTEGER NOT NULL DEFAULT 0,
					client_id INTEGER,
					created_at TEXT NOT NULL,
					FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
					FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
				);

				CREATE UNIQUE INDEX IF NOT EXISTS idx_mail_uid ON mail_messages(user_id, folder, uid);
				CREATE INDEX IF NOT EXISTS idx_mail_user_folder ON mail_messages(user_id, folder, sent_at);
				CREATE INDEX IF NOT EXISTS idx_mail_client ON mail_messages(client_id);
				SQL
			);
			$pdo->exec('PRAGMA user_version = 3');
			$version = 3;
		}

		if ($version < 4) {
			$cols = $pdo->query('PRAGMA table_info(users)')->fetchAll();
			$names = array_column($cols, 'name');
			if (!in_array('signature', $names, true)) {
				$pdo->exec('ALTER TABLE users ADD COLUMN signature TEXT');
			}
			$pdo->exec('PRAGMA user_version = 4');
		}
	}
}
