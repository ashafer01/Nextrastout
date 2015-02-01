//<?php

log::trace('entered f::cmd_logsearch()');

list($command, $params, $_i) = $_ARGV;

$do_offset = false;
switch ($command) {
	case 'first':
	case 'f':
		$orderby = 'ORDER BY uts ASC';
		$do_offset = true;
		break;
	case 'last':
	case 'l':
		$orderby = 'ORDER BY uts DESC';
		$do_offset = true;
		break;
	case 'random':
	case 'rrandom':
		$orderby = 'ORDER BY random()';
		break;
	case 'logsearch':
		log::warning('!logsearch called directly, defaulting to random');
		$orderby = 'ORDER BY random()';
		break;
}
$limit = 'LIMIT 1';
if ($do_offset && (preg_match('/^[+-](\d+)(.*)/', $params, $matches) === 1)) {
	$offset = dbescape($matches[1]);
	$limit .= " OFFSET $offset";
	$params = trim($matches[2]);
}

$query = "SELECT uts, nick, message FROM newlog WHERE (command='PRIVMSG' AND args='{$_i['sent_to']}')" . f::log_where($params) . " $orderby $limit";

$lq = color_formatting::escape($query);
log::debug("log search query >>> $lq");

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
	$say = "\"{$qr['message']}\" ~$b{$qr['nick']}$b ($ts)";
}

$_i['handle']->say($_i['reply_to'], $say);
