//<?php

list($level, $message) = $_ARGV;

$message = color_formatting::strip($message);
$ms = microtime();
$ms = explode(' ', $ms);
$ms = substr($ms[0], 2, -2);
$ts = date('Y-m-d H:i:s');
$addr = str_pad($_SERVER['REMOTE_ADDR'], 15);
fwrite(log::$static->file, "[$ts.$ms] [$addr] $message\n");
