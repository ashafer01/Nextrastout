<?php

require_once 'lib/functions.php';
require_once 'lib/utils.php';
require_once 'lib/log.php';
require_once 'lib/procs.php';
require_once 'lib/config.php';

$conf = config::get_instance();

proc::$name = 'dumpqdb';
log::$level = log::DEBUG;

date_default_timezone_set('UTC');

log::info('Connecting to database');
$dbpw = get_password('db');
$db = pg_connect("host={$conf->db->host} dbname={$conf->db->name} user={$conf->db->user} password=$dbpw application_name=DumpQuoteDB");
if ($db === false) {
	log::fatal('Failed to connect to database, exiting');
	exit(1);
}

$quotes = pg_query($db, "SELECT * FROM quotedb ORDER BY id");
if ($quotes === false) {
	log::fatal('Failed to select log');
	log::fatal(pg_last_error());
	exit(1);
}

while ($qr = pg_fetch_assoc($quotes)) {
	printf("%5d| %s\n", $qr['id'], $qr['quote']);
}
