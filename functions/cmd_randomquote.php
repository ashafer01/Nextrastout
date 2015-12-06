//<?php

log::trace('entered f::cmd_randomquote()');
list($_CMD, $params, $_i) = $_ARGV;

$quote_where = f::quote_where($params);
$channel = dbescape($_i['args'][0]);

if ($quote_where === null) {
	$quote_where = '1=1';
}

$q = Nextrastout::$db->pg_query("SELECT * FROM quotedb WHERE channel='$channel' AND $quote_where ORDER BY RANDOM() LIMIT 1",
	'randomquote query');
if ($q === false) {
	$say = 'Query failed';
} elseif (pg_num_rows($q) == 0) {
	log::debug('No results for randomquote query');
	$say = 'No results';
} else {
	$qr = pg_fetch_assoc($q);
	$say = "Quote #{$qr['id']}: \"{$qr['quote']}\" set by {$qr['set_by']} ({$qr['set_time']})";
}

$_i['handle']->say($_i['reply_to'], $say);
