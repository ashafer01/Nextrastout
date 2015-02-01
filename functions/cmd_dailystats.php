//<?php

log::trace('entered f::cmd_dailystats()');
list($_CMD, $params, $_i) = $_ARGV;

$day_s = 24 * 60 * 60;

date_default_timezone_set(ExtraServ::$output_tz);
if ($params == null) {
	$start_uts = strtotime('midnight');
	$stop_uts = time();

	$say = 'So far today: ';
} else {
	$params = trim($params, '"');
	$params = trim($params);

	$start_uts = strtotime($params);
	if ($start_uts === false) {
		$_i['handle']->say($_i['reply_to'], 'Please specify a valid time string, such as "last tuesday" or "2012-02-17". See http://php.net/strtotime for details.');
		date_default_timezone_set('UTC');
		return null;
	}
	$stop_uts = $start_uts + $day_s;

	$today = date_fmt('Y-m-d');
	$start_str = date_fmt('Y-m-d', $start_uts);
	$start_time = date_fmt('G:i', $start_uts);
	if ($start_str != $today) {
		$start_str .= " at $start_time";
	} else {
		$start_str = $start_time;
	}
	$stop_str = date_fmt('Y-m-d', $stop_uts);
	$stop_time = date_fmt('G:i', $stop_uts);
	if ($stop_str != $today) {
		$stop_str .= " at $stop_time";
	} else {
		$stop_str = $stop_time;
	}

	$say = "Between $start_str and $stop_str: ";
}
date_default_timezone_set('UTC');

$where = "(command='PRIVMSG') AND (args='{$_i['sent_to']}') AND (uts > $start_uts) AND (uts < $stop_uts)";

$total = "SELECT count(uts) FROM newlog WHERE $where";
log::debug("total query >>> $total");
$q = pg_query(ExtraServ::$db, $total);
if ($q === false) {
	log::error('total query failed');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return null;
} else {
	$qr = pg_fetch_assoc($q);
	$TOTAL = $qr['count'];
}

$distinct_nicks = "SELECT count(*) FROM (SELECT count(nick) FROM newlog WHERE $where GROUP BY nick) AS t1;";
log::debug("distinct nicks query >>> $distinct_nicks");
$q = pg_query(ExtraServ::$db, $distinct_nicks);
if ($q === false) {
	log::error('distinct nicks query failed');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return null;
} else {
	$qr = pg_fetch_assoc($q);
	$NUM_NICKS = $qr['count'];
}

$top_nicks = "SELECT nick, count(uts) FROM newlog WHERE $where GROUP BY nick ORDER BY count DESC LIMIT 3";
log::debug("top nicks query >>> $top_nicks");
$q = pg_query(ExtraServ::$db, $top_nicks);
if ($q === false) {
	log::error('top nicks query failed');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return null;
} else {
	$TOP_NICKS = pg_fetch_all($q);
}

$top_hours = "SELECT CASE";
$break = false;
for ($i = $start_uts; $i <= $stop_uts; $i += 3600) {
	$end = $i + 3600;
	if ($end > time()) {
		$end = time();
		$break = true;
	}
	$starttime = date_fmt('G:i', $i);
	$stoptime = date_fmt('G:i', $end);
	$top_hours .= " WHEN uts BETWEEN $i AND $end THEN '$starttime to $stoptime'";
	if ($break) {
		break;
	}
}
$top_hours .= " END AS timerange, count(uts) FROM newlog WHERE $where GROUP BY timerange ORDER BY count DESC LIMIT 3";
log::debug("top hours query >>> $top_hours");
$q = pg_query(ExtraServ::$db, $top_hours);
if ($q === false) {
	log::error('top hours query failed');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return null;
} else {
	$TOP_HOURS = pg_fetch_all($q);
}

$total_str = number_format($TOTAL);
$num_nicks_str = number_format($NUM_NICKS);
$lpn = number_format($TOTAL / $NUM_NICKS, 2);
$lph = number_format($TOTAL / 24, 2);

$say .= "$total_str lines by $NUM_NICKS nicks in {$_i['sent_to']} (average $lpn lines/nick, $lph lines/hour). Top 3 nicks: ";

$b = chr(2);
$top_nick_strs = array();
foreach ($TOP_NICKS as $row) {
	$count_str = number_format($row['count']);
	$day_percent = number_format(($row['count'] / $TOTAL) * 100, 2);
	$lph = number_format($row['count'] / 24, 2);
	$top_nick_strs[] = "$b{$row['nick']}$b with $count_str lines ($day_percent%, $lph lines/hour)";
}

$say .= implode(', ', $top_nick_strs);

$say .= ". Top 3 hours: ";
$top_hour_strs = array();
foreach ($TOP_HOURS as $row) {
	$count_str = number_format($row['count']);
	$day_percent = number_format(($row['count'] / $TOTAL) * 100, 2);
	$top_hour_strs[] = "{$row['timerange']} ($count_str lines, $day_percent%)";
}

$say .= implode(', ', $top_hour_strs);

$_i['handle']->say($_i['reply_to'], $say);
