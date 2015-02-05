//<?php

log::trace('entered f::log_where_nick()');

$_ARGV[] = null;
list($nick, $channel, $leading_and) = $_ARGV;
if ($leading_and === null) {
	$leading_and = true;
}

if (is_array($nick)) {
	$nicks = $nick;
} else {
	$nicks = array("$nick");
}

$nicks = array_map('strtolower', $nicks);
$nicks = array_map('dbescape', $nicks);
$nicks = array_map('single_quote', $nicks);
$nicks = implode(',', $nicks);

$query = str_replace(array("\n","\t"), array(' ',''),
"(
	(
		(split_part(args, ' ', 1) = '$channel')
		OR (split_part(args, ' ', 1) = '')
	) AND (
		(
			(command IN ('PRIVMSG','PART','QUIT','MODE','TOPIC'))
			AND (nick IN ($nicks))
		) OR (
			(command = 'JOIN')
			AND (message = '$channel')
			AND (nick IN ($nicks))
		) OR (
			(command = 'NICK')
			AND (lower(message) IN ($nicks))
		)
	)
)");

if ($leading_and) {
	$query = " AND $query";
}
return $query;
