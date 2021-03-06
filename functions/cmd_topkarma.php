//<?php

log::trace('entered f::cmd_topkarma()');
list($_CMD, $params, $_i) = $_ARGV;

$channel = $_i['sent_to'];

$sayparts = array();

$q = Nextrastout::$db->pg_query("SELECT thing, sum(up) AS up, sum(down) AS down, sum(up) - sum(down) AS net FROM karma_cache WHERE channel='$channel' GROUP BY thing ORDER BY net DESC LIMIT 5",
	'most upvoted thing query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No matching rows');
	$sayparts[] = 'no upvotes';
} else {
	$saypart = array();
	$i = 0;
	while ($qr = pg_fetch_assoc($q)) {
		$fuvmc = number_format($qr['up']);
		$fdvmc = number_format($qr['down']);
		$fnvmc = number_format($qr['net']);
		if ($i == 0) {
			$saypart[] = "%g{$qr['thing']}%0 $fnvmc (+$fuvmc/-$fdvmc)";
		} else {
			$saypart[] = "{$qr['thing']} $fnvmc (+$fuvmc/-$fdvmc)";
		}
		$i++;
	}
	$sayparts[] = 'Most upvoted things: ' . implode(', ', $saypart);
}


$q = Nextrastout::$db->pg_query("SELECT thing, sum(down) AS down, sum(up) AS up, sum(up) - sum(down) AS net FROM karma_cache WHERE channel='$channel' GROUP BY thing ORDER BY net LIMIT 5",
	'most downvoted thing query');
if ($q === false) {
	$sayparts[] = 'Query failed';
	$_i['handle']->say($_i['reply_to'], $sayprefix . implode(' | ', $sayparts));
	return f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug('No matching rows');
	$sayparts[] = 'no downvotes';
} else {
	$saypart = array();
	$i = 0;
	while ($qr = pg_fetch_assoc($q)) {
		$fdvmc = number_format($qr['down']);
		$fuvmc = number_format($qr['up']);
		$fnvmc = number_format($qr['net']);
		if ($i == 0) {
			$saypart[] = "%r{$qr['thing']}%0 $fnvmc (+$fuvmc/-$fdvmc)";
		} else {
			$saypart[] = "{$qr['thing']} $fnvmc (+$fuvmc/-$fdvmc)";
		}
		$i++;
	}
	$sayparts[] = 'Most downvoted things: ' . implode(', ', $saypart);
}

$_i['handle']->say($_i['reply_to'], color_formatting::irc("Top karma items in $channel: " . implode(' | ', $sayparts)));
return f::TRUE;
