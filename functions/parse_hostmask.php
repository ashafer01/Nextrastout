//<?php

log::trace('entering f::parse_hostmask()');
list($hostmask) = $_ARGV;

$match = preg_match('/^([^!]+)!([^@]+)@(.+)$/', $hostmask, $parts);
if ($match === 1) {
	return (object) array(
		'nick' => $parts[1],
		'user' => $parts[2],
		'host' => $parts[3]
	);
} else {
	return f::FALSE;
}
