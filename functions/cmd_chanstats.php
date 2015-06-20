//<?php

log::trace('entered f::cmd_chanstats()');
list($ucmd, $uarg, $_i) = $_ARGV;

$channel = $_i['sent_to'];
$len = strlen($channel);
$where_channel = "(substr(args, 1, $len) = '$channel')";

$sayprefix = "Stats for $channel: ";
$sayparts = array();
$b = chr(2); # bold

$ref = 'chanstats first channel use';
$query = "SELECT nick, uts FROM log WHERE $where_channel ORDER BY uts LIMIT 1";
log::debug("$ref >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error("$ref failed");
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug("No results for $ref '$channel'");
	$_i['handle']->say($_i['reply_to'], 'Channel not found');
	return f::TRUE;
} else {
	log::debug("$ref OK");
	$qr = pg_fetch_assoc($q);

	$first_use_uts = $qr['uts'];
	$chan_age = time() - $first_use_uts;
	$chan_age_days = ceil($chan_age / (24 * 3600));
	$chan_age_hours = ceil($chan_age / 3600);

	$ffud = smart_date_fmt($first_use_uts);
	$fcad = number_format($chan_age_days);
	$frnick = rainbow($qr['nick']);
	$sayparts[] = "First recorded use on $b$ffud$b by $b$frnick%0$b ($fcad days ago)";
}

$ref = 'chanstats total count query';
$query = "SELECT count(uts) FROM log WHERE $where_channel";
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

	$chan_total_count = $qr['count'];

	$fctc = number_format($chan_total_count);
	$flpd = number_format(($chan_total_count / ($chan_age_days + 0.0)), 2);
	$flph = number_format(($chan_total_count / ($chan_age_hours + 0.0)), 2);
	$sayparts[] = "%C$fctc%0 lines ($flpd lines/day; $flph lines/hour)";
}

$ref = 'chanstats number nicks query';
$query = "SELECT count(*) FROM (SELECT nick FROM log WHERE $where_channel GROUP BY nick) t1";
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

	$chan_total_nicks = $qr['count'];
	$avg_lines_per_nick = $chan_total_count / ($chan_total_nicks + 0.0);

	$fctn = number_format($chan_total_nicks);
	$falpn = number_format($avg_lines_per_nick, 5);
	$falpnpd = number_format($avg_lines_per_nick / $chan_age_days, 5);
	$falpnph = number_format($avg_lines_per_nick / $chan_age_hours, 6);
	$sayparts[] = "$b$fctn$b unique nicknames ($falpn lines/nick; $falpnpd lines/nick/day; $falpnph lines/nick/hour)";
}

$ref = 'chanstats number users query';
$query = "SELECT count(*) FROM (SELECT ircuser FROM log WHERE $where_channel GROUP BY ircuser) t1";
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

	$fctu = number_format($qr['count']);
	$sayparts[] = "$b$fctu$b unqiue usernames";
}

$sayparts[] = "See also: $b!karma *$b";

$_i['handle']->say($_i['reply_to'], color_formatting::irc($sayprefix . implode(' | ', $sayparts)));
return f::TRUE;
