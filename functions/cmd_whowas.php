//<?php

log::trace('entered f::cmd_whowas()');

list($_CMD, $_ARG, $_i) = $_ARGV;

$args = explode(' ', $_ARG, 2);
if (count($args) == 0) {
	log::debug('No argument');
	$say = 'Please specify a nickname';
} else {
	$where = f::log_where_nick($args[0], $_i['sent_to'], false);
	if (count($args) == 2) {
		$where .= f::log_where($args[1], true); # ignoring @ and ^
	}

	$query = "SELECT max(uts), ircuser FROM newlog WHERE $where GROUP BY ircuser ORDER BY max DESC LIMIT 5";
	log::debug("whowas query >>> $query");
	$q = pg_query(ExtraServ::$db, $query);
	if ($q === false) {
		log::error('Query failed');
		log::error(pg_last_error());
		$say = 'Query failed';
	} elseif (pg_num_rows($q) == 0) {
		log::debug('No results for whowas query');
		$say = 'Nickname not found';
	} else {
		$b = chr(2);
		$say = "Recent users of the nickname $b{$args[0]}$b: ";
		$sayparts = array();
		while ($qr = pg_fetch_assoc($q)) {
			$date = smart_date_fmt($qr['max']);
			$sayparts[] = "$date: $b{$qr['ircuser']}$b";
		}
		$say .= implode(', ', $sayparts);
	}
}

$_i['handle']->say($_i['reply_to'], $say);
