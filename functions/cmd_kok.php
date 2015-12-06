//<?php

log::trace('entered f::cmd_kok()');

list($_CMD, $params, $_i) = $_ARGV;

$channel = $_i['sent_to'];
$where_notme = "nick NOT IN ('" . Nextrastout::$bot_handle->nick . "', 'extrastout')";

$b = chr(2);

$q = Nextrastout::$db->pg_query("SELECT thing, sum(up)-sum(down) AS net FROM karma_cache WHERE channel='$channel' AND thing IN (SELECT nick FROM karma_cache WHERE channel='$channel' GROUP BY nick) AND thing!=nick AND $where_notme GROUP BY thing ORDER BY net DESC LIMIT 15",
	'karma rank');
if ($q === false) {
	$say = 'Query failed';
} elseif (pg_num_rows($q) == 0) {
	log::debug('No matching rows');
	$say = 'No results';
} else {
	$rank = 1;
	$out = array();
	while ($qr = pg_fetch_assoc($q)) {
		$king_rank = f::king_rank($rank);
		if ($rank == 1) {
			$qr['thing'] = $b . rainbow($qr['thing']) . "\x03$b";
		}
		if ($rank <= 5) {
			$king_rank = "The $king_rank";
		}
		$fnet = number_format($qr['net']);
		$out[] = "$king_rank: {$qr['thing']} ($fnet)";
		$rank++;
	}

	$say = "Most voted nicks in $channel: " . implode('; ', $out);
}

$_i['handle']->say($_i['reply_to'], $say);
