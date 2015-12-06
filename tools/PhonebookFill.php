<?php

require_once __DIR__ . '/../lib/Nextrastout.class.php';
require_once __DIR__ . '/../lib/log.php';

Nextrastout::dbconnect();

$q = Nextrastout::$db->pg_query("SELECT nick, message FROM log WHERE message ~ '^\.phonebook [0-9]{10}$' ORDER BY uts");
while ($qr = db::fetch_assoc($q)) {
	$phone_number = explode(' ', $qr['message']);
	$phone_number = $phone_number[1];
	$u = Nextrastout::$db->pg_upsert("UPDATE phonebook SET phone_number='$phone_number' WHERE nick='{$qr['nick']}'",
		"INSERT INTO phonebook (nick, phone_number) VALUES ('{$qr['nick']}', '$phone_number')");
	if ($u === false) {
		log::error('Query failed, aborting script');
		exit(1);
	}
}
