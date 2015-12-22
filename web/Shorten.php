<?php

require_once __DIR__ . '/../lib/Nextrastout.class.php';
require_once __DIR__ . '/../lib/utils.php';
require_once __DIR__ . '/../lib/log.php';

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

function show($text) {
	header('HTTP/1.1 200 Text Snippet');
	header('Content-Type: text/plain');
	echo $text;
}

Nextrastout::dbconnect();

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
