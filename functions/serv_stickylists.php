//<?php

log::trace('entered f::serv_stickylists.php');
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
	$_i['handle']->notice($_i['reply_to'], 'Please specify a mode list and channel');
	return f::FALSE;
}

$uarg = explode(' ', $uarg, 2);

if (count($uarg) < 2) {
	log::debug('Insufficient arguments');
	$_i['handle']->notice($_i['reply_to'], 'Please specify a mode list and channel');
	return f::FALSE;
}

$fc = substr($uarg[1], 0, 1);
if (($fc != '#') && ($fc != '&')) {
	log::debug('Invalid argument');
	$_i['handle']->notice($_i['reply_to'], 'Channel names must begin with # or &');
	return f::FALSE;
}

$channel = dbescape(strtolower($uarg[1]));

$owner = f::get_channel_owner($channel);
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

$list_modes = array('*', 'b', 'e', 'I', 'o', 'h', 'v');
$modes = str_split($uarg[0], 1);
$mode_string = '';
foreach ($modes as $c) {
	if (!in_array($c, $list_modes)) {
		log::debug('Invalid mode character');
		$_i['handle']->notice($_i['reply_to'], 'Invalid mode character, must be one of: ' . implode($list_modes));
		return f::FALSE;
	} elseif ($c == '*') {
		$mode_string = 'beIohv';
		break;
	} else {
		$mode_string .= $c;
	}
}

$query = "UPDATE chan_register SET stickylists=TRUE, list_flags='$mode_string' WHERE channel='$channel'";
log::debug("stickylists update query >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Query failed');
	return f::FALSE;
} else {
	log::debug('query OK');
}

$modes = str_split($mode_string, 1);

$qn = 'stickylists_backfill';
if (!pg_is_prepared($qn)) {
	log::debug('Need to prepare query');
	$query = "INSERT INTO chan_stickylists (channel, mode_list, value) VALUES ($1, $2, $3)";
	log::debug("Preparing $qn >>> $query");
	$p = pg_prepare(ExtraServ::$db, $qn, $query);
	if ($p === false) {
		log::error('Failed to prepare query');
		log::error(pg_last_error());
		$_i['handle']->notice($_i['reply_to'], 'Query failed');
		return f::FALSE;
	}
} else {
	log::trace('query is already prepared');
}

foreach ($modes as $c) {
	foreach (uplink::$channels[$channel][$c] as $value) {
		pg_execute(ExtraServ::$db, $qn, array($channel, $c, $value));
		$_i['handle']->notice($_i['reply_to'], "Stored initial +$c $value");
		ExtraServ::$chan_stickylists[$channel][$c][] = $value;
	}
}

log::debug('Finished STICKYLISTS');
return f::TRUE;
