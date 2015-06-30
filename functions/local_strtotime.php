//<?php

list($time_str) = $_ARGV;

$tz = date_default_timezone_get();
date_default_timezone_set(ExtraServ::$output_tz);
$ret = strtotime($time_str);
date_default_timezone_set($tz);
return $ret;
