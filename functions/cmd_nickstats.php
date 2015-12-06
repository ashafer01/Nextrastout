//<?php

log::trace('entered f::cmd_nickstats()');

list($_CMD, $params, $_i) = $_ARGV;

$channel = $_i['sent_to'];

$where_notme = "nick NOT IN ('" . strtolower(Nextrastout::$bot_handle->nick)  . "', 'extrastout')";

if ($params != null) {
	$nicks = explode(',', strtolower($params));
	$where_nick = 'nick IN (' . implode(',', array_map('single_quote', array_map('dbescape', $nicks))) . ')';
} else {
	$nicks = array($_i['hostmask']->nick);
	$nick = dbescape(strtolower($nicks[0]));
	$where_nick = "nick='$nick'";
}

$where = "$where_nick AND channel='$channel'";

$nicks = array_map('strtolower', $nicks);
$nicks = array_map('dbescape', $nicks);

$sayprefix = '';
$sayparts = array();
$b = chr(2);

log::debug('Starting nickstats queries');

#########################

# Total number of lines

$q = Nextrastout::$db->pg_query("SELECT val AS count FROM statcache_misc WHERE channel='$channel' AND stat_name='total lines'",
	'nickstats total lines query');
if ($q === false) {
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} else {
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

$q = Nextrastout::$db->pg_query("SELECT SUM(lines) AS count FROM statcache_lines WHERE $where",
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

$q = Nextrastout::$db->pg_query("SELECT nick, sum(lines) AS count FROM statcache_lines WHERE channel='$channel' GROUP BY nick ORDER BY count DESC",
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

$q = Nextrastout::$db->pg_query("SELECT uts, nick FROM statcache_firstuse WHERE $where ORDER BY uts ASC LIMIT 1",
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

$q = Nextrastout::$db->pg_query("SELECT word, wc AS count FROM statcache_words WHERE $where AND word !~ '^\x01' ORDER BY count DESC",
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

# Get time profile

$sums = array();
foreach (array('d_mon','d_tue','d_wed','d_thu','d_fri','d_sat','d_sun') as $d_col) {
	$sums[] = "SUM($d_col) AS $d_col";
}
for ($i = 0; $i < 24; $i++) {
	$sums[] = "SUM(h_$i) AS h_$i";
}
$sums = implode(', ', $sums);

$q = Nextrastout::$db->pg_query("SELECT $sums FROM statcache_timeprofile WHERE $where",
	'nickstats time profile query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);

	$max_hour = '?';
	$max_day = '?';
	$max_h_val = 0;
	$max_d_val = 0;
	foreach ($qr as $col => $val) {
		$f2c = substr($col, 0, 2);
		if ($f2c == 'h_') {
			# hour column
			if ($val > $max_h_val) {
				$max_h_val = $val;
				$max_hour = $col;
			}
		} elseif ($f2c == 'd_') {
			# day column
			if ($val > $max_d_val) {
				$max_d_val = $val;
				$max_day = $col;
			}
		}
	}
	$max_hour = substr($max_hour, 2);
	$tzo = tz_hour_offset();
	$max_hour = $max_hour + $tzo;
	if ($max_hour < 0) {
		$max_hour = 24 + $max_hour;
	}

	$daymap = array(
		'd_mon' => 'Monday',
		'd_tue' => 'Tuesday',
		'd_wed' => 'Wednesday',
		'd_thu' => 'Thursday',
		'd_fri' => 'Friday',
		'd_sat' => 'Saturday',
		'd_sun' => 'Sunday'
	);
	$max_day = $daymap[$max_day];

	$fmdv = number_format($max_d_val);
	$fmdvr = number_format(($max_d_val / $nick_total_lines) * 100, 2);
	$sayparts[] = "Most talkative day: $b{$max_day}$b ($fmdv lines; $fmdvr%)";

	$fmhv = number_format($max_h_val);
	$fmhvr = number_format(($max_h_val / $nick_total_lines) * 100, 2);
	$sayparts[] = "Most talkative hour: $b{$max_hour}:00$b ($fmhv lines; $fmhvr%)";
}

#########################

# number of caps lines

$q = Nextrastout::$db->pg_query("SELECT count(*) FROM caps_cache WHERE args='$channel' AND $where_nick",
	'nickstats caps count query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);

	$caps_count = $qr['count'];
	$fcc = number_format($caps_count);
	$fccr = number_format(($caps_count / $nick_total_lines) * 100, 2);
	$sayparts[] = "$fcc caps lines ($fccr%)";
}

#########################

# average line length

$q = Nextrastout::$db->pg_query("SELECT nick, avg(char_length(message)) AS len FROM log WHERE args='$channel' AND command='PRIVMSG' AND $where_nick GROUP BY nick",
	'nickstats avg line length query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);

	$fal = number_format($qr['len'], 2);
	$sayparts[] = "Avg line length: $fal";
}

#########################

$sayparts[] = 'See also: !nickkarma, !twowords';

log::debug('Finished nickstats queries');

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
$sayparts[] = 'See also: !nickkarma, !twowords';

log::debug('Finished nickstats queries');

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
