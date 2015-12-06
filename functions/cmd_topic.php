//<?php

log::trace('entered f::cmd_topic()');
list($_CMD, $params, $_i, $_globals) = $_ARGV;

$params = explode(' ', $params, 2);

$help = '!topic history : Get PM\'ed recent topics ; !topic recall <id> : Restore a specific topic from history ; !topic restore : Restore the previous topic ; !topic recover : Recover the last known topic after downtime ; !topic add|append <new entry> ; !topic set <index> <new entry> : replace topic item ; !topic delete <index> ; !topic insert <index> <new entry> : add a new entry after the specified index (all indexes start at 1)';

if ($_i['in_pm']) {
	$_i['handle']->say($_i['reply_to'], "You can only use topic commands in a channel. See !topic help for possible commands.");
	return f::TRUE;
} elseif ($params[0] == 'help') {
	$_i['handle']->say($_i['reply_to'], $help);
	return f::TRUE;
}

$channel = dbescape($_i['args'][0]);
$newtopic = null;

if ($params[0] == 'recall') {
	log::debug('Doing !topic recall');
	if (count($params) < 2) {
		$_i['handle']->say($_i['reply_to'], 'Please specify a topic ID');
		return f::FALSE;
	}
	if (!ctype_digit($params[1])) {
		$_i['handle']->say($_i['reply_to'], 'Please specify a numeric topic ID to recall. Use !topic history if you need to find an ID.');
		return f::FALSE;
	}

	$q = Nextrastout::$db->pg_query("SELECT by_nick, topic FROM topic WHERE tid={$params[1]} AND channel='$channel'",
		'topic recall query');
	if ($q === false) {
		$_i['handle']->say($_i['reply_to'], "Failed to look up topic #{$params[1]}");
	} elseif (pg_num_rows($q) == 0) {
		$_i['handle']->say($_i['reply_to'], "Topic #{$params[1]} not found");
	} else {
		$qr = pg_fetch_assoc($q);
		$_globals->topic_nicks[$channel] = $qr['by_nick'];
		$newtopic = $qr['topic'];
	}
} else {
	// get current topic(s)
	$q = Nextrastout::$db->pg_query("SELECT * FROM topic WHERE channel='$channel' ORDER BY uts DESC LIMIT 5",
		'topic query');
	if ($q === false) {
		$_i['handle']->say($_i['reply_to'], 'Failed to get current topic');
		return f::FALSE;
	} elseif (pg_num_rows($q) == 0) {
		log::debug("No topics for $channel");
		$_i['handle']->say($_i['reply_to'], "No topics for $channel. If this is the first time using topic commands in this channel, just /topic <current topic> to initialize.");
		return f::TRUE;
	}

	switch ($params[0]) {
		case 'add':
			log::debug('Doing !topic add');
			if (count($params) < 2) {
				$_i['handle']->say($_i['reply_to'], 'Please specify a new topic entry');
				return f::FALSE;
			}
			$qr = pg_fetch_assoc($q);
			$_globals->topic_nicks[$channel] = $_i['hostmask']->nick;

			$newtopic = $params[1] . ' | ' . $qr['topic'];
			break;

		case 'append':
			log::debug('Doing !topic append');
			if (count($params) < 2) {
				$_i['handle']->say($_i['reply_to'], 'Please specify a new topic entry');
				return f::FALSE;
			}
			$qr = pg_fetch_assoc($q);
			$_globals->topic_nicks[$channel] = $_i['hostmask']->nick;

			$newtopic = $qr['topic'] . ' | ' . $params[1];
			break;

		case 'remove':
		case 'del':
			$params[0] = 'delete';
		case 'set':
		case 'insert':
		case 'delete':
			log::trace('Handling indexed topic sub-command');
			if (count($params) < 2) {
				$_i['handle']->say($_i['reply_to'], 'Please specify an index');
				return f::FALSE;
			}
			$qr = pg_fetch_assoc($q);
			$it = explode(' ', $params[1], 2);
			$index = $it[0];
			if ($params[0] != 'delete') {
				if (count($it) > 1) {
					$newpart = $it[1];
				} else {
					$_i['handle']->say($_i['reply_to'], 'Please specify a new topic entry');
					return f::FALSE;
				}
			}

			if (!ctype_digit($index)) {
				$_i['handle']->say($_i['reply_to'], "Please specify a numeric index after \"{$params[0]}\"");
				return f::FALSE;
			}

			$topicparts = explode(' | ', $qr['topic']);

			$index = (int) $index; // make the index an integer
			if ($index > 0) {      // user indexes start at 1
				$index--;          // but 0 will still work for the first item
			}

			switch ($params[0]) {
				case 'set':
					log::debug('Doing !topic set');
					$topicparts[$index] = $newpart;
					$newtopic = implode(' | ', $topicparts);
					break;
				case 'delete':
					log::debug('Doing !topic delete');
					unset($topicparts[$index]);
					$newtopic = implode(' | ', $topicparts);
					break;
				case 'insert':
					log::debug('Doing !topic insert');
					$index++;
					$before = array_slice($topicparts, 0, $index);
					$after = array_slice($topicparts, $index);
					$before[] = $newpart;
					$newtopic = implode(' | ', array_merge($before, $after));
					break;
			}
			$_globals->topic_nicks[$channel] = $_i['hostmask']->nick;
			break;

		case 'recover':
			log::debug('Doing !topic recover');
			$qr = pg_fetch_assoc($q);
			$_globals->topic_nicks[$channel] = $qr['by_nick'];
			$newtopic = $qr['topic'];
			break;

		case 'restore':
			log::debug('Doing !topic restore');
			pg_fetch_assoc($q);       // trash the first result
			$qr = pg_fetch_assoc($q); // we want the previous topic
			$_globals->topic_nicks[$channel] = $qr['by_nick'];
			$newtopic = $qr['topic'];
			break;

		case 'history':
			log::debug('Doing !topic history');
			$newtopic = null;
			$to = $_i['hostmask']->nick;
			$_i['handle']->say($to, "--- Recent topics from $channel ---");
			while ($qr = pg_fetch_assoc($q)) {
				$ts = smart_date_fmt($qr['uts']);
				$_i['handle']->say($to, $qr['topic']);
				$_i['handle']->say($to, sprintf("\003%02d%s", 10, "#{$qr['tid']}: set by {$qr['by_nick']} [$ts]"));
				sleep(1);
			}
			$_i['handle']->say($to, '--- Done ---');
			break;

		default:
			$_i['handle']->say($_i['reply_to'], "Unknown command... $help");
			break;
	}
}

if ($newtopic !== null) {
	log::trace("sending new topic for $channel");
	uplink::send("TOPIC $channel :$newtopic");
}
