//<?php

log::trace('entered f::cmd_kol()');

list($_CMD, $param, $_i) = $_ARGV;

$channel = $_i['sent_to'];

if ($param != null) {
	$word = dbescape(strtolower($param));
	$where = "word='$word' AND channel='$channel' AND nick NOT IN ('nextrastout','extrastout')";

	$q = Nextrastout::$db->pg_query("SELECT SUM(wc) AS count FROM statcache_words WHERE $where",
		'total query');
	if ($q === false) {
		$say = 'Query failed';
	} else {
		$qr = pg_fetch_assoc($q);
		$total_count = $qr['count'];
		log::debug("Got total: $total_count");

		if ($total_count > 0) {
			$q = Nextrastout::$db->pg_query("SELECT nick, SUM(wc) AS count FROM statcache_words WHERE $where GROUP BY nick ORDER BY count DESC LIMIT 10",
				'kotw query');
			if ($q === false) {
				$say = 'Query failed';
			} else {
				$b = chr(2);
				$total_str = number_format($total_count);
				$say  = "There are $b{$total_str}$b uses of $b{$word}$b in $channel | ";

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
			log::debug('No results');
			$say = 'No one has ever said that';
		}
	}
} else {
	$say = 'Please supply a word';
}


$_i['handle']->say($_i['reply_to'], $say);
