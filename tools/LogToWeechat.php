<?php

require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/utils.php';
require_once __DIR__ . '/../lib/log.php';
require_once __DIR__ . '/../lib/procs.php';
require_once __DIR__ . '/../lib/config.php';

$conf = config::get_instance();

proc::$name = 'log2wee';
log::$level = log::ERROR;

date_default_timezone_set('UTC');

log::info('Connecting to database');
$dbpw = get_password('db');
$db = pg_connect("host={$conf->db->host} dbname={$conf->db->name} user={$conf->db->user} password=$dbpw application_name=LogToWeechat");
if ($db === false) {
	log::fatal('Failed to connect to database, exiting');
	exit(1);
}

$log = pg_query($db, "SELECT uts, nick, message FROM log WHERE command='PRIVMSG' AND args='#geekboy' ORDER BY uts");
if ($log === false) {
	log::fatal('Failed to select log');
	log::fatal(pg_last_error());
	exit(1);
}

while ($qr = pg_fetch_assoc($log)) {
	$date = date('Y-m-d H:i:s', $qr['uts']);
	echo "$date\t{$qr['nick']}\t{$qr['message']}\n";
}
