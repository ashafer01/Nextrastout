//<?php

log::trace('entered f::cmd_sms()');
list($ucmd, $uarg, $_i) = $_ARGV;

$uargs = explode(' ', $uarg, 2);
$reply = f::sms($uargs[0], $uargs[1], $_i['hostmask']->nick, $_i['reply_to']);
$_i['handle']->say($_i['reply_to'], $reply);
