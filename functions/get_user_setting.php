//<?php

log::trace('entered f::get_user_setting()');
list($user, $setting) = $_ARGV;

$col = pg_escape_identifier($setting);
$iuser = pg_escape_literal($user);
$query = "SELECT $col FROM user_register WHERE ircuser=$iuser";
log::debug("get_user_setting query >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('get_user_setting query failed');
	log::error(pg_last_error());
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('user not registered');
	return null;
} else {
	$qr = pg_fetch_assoc($q);
	if (array_key_exists($setting, $qr)) {
		return $qr[$setting];
	} else {
		log::warning('Attempted SQL injection?');
		return f::FALSE;
	}
}
