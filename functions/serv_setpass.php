//<?php

log::trace('entered f::serv_setpass()');
list($ucmd, $uarg, $_i) = $_ARGV;

$nick = strtolower($_i['prefix']);
$user = uplink::$nicks[$nick]['user'];
if (!array_key_exists($user, ExtraServ::$ident)) {
	log::debug('Not identified');
	$_i['handle']->notice($_i['reply_to'], 'You must identify before using this function');
	return f::FALSE;
}

if (strlen($uarg) > 72) {
	log::debug('Password is too long');
	$_i['handle']->notice($_i['reply_to'], 'Password must be no longer than 72 characters');
	return f::FALSE;
} elseif (strlen($uarg) < 4) {
	log::debug('Password is too short');
	$_i['handle']->notice($_i['reply_to'], 'Password must be at least 4 characters');
	return f::FALSE;
}

$query = "UPDATE user_register SET password=crypt($1, gen_salt('bf')) WHERE ircuser=$2";
log::debug("setpass query >>> $query");
$q = pg_query_params(ExtraServ::$db, $query, array($uarg, $user));
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Query failed');
} else {
	log::debug('Query OK');
	$_i['handle']->notice($_i['reply_to'], 'Password has been updated');
}
