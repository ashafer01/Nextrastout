//<?php

log::trace('Entered f::pm_register()');
list($_CMD, $uarg, $_i) = $_ARGV;

$nick = strtolower($_i['prefix']);
$user = uplink::$nicks[$nick]['user'];
if (array_key_exists($user, ExtraServ::$ident)) {
	log::debug('Already identified');
	$_i['handle']->notice($_i['reply_to'], 'You are already identified');
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

$ts = time();
$query = "INSERT INTO user_register (ircuser, password, reg_uts) VALUES ($1, crypt($2, gen_salt('bf')), $3)";
log::debug("user registration query >>> $query");
if (pg_send_query_params(ExtraServ::$db, $query, array($user, $uarg, $ts))) {
	$q = pg_get_result(ExtraServ::$db);
	$err = pg_result_error_field($q, PGSQL_DIAG_SQLSTATE);
	if ($err === null || $err == '00000') {
		log::debug('user registration query OK');
		ExtraServ::$ident[$user] = true;

		$query = "INSERT INTO user_nick_map (ircuser, nick) VALUES ($1, $2)";
		log::debug("nick association query >>> $query");
		if (pg_send_query_params(ExtraServ::$db, $query, array($user, $_i['prefix']))) {
			$q = pg_get_result(ExtraServ::$db);
			$err = pg_result_error_field($q, PGSQL_DIAG_SQLSTATE);
			if ($err === null || $err == '00000') {
				log::debug('nick association query OK');
				$_i['handle']->notice($_i['reply_to'], "Successfully registered username '$user' and added associated nickname '$nick'");
			} elseif ($err == '23505') {
				log::debug('unique key collision on nick association');
				$_i['handle']->notice($_i['reply_to'], "Successfully registered username '$user', but your nickname '$nick' is already associated with another user.");
			} else {
				log::error('Query failed');
				log::error(pg_result_error_field($q, PGSQL_DIAG_MESSAGE_PRIMARY));
				$_i['handle']->notice($_i['reply_to'], "Successfully registered username '$user', but the nick association query failed.");
			}
		} else {
			log::error('Failed to send nick association query');
			log::error(pg_last_error());
			$_i['handle']->notice($_i['reply_to'], "Successfully registered username '$user', but the nick association query failed");
			return f::FALSE;
		}
	} elseif ($err == '23505') {
		log::debug('username already registered');
		$_i['handle']->notice($_i['reply_to'], "Your username '$user' is already registered");
	} else {
		log::error('Query failed');
		log::error(pg_result_error_field($q, PGSQL_DIAG_MESSAGE_PRIMARY));
		$_i['handle']->notice($_i['reply_to'], 'Query failed');
	}
} else {
	log::error('Failed to send user registration query');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Query failed');
	return f::FALSE;
}
