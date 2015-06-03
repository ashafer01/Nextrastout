//<?php

log::trace('Entered f::serv_identify()');
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

		$in_chans = implode(',', array_map('single_quote', uplink::$nicks[$nick]['channels']->getArrayCopy()));
		$q = pg_query(ExtraServ::$db, "SELECT channel, mode_list FROM chan_stickylists WHERE channel IN ($in_chans) AND mode_list IN ('o','h','v') AND value='$nick'");
		if ($q === false) {
			log::error("Failed to look up sticky lists for ident");
			log::error(pg_last_error());
			$_i['handle']->notice($_i['reply_to'], 'Failed to look up sticky lists');
		} elseif (pg_num_rows($q) == 0) {
			log::debug('No sticky lists for ident');
		} else {
			while ($qr = pg_fetch_assoc($q)) {
				$c = $qr['mode_list'];
				$chan = $qr['channel'];
				if (!in_array($nick, uplink::$channels[$chan][$c]->getArrayCopy())) {
					log::debug("Sending $chan +$c $nick on ident");
					ExtraServ::$serv_handle->send("MODE $chan +$c $nick");
					var_dump(uplink::$channels[$chan]);
					uplink::$channels[$chan][$c][] = $nick;
				} else {
					log::debug("Nick $nick already has $chan +$c on ident");
				}
			}
		}
	} else {
		log::info('Password incorrect');
		$_i['handle']->notice($_i['reply_to'], 'Password incorrect');
	}
}
