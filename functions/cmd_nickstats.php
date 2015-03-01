//<?php

log::trace('entered f::cmd_nickstats()');

list($_CMD, $params, $_i) = $_ARGV;

$channel = $_i['sent_to'];

$where_privmsg = "(command='PRIVMSG' AND args='$channel')";
$where_notme = 'nick NOT IN (' . implode(',', array_map('single_quote', array_map(function($handle) {return strtolower($handle->nick);}, ExtraServ::$handles))) . ", 'extrastout')";

if ($params != null) {
	$query_md5sum = md5($params);
	$p = f::parse_logquery($params);
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
		$nicks = array($params);
		$where_nonick = 'TRUE';
		$nick = dbescape(strtolower($nicks[0]));
		$where = "nick='$nick'";
	}
} else {
	$query_md5sum = md5($_i['prefix']);
	$nicks = array($_i['prefix']);
	$where_nonick = 'TRUE';
	$nick = dbescape(strtolower($nicks[0]));
	$where = "nick='$nick'";
}

$nicks = array_map('strtolower', $nicks);
$nicks = array_map('dbescape', $nicks);

$have_cached_stats = false;
$cache_after = 0;
$new_cache = array('last_update_uts' => time());
$stat_cache = pg_query_params(ExtraServ::$db, 'SELECT * FROM stat_cache WHERE query_md5sum=$1', array($query_md5sum));
if ($stat_cache === false) {
	log::error('stat cache lookup query failed');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} elseif (pg_num_rows($stat_cache) == 0) {
	log::debug('No cached stats');
} else {
	log::debug('Have cached stats');
	$have_cached_stats = true;
	$stat_cache = pg_fetch_assoc($stat_cache);
	$cache_after = $stat_cache['last_update_uts'];
}

$sayprefix = '';
$sayparts = array();
$b = chr(2);

log::debug('Starting nickstats queries');

#########################

# Total number of lines matching the query
# Not cached

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
# Not cached

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
# Not cached

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
$cache_col = 'first_join_uts';

$where_nickonly = f::log_where_nick($nicks, $channel, false);

if ($have_cached_stats) {
	$first_join_uts = $stat_cache[$cache_col];
} else {
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
		$new_cache[$cache_col] = $first_join_uts;
	}
}

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

#########################

# Get big list of words
# Not cached

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

# Find upvotes of the nickname
$cache_col = 'upvotes';

$ref = 'nickstats upvote query';
$up_conds = array();
foreach ($nicks as $nick) {
	$up_conds[] = "(message ~* '[[:<:]]\(?$nick\)?\+\+(?![!-~])' AND nick != '$nick')";
}
$up_conds = implode(' OR ', $up_conds);
$query = "SELECT count(uts) FROM log WHERE uts >= $cache_after AND $where_privmsg AND ($up_conds) AND $where_notme";
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
	$up_votes = $qr['count'];
	if ($have_cached_stats) {
		$up_votes += $stat_cache[$cache_col];
	}

	$new_cache[$cache_col] = $up_votes;
}

# Find downvotes of the nickname
$cache_col = 'downvotes';

$ref = 'nickstats downvote query';
$down_conds = array();
foreach ($nicks as $nick) {
	$down_conds[] = "(message ~* '[[:<:]]\(?$nick\)?--(?![!-~])' AND nick != '$nick')";
}
$down_conds = implode(' OR ', $down_conds);
$query = "SELECT count(uts) FROM log WHERE uts >= $cache_after AND $where_privmsg AND ($down_conds) AND $where_notme";
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
	$down_votes = $qr['count'];
	if ($have_cached_stats) {
		$down_votes += $stat_cache[$cache_col];
	}

	$new_cache[$cache_col] = $down_votes;
}

$net_votes = $up_votes - $down_votes;
$fnv = number_format($net_votes);
$fuv = number_format($up_votes);
$fdv = number_format($down_votes);
$fpli = number_format((($up_votes + 0.0 ) / ($up_votes + $down_votes + 0.0)) * 100, 1);
$fakpd = number_format(($net_votes + 0.0) / ($elapsed_days + 0.0), 5);

$sayparts[] = "Net karma: %C$fnv%0 (+$fuv/-$fdv; $fpli% like it; $fakpd net votes/day)";

#########################

# Find the top voters of the nickname
# Is cached

$saypart = 'Top voters:';

$ref = 'nickstats top voters upvote query';
$query = "SELECT nick, count(uts) FROM log WHERE ((nick != '{$stat_cache['top_upvoter_nick']}') OR uts >= $cache_after)) AND $where_privmsg AND ($up_conds) AND $where_notme GROUP BY nick ORDER BY count DESC LIMIT 2";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug("$ref - No results");
	$saypart .= ' no upvotes;';
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);
	if ($have_cached_stats && ($qr['top_upvoter_nick'] == $stat_cache['top_upvoter_nick'])) {
		$qr['count'] += $stat_cache['top_upvoter_count'];
	}
	$fuvc = number_format($qr['count']);
	$saypart .= " {$qr['nick']} (+$fuvc);";

	$new_cache['top_upvoter_nick'] = $qr['nick'];
	$new_cache['top_upvoter_count'] = $qr['count'];
}

