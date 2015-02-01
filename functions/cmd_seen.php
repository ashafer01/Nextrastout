//<?php

log::trace('entering f::cmd_seen()');
list($_CMD, $_ARG, $_i) = $_ARGV;

$channel = $_i['sent_to'];
$fc = substr($channel, 0, 1);
if ($fc != '#' && $fc != '&') {
	$_i['handle']->say($_i['reply_to'], 'seen in PM coming soon');
}

$inick = pg_escape_string(ExtraServ::$db, strtolower($_ARG));

$query = str_replace(array("\n","\t"), array(' ',''), <<<QUERY
SELECT uts, command, nick, message,
	split_part(args, ' ', 1) AS channel,
	split_part(args, ' ', 2) AS arg
FROM newlog
WHERE
	((split_part(args, ' ', 1) = '$channel') OR (split_part(args, ' ', 1) = ''))
	AND ( ((command IN ('PRIVMSG','PART','QUIT','KICK','MODE','TOPIC')) AND (nick = '$inick'))
		OR ((command = 'KICK') AND (split_part(args, ' ', 2) = '$inick'))
		OR ((command = 'JOIN') AND (message = '$channel') AND (nick = '$inick'))
		)
ORDER BY ts DESC
LIMIT 1
QUERY
);

$lq = color_formatting::escape($query);
log::debug("seen query >>> $lq");

$q = pg_query(ExtraServ::$db, $query);
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
} elseif (pg_num_rows($q) == 0) {
	log::info("No results for seen '$inick'");
	$_i['handle']->say($_i['reply_to'], 'Nickname not found');
} else {
	$qr = pg_fetch_assoc($q);
	$date = smart_date_fmt($qr['uts']);
	$ago = duration_str(time() - $qr['uts']);
	switch ($qr['command']) {
		case 'PRIVMSG':
			if (preg_match("/^\001ACTION (.+?)\001/", $qr['message'], $srp) === 1) {
				$said = "* {$qr['nick']} {$srp[1]}";
			} else {
				$said = "\"{$qr['message']}\"";
			}
			$_i['handle']->say($_i['reply_to'], "{$qr['nick']} was last seen $ago ago saying $said ($date)");
			break;
		case 'JOIN':
			$_i['handle']->say($_i['reply_to'], "{$qr['nick']} joined {$qr['message']} $ago ago ($date)");
			break;
		case 'PART':
			$_i['handle']->say($_i['reply_to'], "{$qr['nick']} left {$qr['channel']} $ago ago with reason \"{$qr['message']}\" ($date)");
			break;
		case 'QUIT':
			$_i['handle']->say($_i['reply_to'], "{$qr['nick']} quit IRC $ago ago ($date)");
			break;
		case 'KICK':
			$_i['handle']->say($_i['reply_to'], "{$qr['arg']} was kicked from {$qr['channel']} $ago ago by {$qr['nick']} with reason \"{$qr['message']}\" ($date)");
			break;
		case 'MODE':
			$_i['handle']->say($_i['reply_to'], "{$qr['nick']} was last seen $ago ago changing the mode of {$qr['channel']} ($date)");
			break;
		case 'TOPIC':
			$_i['handle']->say($_i['reply_to'], "{$qr['nick']} was last seen $ago ago changing the topic of {$qr['channel']} ($date)");
			break;
		default:
			log::notice("Unhandled command '{$qr['command']}' found for seen '{$qr['nick']}'");
			$_i['handle']->say($_i['reply_to'], "{$qr['nick']} was last seen $ago ago doing something unexpected ($date)");
			break;
	}
}
