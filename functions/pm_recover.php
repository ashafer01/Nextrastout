//<?php

log::trace('entered f::pm_recover()');
list($ucmd, $uarg, $_i) = $_ARGV;

$user = uplink::$nicks[$_i['prefix']]['user'];
if (!array_key_exists($user, ExtraServ::$ident)) {
	log::debug('Not identified');
	$_i['handle']->notice($_i['reply_to'], 'You must identify before using this function');
	return f::FALSE;
}

if ($uarg == null) {
	$_i['handle']->notice($_i['reply_to'], 'You must specify a nickname');
}

$want_nick = strtolower($uarg);
if (!array_key_exists($want_nick, uplink::$nicks)) {
	log::debug('Nick not in use');
	$_i['handle']->notice($_i['reply_to'], "Nickname '$want_nick' is not in use");
	return f::FALSE;
}

$want_nick = dbescape($want_nick);
$query = "SELECT ircuser FROM user_nick_map WHERE nick='$want_nick'";
log::debug("recover query >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Failed to look up nick association');
} elseif (pg_num_rows($q) == 0) {
	log::debug('Nick not associated');
	$_i['handle']->notice($_i['reply_to'], "Nickname '$want_nick' is not associated with any user");
} else {
	$qr = pg_fetch_assoc($q);
	if ($qr['ircuser'] == $user) {
		log::info('User owns wanted nick, doing swaps');
		$chars = 'abcdefghijklmnopqrstuvwxyz';
		do {
			$placeholder_nick = '';
			for ($i = 0; $i < 10; $i++) {
				$placeholder_nick .= substr($chars, rand(0, 25), 1);
			}
		} while (array_key_exists($placeholder_nick, uplink::$nicks));
		ExtraServ::svsnick($want_nick, $placeholder_nick);
		ExtraServ::svsnick($_i['prefix'], $uarg);
	} else {
		log::info('User does not own nickname');
		$_i['handle']->notice($_i['reply_to'], "You do not own nickname '$want_nick'");
	}
}
