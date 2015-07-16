//<?php

log::trace('entered f::cmd_karmastats.php');
list($ucmd, $uarg, $_i) = $_ARGV;

$uarg = dbescape($uarg);
$channel = $_i['sent_to'];

$do_total = false;
$where_nicks = null;
if (($uarg == '*') || ($ucmd == 'chankarma')) {
	log::debug('Doing total karma');
	$do_total = true;
/*
} elseif ($ucmd == 'nickkarma' || $ucmd == 'nickarma') {
	$uargs = explode(' ', strtolower($uarg), 2);
	if (count($uargs) < 2) {
		$_i['handle']->say($_i['reply_to'], 'Please specify a nickname and a word/phrase (or a comma-separated list of either/both)');
		return f::FALSE;
	}
	$where_nicks = '(nick IN (' . implode(',', array_map('single_quote', explode(',', $uargs[0]))) . '))';
	$things = array_map('trim', explode(',', $uargs[1]));
	$uarg = $uargs[1];
*/
} elseif ($uarg != null) {
	$uarg = strtolower($uarg);
	$things = array_map('trim', explode(',', $uarg));
} else {
	$uarg = strtolower($_i['hostmask']->nick);
	$things = array($uarg);
}

$where_notme = 'nick NOT IN (' . implode(',', array_map('single_quote', array_map(function($handle) {return strtolower($handle->nick);}, ExtraServ::$handles))) . ", 'extrastout')";

if ($do_total) {
	$sayprefix = "All karma in $channel: ";
} elseif (count($things) == 1) {
	$sayprefix = "Karma for '$uarg' in $channel: ";
} else {
	$sayprefix = "Combined karma in $channel: ";
}
$sayparts = array();

if (!$do_total) {
	$where_things_karma = '(' . implode(' OR ', array_map(function($thing) {
		$thing = pg_escape_literal($thing);
		return "(thing=$thing AND nick!=$thing)";
	}, $things)) . ')';
} else {
	$where_things_karma = '1=1';
}

if ($where_nicks !== null) {
	$where_things_karma .= " AND $where_nicks";
}

$q = pg_query_params(ExtraServ::$db, "SELECT sum(up) AS up, sum(down) AS down FROM karma_cache WHERE channel=$1 AND $where_things_karma AND $where_notme", array(
	$channel
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
$q = pg_query_params(ExtraServ::$db, "SELECT nick, sum(up) AS up, sum(down) AS down, sum(up) - sum(down) AS net FROM karma_cache WHERE channel=$1 AND $where_things_karma AND $where_notme AND up>0 GROUP BY nick ORDER BY net DESC LIMIT 5", array(
	$channel
));
if ($q === false) {
	log::error('Failed to get top upvoters for cmd_karmastats()');
	log::error(pg_last_error());
	$sayparts[] = 'Query Failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No upvoters');
	$sayparts[] = 'no upvoters';
} else {
	log::debug('query OK');
	$voters = array();

	$qr = pg_fetch_assoc($q);
	$sign = ($qr['net'] >= 0) ? '+' : '';
	$fv = number_format($qr['net']);
	$fuv = number_format($qr['up']);
	$fdv = number_format($qr['down']);
	$voters[] = "%g{$qr['nick']}%0 $sign$fv (+$fuv/-$fdv)";

	while ($qr = pg_fetch_assoc($q)) {
		$sign = ($qr['net'] >= 0) ? '+' : '';
		$fv = number_format($qr['net']);
		$fuv = number_format($qr['up']);
		$fdv = number_format($qr['down']);
		$voters[] = "{$qr['nick']} $sign$fv (+$fuv/-$fdv)";
	}
	$sayparts[] = 'Top upvoters: ' . implode(', ', $voters);
}

# top downvoters
$q = pg_query_params(ExtraServ::$db, "SELECT nick, sum(up) AS up, sum(down) AS down, sum(up) - sum(down) AS net FROM karma_cache WHERE channel=$1 AND $where_things_karma AND $where_notme AND down>0 GROUP BY nick ORDER BY net LIMIT 5", array(
	$channel
));
if ($q === false) {
	log::error('Failed to get top downvoters for cmd_karmastats()');
	log::error(pg_last_error());
	$sayparts[] = 'Query Failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No downvoters');
	$sayparts[] = 'no downvoters';
} else {
	log::debug('query OK');
	$voters = array();

	$qr = pg_fetch_assoc($q);
	$sign = ($qr['net'] >= 0) ? '+' : '';
	$fv = number_format($qr['net']);
	$fuv = number_format($qr['up']);
	$fdv = number_format($qr['down']);
	$voters[] = "%r{$qr['nick']}%0 $sign$fv (+$fuv/-$fdv)";

	while ($qr = pg_fetch_assoc($q)) {
		$sign = ($qr['net'] >= 0) ? '+' : '';
		$fv = number_format($qr['net']);
		$fuv = number_format($qr['up']);
		$fdv = number_format($qr['down']);
		$voters[] = "{$qr['nick']} $sign$fv (+$fuv/-$fdv)";
	}
	$sayparts[] = 'Top downvoters: ' . implode(', ', $voters);
}

$_i['handle']->say($_i['reply_to'], $sayprefix . color_formatting::irc(implode(' | ', $sayparts)));
