//<?php

log::trace('entered f::cmd_dailylines()');
list($_CMD, $params, $_i) = $_ARGV;

$p = Nextrastout::$db->pg_prepare('dailylines', "SELECT COUNT(uts) AS count FROM log WHERE command='PRIVMSG' AND uts >= $1 AND uts < $2 AND args=$4 AND nick ILIKE $3");

$qchan = $_i['sent_to'];
if ($params != null) {
	$qnick = dbescape(strtolower($params));
	$prefix = "For $qnick: ";
} else {
	$qnick = '%';
	$prefix = "For $qchan: ";
}

$day_s = 24 * 60 * 60;
$stop_uts = time();
$start_uts = local_strtotime('midnight');
$reply = array();
$b = chr(2);
$er = Nextrastout::$db->pg_execute('dailylines', array($start_uts, $stop_uts, $qnick, $qchan));
if ($er === false) {
	$reply[] = 'So far today: failed to execute';
} else {
	$pq = pg_fetch_assoc($er);
	$reply[] = "So far today: $b{$pq['count']}$b";
}

for ($i = 0; $i < 7; $i++) {
	$stop_uts = $start_uts;
	$start_uts -= $day_s;
	$linedate = date_fmt('D j M:', $start_uts+10);
	$er = Nextrastout::$db->pg_execute('dailylines', array($start_uts, $stop_uts, $qnick, $qchan));
	if ($er === false) {
		$reply[] = "$linedate Failed to execute";
		continue;
	}
	$pq = pg_fetch_assoc($er);
	$reply[] = "$linedate $b{$pq['count']}$b";
}
$reply = implode(', ', $reply);
$reply = "$prefix$reply";

$_i['handle']->say($_i['reply_to'], $reply);
