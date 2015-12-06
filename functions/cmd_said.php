//<?php

log::trace('entered f::cmd_said()');
list($_CMD, $params, $_i) = $_ARGV;

$where = "(command='PRIVMSG' and args='{$_i['sent_to']}')";
if ($params == null) {
	log::debug('No arguments');
	$say = 'Please specify a log search';
} else {
	$where .= f::log_where($params);

	$q = Nextrastout::$db->pg_query("SELECT nick FROM log WHERE $where GROUP BY nick ORDER BY RANDOM() LIMIT 200",
		'said query');
	if ($q === false) {
		$say = 'Query failed';
	} elseif (pg_num_rows($q) == 0) {
		log::debug('No results for said');
		$say = 'No results';
	} else {
		$nicks = array();
		while ($qr = pg_fetch_assoc($q)) {
			$nicks[] = $qr['nick'];
		}
		$say = f::pack_list("{$_i['hostmask']->nick}: said results: ", $nicks, $_i);
	}
}

$_i['handle']->say($_i['reply_to'], $say);
