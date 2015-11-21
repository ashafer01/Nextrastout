//<?php

log::trace('entered f::cmd_kol()');

list($_CMD, $param, $_i) = $_ARGV;

$channel = $_i['sent_to'];
$where = "channel='$channel'";

$query = "SELECT SUM(lines) AS count FROM statcache_lines WHERE $where";
log::debug("total query >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	$say = 'Query failed';
} else {
	log::debug('total query OK');
	$qr = pg_fetch_assoc($q);
	$total_count = $qr['count'];
	log::debug("Got total: $total_count");

	if ($total_count > 0) {
		$query = "SELECT nick, SUM(lines) AS count FROM statcache_lines WHERE $where GROUP BY nick ORDER BY count DESC LIMIT 10";
		log::debug("kol query >>> $query");
		$q = pg_query(ExtraServ::$db, $query);
		if ($q === false) {
			log::error('Query failed');
			log::error(pg_last_error());
			$say = 'Query failed';
		} else {
			log::debug('kol query OK');

			$b = chr(2);
			$total_str = number_format($total_count);
			$say = "Out of $b{$total_str}$b lines in $channel: ";

			$i = 1;
			$sayparts = array();
			$lines = 'lines';
			while ($qr = pg_fetch_assoc($q)) {
				if ($qr['count'] == 1) {
					$lines = 'line';
				}
				$count = number_format($qr['count']);
				$percent = number_format(($qr['count'] / $total_count) * 100, 2);

				if ($i == 1) {
					$nick = rainbow($qr['nick']);
					$sayparts[] = "The King: $b{$nick}\x03$b with $count $lines ($percent%)";
				} else {
					$rank = f::king_rank($i);
					if ($i <= 5) {
						$sp = 'The ';
					} else {
						$sp = '';
					}
					$sp .= "$rank: $b{$qr['nick']}$b with $count $lines ($percent%)";
					$sayparts[] = $sp;
				}
				$i++;
			}

			$say .= implode(', ', $sayparts);
		}
	} else {
		log::debug('No lines in channel');
		$say = 'No lines in channel';
	}
}

$_i['handle']->say($_i['reply_to'], $say);
