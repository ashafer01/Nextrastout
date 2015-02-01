//<?php

log::trace('entered f::cmd_said()');
list($_CMD, $params, $_i) = $_ARGV;

$where = "(command='PRIVMSG' and args='{$_i['sent_to']}')";
if ($params == null) {
	log::debug('No arguments');
	$say = 'Please specify a log search';
} else {
	$where .= f::log_where($params);

	$query = "SELECT nick FROM newlog WHERE $where GROUP BY nick LIMIT 200";
	log::debug("said query >>> $query");
	$q = pg_query(ExtraServ::$db, $query);
	if ($q === false) {
		log::error('said query failed');
		log::error(pg_last_error());
		$say = 'Query failed';
	} elseif (pg_num_rows($q) == 0) {
		log::debug('No results for said');
		$say = 'No results';
	} else {
		log::debug('said query OK');
		$nicks = array();
		while ($qr = pg_fetch_assoc($q)) {
			$nicks[] = $qr['nick'];
		}
		$say = f::pack_list("{$_i['prefix']}: said results: ", $nicks, $_i);
	}
}

$_i['handle']->say($_i['reply_to'], $say);
