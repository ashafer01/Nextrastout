//<?php

log::trace('entered f::shorten()');
list($url) = $_ARGV;

$url = dbescape($url);
$q = Nextrastout::$db->pg_query("SELECT l FROM shorten WHERE url='$url'", 'check existing short url');
if ($q === false) {
	$ret = f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	$q = Nextrastout::$db->pg_query("INSERT INTO shorten (url) VALUES ('$url') RETURNING l", 'shorten url');
	if ($q === false) {
		$ret = f::FALSE;
	} else {
		$qr = pg_fetch_assoc($q);
		$ret = Nextrastout::$conf->shorten_base . $qr['l'];
	}
} else {
	$qr = pg_fetch_assoc($q);
	$ret = Nextrastout::$conf->shorten_base . $qr['l'];
}

return $ret;
