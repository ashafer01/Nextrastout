//<?php

log::trace('entered f::cmd_mms()');
list($ucmd, $uarg, $_i) = $_ARGV;

$uargs = explode(' -- ', $uarg, 2);
$uargs[] = '';

$media_urls = explode(' ', $uargs[0]);

$to = array_shift($media_urls);

$reply = f::sms($to, $uargs[1], $_i['hostmask']->nick, $_i['reply_to'], $media_urls);
$_i['handle']->say($_i['reply_to'], $reply);
