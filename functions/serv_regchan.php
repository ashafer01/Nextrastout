//<?php

log::trace('entered f::serv_regchan()');
list($ucmd, $uarg, $_i) = $_ARGV;

$nick = strtolower($_i['prefix']);
$user = uplink::$nicks[$nick]['user'];
if (!array_key_exists($user, ExtraServ::$ident)) {
	log::debug('Not identified');
	$_i['handle']->notice($_i['reply_to'], 'You must identify before using this function');
	return f::FALSE;
}

if ($uarg == null) {
	log::debug('No argument');
	$_i['handle']->notice($_i['reply_to'], 'Please specify a channel');
	return f::FALSE;
}

$fc = substr($uarg, 0, 1);
if (($fc != '#') && ($fc != '&')) {
	log::debug('Invalid argument');
	$_i['handle']->notice($_i['reply_to'], 'Channel names must begin with # or &');
	return f::FALSE;
}

$channel = strtolower($uarg);

if (!array_key_exists($channel, uplink::$channels)) {
	log::debug('Channel does not exist');
	$_i['handle']->notice($_i['reply_to'], 'Channel does not exist');
	return f::FALSE;
}

if (!in_array($nick, uplink::$channels[$channel]['o']->getArrayCopy())) {
	log::debug('Not an op');
	$_i['handle']->notice($_i['reply_to'], "You are not an operator on channel $channel");
	return f::FALSE;
}

log::debug('Okay to try channel registration');

$user = dbescape($user);
$channel = dbescape($channel);
$ts = time();
$query = "INSERT INTO chan_register (channel, reg_uts, owner_ircuser) VALUES ('$channel', $ts, '$user')";
log::debug("regchan query >>> $query");
if (pg_send_query(ExtraServ::$db, $query)) {
	$q = pg_get_result(ExtraServ::$db);
	$err = pg_result_error_field($q, PGSQL_DIAG_SQLSTATE);
	if ($err === null || $err == '00000') {
		log::debug('query OK');
		$_i['handle']->notice($_i['reply_to'], "Channel $channel has been registered to your username ($user)");
		return f::TRUE;
	} elseif ($err == '23505') {
		log::debug('channel already registered');
		$_i['handle']->notice($_i['reply_to'], "Channel $channel is already registered");
		return f::FALSE;
	} else {
		log::error('Query failed');
		log::error(pg_result_error($q));
		$_i['handle']->notice($_i['reply_to'], 'Query failed');
		return f::FALSE;
	}
} else {
	log::error('Failed to send query');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Query failed');
	return f::FALSE;
}
