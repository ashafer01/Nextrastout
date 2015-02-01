//<?php

log::trace('entered f::cmd_kol()');

list($_CMD, $param, $_i) = $_ARGV;

$where = "(command='PRIVMSG' AND args='{$_i['sent_to']}')";
if ($param != null) {
	$where .= f::log_where($param);
}

$query = "SELECT COUNT(uts) AS count FROM newlog WHERE $where";
$lq = color_formatting::escape($query);
log::debug("total matching rows query >>> $lq");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	$say = 'Query failed';
} else {
	log::debug('total matching rows query OK');
	$qr = pg_fetch_assoc($q);
	$total_count = $qr['count'];
	log::debug("Got total matching rows: $total_count");

	if ($total_count > 0) {
		$query = "SELECT nick, COUNT(uts) AS count FROM newlog WHERE $where GROUP BY nick ORDER BY count DESC LIMIT 11";
		$lq = color_formatting::escape($query);
		log::debug("kol query >>> $lq");
		$q = pg_query(ExtraServ::$db, $query);
		if ($q === false) {
			log::error('Query failed');
			log::error(pg_last_error());
			$say = 'Query failed';
		} else {
			log::debug('kol query OK');

			$b = chr(2);
			$total_str = number_format($total_count);
			$say = "Matched $b{$total_str}$b lines in {$_i['sent_to']}: ";

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
		log::debug('No matching rows');
		$say = 'No matching rows';
	}
}

$_i['handle']->say($_i['reply_to'], $say);
