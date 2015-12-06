//<?php

log::trace('entered f::delquote()');
list($_CMD, $params, $_i) = $_ARGV;

if (preg_match('/^(\d+)$/', $params) !== 1) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a number');
	return f::FALSE;
}

$quote_id = dbescape($params);

$q = Nextrastout::$db->pg_query("SELECT set_by FROM quotedb WHERE id=$quote_id", 'getquote/delquote query');
if ($q === false) {
	$say = 'Query failed';
} elseif (pg_num_rows($q) == 0) {
	log::debug('No results for getquote/delquote query');
	$say = 'Quote not found';
} else {
	$qr = pg_fetch_assoc($q);

	if (!in_array($_i['hostmask']->nick, Nextrastout::$conf->quotes->admins) && (strtolower($_i['hostmask']->nick) != $qr['set_by'])) {
		log::info('Nickname does not match set_by for quote delete and not an admin');
		$say = "Only {$qr['set_by']} can delete quote #$quote_id";
	} else {
		$q = Nextrastout::$db->pg_query("INSERT INTO quotedb_deleted SELECT * FROM quotedb WHERE id=$quote_id", 'quote backup query');
		if ($q === false) {
			$say = 'Failed to back up quote before deleting';
		} else {
			$q = Nextrastout::$db->pg_query("DELETE FROM quotedb WHERE id=$quote_id", 'delquote query');

			if ($q === false) {
				$say = 'Failed to delete quote';
			} elseif (pg_affected_rows($q) == 0) {
				log::error('No affected rows after delete, but select found results!');
				$say = 'No quote deleted';
			} else {
				$say = "Quote #$quote_id has been deleted";
			}
		}
	}
}

$_i['handle']->say($_i['reply_to'], $say);
