//<?php

log::trace('entered f::cmd_searchquote()');
list($_CMD, $params, $_i) = $_ARGV;

$quote_where = f::quote_where($params);

if ($quote_where === null) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a log-style query');
	return f::FALSE;
}

$query = "SELECT id FROM quotedb WHERE $quote_where ORDER BY id";
log::debug("searchquote query >> $query");
$q = pg_query(ExtraServ::$db, $query);

if ($q === false) {
	log::error("Query failed");
	log::error(pg_last_error());
	$say = 'Query failed';
} elseif (($num_results = pg_num_rows($q)) == 0) {
	log::debug('No results for searchquote query');
	$say = 'No results';
} else {
	log::debug('searchquote query ok');

	$ids = array();
	while ($qr = pg_fetch_assoc($q)) {
		$ids[] = $qr['id'];
	}

	$say = f::pack_list("$num_results Matching quotes: ", $ids, $_i);
}

$_i['handle']->say($_i['reply_to'], $say);
