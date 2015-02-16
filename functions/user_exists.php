//<?php

log::trace('entered f::user_exists()');
list($user) = $_ARGV;

foreach (uplink::$nicks as $nick => $params) {
	if ($params['user'] == $user) {
		return f::TRUE;
	}
}

return f::FALSE;
