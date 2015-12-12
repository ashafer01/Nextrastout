//<?php

log::trace('entered f::cmd_stat()');
list($_CMD, $_ARG, $_i, $_globals) = $_ARGV;

$pid = getmypid();

# Get memory usage
$mem = ceil(memory_get_usage(false)/1024);
$alloc = ceil(memory_get_usage(true)/1024);

# Get uptime
$uptime_string = duration_str(time()-Nextrastout::$start_time);

# Reply
$_i['handle']->say($_i['reply_to'], "PID: $pid; Using $mem KB of RAM ($alloc KB allocated); $uptime_string up");
