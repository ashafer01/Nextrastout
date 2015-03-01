<?php

require_once 'lib/functions.php';
require_once 'lib/utils.php';
require_once 'lib/log.php';
require_once 'lib/procs.php';

proc::$name = 'rbkcache';
log::$level = log::DEBUG;

date_default_timezone_set('UTC');

log::info('Connecting to database');
$dbpw = get_password('db');
$db = pg_connect("host=localhost dbname=extraserv user=alex password=$dbpw application_name=RebuildKarmaCache");
if ($db === false) {
	log::fatal('Failed to connect to database, exiting');
	exit(1);
}

log::info('Connected to db, truncating karma_cache');

$q = pg_query($db, 'TRUNCATE karma_cache');
if ($q === false) {
	log::fatal('Failed to truncate karma_cache');
	log::fatal(pg_last_error());
	exit(1);
}

log::info('Selecting log');

$irclog = pg_query($db, "SELECT nick, args AS channel, message FROM log WHERE command='PRIVMSG'");
if ($irclog === false) {
	log::fatal('Failed to select log');
	log::fatal(pg_last_error());
	exit(1);
}

log::info('Doing inserts');

$inserted = array();

$i = 0;
while ($qr = pg_fetch_assoc($irclog)) {
	$karma = f::parse_karma($qr['message']);
	if (count($karma) > 0) {
		foreach ($karma as $thing => $_) {
			$pk = $qr['channel'] . $qr['nick'] . $thing;
			if (in_array($pk, $inserted)) {
				continue;
			}
			$q = pg_query_params($db, 'INSERT INTO karma_cache (channel, nick, thing, up, down) VALUES ($1, $2, $3, 0, 0)', array($qr['channel'], $qr['nick'], $thing));
			if ($q === false) {
				log::fatal('Failed to insert row');
				log::fatal(pg_last_error());
				exit(1);
			}
			$inserted[] = $pk;
			$i++;

			if ($i % 1000 == 0) {
				log::debug("Inserted $i rows");
			}
		}
	}
}

pg_result_seek($irclog, 0);

log::info('Doing updates');

$i = 0;
while ($qr = pg_fetch_assoc($irclog)) {
	$karma = f::parse_karma($qr['message']);
	if (count($karma) > 0) {
		foreach ($karma as $thing => $changes) {
			$q = pg_query_params($db, 'UPDATE karma_cache SET up = up + $1, down = down + $2 WHERE channel=$3 AND nick=$4 AND thing=$5', array(
				$changes['++'],
				$changes['--'],
				$qr['channel'],
				$qr['nick'],
				$thing
			));

			$i++;
			if ($i % 1000 == 0) {
				log::debug("Updated $i rows");
			}
		}
	}
}

log::info('Done');
