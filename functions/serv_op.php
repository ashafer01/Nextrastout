//<?php

log::trace('entered f::serv_op()');
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
	$owner = f::get_channel_owner($channel);
	if ($owner === false) {
		$_i['handle']->notice($_i['reply_to'], 'Failed to look up channel owner');
		return f::FALSE;
	} elseif ($owner === null) {
		$_i['handle']->notice($_i['reply_to'], 'Channel is not registered');
		return f::FALSE;
	} else {
		if ($owner == $user) {
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
