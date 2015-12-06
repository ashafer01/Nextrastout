<?php

require_once __DIR__ . '/../lib/Nextrastout.class.php';
require_once __DIR__ . '/../lib/log.php';
require_once __DIR__ . '/../lib/procs.php';

proc::$name = 'phonefill';
Nextrastout::dbconnect();

foreach (file($argv[1]) as $line) {
	$phone_number = explode(',', trim($line));
	$nick = strtolower($phone_number[0]);
	$phone_number = $phone_number[1];
	if (!ctype_digit($phone_number) || (strlen($phone_number) != 10)) {
		log::error("Invalid phone number $phone_number");
		continue;
	}
	$u = Nextrastout::$db->pg_upsert("UPDATE phonebook SET phone_number='$phone_number' WHERE nick='$nick'",
		"INSERT INTO phonebook (nick, phone_number) VALUES ('$nick', '$phone_number')");
	if ($u === false) {
		log::error('Query failed, aborting script');
		exit(1);
	}
}

log::info('Done');
