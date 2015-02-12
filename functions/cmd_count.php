//<?php

log::trace('entering f::cmd_count()');
list($_CMD, $params, $_i) = $_ARGV;

$query = "SELECT count(uts) FROM log WHERE (command='PRIVMSG' and args='{$_i['sent_to']}')" . f::log_where($params);
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

$_i['handle']->say($_i['reply_to'], $say);
