//<?php

# main function for ExtraServ
log::trace('entered f::main()');

f::ALIAS('cmd_f', 'cmd_logsearch');
f::ALIAS('cmd_first', 'cmd_logsearch');
f::ALIAS('cmd_l', 'cmd_logsearch');
f::ALIAS('cmd_last', 'cmd_logsearch');
f::ALIAS('cmd_random', 'cmd_logsearch');

$_socket_start = null;
$_socket_timeout = ini_get('default_socket_timeout');
while (!uplink::safe_feof($_socket_start) && (microtime(true) - $_socket_start) < $_socket_timeout) {
	$line = uplink::readline();
	if ($line != null) {
		$lline = color_formatting::escape($line);
		log::rawlog(log::INFO, "%c<= $lline%0");
	} else {
		continue;
	}

	$_i = f::parse_line($line);

	// Handle line
	switch ($_i['cmd']) {
		case 'SERVER':
			log::trace('Started SERVER handling');
			$server = array(
				'name' => $_i['args'][0],
				'token' => $_i['args'][1],
				'desc' => $_i['text']
			);

			uplink::$network[$_i['args'][0]] = $server;
			if ($_i['prefix'] === null) { // this is the SERVER line for the uplink server
				uplink::$server = $server;
			}

			log::debug("Stored server {$server['name']}");
			break;
		case 'NICK':
			log::trace('Started NICK handling');
			// NICK NickServ 2 1409083701 +io NickServ dot.cs.wmich.edu dot.cs.wmich.edu 0 :Nickname Services
			if ($_i['prefix'] === null || uplink::is_server($_i['prefix'])) { // a server is telling us about a nick
				uplink::$nicks[$_i['args'][0]] = array(
					'nick' => $_i['args'][0],
					'hopcount' => $_i['args'][1]+1,
					'jointime' => $_i['args'][2],
					'mode' => $_i['args'][3],
					'user' => $_i['args'][4],
					'host' => $_i['args'][5],
					'server' => $_i['args'][6],
					'modtime' => $_i['args'][7],
					'channels' => array()
				);
				log::debug("Stored nick {$_i['args'][0]}");
			} elseif (uplink::is_nick($_i['prefix'])) { // user has changed their nick
				$nick = uplink::$nicks[$_i['prefix']];
				$nick['nick'] = $_i['args'][0];
				if (count($_i['args']) > 1)
					$nick['modtime'] = $_i['args'][1];
				uplink::$nicks[$_i['args'][0]] = $nick;
				unset(uplink::$nicks[$_i['prefix']]);
				log::debug("Nick change {$_i['prefix']} => {$_i['args'][0]}");
			} else {
				log::notice('Input to NICK is not a handled condition');
			}
			log::trace('Finished NICK handling');
			break;
		case 'PING':
			uplink::send("PONG :{$_i['text']}");
			break;
		case 'SJOIN':
			$chan = $_i['args'][1];
			log::debug("Got SJOIN for $chan");
			$names = explode(' ', $_i['text']);
			foreach ($names as $name) {
				while (in_array(substr($name, 0, 1), array('@','%','+','~')))
					$name = substr($name, 1);
				if (array_key_exists($name, uplink::$nicks)) {
					uplink::$nicks[$name]['channels'][] = $chan;
					log::trace("$name in $chan");
				} else {
					log::notice('Got unknown nick in SJOIN');
				}
			}
			log::trace('Finished SJOIN handling');
			break;
		case 'JOIN':
			log::trace('Started JOIN handling');
			if ($_i['prefix'] === null) {
				log::notice("Got a JOIN from the uplink server?");
				break;
			}
			uplink::$nicks[$_i['prefix']]['channels'][] = $_i['args'][0];
			log::debug("{$_i['prefix']} joined {$_i['args'][0]}");
			break;
		case 'KICK':
			log::trace('Started KICK handling');
			$params = uplink::$nicks[$_i['args'][1]];
			$params['channels'] = array_diff($params['channels'], array($_i['args'][0]));
			uplink::$nicks[$_i['args'][1]] = $params;
			log::debug("Removed {$_i['args'][1]} from {$_i['args'][0]} due to kick by {$_i['prefix']}");
			break;
		case 'QUIT':
			unset(uplink::$nicks[$_i['prefix']]);
			log::debug("Deleted nick {$_i['prefix']} due to QUIT");
			break;
		case 'PRIVMSG':
			$_i['sent_to'] = $_i['args'][0];
			$_i['reply_to'] = $_i['args'][0];
			$_i['handle'] = ExtraServ::$bot_handle;
			foreach (ExtraServ::$handles as $handle) {
				if ($_i['reply_to'] == $handle->nick) {
					log::trace('Received private message');
					$_i['reply_to'] = $_i['prefix'];
					$_i['handle'] = $handle;
					break;
				}
			}
			$leader = '!';
			if (substr($_i['text'], 0, 1) == $leader) {
				log::trace('Detected command');
				$ucmd = explode(' ', $_i['text'], 2);
				$uarg = null;
				if (count($ucmd) > 1)
					$uarg = $ucmd[1];
				$ucmd = substr($ucmd[0], 1);

				$cmdfunc = "cmd_$ucmd";
				if (f::EXISTS($cmdfunc)) {
					f::CALL($cmdfunc, array($ucmd, $uarg, $_i));
				} else {
					# Simple commands that don't need their own function file
					$handled = f::simple_commands($ucmd, $uarg, $_i);

					# Special admin commands
					if (!$handled && is_admin($_i['prefix'])) {
						switch ($ucmd) {
							case 'es-reload':
								log::notice('Got !es-reload, reloading main()');
								$_i['handle']->say($_i['reply_to'], 'Reloading main');
								f::RELOAD('main');
								return 0;
							case 'es-stop':
								log::notice('Got !es-stop, stopping');
								return 2;
							case 'es-reinit':
								log::notice('Got !es-reinit');
								return 3;
							case 'loglevel':
								log::notice("Got !loglevel $uarg");
								log::$level = log::string_to_level($uarg);
								$_i['handle']->say($_i['reply_to'], 'Changed log level');
								break;
							case 'reload-all':
								log::notice('Got !reload-all');
								$_i['handle']->say($_i['reply_to'], 'Marking all functions for reloading');
								f::RELOAD_ALL();
								break;
							case 'f-reload':
								log::notice('Got !f-reload');
								f::RELOAD($uarg);
								$_i['handle']->say($_i['reply_to'], "Reloading f::$uarg()");
								break;
							case 'c-reload':
								log::notice('Got !c-reload');
								f::RELOAD("cmd_$uarg");
								$_i['handle']->say($_i['reply_to'], "Reloading f::cmd_$uarg()");
								break;
							case 'set-tz':
								log::notice('Got !set-tz');
								ExtraServ::$output_tz = $uarg;
								$_i['handle']->say($_i['reply_to'], 'Changed output timezone');
								break;
							default:
								log::trace('Not an admin command');
						}
					} else {
						log::trace("simple_commands handled '$ucmd'");
					}
				}
			}
			break;
	}
}

return 1;
