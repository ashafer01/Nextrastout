//<?php

log::trace('entered f::cmd_getquote()');
list($_CMD, $params, $_i) = $_ARGV;

if (preg_match('/^(\d+)$/', $params) !== 1) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a number');
	return f::FALSE;
}

$quote_id = dbescape($params);

$query = "SELECT * FROM quotedb WHERE id=$quote_id";
log::debug("getquote query >> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("Query failed");
	log::error(pg_last_error());
	$say = 'Query failed';
} elseif (pg_num_rows($q) == 0) {
	log::debug('No results for getquote query');
	$say = 'No results';
} else {
	log::debug('getquote query ok');

	$qr = pg_fetch_assoc($q);
	$say = "Quote #{$qr['id']}: \"{$qr['quote']}\" set by {$qr['set_by']} ({$qr['set_time']})";
}

$_i['handle']->say($_i['reply_to'], $say);
