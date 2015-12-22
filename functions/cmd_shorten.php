//<?php

log::trace('entering f::cmd_shorten()');
list($_CMD, $_ARG, $_i) = $_ARGV;

$_i['handle']->say($_i['reply_to'], $_i['hostmask']->nick . ': ' . f::shorten($_ARG));
