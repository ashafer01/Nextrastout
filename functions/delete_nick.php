//<?php

log::trace('entered f::delete_nick()');
list($nick) = $_ARGV;

$user = uplink::get_user_by_nick($nick);
if ($user === false) {
	log::warning("Nick '$nick' does not exist for delete_nick");
} else {
	uplink::remove_from_modelists($nick);
	unset(uplink::$nicks[$nick]);
	if (Nextrastout::$ident->offsetExists($user) && !f::user_exists($user)) {
		log::info("Deleted ident for user '$user'");
		unset(Nextrastout::$ident[$user]);
	}
	log::debug("Deleted nick '$nick'");
}
