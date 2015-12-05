//<?php

log::trace('entered f::cmd_logsearch()');

list($command, $params, $_i) = $_ARGV;

$where = '';
$rainbow = false;
$table = 'log';
$req_nicks = null;
switch ($command) {
	case 'fn':
		$params = explode(' ', $params, 2);
		$req_nicks = strtolower($params[0]);
		$params[] = '';
		$params = $params[1];
	case 'first':
	case 'f':
		$orderby = 'ORDER BY uts ASC';
		$limit = 'LIMIT 1';
		if (preg_match('/^[+-](\d+)(.*)/', $params, $matches) === 1) {
			$offset = dbescape($matches[1]);
			$limit .= " OFFSET $offset";
			$params = trim($matches[2]);
		}
		break;
	case 'ln':
		$params = explode(' ', $params, 2);
		$req_nicks = strtolower($params[0]);
		$params[] = '';
		$params = $params[1];
	case 'last':
	case 'l':
		$orderby = 'ORDER BY uts DESC';
		$limit = 'LIMIT 1';
		if (preg_match('/^[+-](\d+)(.*)/', $params, $matches) === 1) {
			$offset = dbescape($matches[1]);
			$limit .= " OFFSET $offset";
			$params = trim($matches[2]);
		}
		break;
	case 'line':
		if ($params == null) {
			$_i['handle']->say($_i['reply_to'], 'Please specify a line number');
			return null;
		}
		$args = explode(' ', $params, 2);
		if (!ctype_digit($args[0])) {
			$_i['handle']->say($_i['reply_to'], 'Please specify a line number first');
			return null;
		}
		$params = $args[1];
		$orderby = 'ORDER BY uts ASC';
		$limit = "LIMIT 1 OFFSET {$args[0]}";
		break;
	case 'rrandomcaps':
	case 'rrandcaps':
	case 'rrancaps':
		$rainbow = true;
	case 'randcaps':
	case 'rancaps':
	case 'randomcaps':
		$table = 'caps_cache';
		$orderby = 'ORDER BY random()';
		$limit = 'LIMIT 1';
		break;
	case 'random':
		$orderby = 'ORDER BY random()';
		$limit = 'LIMIT 1';
		break;
	case 'logsearch':
		log::warning('!logsearch called directly, defaulting to random');
		$orderby = 'ORDER BY random()';
		$limit = 'LIMIT 1';
		break;
}

$query_params = f::parse_logquery($params);
if ($req_nicks != null) {
	$query_params->req_nicks[] = array($req_nicks);
}

foreach ($query_params->notlikes as $nl) {
	if ((count($nl) >= 2) && ($nl[0] == 'n')) {
		$plus = '';
		if (count($nl) >= 3) {
			$plus = ' +' . implode(' ', array_slice($nl, 2));
		}
		$_i['handle']->say($_i['reply_to'], "You may be looking for @{$nl[1]}$plus");
		break;
	}
}

$conf = config::get_instance();
$query_params->exc_nicks[] = array($conf->bot_handle);

if ($command == 'line') {
	$cmd_in = '1=1';
} else {
	$cmds = f::LISTALL();
	$cmds = array_filter($cmds, function($e) {
		return (substr($e, 0, 4) == 'cmd_');
	});
	$cmds = array_map(function($e) {
		return '!' . substr($e, 4);
	}, $cmds);
	$cmds = array_filter($cmds, function($e) use ($query_params) {
		foreach (array('likes', 'req_wordbound') as $var) {
			foreach ($query_params->$var as $list) {
				if (in_array($e, $list)) {
					return false;
				}
			}
		}
		return true;
	});
	$cmd_in = "(message NOT SIMILAR TO '(" . implode('|', $cmds) . ")%')";
}

$channel = $_i['sent_to'];
$query = "SELECT uts, nick, message FROM $table WHERE (command='PRIVMSG' AND args='$channel') AND $cmd_in" . f::log_where($query_params) . "$where $orderby $limit";
log::debug("log search query >>> $query");

$q = pg_query(Nextrastout::$db, $query);
if ($q === false) {
	log::error("Query failed");
	log::error(pg_last_error());
	$say = 'Query failed';
} elseif (pg_num_rows($q) == 0) {
	log::debug('No results for log search query');
	$say = 'No results';
} else {
	log::debug('log search query ok');
	$qr = pg_fetch_assoc($q);
	$b = chr(2); #bold
	$ts = smart_date_fmt($qr['uts']);
	if (!$rainbow) {
		$say = "\"{$qr['message']}\" ~$b{$qr['nick']}$b ($ts)";
	} else {
		$msg = rainbow($qr['message']);
		$say = "\"$msg\x03\" ~$b{$qr['nick']}$b ($ts)";
	}
}

$_i['handle']->say($_i['reply_to'], $say);
