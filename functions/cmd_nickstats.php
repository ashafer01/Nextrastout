//<?php

log::trace('entered f::cmd_nickstats()');

list($_CMD, $params, $_i) = $_ARGV;

$channel = $_i['sent_to'];

$where_privmsg = "(command='PRIVMSG' AND args='$channel')";
$where_notme = 'nick NOT IN (' . implode(',', array_map('single_quote', array_map(function($handle) {return strtolower($handle->nick);}, ExtraServ::$handles))) . ", 'extrastout')";

if ($params != null) {
	$p = f::parse_logquery($params, 'req_nicks');
	if (count($p->req_nicks) > 0) {
		$req_nicks = $p->req_nicks;
		$nicks = array();
		foreach ($req_nicks as $nickgrp) {
			foreach ($nickgrp as $nickstr) {
				$nicklist = explode(',', strtolower($nickstr));
				$nicks = array_merge($nicks, $nicklist);
			}
		}
		$nicks = array_unique($nicks);

		$where_nonick = f::log_where($p, true, null, false);
		if ($where_nonick == null) {
			$where_nonick = 'TRUE';
		}
		$where = f::log_where($p, false, null, false);
	} else {
		$nicks = array(strtolower($params));
		$where_nonick = 'TRUE';
		$nick = dbescape(strtolower($nicks[0]));
		$where = "nick='$nick'";
	}
} else {
	$nicks = array($_i['prefix']);
	$where_nonick = 'TRUE';
	$nick = dbescape(strtolower($nicks[0]));
	$where = "nick='$nick'";
}

$nicks = array_map('strtolower', $nicks);
$nicks = array_map('dbescape', $nicks);

$sayprefix = '';
$sayparts = array();
$b = chr(2);

log::debug('Starting nickstats queries');

#########################

# Total number of lines matching the query

$ref = 'nickstats total lines query';
$query = "SELECT count(uts) FROM log WHERE $where_privmsg AND $where_nonick";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);
	$total_lines = $qr['count'];
	if ($total_lines == 0) {
		log::debug('nickstats: No matching rows for log query');
		$_i['handle']->say($_i['reply_to'], 'No lines match your query');
		return f::TRUE;
	}
	$ftl = number_format($total_lines);
}

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
	$fpcnt = number_format(($nick_total_lines / $total_lines) * 100, 6);
}

#########################

# Find the rank of the line count

$ref = 'nickstats rank query';
$query = "SELECT nick, count(uts) FROM log WHERE $where_privmsg AND $where_nonick GROUP BY nick ORDER BY count DESC";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} else {
	log::debug("$ref OK");
	$found = false;
	$rank = 1;
	while ($row = pg_fetch_assoc($q)) {
		if (count($nicks) == 1) {
			if (in_array($row['nick'], $nicks)) {
				$found = true;
				$sayprefix = "For {$nicks[0]} in $channel: ";
				break;
			}
		} else {
			if ($nick_total_lines > $row['count']) {
				$found = true;
				$sayprefix = "Multi-nickstats in $channel: ";
				break;
			}
		}
		$rank++;
	}
	if (!$found) {
		$_i['handle']->say($_i['reply_to'], 'Nickname not found');
		return f::FALSE;
	} else {
		$th = ord_suffix($rank);
		$frank = number_format($rank); // lol frank
		$sayparts[] = "Ranked $b{$frank}{$th}$b";
	}
}

$sayparts[] = "%C$fntl%0 lines / $ftl total ($fpcnt%)";

#########################

# Find the first usage of the given nick(s)

$where_nickonly = f::log_where_nick($nicks, $channel, false);

$ref = 'nickstats first use query';
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

	if (count($nicks) == 1) {
		$as = "as user {$qr['ircuser']}";
	} else {
		$as = "by nick {$qr['nick']}";
	}
	$sayparts[] = "First join: $fdate ($fed $days ago $as)";

	$flpd = number_format($nick_total_lines / $elapsed_days, 3);
	$sayparts[] = "$flpd lines/day";
}

#########################

# Get big list of words

$ref = 'nickstats word list query';
$query = "SELECT * FROM (SELECT regexp_split_to_table(lower(message), '\s+') AS word, count(uts) FROM log WHERE $where_privmsg AND $where GROUP BY word ORDER BY count DESC) AS t1 WHERE word !~ '^\x01'";
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

	$total_unique_words = pg_num_rows($q);

	$ftuw = number_format($total_unique_words);
	$fuwpl = number_format($total_unique_words / $nick_total_lines, 3);
	$sayparts[] = "Total unique words: $ftuw ($fuwpl unique words/line)";
}

#########################

while ($qr = pg_fetch_assoc($q)) {
	if (!in_array($qr['word'], config::get_list('smallwords'))) {
		$ftwc = number_format($qr['count']);
		$sayparts[] = "Top word: {$qr['word']} ($ftwc)";
		break;
	}
}

pg_result_seek($q, 0);

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

$saypart = 'Top voters:';

$ref = 'top upvoter query';
$query = "SELECT nick, sum(up) AS up FROM karma_cache WHERE channel='$channel' AND $where_nicks_karma AND $where_notme GROUP BY nick ORDER BY up DESC LIMIT 1";
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
	$sayparts[] = 'no upvoters;';
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);

	$fuvc = number_format($qr['up']);
	$saypart .= " {$qr['nick']} (+$fuvc);";
}

$ref = 'top downvoter query';
$query = "SELECT nick, sum(down) AS down FROM karma_cache WHERE channel='$channel' AND $where_nicks_karma AND $where_notme GROUP BY nick ORDER BY down DESC LIMIT 1";
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
	$qr = pg_fetch_assoc($q);

	$fuvc = number_format($qr['down']);
	$saypart .= " {$qr['nick']} (-$fuvc)";
}

$sayparts[] = $saypart;

#########################

# Find most voted things

$saypart = 'Most voted: ';

$where_things_karma = '(' . implode(' OR ', array_map(function($nick) {
	return "(nick='$nick' AND thing!='$nick')";
}, $nicks));
$where_things_karma .= ')';

$ret = 'most upvoted thing query';
$query = "SELECT thing, sum(up) AS up FROM karma_cache WHERE channel='$channel' AND $where_things_karma GROUP BY thing ORDER BY up DESC LIMIT 1";
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
	$sayparts[] = 'no upvotes;';
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);

	$fuvmc = number_format($qr['up']);
	$saypart .= "{$qr['thing']} (+$fuvmc);";
}

$ret = 'most downvoted thing query';
$query = "SELECT thing, sum(down) AS down FROM karma_cache WHERE channel='$channel' AND $where_things_karma GROUP BY thing ORDER BY down DESC LIMIT 1";
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
	$sayparts[] = ' no downvotes';
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);

	$fdvmc = number_format($qr['down']);
	$saypart .= " {$qr['thing']} (+$fdvmc)";
}

$sayparts[] = $saypart;

#########################

log::debug('Finished nickstats queries');

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
