//<?php

log::trace('entered f::log_where_nick()');

$_ARGV[] = null;
list($nick, $channel, $leading_and) = $_ARGV;
if ($leading_and === null) {
	$leading_and = true;
}

$nick = dbescape(strtolower($nick));

$query = str_replace(array("\n","\t"), array(' ',''), "(((split_part(args, ' ', 1) = '$channel') OR (split_part(args, ' ', 1) = ''))
	AND ( ((command IN ('PRIVMSG','PART','QUIT','MODE','TOPIC')) AND (nick = '$nick'))
		OR ((command = 'JOIN') AND (message = '$channel') AND (nick = '$nick'))
		))");
if ($leading_and) {
	$query = " AND $query";
}
return $query;
