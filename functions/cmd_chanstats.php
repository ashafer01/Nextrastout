//<?php

log::trace('entered f::cmd_chanstats()');
list($ucmd, $uarg, $_i) = $_ARGV;

$channel = $_i['sent_to'];
$len = strlen($channel);
$where_channel = "(substr(args, 1, $len) = '$channel')";

$sayprefix = "Stats for $channel: ";
$sayparts = array();
$b = chr(2); # bold

$q = Nextrastout::$db->pg_query("SELECT nick, uts FROM statcache_firstuse WHERE channel='$channel' ORDER BY uts LIMIT 1", 'chanstats first channel use');
if ($q === false) {
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug("No results for '$channel'");
	$_i['handle']->say($_i['reply_to'], 'Channel not found');
	return f::TRUE;
} else {
	$qr = pg_fetch_assoc($q);

	$first_use_uts = $qr['uts'];
	$chan_age = time() - $first_use_uts;
	$chan_age_days = ceil($chan_age / (24 * 3600));
	$chan_age_hours = ceil($chan_age / 3600);

	$ffud = smart_date_fmt($first_use_uts);
	$fcad = number_format($chan_age_days);
	$sayparts[] = "First recorded use: $b$ffud$b by $b{$qr['nick']}$b ($fcad days ago)";
}

$q = Nextrastout::$db->pg_query("SELECT val AS count FROM statcache_misc WHERE channel='$channel' AND stat_name='total lines'", 'chanstats total count query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);

	$chan_total_count = $qr['count'];

	$fctc = number_format($chan_total_count);
	$flpd = number_format(($chan_total_count / ($chan_age_days + 0.0)), 2);
	$sayparts[] = "$fctc lines ($flpd lines/day)";
}

$q = Nextrastout::$db->pg_query("SELECT count(*) FROM (SELECT nick FROM statcache_lines WHERE channel='$channel' GROUP BY nick) t1", 'chanstats number nicks query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);

	$chan_total_nicks = $qr['count'];
	$avg_lines_per_nick = $chan_total_count / ($chan_total_nicks + 0.0);

	$fctn = number_format($chan_total_nicks);
	$falpn = number_format($avg_lines_per_nick, 2);
	$falpnpd = number_format($avg_lines_per_nick / $chan_age_days, 2);
	$sayparts[] = "$fctn nicks ($falpn lines/nick; $falpnpd lines/nick/day)";
}

$q = Nextrastout::$db->pg_query("SELECT count(*) FROM (SELECT ircuser FROM log WHERE $where_channel GROUP BY ircuser) t1", 'chanstats number users query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);

	$fctu = number_format($qr['count']);
	$sayparts[] = "$fctu usernames";
}

$sums = array();
foreach (array('d_mon','d_tue','d_wed','d_thu','d_fri','d_sat','d_sun') as $d_col) {
	$sums[] = "SUM($d_col) AS $d_col";
}
for ($i = 0; $i < 24; $i++) {
	$sums[] = "SUM(h_$i) AS h_$i";
}
$sums = implode(', ', $sums);

$q = Nextrastout::$db->pg_query("SELECT $sums FROM statcache_timeprofile WHERE channel='$channel'", 'chanstats time profile query');
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
	$fmdvr = number_format(($max_d_val / $chan_total_count) * 100, 2);
	$sayparts[] = "Most talkative day: $b{$max_day}$b ($fmdv lines; $fmdvr%)";

	$fmhv = number_format($max_h_val);
	$fmhvr = number_format(($max_h_val / $chan_total_count) * 100, 2);
	$sayparts[] = "Most talkative hour: $b{$max_hour}:00$b ($fmhv lines; $fmhvr%)";
}

$q = Nextrastout::$db->pg_query("SELECT word, sum(wc) AS count FROM statcache_words WHERE channel='$channel' GROUP BY word ORDER BY count DESC", 'chanstats word list query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} else {
	$total_unique_words = pg_num_rows($q);

	$ftuw = number_format($total_unique_words);
	$fuwpd = number_format($total_unique_words / $chan_age_days, 2);
	$sayparts[] = "Unique words: $ftuw ($fuwpd unique words/day)";
}

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

$sayparts[] = "See also: !karma *, !topkarma";

$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
return f::TRUE;
