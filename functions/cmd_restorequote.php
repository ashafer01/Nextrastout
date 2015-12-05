//<?php

log::trace('entered f::cmd_restorequote()');
list($_CMD, $params, $_i) = $_ARGV;

if (preg_match('/^(\d+)$/', $params) !== 1) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a number');
	return f::FALSE;
}

$quote_id = dbescape($params);

if (!in_array($_i['hostmask']->nick, Nextrastout::$conf->quotes->admins)) {
	log::info('Not a quote admin');
	$say = "Only quote admins can restore deleted quotes";
} else {
	$query = "INSERT INTO quotedb SELECT * FROM quotedb_deleted WHERE id=$quote_id";
	log::debug("restorequote query >> $query");
	$q = pg_query(Nextrastout::$db, $query);
	if ($q === false) {
		log::error("Query failed");
		log::error(pg_last_error());
		$say = 'Query failed';
	} elseif (pg_affected_rows($q) == 0) {
		log::debug('No affected rows for restorequote query');
		$say = 'Deleted quote not found';
	} else {
		log::debug('restorequote query ok');
		$say = "Deleted quote #$quote_id has been restored";

		$query = "DELETE FROM quotedb_deleted WHERE id=$quote_id";
		log::debug("restorequote/delete backup query >> $query");
		$q = pg_query(Nextrastout::$db, $query);
		if ($q === false) {
			log::error('Failed to delete quote backup after restore');
			log::error(pg_last_error());
		}
	}
}

$_i['handle']->say($_i['reply_to'], $say);
