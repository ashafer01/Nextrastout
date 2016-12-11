//<?php

log::trace('entering f::cmd_shorten()');
list($_CMD, $_ARG, $_i) = $_ARGV;

if ($_i['in_pm']) {
	$say = 'shorten not allowed in PM';
} else {
	$say = $_i['hostmask']->nick . ': ' . f::shorten($_ARG, $_i['hostmask']->nick);
}

$_i['handle']->say($_i['reply_to'], $say);
