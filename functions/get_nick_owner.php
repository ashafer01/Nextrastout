//<?php

log::trace('entered f::get_nick_owner()');
list($nick) = $_ARGV;

$inick = dbescape($nick);
$q = pg_query(ExtraServ::$db, "SELECT ircuser FROM user_nick_map WHERE nick='$inick'");
if ($q === false) {
	log::error('get_nick_owner query failed');
	log::error(pg_last_error());
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::trace('Nickname is not associated');
	return null;
} else {
	log::trace('get_nick_owner query ok');
	return pg_fetch_assoc($q)['ircuser'];
}
