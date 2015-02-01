//<?php

log::trace('entered f::pack_list()');
list($prefix, $list, $_i) = $_ARGV;

$maxlen = 512 - (strlen($_i['handle']->nick) + strlen($_i['handle']->user) + strlen($_i['handle']->host)
	+ strlen($_i['reply_to']) + 15);

$ret = $prefix;
if (strlen($ret) >= $maxlen) {
	return $ret;
}
$ret .= array_shift($list);
if (strlen($ret) >= $maxlen) {
	return $ret;
}

foreach ($list as $li) {
	$append = ", $li";
	if ((strlen($ret) + strlen($append)) >= $maxlen) {
		return "$ret, ...";
	} else {
		$ret .= $append;
	}
}

return $ret;
