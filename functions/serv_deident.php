//<?php

log::trace('entered f::serv_deident()');
list($ucmd, $uarg, $_i) = $_ARGV;

$user = uplink::$nicks[strtolower($_i['prefix'])]['user'];
if (!array_key_exists($user, ExtraServ::$ident)) {
	log::debug('Not identified');
	$_i['handle']->notice($_i['reply_to'], 'You must identify before using this function');
	return f::FALSE;
}

unset(ExtraServ::$ident[$user]);
$_i['handle']->notice($_i['reply_to'], 'You have been deidentified.');
