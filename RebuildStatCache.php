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
}

ExtraServ::$db = $db;

require_once 'lib/es_utils.php';

log::info('Connected to db, truncating statcache_* tables');

foreach (array('statcache_lines', 'statcache_misc', 'statcache_words', 'statcache_twowords') as $table) {
	$q = pg_query($db, "TRUNCATE $table");
	if ($q === false) {
		log::fatal("Failed to truncate $table");
		log::fatal(pg_last_error());
		exit(1);
	}
}

log::info('Selecting log');

$irclog = pg_query($db, "SELECT uts, nick, args AS channel, message FROM log WHERE command='PRIVMSG' ORDER BY uts");
if ($irclog === false) {
	log::fatal('Failed to select log');
	log::fatal(pg_last_error());
	exit(1);
}

log::info('Starting processing');

$n = number_format(pg_num_rows($irclog));
$l = strlen($n);
$i = 0;
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
	printf(" %{$l}s / %s\r", number_format($i), $n);
}

echo "\n";

log::info('Done');
