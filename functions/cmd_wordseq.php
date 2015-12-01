//<?php

log::trace('entered f::cmd_twowords()');
list($_CMD, $params, $_i) = $_ARGV;

if ($params == null) {
	log::debug('No nick supplied');
	$_i['handle']->say($_i['reply_to'], 'Please supply a nickname or list of nicks');
	return f::FALSE;
}
$nicks = array_map('trim', explode(',', strtolower($params)));
$channel = $_i['sent_to'];

switch ($_CMD) {
	case 'twowords':
		$N = 2;
		break;
	default:
		if (preg_match('/^(\d)words$/', $_CMD, $matches) === 1) {
			$N = (int) $matches[1];
		} else {
			return f::FALSE;
		}
}

$nick_in = implode(',', array_map('single_quote', array_map('dbescape', $nicks)));

$q = pg_query(ExtraServ::$db, $query = "SELECT message FROM log WHERE command='PRIVMSG' AND nick IN ($nick_in) AND args='$channel'");
log::debug("word_sequences query >>> $query");
$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('word_sequences query failed');
	log::error(pg_last_error());
	$say = 'Query failed';
} elseif (pg_num_rows($q) == 0) {
	log::debug('No results for word_sequences');
	$say = 'No results for nickname';
} else {
	log::debug('word_sequences query OK');

	$sequences = array();
	$stopwords = config::get_list('stopwords_extended');
	while ($row = pg_fetch_assoc($q)) {
		$words = array_map(function($w) {
			return str_replace(chr(1), '', $w);
		}, array_filter(array_map('trim', explode(' ', strtolower($row['message']))), function($w) use ($stopwords) {
			if ($w == null) {
				return false;
			}
			if ($w == chr(1).'action') {
				return false;
			}
			if (in_array($w, $stopwords)) {
				return false;
			}
			return true;
		}));
		$words = array_values($words);

		if (count($words) < $N) {
			continue;
		}
		for ($i = 0; $i < count($words) - ($N-1); $i++) {
			$seq = array();
			for ($j = $i; $j < $i+$N; $j++) {
				$seq[] = $words[$j];
			}
			$seq = implode(' ', $seq);
			if (isset($sequences[$seq])) {
				$sequences[$seq]++;
			} else {
				$sequences[$seq] = 1;
			}
		}
	}
	arsort($sequences);

	$sayparts = array();
	foreach ($sequences as $seq => $count) {
		$seq = str_replace(chr(1), '', $seq);
		$sayparts[] = "$seq ($count)";
	}

	if (count($nicks) > 1) {
		$saynick = implode(', ', $nicks);
	} else {
		$saynick = $nicks[0];
	}
	if ($N == 2) {
		$w = 'pairs';
	} else {
		$w = 'sequences';
	}
	$say = f::pack_list("Top $N-word $w for $saynick: ", $sayparts, $_i);
}

$_i['handle']->say($_i['reply_to'], $say);
