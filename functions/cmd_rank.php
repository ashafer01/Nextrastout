//<?php

log::trace('entered f::cmd_rank()');
list($command, $params, $_i) = $_ARGV;

$params = explode(' ', $params, 2);

if (!ctype_digit($params[0])) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a number first');
	return f::FALSE;
}

if ($params[0] < 1) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a number >= 1');
	return f::FALSE;
}

$params[0]--;

$channel = $_i['sent_to'];

$query = "SELECT nick, lines FROM statcache_lines WHERE channel='$channel' ORDER BY lines DESC OFFSET {$params[0]} LIMIT 1";
log::debug("rank query >> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
} elseif (pg_num_rows($q) == 0) {
	$_i['handle']->say($_i['reply_to'], 'No results');
} else {
	log::debug('Query OK');
	$qr = pg_fetch_assoc($q);

	$frank = number_format($params[0]+1);
	$flines = number_format($qr['lines']);
	$_i['handle']->say($_i['reply_to'], "In $channel, ranked by number of lines, #$frank is {$qr['nick']} ($flines lines)");
}
