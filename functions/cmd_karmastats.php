//<?php

log::trace('entered f::cmd_karmastats.php');
list($ucmd, $uarg, $_i) = $_ARGV;

$channel = $_i['sent_to'];

$where_notme = 'nick NOT IN (' . implode(',', array_map('single_quote', array_map(function($handle) {return strtolower($handle->nick);}, ExtraServ::$handles))) . ", 'extrastout')";

$sayprefix = "Karma stats for '$uarg' in $channel: ";
$sayparts = array();

$q = pg_query_params(ExtraServ::$db, "SELECT sum(up) AS up, sum(down) AS down FROM karma_cache WHERE channel=$1 AND (thing=$2 AND nick!=$2) AND $where_notme", array(
	$channel,
	$uarg
));
if ($q === false) {
	log::error('Failed to look up karma for cmd_karma()');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No votes');
	$_i['handle']->say($_i['reply_to'], "No votes found for '$uarg'");
	return f::TRUE;
} else {
	log::debug('query OK');
	$qr = pg_fetch_assoc($q);

	$upvotes = $qr['up'];
	$downvotes = $qr['down'];
	$net_votes = $upvotes - $downvotes;
	$fnv = number_format($net_votes);
	$fuv = number_format($upvotes);
	$fdv = number_format($downvotes);
	$fpli = number_format((($upvotes + 0.0 ) / ($upvotes + $downvotes + 0.0)) * 100, 1);

	$sayparts[] = "Net karma: %C$fnv%0 (+$fuv/-$fdv; $fpli% like it)";
}

# top upvoters
$q = pg_query_params(ExtraServ::$db, "SELECT nick, sum(up) AS up FROM karma_cache WHERE channel=$1 AND (thing=$2 AND nick!=$2) AND $where_notme GROUP BY nick ORDER BY up DESC LIMIT 5", array(
	$channel,
	$uarg
));
if ($q === false) {
	log::error('Failed to get top upvoters for cmd_karmastats()');
	log::error(pg_last_error());
	$sayparts[] = 'Query Failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No upvotes');
	$sayparts[] = 'no upvotes';
} else {
	log::debug('query OK');
	$voters = array();

	$qr = pg_fetch_assoc($q);
	$voters[] = "%g{$qr['nick']}%0 (+{$qr['up']})";

	while ($qr = pg_fetch_assoc($q)) {
		$voters[] = "{$qr['nick']} (+{$qr['up']})";
	}
	$sayparts[] = 'Top upvoters: ' . implode(', ', $voters);
}

# top downvoters
$q = pg_query_params(ExtraServ::$db, "SELECT nick, sum(down) AS down FROM karma_cache WHERE channel=$1 AND (thing=$2 AND nick!=$2) AND $where_notme GROUP BY nick ORDER BY down DESC LIMIT 5", array(
	$channel,
	$uarg
));
if ($q === false) {
	log::error('Failed to get top downvoters for cmd_karmastats()');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No downvotes');
	$sayparts[] = 'no downvotes';
} else {
	log::debug('query OK');
	$voters = array();

	$qr = pg_fetch_assoc($q);
	$voters[] = "%r{$qr['nick']}%0 (-{$qr['down']})";

	while ($qr = pg_fetch_assoc($q)) {
		$voters[] = "{$qr['nick']} (-{$qr['down']})";
	}
	$sayparts[] = 'Top downvoters: ' . implode(', ', $voters);
}

$_i['handle']->say($_i['reply_to'], $sayprefix . color_formatting::irc(implode(' | ', $sayparts)));
