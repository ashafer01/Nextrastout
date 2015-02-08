//<?php

log::trace('Entered f::pm_identify()');
list($_CMD, $uarg, $_i) = $_ARGV;

$nick = strtolower($_i['prefix']);
$user = uplink::$nicks[$nick]['user'];
if (array_key_exists($user, ExtraServ::$ident)) {
	log::debug('Already identified');
	$_i['handle']->notice($_i['reply_to'], 'You are already identified');
	return f::FALSE;
}

$uarg = substr($uarg, 0, 72);

$query = "SELECT (password = crypt($1, password)) AS valid FROM user_register WHERE ircuser=$2";
log::debug("identify query >>> $query");
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
		log::info("Identify OK for user '$user'");
		ExtraServ::$ident[$user] = true;
		$_i['handle']->notice($_i['reply_to'], 'You are now identified');

		if (uplink::is_oper($nick)) {
			$_i['handle']->notice($_i['reply_to'], 'Welcome, operator.');
		}
	} else {
		log::info('Password incorrect');
		$_i['handle']->notice($_i['reply_to'], 'Password incorrect');
	}
}
