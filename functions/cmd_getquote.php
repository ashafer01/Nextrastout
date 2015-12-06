//<?php

log::trace('entered f::cmd_getquote()');
list($_CMD, $params, $_i) = $_ARGV;

if (preg_match('/^(\d+)$/', $params) !== 1) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a number');
	return f::FALSE;
}

$quote_id = dbescape($params);
$channel = dbescape($_i['args'][0]);

$q = Nextrastout::$db->pg_query("SELECT * FROM quotedb WHERE id=$quote_id AND channel='$channel'", 'getquote query');
if ($q === false) {
	$say = 'Query failed';
} elseif (pg_num_rows($q) == 0) {
	log::debug('No results for getquote query');
	$say = 'No results';
} else {
	$qr = pg_fetch_assoc($q);
	$say = "Quote #{$qr['id']}: \"{$qr['quote']}\" set by {$qr['set_by']} ({$qr['set_time']})";
}

$_i['handle']->say($_i['reply_to'], $say);
