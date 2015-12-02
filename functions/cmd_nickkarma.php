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

$ref = 'nickkarma first use query';
$query = "SELECT uts, nick FROM statcache_firstuse WHERE $where AND channel='$channel'";
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
}

#########################

# Find the karma rank

$ref = 'karma rank';
$query = "SELECT thing, sum(up)-sum(down) AS net FROM karma_cache WHERE channel='$channel' AND thing IN (SELECT nick FROM karma_cache WHERE channel='$channel' GROUP BY nick) AND thing!=nick AND $where_notme GROUP BY thing ORDER BY net DESC";
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
	$rank = 1;
	while ($qr = pg_fetch_assoc($q)) {
		log::debug("thing={$qr['thing']} net={$qr['net']} rank=$rank");
		if ($net_votes >= $qr['net']) {
			log::debug("beat rank $rank");
			break;
		}
		$rank++;
	}

	$frank = number_format($rank);
	$suffix = ord_suffix($rank);
	$sayparts[] = "Karma rank: $frank$suffix";

	$fnv = number_format($net_votes);
	$fuv = number_format($up_votes);
	$fdv = number_format($down_votes);
	$fpli = number_format((($up_votes + 0.0 ) / ($up_votes + $down_votes + 0.0)) * 100, 1);
	$fakpd = number_format(($net_votes + 0.0) / ($elapsed_days + 0.0), 5);

	$sayparts[] = "Net karma: %C$fnv%0 (+$fuv/-$fdv; $fpli% like it; $fakpd net votes/day over $fed $days)";
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
$query = "SELECT thing, sum(up) AS up, sum(down) AS down, sum(up) - sum(down) AS net FROM karma_cache WHERE channel='$channel' AND $where_things_karma GROUP BY thing ORDER BY net DESC LIMIT 5";
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
	$i = 0;
	while ($qr = pg_fetch_assoc($q)) {
		$fuvmc = number_format($qr['up']);
		$fdvmc = number_format($qr['down']);
		$fnvmc = number_format($qr['net']);
		if ($i == 0) {
			$saypart[] = "%g{$qr['thing']}%0 $fnvmc (+$fuvmc/-$fdvmc)";
		} else {
			$saypart[] = "{$qr['thing']} $fnvmc (+$fuvmc/-$fdvmc)";
		}
		$i++;
	}
	$sayparts[] = 'Most upvoted things: ' . implode(', ', $saypart);
}

#########################

$ref = 'most downvoted thing query';
$query = "SELECT thing, sum(down) AS down, sum(up) AS up, sum(up) - sum(down) AS net FROM karma_cache WHERE channel='$channel' AND $where_things_karma GROUP BY thing ORDER BY net LIMIT 5";
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
	$i = 0;
	while ($qr = pg_fetch_assoc($q)) {
		$fdvmc = number_format($qr['down']);
		$fuvmc = number_format($qr['up']);
		$fnvmc = number_format($qr['net']);
		if ($i == 0) {
			$saypart[] = "%r{$qr['thing']}%0 $fnvmc (+$fuvmc/-$fdvmc)";
		} else {
			$saypart[] = "{$qr['thing']} $fnvmc (+$fuvmc/-$fdvmc)";
		}
		$i++;
	}
	$sayparts[] = 'Most downvoted things: ' . implode(', ', $saypart);
}

#########################

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
