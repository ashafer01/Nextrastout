//<?php

log::trace('entered f::serv_associate()');
list($ucmd, $uarg, $_i) = $_ARGV;

$nick = strtolower($_i['prefix']);
$user = uplink::$nicks[$nick]['user'];
if (!array_key_exists($user, ExtraServ::$ident)) {
	log::debug('Not identified');
	$_i['handle']->notice($_i['reply_to'], 'You must identify before using this function');
	return f::FALSE;
}

# check password
if ($uarg == null) {
	log::trace('No password supplied');
	$_i['handle']->notice($_i['reply_to'], 'Please supply your password');
	return f::FALSE;
}

$uarg = substr($uarg, 0, 72);

$query = "SELECT (password = crypt($1, password)) AS valid FROM user_register WHERE ircuser=$2";
log::debug("associate pw check query >>> $query");
$q = pg_query_params(ExtraServ::$db, $query, array($uarg, $user));
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Query failed');
} elseif (pg_num_rows($q) == 0) {
	log::info('User not registered');
	$_i['handle']->notice($_i['reply_to'], 'You are not registered');
} else {
	$qr = pg_fetch_assoc($q);
	if ($qr['valid'] == 't') {
		log::info('Password OK for associate');
	} else {
		log::info('Password incorrect');
		$_i['handle']->notice($_i['reply_to'], 'Password incorrect');
		return f::FALSE;
	}
}

$query = "INSERT INTO user_nick_map (ircuser, nick) VALUES ($1, $2)";
log::debug("nick association query >>> $query");
if (pg_send_query_params(ExtraServ::$db, $query, array($user, $nick))) {
	$q = pg_get_result(ExtraServ::$db);
	$err = pg_result_error_field($q, PGSQL_DIAG_SQLSTATE);
	if ($err === null || $err == '00000') {
		log::debug('nick association query OK');
		$_i['handle']->notice($_i['reply_to'], "Successfully associated nickname '$nick' with your username");
	} elseif ($err == '23505') {
		log::debug('unique key collision on nick association');
		$_i['handle']->notice($_i['reply_to'], "Nickname '$nick' is already associated with another user");
	} else {
		log::error('Query failed');
		log::error(pg_result_error_field($q, PGSQL_DIAG_MESSAGE_PRIMARY));
		$_i['handle']->notice($_i['reply_to'], 'Query failed');
	}
} else {
	log::error('Failed to send nick association query');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Query failed');
	return f::FALSE;
}
