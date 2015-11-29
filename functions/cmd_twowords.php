//<?php

log::trace('entered f::cmd_twowords()');
list($_CMD, $params, $_i) = $_ARGV;

if ($params == null) {
	log::debug('No nick supplied');
	$_i['handle']->say($_i['reply_to'], 'Please supply a nickname or list of nicks');
	return f::FALSE;
}
$nicks = array_map('trim', explode(',', strtolower($params)));
$channel = $_i['sent_to'];

$N = 2;

$nick_in = implode(',', array_map('single_quote', array_map('dbescape', $nicks)));

$q = pg_query(ExtraServ::$db, $query = "SELECT twowords, wc AS count FROM statcache_twowords WHERE nick IN ($nick_in) AND channel='$channel' LIMIT 200");
log::debug("twowords query >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('twowords query failed');
	log::error(pg_last_error());
	$say = 'Query failed';
} elseif (pg_num_rows($q) == 0) {
	log::debug('No results for twowords');
	$say = 'No results for nickname';
} else {
	log::debug('twowords query OK');

	arsort($sequences);

	$sayparts = array();
	while ($row = pg_fetch_assoc($q)) {
		$seq = str_replace(chr(1), '', $row['twowords']);
		$sayparts[] = "$seq ({$row['count']})";
	}

	if (count($nicks) > 1) {
		$saynick = implode(', ', $nicks);
	} else {
		$saynick = $nicks[0];
	}
	if ($N == 2) {
		$w = 'pairs';
	} else {
		$w = 'sequences';
	}
	$say = f::pack_list("Top $N-word $w for $saynick: ", $sayparts, $_i);
}

$_i['handle']->say($_i['reply_to'], $say);
