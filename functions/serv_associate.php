//<?php

log::trace('entered f::pm_associate()');
list($ucmd, $uarg, $_i) = $_ARGV;

$nick = strtolower($_i['prefix']);
$user = uplink::$nicks[$nick]['user'];
if (!array_key_exists($user, ExtraServ::$ident)) {
	log::debug('Not identified');
	$_i['handle']->notice($_i['reply_to'], 'You must identify before using this function');
	return f::FALSE;
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
