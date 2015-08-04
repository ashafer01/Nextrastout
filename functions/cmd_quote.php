//<?php

log::trace('entered f::cmd_quote()');
list($_CMD, $params, $_i) = $_ARGV;

if (preg_match('/^(\d+)$/', $params) === 1) {
	log::debug('Got a number, calling f::cmd_getquote()');
	return f::cmd_getquote($_CMD, $params, $_i);
}

log::trace('Storing new quote');

$quote = dbescape($params);
$set_by = dbescape($_i['hostmask']->nick);
$channel = dbescape($_i['args'][0]);

$query = "INSERT INTO quotedb (quote, set_by, channel) VALUES ('$quote', '$set_by', '$channel') RETURNING id";
log::debug("new quote query >> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("Query failed");
	log::error(pg_last_error());
	$say = 'Query failed';
} else {
	log::debug('new quote query ok');

	$qr = pg_fetch_assoc($q);
	$_i['handle']->say($_i['reply_to'], "Quote #{$qr['id']} added!");
}