$ref = 'nickstats top voters downvote query';
$query = "SELECT nick, count(uts) FROM log WHERE uts >= $cache_after AND $where_privmsg AND ($down_conds) AND $where_notme GROUP BY nick ORDER BY count DESC LIMIT 1";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug("$ref - No results");
	$saypart .= ' no downvotes;';
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);
	if ($have_cached_stats && ($qr['top_downvoter_nick'] == $stat_cache['top_downvoter_nick'])) {
		$qr['count'] += $stat_cache['top_downvoter_count'];
	}
	$fuvc = number_format($qr['count']);
	$saypart .= " {$qr['nick']} (-$fuvc);";

	$new_cache['top_downvoter_nick'] = $qr['nick'];
	$new_cache['top_downvoter_count'] = $qr['count'];
}

$sayparts[] = $saypart;

#########################

$upvote_max_count = 0;
$upvote_max_thing = null;

$ref = 'nickstats karma upvote without parens query';
$query = "SELECT karma[1] AS thing, count(karma[1]) FROM (SELECT regexp_matches(message, '[[:<:]]([!-&*-~]+?)(\+\+|--)(?![!-~])') AS karma FROM log WHERE $where_privmsg AND $where) AS t1 WHERE karma[2]='++' GROUP BY karma[1]";
log::debug("$ref >>> $query");
$q1 = pg_query(ExtraServ::$db, $query);
if ($q1 === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	log::debug("$ref OK");
}

$ref = 'nickstats karma upvote with parens query';
$query = "SELECT karma[1] AS thing, count(karma[1]) FROM (SELECT regexp_matches(message, '\(([!-~]+?)\)(\+\+|--)(?![!-~])') AS karma FROM log WHERE $where_privmsg AND $where) AS t1 WHERE karma[2]='++' GROUP BY karma[1]";
log::debug("$ref >>> $query");
$q2 = pg_query(ExtraServ::$db, $query);
if ($q2 === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	log::debug("$ref OK");
}

while ($row = pg_fetch_assoc($q1)) {
	if ($row['count'] > $upvote_max_count) {
		$upvote_max_count = $row['count'];
		$upvote_max_thing = $row['thing'];
	}
}

while ($row = pg_fetch_assoc($q2)) {
	if ($row['count'] > $upvote_max_count) {
		$upvote_max_count = $row['count'];
		$upvote_max_thing = $row['thing'];
	}
}

if ($have_cached_stats) {
	$upvote_max_count
}

$downvote_max_count = 0;
$downvote_max_thing = null;

$ref = 'nickstats karma downvote without parens query';
$query = "SELECT karma[1] AS thing, count(karma[1]) FROM (SELECT regexp_matches(message, '[[:<:]]([!-&*-~]+?)(\+\+|--)(?![!-~])') AS karma FROM log WHERE $where_privmsg AND $where) AS t1 WHERE karma[2]='--' GROUP BY karma[1]";
log::debug("$ref >>> $query");
$q1 = pg_query(ExtraServ::$db, $query);
if ($q1 === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	log::debug("$ref OK");
}

$ref = 'nickstats karma downvote with parens query';
$query = "SELECT karma[1] AS thing, count(karma[1]) FROM (SELECT regexp_matches(message, '\(([ -~]+?)\)(\+\+|--)(?![!-~])') AS karma FROM log WHERE $where_privmsg AND $where) AS t1 WHERE karma[2]='--' GROUP BY karma[1]";
log::debug("$ref >>> $query");
$q2 = pg_query(ExtraServ::$db, $query);
if ($q2 === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	log::debug("$ref OK");
}

while ($row = pg_fetch_assoc($q1)) {
	if ($row['count'] > $downvote_max_count) {
		$downvote_max_count = $row['count'];
		$downvote_max_thing = $row['thing'];
	}
}

while ($row = pg_fetch_assoc($q2)) {
	if ($row['count'] > $downvote_max_count) {
		$downvote_max_count = $row['count'];
		$downvote_max_thing = $row['thing'];
	}
}

$fdvmc = number_format($downvote_max_count);

$saypart = 'Most voted: ';
if ($upvote_max_thing == null) {
	$saypart .= 'no upvotes; ';
} else {
	$fuvmc = number_format($upvote_max_count);
	$saypart .= "$upvote_max_thing (+$fuvmc); ";
}

if ($downvote_max_thing == null) {
	$saypart .= 'no downvotes';
} else {
	$fdvmc = number_format($downvote_max_count);
	$saypart .= "$downvote_max_thing (-$fdvmc)";
}

$sayparts[] = $saypart;

#########################

log::debug('Finished nickstats queries');

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
