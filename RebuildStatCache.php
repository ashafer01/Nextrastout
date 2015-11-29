<?php

require_once 'lib/functions.php';
require_once 'lib/utils.php';
require_once 'lib/log.php';
require_once 'lib/procs.php';
require_once 'lib/config.php';

$conf = config::get_instance();

proc::$name = 'rbscache';
log::$level = log::DEBUG;

date_default_timezone_set('UTC');

log::info('Connecting to database');
$dbpw = get_password('db');
$db = pg_connect("host={$conf->db->host} dbname={$conf->db->name} user={$conf->db->user} password=$dbpw application_name=RebuildStatCache");
if ($db === false) {
	log::fatal('Failed to connect to database, exiting');
	exit(1);
}

class ExtraServ {
	public static $db;
	public static $prepared_queries = array();
}
ExtraServ::$db = $db;

log::info('Connected to db');

require_once 'lib/es_utils.php';

#log::info('truncating statcache_* tables');
#foreach (array('statcache_lines', 'statcache_misc', 'statcache_words', 'statcache_twowords') as $table) {
#	$q = pg_query($db, "TRUNCATE $table");
#	if ($q === false) {
#		log::fatal("Failed to truncate $table");
#		log::fatal(pg_last_error());
#		exit(1);
#	}
#}

log::info('Selecting log');

$irclog = pg_query($db, "SELECT uts, nick, args AS channel, message FROM log WHERE command='PRIVMSG' ORDER BY uts");
if ($irclog === false) {
	log::fatal('Failed to select log');
	log::fatal(pg_last_error());
	exit(1);
}

log::info('Starting processing');

$n = pg_num_rows($irclog);
$total = number_format($n);
$l = strlen($total);
$i = 0;
$time = time();
$s_n = 0;
$rate = '?';
$est_complete = '?';
$status_fmt = " %{$l}s / $total (%6s%%) | %7s lines/sec | Est completion %s   \r";
while ($row = pg_fetch_assoc($irclog)) {
	$_i = array(
		'uts' => $row['uts'],
		'text' => dbescape($row['message']),
		'cmd' => 'PRIVMSG',
		'sent_to' => $row['channel'],
		'hostmask' => new stdClass
	);
	$_i['hostmask']->nick = $row['nick'];
	f::statcache_line($_i);
	$i++;
	$ntime = time();
	if ($ntime != $time) {
		$time = $ntime;
		$s_complete = round(($n - $i) / $s_n);
		$est_complete = date('Y-m-d H:i:s', $ntime + $s_complete);
		$rate = number_format($s_n);
		$s_n = 0;
	}
	$s_n++;
	printf($status_fmt, number_format($i), number_format(($i/$n)*100, 2), $rate, $est_complete);
}

echo "\n";

log::info('Done');
