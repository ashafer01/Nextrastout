//<?php

log::trace('entering f::cmd_count()');
list($_CMD, $params, $_i) = $_ARGV;

$where = "(command='PRIVMSG' and args='{$_i['sent_to']}')";
if ($params == null) {
	log::debug('No arguments');
	$say = 'Please specify a log search';
} else {
	$where .= f::log_where($params);

	$query = "SELECT count(uts) FROM newlog WHERE $where";
	log::debug("count query >>> $query");
	$q = pg_query(ExtraServ::$db, $query);
	if ($q === false) {
		log::error('Query failed');
		log::error(pg_last_error());
		$say = 'Query failed';
	} else {
		$qr = pg_fetch_assoc($q);
		if ($qr['count'] == 0) {
			log::debug('Query ok, no matches');
			$say = "No lines in {$_i['sent_to']} match your search";
		} else {
			log::debug('Query ok, got matches');
			$b = chr(2);
			$count = number_format($qr['count']);
			$say = "There are $b$count$b lines in {$_i['sent_to']} that match your search";
		}
	}
}

$_i['handle']->say($_i['reply_to'], $say);
