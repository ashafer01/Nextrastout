//<?php

log::trace('entered f::cmd_karma()');
list($ucmd, $uarg, $_i) = $_ARGV;

$channel = $_i['sent_to'];
$channel = '#geekboy';

$where_notme = 'nick NOT IN (' . implode(',', array_map('single_quote', array_map(function($handle) {return strtolower($handle->nick);}, ExtraServ::$handles))) . ", 'extrastout')";
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

	$_i['handle']->say($_i['reply_to'], color_formatting::irc("Net karma for '$uarg' in $channel: %C$fnv%0 (+$fuv/-$fdv; $fpli% like it)"));
	return f::TRUE;
}
