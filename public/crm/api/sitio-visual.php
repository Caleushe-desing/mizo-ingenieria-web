<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/src/Autoload.php';

use MizoCrm\Auth;
use MizoCrm\Csrf;
use MizoCrm\Models\SitePage;

session_name('mizo_crm');
session_start([
	'cookie_httponly' => true,
	'cookie_samesite' => 'Lax',
	'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
	echo json_encode([
		'ok' => true,
		'admin' => Auth::isAdmin(),
		'csrf' => Auth::isAdmin() ? Csrf::token() : '',
	], JSON_UNESCAPED_UNICODE);
	exit;
}

if ($method !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
	exit;
}

if (!Auth::isAdmin()) {
	http_response_code(401);
	echo json_encode(['ok' => false, 'error' => 'Inicia sesión como administrador.'], JSON_UNESCAPED_UNICODE);
	exit;
}

$sent = (string) ($_POST['_csrf'] ?? '');
if ($sent === '' || !hash_equals(Csrf::token(), $sent)) {
	http_response_code(419);
	echo json_encode(['ok' => false, 'error' => 'Sesión expirada. Recarga la página.'], JSON_UNESCAPED_UNICODE);
	exit;
}

$action = (string) ($_POST['action'] ?? 'publicar');
$path = SitePage::normalizePath((string) ($_POST['path'] ?? '/'));
if (!isset(SitePage::catalog()[$path])) {
	http_response_code(422);
	echo json_encode(['ok' => false, 'error' => 'Esa página no se puede editar.'], JSON_UNESCAPED_UNICODE);
	exit;
}

if ($action === 'restaurar') {
	SitePage::clear($path);
	echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
	exit;
}

if ($action === 'restaurar-chrome') {
	SitePage::clear('#chrome');
	echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
	exit;
}

$html = sanitizeFragment((string) ($_POST['html'] ?? ''));
if ($html === '' || strlen($html) > 800000) {
	http_response_code(422);
	echo json_encode(['ok' => false, 'error' => 'El contenido de la página no se pudo guardar.'], JSON_UNESCAPED_UNICODE);
	exit;
}
SitePage::saveVisual($path, $html);

if ((string) ($_POST['chrome'] ?? '') === '1') {
	$header = sanitizeFragment((string) ($_POST['header'] ?? ''));
	$footer = sanitizeFragment((string) ($_POST['footer'] ?? ''));
	if ($header !== '' && $footer !== '' && strlen($header) < 400000 && strlen($footer) < 400000) {
		SitePage::saveChrome($header, $footer);
	}
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);

function sanitizeFragment(string $html): string
{
	$html = trim($html);
	if ($html === '') {
		return '';
	}
	$dom = new DOMDocument();
	$prev = libxml_use_internal_errors(true);
	$dom->loadHTML('<?xml encoding="UTF-8"><body><div id="mizo-root">' . $html . '</div></body>', LIBXML_HTML_NODEFDTD);
	libxml_clear_errors();
	libxml_use_internal_errors($prev);
	$root = $dom->getElementById('mizo-root');
	if (!$root) {
		return '';
	}
	$drop = ['script', 'iframe', 'object', 'embed', 'link', 'meta', 'base'];
	$xpath = new DOMXPath($dom);
	foreach ($xpath->query('//*[@data-editor-ui]') ?: [] as $node) {
		$node->parentNode?->removeChild($node);
	}
	$walker = [];
	foreach ($root->getElementsByTagName('*') as $el) {
		$walker[] = $el;
	}
	foreach ($walker as $el) {
		if (!$el->parentNode) {
			continue;
		}
		if (in_array(strtolower($el->tagName), $drop, true)) {
			$el->parentNode->removeChild($el);
			continue;
		}
		if ($el->hasAttributes()) {
			$remove = [];
			foreach ($el->attributes as $attr) {
				$name = strtolower($attr->name);
				$value = $attr->value;
				if (str_starts_with($name, 'on') || $name === 'contenteditable' || $name === 'srcdoc') {
					$remove[] = $attr->name;
					continue;
				}
				if (in_array($name, ['href', 'src', 'action'], true) && preg_match('/^\s*javascript\s*:/i', $value)) {
					$remove[] = $attr->name;
					continue;
				}
				if ($name === 'src' && $value !== '' && !preg_match('#^(?:/|https?:|data:image/)#i', $value)) {
					$remove[] = $attr->name;
				}
				if ($name === 'href' && $value !== '' && !preg_match('#^(?:/|https?:|mailto:|tel:|\#)#i', $value)) {
					$remove[] = $attr->name;
				}
			}
			foreach ($remove as $name) {
				$el->removeAttribute($name);
			}
		}
	}
	$out = '';
	foreach ($root->childNodes as $child) {
		$out .= $dom->saveHTML($child);
	}
	return $out;
}
