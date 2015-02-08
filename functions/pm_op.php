//<?php

log::trace('entered f::pm_op()');
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

$channel = dbescape(strtolower($uarg));

if (!array_key_exists($channel, uplink::$channels)) {
	log::debug('Channel does not exist');
	$_i['handle']->notice($_i['reply_to'], 'Channel does not exist');
	return f::FALSE;
}

if (in_array($nick, uplink::$channels[$channel]['o'])) {
	log::debug('Already has op');
	$_i['handle']->notice($_i['reply_to'], "You are already an operator on $channel");
	return f::FALSE;
}

if (uplink::is_oper($nick)) {
	log::info("Giving oper $nick op on channel $channel");
	ExtraServ::$serv_handle->send("MODE $channel +o $nick");
	$_i['handle']->notice($_i['reply_to'], 'Mode change sent for server operator');
	uplink::$channels[$channel]['o'][] = $nick;
	return f::TRUE;
} else {
	$query = "SELECT owner_ircuser FROM chan_register WHERE channel='$channel'";
	log::debug("op chan owner query >>> $query");
	$q = pg_query(ExtraServ::$db, $query);
	if ($q === false) {
		log::error('query failed');
		log::error(pg_last_error());
		$_i['handle']->notice($_i['reply_to'], 'Query failed');
		return f::FALSE;
	} elseif (pg_num_rows($q) == 0) {
		log::debug('channel not registered');
		$_i['handle']->notice($_i['reply_to'], 'Channel is not registered');
		return f::FALSE;
	} else {
		log::debug('query ok');
		$qr = pg_fetch_assoc($q);
		if ($qr['owner_ircuser'] == $user) {
			log::info("Adding op to $channel");
			ExtraServ::$serv_handle->send("MODE $channel +o $nick");
			$_i['handle']->notice($_i['reply_to'], 'Mode change sent for channel owner');
			uplink::$channels[$channel]['o'][] = $nick;
			return f::TRUE;
		} else {
			log::info("Unauthorized OP attempt by $nick for $channel");
			$_i['handle']->notice($_i['reply_to'], 'Unauthorized');
			return f::FALSE;
		}
	}
}
