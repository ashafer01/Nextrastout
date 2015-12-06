//<?php

log::trace('entering f::cmd_count()');
list($_CMD, $params, $_i) = $_ARGV;

$q = Nextrastout::$db->pg_query("SELECT count(uts) FROM log WHERE (command='PRIVMSG' and args='{$_i['sent_to']}')" . f::log_where($params), 'count query');
if ($q === false) {
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
