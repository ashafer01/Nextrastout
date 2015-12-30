//<?php

log::trace('entering f::cmd_get_shorten_key()');
list($_CMD, $_ARG, $_i) = $_ARGV;

if (!$_i['in_pm']) {
	$say = 'This function must be used in PM';
} else {
	$nick = dbescape($_i['hostmask']->nick);
	$q = Nextrastout::$db->pg_query("SELECT shorten_key FROM shorten_keys WHERE nick='$nick'");
	if ($q === false) {
		$say = 'Query failed';
	} elseif (pg_num_rows($q) > 0) {
		$qr = pg_fetch_assoc($q);
		$say = "Your shorten key: {$qr['shorten_key']}";
	} else {
		$q = Nextrastout::$db->pg_query("INSERT INTO shorten_keys (nick) VALUES ('$nick') RETURNING shorten_key", 'get new shorten key');
		if ($q === false) {
			$say = 'Query failed';
		} else {
			$qr = pg_fetch_assoc($q);
			$say = "Your new shorten key: {$qr['shorten_key']}";
		}
	}
}

$_i['handle']->say($_i['reply_to'], $say);
