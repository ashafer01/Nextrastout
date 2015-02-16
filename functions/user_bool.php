//<?php

log::trace('entered f::user_bool()');
list($string) = $_ARGV;

$string = strtolower($string);
if (in_array($string, array('enable','enabled','on','true','yes'))) {
	return f::TRUE;
} elseif (in_array($string, array('disable','disabled','off','false','no'))) {
	return f::FALSE;
} else {
	return null;
}
