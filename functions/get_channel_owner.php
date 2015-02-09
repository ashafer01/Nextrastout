//<?php

list($channel) = $_ARGV;

$channel = dbescape($channel);

$query = "SELECT owner_ircuser FROM chan_register WHERE channel='$channel'";
log::debug("f::get_channel_owner() query >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('Channel not registered');
	return null;
} else {
	log::debug('query ok');
	$qr = pg_fetch_assoc($q);
	return $qr['owner_ircuser'];
}
