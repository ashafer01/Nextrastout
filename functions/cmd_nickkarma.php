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
$green = "\x0303";
$red = "\x0304";
$lgreen = "\x0309";
$orange = "\x0307";

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

	$fed = number_format($elapsed_days);
	$days = 'days';
	if ($elapsed_days == 1) {
		$days = 'day';
	}
}

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

	$sayparts[] = "Net karma: %C$fnv%0 (+$fuv/-$fdv; $fpli% like it; $fakpd net votes/day over $fed $days)";
}

#########################

# Find the top voters of the nickname

$ref = 'top upvoter query';
$query = "SELECT nick, sum(up) AS up, sum(down) AS down, sum(up) - sum(down) AS net FROM karma_cache WHERE channel='$channel' AND $where_nicks_karma AND $where_notme GROUP BY nick ORDER BY net DESC LIMIT 3";
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
		$fdvc = number_format($qr['down']);
		$fnvc = number_format($qr['net']);
		$saypart[] = "{$qr['nick']} $fnvc (+$fuvc/-$fdvc)";
	}
	$sayparts[] = 'Top upvoters: ' . implode(', ', $saypart);
}

#########################

$ref = 'top downvoter query';
$query = "SELECT nick, sum(up) AS up, sum(down) AS down, sum(up) - sum(down) AS net FROM karma_cache WHERE channel='$channel' AND $where_nicks_karma AND $where_notme GROUP BY nick ORDER BY net LIMIT 3";
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
	$sayparts[] = 'no downvoters';
} else {
	log::debug("$ref OK");
	$saypart = array();
	while ($qr = pg_fetch_assoc($q)) {
		$fuvc = number_format($qr['up']);
		$fdvc = number_format($qr['down']);
		$fnvc = number_format($qr['net']);
		$saypart[] = "{$qr['nick']} $fnvc (+$fuvc/-$fdvc)";
	}
	$sayparts[] = 'Top downvoters: ' . implode(', ', $saypart);
}

#########################

# Find vote totals

$where_things_karma = '(' . implode(' OR ', array_map(function($nick) {
	return "(nick='$nick' AND thing!='$nick')";
}, $nicks));
$where_things_karma .= ')';

$ref = 'vote totals query';
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

$ref = 'most upvoted thing query';
$query = "SELECT thing, sum(up) AS up, sum(down) AS down, sum(up) - sum(down) AS net FROM karma_cache WHERE channel='$channel' AND $where_things_karma GROUP BY thing ORDER BY net DESC LIMIT 3";
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
		$fdvmc = number_format($qr['down']);
		$fnvmc = number_format($qr['net']);
		$saypart[] = "{$qr['thing']} $fnvmc (+$fuvmc/-$fdvmc)";
	}
	$sayparts[] = 'Most upvoted things: ' . implode(', ', $saypart);
}

#########################

$ref = 'most downvoted thing query';
$query = "SELECT thing, sum(down) AS down, sum(up) AS up, sum(up) - sum(down) AS net FROM karma_cache WHERE channel='$channel' AND $where_things_karma GROUP BY thing ORDER BY net LIMIT 3";
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
		$fuvmc = number_format($qr['up']);
		$fnvmc = number_format($qr['net']);
		$saypart[] = "{$qr['thing']} $fnvmc (+$fuvmc/-$fdvmc)";
	}
	$sayparts[] = 'Most downvoted things: ' . implode(', ', $saypart);
}

#########################

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
