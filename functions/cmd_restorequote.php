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
	$q = Nextrastout::$db->pg_query("INSERT INTO quotedb SELECT * FROM quotedb_deleted WHERE id=$quote_id",
		'restorequote query');
	if ($q === false) {
		$say = 'Query failed';
	} elseif (pg_affected_rows($q) == 0) {
		log::debug('No affected rows for restorequote query');
		$say = 'Deleted quote not found';
	} else {
		$say = "Deleted quote #$quote_id has been restored";

		Nextrastout::$db->pg_query("DELETE FROM quotedb_deleted WHERE id=$quote_id",
			'restorequote/delete backup query');
	}
}

$_i['handle']->say($_i['reply_to'], $say);
