//<?php

log::trace('entered f::pm_validate()');
list($ucmd, $uargs, $_i) = $_ARGV;

$uargs = explode(' ', $uargs);
if (count($uargs) == 0) {
	log::debug('No arguments');
	$_i['handle']->notice($_i['reply_to'], 'Please supply a nickname');
	return f::FALSE;
}
$req_nick = strtolower($uargs[0]);

$api = false;
if ((count($uargs) >= 2) && ($uargs[1] == 'API')) {
	log::trace('API response requested');
	$api = true;
}

$reply = array();
$req_nick = dbescape($req_nick);
$owner = null;
$user = null;
$name = null;

$query = "SELECT ircuser FROM user_nick_map WHERE nick='$req_nick'";
log::debug("validate query >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	if ($api) {
		$_i['handle']->notice($_i['reply_to'], 'ERROR');
		return f::FALSE;
	} else {
		$reply[] = '*** Failed to look up nickname association!';
	}
} elseif (pg_num_rows($q) == 0) {
	log::debug('Nickname not registered');
	if ($api)
		$reply[] = 'NOASSOC';
	else
		$reply[] = '*** Nickname is not associated with any user';
} else {
	log::debug('validate query OK');
	$qr = pg_fetch_assoc($q);
	$owner = $qr['ircuser'];
	if (!$api)
		$reply[] = "* Owned by: $owner";
}

if (!array_key_exists($req_nick, uplink::$nicks)) {
	log::debug('Nickname not in use');
	if (!$api)
		$reply[] = '* Nickname is currently OFFLINE';
	else
		$reply[] = 'OFFLINE';
} else {
	log::debug('Nickname is online');
	$user = uplink::$nicks[$req_nick]['user'];
	$name = uplink::$nicks[$req_nick]['realname'];
	if (!$api) {
		$reply[] = '* Nickname is currently ONLINE';
		$reply[] = "* Current username: $user";
		$reply[] = "* Specified real name: $name";
	} else {
		$reply[] = 'ONLINE';
	}
}

if ($user != null) {
	if (array_key_exists($user, ExtraServ::$ident)) {
		if (!$api)
			$reply[] = '* User is identified';
		else
			$reply[] = 'IDENT';
	} else {
		if (!$api)
			$reply[] = '* User is not currently identified';
		else
			$reply[] = 'NOIDENT';
	}
}

if (($owner != null) && ($user != null)) {
	if ($owner == $user) {
		if (!$api)
			$reply[] = '*** Nickname is VALID';
		else
			$reply[] = 'VALID';
	} else {
		if (!$api)
			$reply[] = '*** Nickname is INVALID';
		else
			$reply[] = 'INVALID';
	}
}

if ($api) {
	log::trace('validate outputting API response');
	$_i['handle']->notice($_i['reply_to'], implode(' ', $reply));
} else {
	log::trace('validate outputting user response');
	foreach ($reply as $line)
		$_i['handle']->notice($_i['reply_to'], $line);
}
