//<?php

log::trace('entered f::cmd_nickstats()');

list($_CMD, $params, $_i) = $_ARGV;

$channel = $_i['sent_to'];

$where_privmsg = "(command='PRIVMSG' AND args='$channel')";
$where_notme = "nick NOT IN ('" . Nextrastout::$bot_handle->nick . "', 'extrastout')";

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

	if (count($p->before) > 0 || count($p->after) > 0) {
		$where_dateonly = f::log_where(new parsed_logquery(array('before' => $p->before, 'after' => $p->after)), true, null, false);
	} else {
		$where_dateonly = 'TRUE';
	}
} else {
	$nicks = array($_i['hostmask']->nick);
	$where_nonick = 'TRUE';
	$nick = dbescape(strtolower($nicks[0]));
	$where = "nick='$nick'";
	$where_dateonly = 'TRUE';
}

$nicks = array_map('strtolower', $nicks);
$nicks = array_map('dbescape', $nicks);

$sayprefix = '';
$sayparts = array();
$b = chr(2);

log::debug('Starting nickstats queries');

#########################

# Total number of lines matching the query

$q = Nextrastout::$db->pg_query("SELECT count(uts) FROM log WHERE $where_privmsg AND $where_nonick",
	'nickstats total lines query');
if ($q === false) {
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} else {
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

$q = Nextrastout::$db->pg_query("SELECT count(uts) FROM log WHERE $where_privmsg AND $where",
	'nickstats total nick lines query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);

	$nick_total_lines = $qr['count'];
	$fntl = number_format($nick_total_lines);
	$fpcnt = number_format(($nick_total_lines / $total_lines) * 100, 6);
}

#########################

# Find the rank of the line count

$q = Nextrastout::$db->pg_query("SELECT nick, count(uts) FROM log WHERE $where_privmsg AND $where_nonick GROUP BY nick ORDER BY count DESC",
	'nickstats rank query');
if ($q === false) {
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} else {
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

$q = Nextrastout::$db->pg_query("SELECT uts, nick, ircuser FROM log WHERE $where_nickonly AND $where_dateonly ORDER BY uts ASC LIMIT 1",
	'nickstats first use query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
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

$q = Nextrastout::$db->pg_query("SELECT * FROM (SELECT regexp_split_to_table(lower(message), '\W+') AS word, count(uts) FROM log WHERE $where_privmsg AND $where GROUP BY word ORDER BY count DESC) AS t1 WHERE word !~ '^\x01'",
	'nickstats word list query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	$total_unique_words = pg_num_rows($q);

	$ftuw = number_format($total_unique_words);
	$fuwpl = number_format($total_unique_words / $nick_total_lines, 3);
	$fuwpd = number_format($total_unique_words / $elapsed_days, 3);
	$sayparts[] = "Total unique words: $ftuw ($fuwpl unique words/line; $fuwpd unique words/day)";
}

#########################

while ($qr = pg_fetch_assoc($q)) {
	if (strlen($qr['word']) < 3) {
		continue;
	}
	if (!in_array($qr['word'], config::get_list('stopwords'))) {
		$ftwc = number_format($qr['count']);
		$sayparts[] = "Top word: {$qr['word']} ($ftwc)";
		break;
	}
}

pg_result_seek($q, 0);

#########################

$sayparts[] = 'See also: !nickkarma, !s2words';

log::debug('Finished nickstats queries');

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
