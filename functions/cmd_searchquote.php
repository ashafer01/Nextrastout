//<?php

log::trace('entered f::cmd_searchquote()');
list($_CMD, $params, $_i) = $_ARGV;

$quote_where = f::quote_where($params);
$channel = dbescape($_i['args'][0]);

if ($quote_where === null) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a log-style query');
	return f::FALSE;
}

$q = Nextrastout::$db->pg_query("SELECT id FROM quotedb WHERE channel='$channel' AND $quote_where ORDER BY id",
	'searchquote query');
if ($q === false) {
	$say = 'Query failed';
} elseif (($num_results = pg_num_rows($q)) == 0) {
	log::debug('No results for searchquote query');
	$say = 'No results';
} else {
	$ids = array();
	while ($qr = pg_fetch_assoc($q)) {
		$ids[] = $qr['id'];
	}

	if (count($ids) > 1) {
		$say = f::pack_list("$num_results Matching quotes: ", $ids, $_i);
	} else {
		log::debug('Only one searchquote result, calling getquote');
		return f::cmd_getquote($_CMD, $ids[0], $_i);
	}
}

$_i['handle']->say($_i['reply_to'], $say);
