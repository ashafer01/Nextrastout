//<?php

log::trace('entered f::cmd_topic()');
list($_CMD, $args, $_i) = $_ARGV;

$_i['handle']->say($_i['reply_to'], 'Coming soon');
