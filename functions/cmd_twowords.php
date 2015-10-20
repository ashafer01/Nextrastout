//<?php

log::trace('entered f::cmd_twowords()');
list($_CMD, $params, $_i) = $_ARGV;

if ($params == null) {
	$_i['handle']->say($_i['reply_to'], 'Please supply a nickname or list of nicks');
	return f::FALSE;
}
$nicks = array_map('trim', explode(',', strtolower($params)));
$nick_in = implode(',', array_map('single_quote', array_map('dbescape', $nicks)));

$channel = $_i['sent_to'];

$q = pg_query(ExtraServ::$db, $query = "SELECT message FROM log WHERE command='PRIVMSG' AND nick IN ($nick_in) AND args='$channel'");
log::debug("twowords query >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('twowords query failed');
	log::error(pg_last_error());
	$say = 'Query failed';
} elseif (pg_num_rows($q) == 0) {
	log::debug('No results for twowords');
	$say = 'No twowords';
} else {
	log::debug('twowords query OK');

	$pairs = array();
	$stopwords = config::get_list('stopwords');
	while ($row = pg_fetch_assoc($q)) {
		$words = array_filter(array_map('trim', explode(' ', strtolower($row['message']))), function($w) use ($stopwords) {
			if ($w == null) {
				return false;
			}
			if (in_array($w, $stopwords)) {
				return false;
			}
			return true;
		});
		$words = array_values($words);

		if (count($words) < 2) {
			continue;
		}
		for ($i = 0; $i < count($words)-1; $i++) {
			$pair = $words[$i] . ' ' . $words[$i+1];
			if (isset($pairs[$pair])) {
				$pairs[$pair]++;
			} else {
				$pairs[$pair] = 1;
			}
		}
	}
	arsort($pairs);

	$sayparts = array();
	foreach ($pairs as $pair => $count) {
		$pair = str_replace(chr(1).'action', '*', $pair);
		$pair = str_replace(chr(1), '', $pair);
		$sayparts[] = "$pair ($count)";
	}

	if (count($nicks) > 1) {
		$saynick = implode(', ', $nicks);
	} else {
		$saynick = $nicks[0];
	}
	$say = f::pack_list("Top two-word pairs for $saynick: ", $sayparts, $_i);
}

$_i['handle']->say($_i['reply_to'], $say);
