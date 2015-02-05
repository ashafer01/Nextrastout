//<?php

log::trace('entered f::cmd_nickstats()');

list($_CMD, $params, $_i) = $_ARGV;

$channel = $_i['sent_to'];

$where_privmsg = "(command='PRIVMSG' AND args='$channel')";
if ($params != null) {
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
	$handle = log::parse_hostmask($_i['prefix']);
	$nicks = array($handle->nick);
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

$ref = 'nickstats total lines query';
$query = "SELECT count(uts) FROM newlog WHERE $where_privmsg AND $where_nonick";
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
	$ftl = number_format($total_lines);
}

#########################

if (count($nicks) == 1) {
	$ref = 'nickstats rank query';
	$query = "SELECT nick, count(uts) FROM newlog WHERE $where_privmsg AND $where_nonick GROUP BY nick ORDER BY count DESC";
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
			if (in_array($row['nick'], $nicks)) {
				$found = true;

				$sayprefix = "For {$nicks[0]} in $channel ($ftl total lines): ";

				$th = ord_suffix($rank);
				$frank = number_format($rank); // lol frank
				$sayparts[] = "Ranked $b{$frank}{$th}$b";
				break;
			}
			$rank++;
		}
		if (!$found) {
			$_i['handle']->say($_i['reply_to'], 'Nickname not found in log');
			return f::FALSE;
		}
	}
} else {
	$sayprefix = "Multi-nickstats in $channel ($ftl total lines): ";
}

#########################

$where_nickonly = f::log_where_nick($nicks, $channel, false);

$ref = 'nickstats total nick lines query';
$query = "SELECT count(uts) FROM newlog WHERE $where_privmsg AND $where_nickonly";
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

	$sayparts[] = "$b$fntl$b lines ($fpcnt% of total)";
}

#########################

if (count($nicks) == 1) {
	$ref = 'nickstats first use query';
	$query = "SELECT uts, ircuser FROM newlog WHERE $where_nickonly ORDER BY uts ASC LIMIT 1";
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
			$elapsed_day = 1;
		}

		$fdate = date('Y-m-d', $first_join_uts);
		$fed = number_format($elapsed_days);
		$days = 'days';
		if ($elapsed_days == 1) {
			$days = 'day';
		}

		$sayparts[] = "First join: $fdate ($fed $days ago as user {$qr['ircuser']})";

		$flpd = number_format($nick_total_lines / $elapsed_days, 3);
		$sayparts[] = "$flpd lines/day";
	}
}

#########################

# Get big list of words
$ref = 'nickstats word list query';
$query = "SELECT * FROM (SELECT regexp_split_to_table(lower(message), '\s+') AS word, count(uts) FROM newlog WHERE $where_privmsg AND $where GROUP BY word ORDER BY count DESC) AS t1 WHERE word !~ '^\x01'";
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

# top word is first in result set
$qr = pg_fetch_assoc($q);
$ftwc = number_format($qr['count']);
$sayparts[] = "Top word: {$qr['word']} ($ftwc)";

pg_result_seek($q, 0);

#########################

$ref = 'nickstats upvote query';
$up_conds = array();
foreach ($nicks as $nick) {
	$up_conds[] = "(message ~* '[[:<:]]\(?$nick\)?\+\+(?![!-~])' AND nick != '$nick')";
}
$conds = implode(' OR ', $up_conds);
$query = "SELECT count(uts) FROM newlog WHERE $where_privmsg AND ($conds)";
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
}

$ref = 'nickstats downvote query';
$down_conds = array();
foreach ($nicks as $nick) {
	$down_conds[] = "(message ~* '[[:<:]]\(?$nick\)?--(?![!-~])' AND nick != '$nick')";
}
$conds = implode(' OR ', $down_conds);
$query = "SELECT count(uts) FROM newlog WHERE $where_privmsg AND ($conds)";
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
}

$net_votes = $up_votes - $down_votes;
$fnv = number_format($net_votes);
$fuv = number_format($up_votes);
$fdv = number_format($down_votes);
$fpli = number_format(($up_votes + 0.0 )/ ($up_votes + $down_votes + 0.0), 1);
$fakpd = number_format(($net_votes + 0.0) / ($elapsed_days + 0.0), 5);

$sayparts[] = "Net karma: $fnv (+$fuv/-$fdv; $fpli% like it; $fakpd net votes per day)";

#########################

log::debug('Finished nickstats queries');

$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
return f::TRUE;
