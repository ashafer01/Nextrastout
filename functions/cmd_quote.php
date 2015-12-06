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

$q = Nextrastout::$db->pg_query("INSERT INTO quotedb (quote, set_by, channel) VALUES ('$quote', '$set_by', '$channel') RETURNING id", 'new quote query');
if ($q === false) {
	$say = 'Query failed';
} else {
	$qr = pg_fetch_assoc($q);
	$_i['handle']->say($_i['reply_to'], "Quote #{$qr['id']} added!");
}
