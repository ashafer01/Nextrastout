<?php

require_once __DIR__ . '/../lib/Nextrastout.class.php';
require_once __DIR__ . '/../lib/utils.php';
require_once __DIR__ . '/../lib/log.php';
require_once __DIR__ . '/../lib/functions.php';

function noop($_, $__) {
	return null;
}
log::set_logger('noop');

function fail($header) {
	header($header);
	echo "<!DOCTYPE html><html><body><h1>$header</h1></body></html>";
}

function redir($url) {
	header('HTTP/1.1 301 Shortened URL');
	header("Location: $url");
}

function show($text, $hdr = 'Text Snippet') {
	header("HTTP/1.1 200 $hdr");
	header('Content-Type: text/plain');
	echo $text;
}

Nextrastout::load_conf();
Nextrastout::dbconnect();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
	if (!isset($_GET['l'])) {
		fail('HTTP/1.1 400 Bad Request');
	} else {
		$l = dbescape($_GET['l']);
		$q = Nextrastout::$db->pg_query("SELECT url FROM shorten WHERE l='$l'");
		if ($q === false) {
			fail('HTTP/1.1 500 Query Failed');
		} elseif (pg_num_rows($q) == 0) {
			fail('HTTP/1.1 404 Not Found');
		} else {
			$qr = pg_fetch_assoc($q);
			$u = parse_url($qr['url']);
			if (isset($u['scheme']) && ($u['scheme'] != 'http') && ($u['scheme'] != 'https')) {
				show($qr['url']);
			} elseif (!isset($u['host'])) {
				show($qr['url']);
			} else {
				redir($qr['url']);
			}
		}
	}
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
	if (!isset($_POST['shorten_key'])) {
		fail('HTTP/1.1 400 Missing shorten_key Parameter');
	} elseif (!isset($_POST['text'])) {
		fail('HTTP/1.1 400 Missing text Parameter');
	} else {
		$key = dbescape($_POST['shorten_key']);
		$url = dbescape($_POST['text']);
		$q = Nextrastout::$db->pg_query("SELECT 1 FROM shorten_keys WHERE shorten_key='$key'");
		if ($q === false) {
			fail('HTTP/1.1 500 Query Failed');
		} elseif (pg_num_rows($q) == 0) {
			fail('HTTP/1.1 403 Unauthorized');
		} else {
			show(f::shorten($url, $key), 'New Short URL');
		}
	}
} else {
	fail('HTTP/1.1 405 Method Not Allowed');
}
