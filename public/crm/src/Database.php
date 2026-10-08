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
		self::ensureMailSchema($pdo);
		self::ensureQuoteServiceSchema($pdo);
		self::ensureQuoteSendSchema($pdo);
		try {
			self::ensureMarketingSchema($pdo);
		} catch (\Throwable) {
			// Módulo marketing opcional en installs parciales.
		}
		try {
			Models\Pipeline::ensureRoles();
		} catch (\Throwable) {
			// Tablero aún no migrado en installs parciales.
		}
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
					name TEXT NOT NULL DEFAULT '',
					description TEXT NOT NULL,
					quantity REAL NOT NULL DEFAULT 1,
					unit TEXT NOT NULL DEFAULT 'un',
					cost_price INTEGER NOT NULL DEFAULT 0,
					margin_percent REAL NOT NULL DEFAULT 0,
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

		if ($version < 5) {
			$pdo->exec(
				<<<'SQL'
				CREATE TABLE IF NOT EXISTS chat_messages (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					from_user_id INTEGER NOT NULL,
					to_user_id INTEGER NOT NULL,
					body TEXT NOT NULL,
					seen INTEGER NOT NULL DEFAULT 0,
					created_at TEXT NOT NULL,
					FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
					FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
				);
				CREATE INDEX IF NOT EXISTS idx_chat_peer ON chat_messages(from_user_id, to_user_id, id);
				CREATE INDEX IF NOT EXISTS idx_chat_to_seen ON chat_messages(to_user_id, seen, id);
				SQL
			);
			$cols = $pdo->query('PRAGMA table_info(quotes)')->fetchAll();
			$names = array_column($cols, 'name');
			if (!in_array('updated_by', $names, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN updated_by INTEGER');
			}
			$pdo->exec('PRAGMA user_version = 5');
		}

		if ($version < 6) {
			$cols = $pdo->query('PRAGMA table_info(mail_messages)')->fetchAll();
			$names = array_column($cols, 'name');
			if (!in_array('important', $names, true)) {
				$pdo->exec('ALTER TABLE mail_messages ADD COLUMN important INTEGER NOT NULL DEFAULT 0');
			}
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_mail_important ON mail_messages(user_id, folder, important, sent_at)');
			$pdo->exec('PRAGMA user_version = 6');
		}

		if ($version < 7) {
			// La sync antigua con RFC822 marcaba todos como leídos en el servidor/CRM.
			// Si no queda ningún no-leído en bandeja, restauramos el estado para que se note la diferencia.
			try {
				$inbox = (int) $pdo->query("SELECT COUNT(*) FROM mail_messages WHERE folder = 'inbox'")->fetchColumn();
				$unread = (int) $pdo->query("SELECT COUNT(*) FROM mail_messages WHERE folder = 'inbox' AND seen = 0")->fetchColumn();
				if ($inbox > 0 && $unread === 0) {
					$pdo->exec("UPDATE mail_messages SET seen = 0 WHERE folder = 'inbox'");
				}
			} catch (\Throwable) {
			}
			$pdo->exec('PRAGMA user_version = 7');
		}

		if ($version < 8) {
			$pdo->exec(
				<<<'SQL'
				CREATE TABLE IF NOT EXISTS client_contacts (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					client_id INTEGER NOT NULL,
					name TEXT NOT NULL DEFAULT '',
					email TEXT NOT NULL DEFAULT '',
					phone TEXT NOT NULL DEFAULT '',
					title TEXT NOT NULL DEFAULT '',
					is_primary INTEGER NOT NULL DEFAULT 0,
					created_at TEXT NOT NULL,
					FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
				);
				CREATE INDEX IF NOT EXISTS idx_contacts_client ON client_contacts(client_id);
				CREATE INDEX IF NOT EXISTS idx_contacts_email ON client_contacts(email);

				CREATE TABLE IF NOT EXISTS mail_attachments (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					message_id INTEGER NOT NULL,
					user_id INTEGER NOT NULL,
					filename TEXT NOT NULL,
					mime TEXT NOT NULL DEFAULT 'application/octet-stream',
					size INTEGER NOT NULL DEFAULT 0,
					storage_path TEXT NOT NULL,
					created_at TEXT NOT NULL,
					FOREIGN KEY (message_id) REFERENCES mail_messages(id) ON DELETE CASCADE,
					FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
				);
				CREATE INDEX IF NOT EXISTS idx_mail_attach_msg ON mail_attachments(message_id);
				SQL
			);
			$cols = $pdo->query('PRAGMA table_info(mail_messages)')->fetchAll();
			$names = array_column($cols, 'name');
			if (!in_array('cc_email', $names, true)) {
				$pdo->exec('ALTER TABLE mail_messages ADD COLUMN cc_email TEXT');
			}
			if (!in_array('has_attachments', $names, true)) {
				$pdo->exec('ALTER TABLE mail_messages ADD COLUMN has_attachments INTEGER NOT NULL DEFAULT 0');
			}
			// Migra el contacto principal desde la ficha del cliente.
			$clients = $pdo->query('SELECT id, contact_name, email, phone, created_at FROM clients')->fetchAll();
			$ins = $pdo->prepare(
				'INSERT INTO client_contacts (client_id, name, email, phone, title, is_primary, created_at)
				 SELECT ?, ?, ?, ?, ?, 1, ?
				 WHERE NOT EXISTS (SELECT 1 FROM client_contacts WHERE client_id = ?)'
			);
			foreach ($clients as $c) {
				$email = mb_strtolower(trim((string) ($c['email'] ?? '')));
				$name = trim((string) ($c['contact_name'] ?? ''));
				$phone = trim((string) ($c['phone'] ?? ''));
				if ($name === '' && $email === '' && $phone === '') {
					continue;
				}
				$now = (string) ($c['created_at'] ?? date('c'));
				$ins->execute([(int) $c['id'], $name !== '' ? $name : 'Contacto', $email, $phone, '', $now, (int) $c['id']]);
			}
			$pdo->exec('PRAGMA user_version = 8');
		}

		if ($version < 9) {
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS deal_contacts (
					deal_id INTEGER NOT NULL,
					contact_id INTEGER NOT NULL,
					PRIMARY KEY (deal_id, contact_id)
				)'
			);
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_deal_contacts_contact ON deal_contacts(contact_id)');
			$pdo->exec('PRAGMA user_version = 9');
			$version = 9;
		}

		if ($version < 10) {
			$cols = $pdo->query('PRAGMA table_info(quotes)')->fetchAll();
			$names = array_column($cols, 'name');
			if (!in_array('contact_id', $names, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN contact_id INTEGER');
			}
			$pdo->exec('PRAGMA user_version = 10');
			$version = 10;
		}

		if ($version < 11 && function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor')) {
			try {
				\MizoCrm\Models\User::optimizeSignatureDirectory();
			} catch (\Throwable) {
			}
			$pdo->exec('PRAGMA user_version = 11');
			$version = 11;
		}

		if ($version < 12) {
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_activities_client ON activities(client_id)');
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_activities_user ON activities(user_id)');
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_activities_type_created ON activities(type, created_at)');
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_quotes_author ON quotes(created_by)');
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_deals_owner ON deals(owner_id)');
			$pdo->exec('PRAGMA user_version = 12');
			$version = 12;
		}

		if ($version < 13) {
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS board_stages (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					slug TEXT NOT NULL UNIQUE,
					label TEXT NOT NULL,
					color TEXT NOT NULL DEFAULT \'#1c9bd8\',
					position INTEGER NOT NULL DEFAULT 0,
					kind TEXT NOT NULL DEFAULT \'open\',
					created_at TEXT NOT NULL
				)'
			);
			$insert = $pdo->prepare('INSERT INTO board_stages (slug, label, color, position, kind, created_at) VALUES (?, ?, ?, ?, ?, ?)');
			$now = date('c');
			$defaults = [
				['nuevo', 'Prospecto / Lead nuevo', '#1c9bd8', 1, 'open'],
				['contactado', 'Contacto / Llamada realizada', '#0b6ea8', 2, 'open'],
				['negociacion', 'Diagnóstico / Requerimiento', '#5b6b8c', 3, 'open'],
				['propuesta', 'Propuesta / Cotización enviada', '#f47b20', 4, 'open'],
				['ganado', 'Cierre ganado', '#1f8a4c', 5, 'won'],
				['perdido', 'Descartado / En pausa', '#8a9099', 6, 'lost'],
			];
			foreach ($defaults as $row) {
				$insert->execute([$row[0], $row[1], $row[2], $row[3], $row[4], $now]);
			}
			$pdo->exec('PRAGMA user_version = 13');
			$version = 13;
		}

		if ($version < 14) {
			$stageCols = array_column($pdo->query('PRAGMA table_info(board_stages)')->fetchAll(), 'name');
			if (!in_array('role', $stageCols, true)) {
				$pdo->exec("ALTER TABLE board_stages ADD COLUMN role TEXT NOT NULL DEFAULT ''");
			}
			$dealCols = array_column($pdo->query('PRAGMA table_info(deals)')->fetchAll(), 'name');
			if (!in_array('archived', $dealCols, true)) {
				$pdo->exec('ALTER TABLE deals ADD COLUMN archived INTEGER NOT NULL DEFAULT 0');
			}
			$pdo->exec("UPDATE board_stages SET role = 'sent' WHERE slug = 'propuesta' AND (role IS NULL OR role = '')");
			$pdo->exec("UPDATE board_stages SET label = 'Presupuesto enviado' WHERE slug = 'propuesta' AND label LIKE 'Propuesta / Cotiz%'");
			$wanted = [
				['accepted', 'presupuesto-aceptado', 'Presupuesto aceptado', '#1c9bd8'],
				['invoiced', 'proyecto-facturado', 'Proyecto facturado', '#0b6ea8'],
				['paid', 'factura-pagada', 'Factura pagada', '#1f8a4c'],
			];
			$missing = [];
			$check = $pdo->prepare('SELECT id, role FROM board_stages WHERE role = ? OR slug = ? LIMIT 1');
			$fillRole = $pdo->prepare("UPDATE board_stages SET role = ? WHERE slug = ? AND (role IS NULL OR role = '')");
			foreach ($wanted as $row) {
				$check->execute([$row[0], $row[1]]);
				$found = $check->fetch();
				if (!$found) {
					$missing[] = $row;
					continue;
				}
				$fillRole->execute([$row[0], $row[1]]);
			}
			if ($missing !== []) {
				$base = (int) $pdo->query("SELECT position FROM board_stages WHERE slug = 'propuesta'")->fetchColumn();
				if ($base > 0) {
					$shift = $pdo->prepare('UPDATE board_stages SET position = position + ? WHERE position > ?');
					$shift->execute([count($missing), $base]);
					$pos = $base;
				} else {
					$pos = (int) $pdo->query('SELECT COALESCE(MAX(position), 0) FROM board_stages')->fetchColumn();
				}
				$insertStage = $pdo->prepare('INSERT INTO board_stages (slug, label, color, position, kind, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
				$now = date('c');
				foreach ($missing as $row) {
					$pos++;
					$insertStage->execute([$row[1], $row[2], $row[3], $pos, 'open', $row[0], $now]);
				}
			}
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS sales_invoices (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					number TEXT NOT NULL,
					client_id INTEGER NOT NULL,
					deal_id INTEGER NOT NULL,
					quote_id INTEGER,
					net INTEGER NOT NULL DEFAULT 0,
					tax INTEGER NOT NULL DEFAULT 0,
					total INTEGER NOT NULL DEFAULT 0,
					status TEXT NOT NULL DEFAULT \'pending\',
					issued_on TEXT NOT NULL,
					paid_at TEXT,
					created_by INTEGER,
					created_at TEXT NOT NULL,
					updated_at TEXT NOT NULL
				)'
			);
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS purchase_invoices (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					supplier TEXT NOT NULL,
					number TEXT NOT NULL,
					deal_id INTEGER,
					net INTEGER NOT NULL DEFAULT 0,
					tax INTEGER NOT NULL DEFAULT 0,
					travel INTEGER NOT NULL DEFAULT 0,
					operations INTEGER NOT NULL DEFAULT 0,
					other_costs INTEGER NOT NULL DEFAULT 0,
					issued_on TEXT NOT NULL,
					created_by INTEGER,
					created_at TEXT NOT NULL
				)'
			);
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_sales_deal ON sales_invoices(deal_id)');
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_sales_client ON sales_invoices(client_id)');
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_purchase_deal ON purchase_invoices(deal_id)');
			$pdo->exec('PRAGMA user_version = 14');
			$version = 14;
		}

		if ($version < 15) {
			$quoteCols = array_column($pdo->query('PRAGMA table_info(quotes)')->fetchAll(), 'name');
			if (!in_array('revision', $quoteCols, true)) {
				$pdo->exec("ALTER TABLE quotes ADD COLUMN revision TEXT NOT NULL DEFAULT ''");
			}
			$pdo->exec('PRAGMA user_version = 15');
			$version = 15;
		}

		if ($version < 16) {
			$userCols = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
			if (!in_array('commission_rate', $userCols, true)) {
				$pdo->exec('ALTER TABLE users ADD COLUMN commission_rate REAL NOT NULL DEFAULT 0');
			}
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS accounting_settings (
					id INTEGER PRIMARY KEY CHECK (id = 1),
					income_tax_rate REAL NOT NULL DEFAULT 27
				)'
			);
			$pdo->exec('INSERT OR IGNORE INTO accounting_settings (id, income_tax_rate) VALUES (1, 27)');
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS company_obligations (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					kind TEXT NOT NULL,
					concept TEXT NOT NULL,
					notes TEXT NOT NULL DEFAULT \'\',
					net INTEGER NOT NULL DEFAULT 0,
					tax INTEGER NOT NULL DEFAULT 0,
					total INTEGER NOT NULL DEFAULT 0,
					status TEXT NOT NULL DEFAULT \'pending\',
					issued_on TEXT NOT NULL,
					due_on TEXT,
					paid_at TEXT,
					created_by INTEGER,
					created_at TEXT NOT NULL,
					updated_at TEXT NOT NULL
				)'
			);
			$pdo->exec('PRAGMA user_version = 16');
			$version = 16;
		}

		if ($version < 17) {
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS products (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					sku TEXT NOT NULL,
					nombre TEXT NOT NULL,
					descripcion TEXT NOT NULL,
					categoria TEXT NOT NULL,
					proveedor_empresa TEXT NOT NULL,
					proveedor_link TEXT NOT NULL,
					activo INTEGER NOT NULL DEFAULT 1,
					created_at TEXT NOT NULL,
					updated_at TEXT NOT NULL
				)'
			);
			$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_products_sku ON products(sku COLLATE NOCASE)');
			$pdo->exec('PRAGMA user_version = 17');
			$version = 17;
		}

		if ($version < 18) {
			$productCols = array_column($pdo->query('PRAGMA table_info(products)')->fetchAll(), 'name');
			if ($productCols !== [] && !in_array('imagenes', $productCols, true)) {
				$pdo->exec('ALTER TABLE products ADD COLUMN imagenes TEXT');
			}
			$pdo->exec('PRAGMA user_version = 18');
			$version = 18;
		}

		if ($version < 19) {
			$itemCols = array_column($pdo->query('PRAGMA table_info(quote_items)')->fetchAll(), 'name');
			if ($itemCols !== [] && !in_array('cost_price', $itemCols, true)) {
				$pdo->exec('ALTER TABLE quote_items ADD COLUMN cost_price INTEGER NOT NULL DEFAULT 0');
			}
			if ($itemCols !== [] && !in_array('margin_percent', $itemCols, true)) {
				$pdo->exec('ALTER TABLE quote_items ADD COLUMN margin_percent REAL NOT NULL DEFAULT 0');
			}
			$pdo->exec('PRAGMA user_version = 19');
			$version = 19;
		}

		if ($version < 20) {
			$itemCols = array_column($pdo->query('PRAGMA table_info(quote_items)')->fetchAll(), 'name');
			if ($itemCols !== [] && !in_array('name', $itemCols, true)) {
				$pdo->exec("ALTER TABLE quote_items ADD COLUMN name TEXT NOT NULL DEFAULT ''");
				$pdo->exec("UPDATE quote_items SET name = description WHERE TRIM(COALESCE(name, '')) = '' AND TRIM(COALESCE(description, '')) != ''");
			}
			$pdo->exec('PRAGMA user_version = 20');
			$version = 20;
		}

		if ($version < 21) {
			$productCols = array_column($pdo->query('PRAGMA table_info(products)')->fetchAll(), 'name');
			if ($productCols !== [] && !in_array('precio_compra_iva', $productCols, true)) {
				$pdo->exec('ALTER TABLE products ADD COLUMN precio_compra_iva INTEGER NOT NULL DEFAULT 0');
			}
			$itemCols = array_column($pdo->query('PRAGMA table_info(quote_items)')->fetchAll(), 'name');
			if ($itemCols !== [] && !in_array('product_id', $itemCols, true)) {
				$pdo->exec('ALTER TABLE quote_items ADD COLUMN product_id INTEGER');
			}
			$pdo->exec('PRAGMA user_version = 21');
			$version = 21;
		}

		if ($version < 22) {
			$itemCols = array_column($pdo->query('PRAGMA table_info(quote_items)')->fetchAll(), 'name');
			if ($itemCols !== [] && !in_array('service_breakdown', $itemCols, true)) {
				$pdo->exec('ALTER TABLE quote_items ADD COLUMN service_breakdown TEXT');
			}
			self::ensureProfessionalServiceProduct($pdo);
			$pdo->exec('PRAGMA user_version = 22');
			$version = 22;
		}

		if ($version < 23) {
			$quoteCols = array_column($pdo->query('PRAGMA table_info(quotes)')->fetchAll(), 'name');
			if ($quoteCols !== [] && !in_array('terms_text', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN terms_text TEXT NOT NULL DEFAULT \'\'');
			}
			if ($quoteCols !== [] && !in_array('about_text', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN about_text TEXT NOT NULL DEFAULT \'\'');
			}
			$pdo->exec('PRAGMA user_version = 23');
			$version = 23;
		}

		if ($version < 24) {
			$quoteCols = array_column($pdo->query('PRAGMA table_info(quotes)')->fetchAll(), 'name');
			if ($quoteCols !== [] && !in_array('last_mail_id', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN last_mail_id INTEGER');
			}
			if ($quoteCols !== [] && !in_array('last_email_html', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN last_email_html TEXT NOT NULL DEFAULT \'\'');
			}
			if ($quoteCols !== [] && !in_array('last_email_subject', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN last_email_subject TEXT NOT NULL DEFAULT \'\'');
			}
			if ($quoteCols !== [] && !in_array('sent_cc', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN sent_cc TEXT NOT NULL DEFAULT \'\'');
			}
			$pdo->exec('PRAGMA user_version = 24');
			$version = 24;
		}

		if ($version < 25) {
			self::ensureMarketingSchema($pdo);
			$pdo->exec('PRAGMA user_version = 25');
			$version = 25;
		}

		if ($version < 26) {
			self::ensureMarketingSchema($pdo);
			$pdo->exec('PRAGMA user_version = 26');
			$version = 26;
		}

		if ($version < 27) {
			self::ensureMarketingSchema($pdo);
			$pdo->exec('PRAGMA user_version = 27');
			$version = 27;
		}

		if ($version < 28) {
			self::ensureMarketingSchema($pdo);
			$pdo->exec('PRAGMA user_version = 28');
			$version = 28;
		}

		if ($version < 29) {
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS product_landing_features (
					product_id INTEGER NOT NULL,
					landing_slug TEXT NOT NULL,
					position INTEGER NOT NULL DEFAULT 0,
					PRIMARY KEY (product_id, landing_slug)
				)'
			);
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_product_landing_slug ON product_landing_features(landing_slug, position)');
			$pdo->exec('PRAGMA user_version = 29');
			$version = 29;
		}

		if ($version < 30) {
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS site_pages (
					path TEXT PRIMARY KEY,
					title TEXT NOT NULL DEFAULT \'\',
					description TEXT NOT NULL DEFAULT \'\',
					blocks TEXT NOT NULL DEFAULT \'[]\',
					active INTEGER NOT NULL DEFAULT 0,
					updated_at TEXT NOT NULL
				)'
			);
			$pdo->exec('PRAGMA user_version = 30');
		}
	}

	/** Plantillas y recursos del módulo Marketing (aditivo; no altera tablas existentes). */
	private static function ensureMarketingSchema(PDO $pdo): void
	{
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS marketing_templates (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				slug TEXT NOT NULL UNIQUE,
				name TEXT NOT NULL,
				subject TEXT NOT NULL,
				body TEXT NOT NULL,
				active INTEGER NOT NULL DEFAULT 1,
				position INTEGER NOT NULL DEFAULT 0,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)'
		);
		$exists = $pdo->prepare('SELECT id FROM marketing_templates WHERE slug = ? LIMIT 1');
		$insert = $pdo->prepare(
			'INSERT INTO marketing_templates (slug, name, subject, body, active, position, created_at, updated_at)
			 VALUES (?, ?, ?, ?, 1, ?, ?, ?)'
		);
		$now = date('c');
		$pos = 0;
		foreach (Models\MarketingTemplate::defaults() as $row) {
			$pos++;
			$exists->execute([(string) $row['slug']]);
			if ($exists->fetchColumn()) {
				continue;
			}
			$insert->execute([
				$row['slug'],
				$row['name'],
				$row['subject'],
				$row['body'],
				$pos,
				$now,
				$now,
			]);
		}

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS marketing_resources (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				kind TEXT NOT NULL,
				category TEXT NOT NULL,
				title TEXT NOT NULL,
				description TEXT NOT NULL DEFAULT \'\',
				file_path TEXT NOT NULL DEFAULT \'\',
				thumb_path TEXT NOT NULL DEFAULT \'\',
				original_name TEXT NOT NULL DEFAULT \'\',
				mime TEXT NOT NULL DEFAULT \'\',
				file_size INTEGER NOT NULL DEFAULT 0,
				design_json TEXT NOT NULL DEFAULT \'\',
				active INTEGER NOT NULL DEFAULT 1,
				position INTEGER NOT NULL DEFAULT 0,
				created_by INTEGER,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)'
		);
		$resCols = array_column($pdo->query('PRAGMA table_info(marketing_resources)')->fetchAll(), 'name');
		if ($resCols !== [] && !in_array('design_json', $resCols, true)) {
			$pdo->exec("ALTER TABLE marketing_resources ADD COLUMN design_json TEXT NOT NULL DEFAULT ''");
		}
		$pdo->exec('CREATE INDEX IF NOT EXISTS idx_mkt_resources_kind ON marketing_resources(kind, active)');
		$pdo->exec('CREATE INDEX IF NOT EXISTS idx_mkt_resources_cat ON marketing_resources(category, active)');

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS marketing_media (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				category TEXT NOT NULL,
				title TEXT NOT NULL,
				description TEXT NOT NULL DEFAULT \'\',
				file_path TEXT NOT NULL DEFAULT \'\',
				thumb_path TEXT NOT NULL DEFAULT \'\',
				original_name TEXT NOT NULL DEFAULT \'\',
				mime TEXT NOT NULL DEFAULT \'\',
				file_size INTEGER NOT NULL DEFAULT 0,
				active INTEGER NOT NULL DEFAULT 1,
				position INTEGER NOT NULL DEFAULT 0,
				created_by INTEGER,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)'
		);
		$pdo->exec('CREATE INDEX IF NOT EXISTS idx_mkt_media_cat ON marketing_media(category, active)');
	}

	/** Producto interno del cotizador: oculto en la web pública. */
	private static function ensureProfessionalServiceProduct(PDO $pdo): void
	{
		$sku = 'MIZO-SP';
		$exists = $pdo->prepare('SELECT id FROM products WHERE sku = ? COLLATE NOCASE LIMIT 1');
		$exists->execute([$sku]);
		if ($exists->fetchColumn()) {
			$pdo->prepare('UPDATE products SET activo = 0, updated_at = ? WHERE sku = ? COLLATE NOCASE')->execute([date('c'), $sku]);
			return;
		}
		$now = date('c');
		$pdo->prepare(
			'INSERT INTO products (sku, nombre, descripcion, categoria, proveedor_empresa, proveedor_link, activo, precio_compra_iva, imagenes, created_at, updated_at)
			 VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?)'
		)->execute([
			$sku,
			'Servicio profesional',
			'Servicio profesional Mizo. El costo se arma en la cotización con ítems netos y se convierte a c/IVA.',
			'Servicios',
			'Mizo Ingeniería',
			'',
			'[]',
			$now,
			$now,
		]);
	}

	/** Bitácora de envíos/reenvíos de cotización para auditoría. */
	private static function ensureQuoteSendSchema(PDO $pdo): void
	{
		try {
			$pdo->exec(
				<<<'SQL'
				CREATE TABLE IF NOT EXISTS quote_sends (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					quote_id INTEGER NOT NULL,
					user_id INTEGER,
					contact_id INTEGER,
					to_email TEXT NOT NULL,
					cc_email TEXT NOT NULL DEFAULT '',
					kind TEXT NOT NULL DEFAULT 'send',
					mail_id INTEGER,
					had_pdf INTEGER NOT NULL DEFAULT 0,
					sent_at TEXT NOT NULL,
					FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
				);
				CREATE INDEX IF NOT EXISTS idx_quote_sends_quote ON quote_sends(quote_id);
				CREATE INDEX IF NOT EXISTS idx_quote_sends_sent ON quote_sends(sent_at);
				SQL
			);
		} catch (\Throwable) {
			// Installs parciales sin tabla quotes todavía.
		}
	}

	/** Garantiza desglose de servicio y textos de página 2 aunque el schema venga atrasado. */
	private static function ensureQuoteServiceSchema(PDO $pdo): void
	{
		try {
			$itemCols = array_column($pdo->query('PRAGMA table_info(quote_items)')->fetchAll(), 'name');
			if ($itemCols !== [] && !in_array('service_breakdown', $itemCols, true)) {
				$pdo->exec('ALTER TABLE quote_items ADD COLUMN service_breakdown TEXT');
			}
			$quoteCols = array_column($pdo->query('PRAGMA table_info(quotes)')->fetchAll(), 'name');
			if ($quoteCols !== [] && !in_array('terms_text', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN terms_text TEXT NOT NULL DEFAULT \'\'');
			}
			if ($quoteCols !== [] && !in_array('about_text', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN about_text TEXT NOT NULL DEFAULT \'\'');
			}
			if ($quoteCols !== [] && !in_array('last_mail_id', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN last_mail_id INTEGER');
			}
			if ($quoteCols !== [] && !in_array('last_email_html', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN last_email_html TEXT NOT NULL DEFAULT \'\'');
			}
			if ($quoteCols !== [] && !in_array('last_email_subject', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN last_email_subject TEXT NOT NULL DEFAULT \'\'');
			}
			if ($quoteCols !== [] && !in_array('sent_cc', $quoteCols, true)) {
				$pdo->exec('ALTER TABLE quotes ADD COLUMN sent_cc TEXT NOT NULL DEFAULT \'\'');
			}
			self::ensureProfessionalServiceProduct($pdo);
		} catch (\Throwable) {
			// Si aún no existen tablas de cotización, la migración normal las crea.
		}
	}

	/** Garantiza columnas/tablas de correo y contactos aunque un deploy parcial deje el schema atrasado. */
	private static function ensureMailSchema(PDO $pdo): void
	{
		try {
			$cols = $pdo->query('PRAGMA table_info(mail_messages)')->fetchAll();
			if ($cols === []) {
				return;
			}
			$names = array_column($cols, 'name');
			if (!in_array('important', $names, true)) {
				$pdo->exec('ALTER TABLE mail_messages ADD COLUMN important INTEGER NOT NULL DEFAULT 0');
			}
			if (!in_array('cc_email', $names, true)) {
				$pdo->exec('ALTER TABLE mail_messages ADD COLUMN cc_email TEXT');
			}
			if (!in_array('has_attachments', $names, true)) {
				$pdo->exec('ALTER TABLE mail_messages ADD COLUMN has_attachments INTEGER NOT NULL DEFAULT 0');
			}
			$boxCols = $pdo->query('PRAGMA table_info(mailboxes)')->fetchAll();
			$boxNames = array_column($boxCols, 'name');
			if ($boxNames !== [] && !in_array('junk_folder', $boxNames, true)) {
				$pdo->exec("ALTER TABLE mailboxes ADD COLUMN junk_folder TEXT NOT NULL DEFAULT ''");
			}
			$pdo->exec('CREATE INDEX IF NOT EXISTS idx_mail_important ON mail_messages(user_id, folder, important, sent_at)');
			$pdo->exec(
				<<<'SQL'
				CREATE TABLE IF NOT EXISTS client_contacts (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					client_id INTEGER NOT NULL,
					name TEXT NOT NULL DEFAULT '',
					email TEXT NOT NULL DEFAULT '',
					phone TEXT NOT NULL DEFAULT '',
					title TEXT NOT NULL DEFAULT '',
					is_primary INTEGER NOT NULL DEFAULT 0,
					created_at TEXT NOT NULL,
					FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
				);
				CREATE INDEX IF NOT EXISTS idx_contacts_client ON client_contacts(client_id);
				CREATE TABLE IF NOT EXISTS mail_attachments (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					message_id INTEGER NOT NULL,
					user_id INTEGER NOT NULL,
					filename TEXT NOT NULL,
					mime TEXT NOT NULL DEFAULT 'application/octet-stream',
					size INTEGER NOT NULL DEFAULT 0,
					storage_path TEXT NOT NULL,
					created_at TEXT NOT NULL,
					FOREIGN KEY (message_id) REFERENCES mail_messages(id) ON DELETE CASCADE,
					FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
				);
				CREATE INDEX IF NOT EXISTS idx_mail_attach_msg ON mail_attachments(message_id);
				SQL
			);
		} catch (\Throwable) {
			// Si la tabla aún no existe, migrate la creará en el próximo arranque limpio.
		}
	}
}
