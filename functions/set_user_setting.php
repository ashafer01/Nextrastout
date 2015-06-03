//<?php

log::trace('entered f::set_user_setting()');
list($user, $setting, $val) = $_ARGV;

if (in_array($setting, array('ircuser', 'password', 'reg_uts'))) {
	log::warning('Non-setting column passed to f::set_user_setting()');
	return f::FALSE;
}

$val = strtolower(trim($val));
if (in_array($val, array('enable', 'true', 'yes', 'on'))) {
	$val = 'TRUE';
} elseif (in_array($val, array('disable', 'false', 'no', 'off'))) {
	$val = 'FALSE';
} else {
	log::debug('set_user_setting: Invalid setting');
	return f::FALSE;
}

$col = pg_escape_identifier($setting);
$q = pg_query_params(ExtraServ::$db, "UPDATE user_register SET $col = $val WHERE ircuser = $1", array($user));
if ($q === false) {
	log::error('set_user_setting query failed');
	log::error(pg_last_error());
	return f::FALSE;
} elseif (pg_affected_rows($q) == 0) {
	log::debug('user not registered');
	return f::FALSE;
} else {
	log::debug("Query OK, set $setting => $val for user '$user'");
}
