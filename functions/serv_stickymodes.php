//<?php

log::trace('entered f::pm_stickymodes()');
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

$chan = dbescape(strtolower($uarg));

if (!array_key_exists($chan, uplink::$channels)) {
	log::debug('Channel does not exist');
	$_i['handle']->notice($_i['reply_to'], 'Channel does not exist');
	return f::FALSE;
}

$owner = f::get_channel_owner($chan);
if ($owner === false) {
	$_i['handle']->notice($_i['reply_to'], 'Failed to look up channel owner');
	return f::FALSE;
} elseif ($owner === null) {
	$_i['handle']->notice($_i['reply_to'], 'Channel is not registered');
	return f::FALSE;
} elseif ($owner != $user) {
	$_i['handle']->notice($_i['reply_to'], 'Unauthorized.');
	return f::FALSE;
}

$updates = array();
$list_modes = array('b', 'e', 'I', 'o', 'h', 'v');
$modes_array = array_filter(uplink::$channels[$chan], function($val) use ($list_modes) {
	return !is_array($val);
});
$_modes_array = $modes_array;
if (array_key_exists('k', $_modes_array)) {
	$updates[] = "mode_k='{$_modes_array['k']}'";
	unset($_modes_array['k']);
}
if (array_key_exists('l', $_modes_array)) {
	$updates[] = "mode_l='{$_modes_array['l']}'";
	unset($_modes_array['l']);
}
$updates[] = "mode_flags='" . implode(array_keys($_modes_array)) . "'";
$updates[] = 'stickymodes=TRUE';

$set = implode(', ', $updates);
$query = "UPDATE chan_register SET $set WHERE channel='$chan'";
log::debug("stickymodes update query >>> $query");
$u = pg_query(ExtraServ::$db, $query);
if ($u === false) {
	log::error('stickymodes update query failed');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Query failed');
} else {
	log::debug('stickymodes update query OK');
	$_i['handle']->notice($_i['reply_to'], "Enabled stickymodes for channel '$chan'");
	ExtraServ::$chan_stickymodes[$chan] = $modes_array;
}
