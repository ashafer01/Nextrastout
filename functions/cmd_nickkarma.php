//<?php

log::trace('entered f::cmd_nickkarma()');

list($_CMD, $params, $_i) = $_ARGV;

$channel = $_i['sent_to'];

if (substr($params, 0, 1) == '@') {
	$params = substr($params, 1);
}

if ($params != null) {
	$nicks = array_map('trim', array_map('strtolower', explode(',', $params)));
} else {
	$nicks = array(strtolower($_i['hostmask']->nick));
}

$where = 'nick IN (' . implode(',', array_map('single_quote', $nicks)) . ')';
$where_notme = 'nick NOT IN (' . implode(',', array_map('single_quote', array_map(function($handle) {return strtolower($handle->nick);}, ExtraServ::$handles))) . ", 'extrastout')";
$where_privmsg = "(command='PRIVMSG' AND args='$channel')";

if (count($nicks) == 1) {
	$sayprefix = "For {$nicks[0]} in $channel: ";
} elseif (count($nicks) > 1) {
	$sayprefix = "Multi-nick karma info in $channel: ";
}
$sayparts = array();
$b = chr(2);

#########################

# Total number of lines matching the query by the given nick(s)

$ref = 'nickstats total nick lines query';
$query = "SELECT count(uts) FROM log WHERE $where_privmsg AND $where";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);

	$nick_total_lines = $qr['count'];
	$fntl = number_format($nick_total_lines);
}

$saypart = "$fntl lines logged";

#########################

# Find the first usage of the given nick(s)

$where_nickonly = f::log_where_nick($nicks, $channel, false);

$ref = 'nickkarma first use query';
$query = "SELECT uts, nick, ircuser FROM log WHERE $where_nickonly ORDER BY uts ASC LIMIT 1";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);

	$first_join_uts = $qr['uts'];

	$elapsed_days = round((time()-$first_join_uts)/(24 * 60 * 60));
	if ($elapsed_days == 0) {
		$elapsed_days = 1;
	}

	$fdate = date('Y-m-d', $first_join_uts);
	$fed = number_format($elapsed_days);
	$days = 'days';
	if ($elapsed_days == 1) {
		$days = 'day';
	}
}

$saypart .= " over $fed $days";
$sayparts[] = $saypart;

#########################

# Find the nickname(s) karma

$where_nicks_karma = '(' . implode(' OR ', array_map(function($nick) {
	return "(thing='$nick' AND nick!='$nick')";
}, $nicks));
$where_nicks_karma .= ')';

$ref = 'karma lookup';
$query = "SELECT sum(up) AS up, sum(down) AS down FROM karma_cache WHERE channel='$channel' AND $where_nicks_karma AND $where_notme";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No matching rows');
	$sayparts[] = 'no votes';
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);

	$up_votes = $qr['up'];
	$down_votes = $qr['down'];
	$net_votes = $up_votes - $down_votes;
	$fnv = number_format($net_votes);
	$fuv = number_format($up_votes);
	$fdv = number_format($down_votes);
	$fpli = number_format((($up_votes + 0.0 ) / ($up_votes + $down_votes + 0.0)) * 100, 1);
	$fakpd = number_format(($net_votes + 0.0) / ($elapsed_days + 0.0), 5);

	$sayparts[] = "Net karma: %C$fnv%0 (+$fuv/-$fdv; $fpli% like it; $fakpd net votes/day)";
}

#########################

# Find the top voters of the nickname

$ref = 'top upvoter query';
$query = "SELECT nick, sum(up) AS up FROM karma_cache WHERE channel='$channel' AND $where_nicks_karma AND $where_notme GROUP BY nick ORDER BY up DESC LIMIT 3";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No matching rows');
	$sayparts[] = 'no upvoters';
} else {
	log::debug("$ref OK");
	$saypart = array();
	while ($qr = pg_fetch_assoc($q)) {
		$fuvc = number_format($qr['up']);
		$saypart[] = "{$qr['nick']} (+$fuvc)";
	}
	$sayparts[] = 'Top upvoters: ' . implode(', ', $saypart);
}

#########################

$ref = 'top downvoter query';
$query = "SELECT nick, sum(down) AS down FROM karma_cache WHERE channel='$channel' AND $where_nicks_karma AND $where_notme GROUP BY nick ORDER BY down DESC LIMIT 3";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No matching rows');
	$sayparts[] = ' no downvoters';
} else {
	log::debug("$ref OK");
	$saypart = array();
	while ($qr = pg_fetch_assoc($q)) {
		$fuvc = number_format($qr['down']);
		$saypart[] = "{$qr['nick']} (-$fuvc)";
	}
	$sayparts[] = 'Top downvoters: ' . implode(', ', $saypart);
}

#########################

# Find vote totals

$where_things_karma = '(' . implode(' OR ', array_map(function($nick) {
	return "(nick='$nick' AND thing!='$nick')";
}, $nicks));
$where_things_karma .= ')';

$ret = 'vote totals query';
$query = "SELECT sum(up) AS up, sum(down) AS down FROM karma_cache WHERE channel='$channel' AND $where_things_karma";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No matching rows');
	$sayparts[] = 'no votes';
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);

	$total_up_cast = $qr['up'];
	$total_down_cast = $qr['down'];
	$total_cast = $total_up_cast + $total_down_cast;

	$ftuc = number_format($total_up_cast);
	$ftdc = number_format($total_down_cast);
	$ftc = number_format($total_cast);
	$ftutcr = number_format(($total_up_cast / $total_cast) * 100, 3);
	$sayparts[] = "Cast $ftc votes (+$ftuc/-$ftdc; likes $ftutcr% of things)";
}

#########################

# Find most voted things

$ret = 'most upvoted thing query';
$query = "SELECT thing, sum(up) AS up FROM karma_cache WHERE channel='$channel' AND $where_things_karma GROUP BY thing ORDER BY up DESC LIMIT 3";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No matching rows');
	$sayparts[] = 'no upvotes';
} else {
	log::debug("$ref OK");
	$saypart = array();
	while ($qr = pg_fetch_assoc($q)) {
		$fuvmc = number_format($qr['up']);
		$saypart[] = "{$qr['thing']} (+$fuvmc)";
	}
	$sayparts[] = 'Most upvoted things: ' . implode(', ', $saypart);
}

#########################

$ret = 'most downvoted thing query';
$query = "SELECT thing, sum(down) AS down FROM karma_cache WHERE channel='$channel' AND $where_things_karma GROUP BY thing ORDER BY down DESC LIMIT 3";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No matching rows');
	$sayparts[] = 'no downvotes';
} else {
	log::debug("$ref OK");
	$saypart = array();
	while ($qr = pg_fetch_assoc($q)) {
		$fdvmc = number_format($qr['down']);
		$saypart[] = "{$qr['thing']} (-$fdvmc)";
	}
	$sayparts[] = 'Most downvoted things: ' . implode(', ', $saypart);
}

#########################

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
