//<?php

log::trace('entered f::cmd_logsearch()');

list($command, $params, $_i) = $_ARGV;

$where = '';
$rainbow = false;
$capswhere = " AND message SIMILAR TO '[A-Z][A-Z!,\?\.''\" ]{3,}'";
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
		$where = $capswhere;
		$rainbow = true;
	case 'randcaps':
	case 'rancaps':
	case 'randomcaps':
		$where = $capswhere;
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

$cmds = f::LISTALL();
$cmds = array_filter($cmds, function($e) {
	return (substr($e, 0, 4) == 'cmd_');
});
$cmds = array_map(function($e) {
	return '!' . substr($e, 4);
}, $cmds);
$cmd_in = implode('|', $cmds);

$query = "SELECT uts, nick, message FROM log WHERE (command='PRIVMSG' AND args='{$_i['sent_to']}') AND (message NOT SIMILAR TO '($cmd_in)%')" . f::log_where($query_params) . "$where $orderby $limit";
log::debug("log search query >>> $query");

$q = pg_query(ExtraServ::$db, $query);
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
