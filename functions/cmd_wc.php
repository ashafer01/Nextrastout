//<?php

log::trace('entered f::cmd_wc()');
list($_CMD, $params, $_i) = $_ARGV;

if ($params == null) {
	$_i['handle']->say($_i['reply_to'], 'Please supply a word');
	return f::FALSE;
}

$channel = $_i['sent_to'];

$params = explode(' ', $params);
switch ($_CMD) {
	case 'nwc':
		if (count($params) == 1) {
			$nick = $_i['hostmask']->nick;
			$word = dbescape(ltrim($params[0], '+='));
		} else {
			$nick = dbescape(ltrim($params[0], '@'));
			$word = dbescape(ltrim($params[1], '+='));
		}
		$where = "word='$word' AND nick='$nick' AND channel='$channel'";
		break;
	case 'wc':
		$word = dbescape(ltrim($params[0], '+='));
		$where = "word='$word' AND channel='$channel'";
		break;
	default:
		log::warning('unhandled command in f::cmd_wc()');
		return f::FALSE;
}


$q = pg_query(Nextrastout::$db, "SELECT SUM(wc) AS wc FROM statcache_words WHERE $where");
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	$say = 'Query failed';
} else {
	$qr = pg_fetch_assoc($q);
	$b = chr(2);
	if ($q['wc'] == 1) {
		$times = 'time';
	} else {
		$times = 'times';
	}
	$n = number_format($qr['wc']);
	switch ($_CMD) {
		case 'nwc':
			$say = "$nick has said $word in $channel $b{$n}$b $times";
			break;
		case 'wc':
			$say = "$word has been said in $channel $b{$n}$b $times";
			break;
	}
}

$_i['handle']->say($_i['reply_to'], $say);
