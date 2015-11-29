//<?php

log::trace('entered f::cmd_nickstats()');

list($_CMD, $params, $_i) = $_ARGV;

$channel = $_i['sent_to'];

$where_notme = "nick NOT IN ('" . strtolower(ExtraServ::$bot_handle->nick)  . "', 'extrastout')";

if ($params != null) {
	$nicks = explode(',', strtolower($params));
	$where = 'nick IN (' . implode(',', array_map('single_quote', array_map('dbescape', $nicks))) . ") AND channel='$channel'";
} else {
	$nicks = array($_i['hostmask']->nick);
	$nick = dbescape(strtolower($nicks[0]));
	$where = "nick='$nick' AND channel='$channel'";
}

$nicks = array_map('strtolower', $nicks);
$nicks = array_map('dbescape', $nicks);

$sayprefix = '';
$sayparts = array();
$b = chr(2);

log::debug('Starting nickstats queries');

#########################

# Total number of lines

$ref = 'nickstats total lines query';
$query = "SELECT val AS count FROM statcache_misc WHERE channel='$channel' AND stat_name='total lines'";
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
		log::debug('nickstats: channel not found');
		$_i['handle']->say($_i['reply_to'], 'No lines for channel');
		return f::TRUE;
	}
	$ftl = number_format($total_lines);
}

#########################

# Total number of lines by the given nick(s)

$ref = 'nickstats total nick lines query';
$query = "SELECT SUM(lines) AS count FROM statcache_lines WHERE $where";
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
$query = "SELECT nick, sum(lines) AS count FROM statcache_lines WHERE channel='$channel' GROUP BY nick ORDER BY count DESC";
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

$ref = 'nickstats first use query';
$query = "SELECT uts, nick FROM statcache_firstuse WHERE $where ORDER BY uts ASC LIMIT 1";
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
		$as = '';
	} else {
		$as = " by nick {$qr['nick']}";
	}
	$sayparts[] = "First join: $fdate ($fed $days ago$as)";

	$flpd = number_format($nick_total_lines / $elapsed_days, 3);
	$sayparts[] = "$flpd lines/day";
}

#########################

# Get big list of words

$ref = 'nickstats word list query';
$query = "SELECT word, wc AS count FROM statcache_words WHERE $where AND word !~ '^\x01' ORDER BY count DESC";
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

$sayparts[] = 'See also: !nickkarma, !twowords';

log::debug('Finished nickstats queries');

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
