//<?php

ExtraServ::dbconnect();
f::ALIAS_INIT();

$handles_re = implode('|', array_keys(ExtraServ::$handles));
$topicdata = array();

$cmd_globals = new stdClass;
$cmd_globals->topic_nicks = array();

$_socket_start = null;
$_socket_timeout = ini_get('default_socket_timeout');
while (!uplink::safe_feof($_socket_start) && (microtime(true) - $_socket_start) < $_socket_timeout) {
	$line = uplink::readline();
	if ($line != null) {
		$lline = color_formatting::escape($line);
		if (preg_match("/^(:.+? PRIVMSG ($handles_re) [^:]*:(REGISTER|SETPASS|IDENTIFY|ASSOCIATE) )(.+)$/i", $lline, $matches) === 1) {
			log::debug('Hiding password from log');
			$lline = "{$matches[1]}**********";
		}
		log::rawlog(log::INFO, "%c<= $lline%0");
	} else {
		continue;
	}

	$_i = f::parse_line($line);

	# Handle line
	switch ($_i['cmd']) {
		case 'ERROR':
			log::fatal('Got ERROR line');
			exit(13);
		case 'PING':
			uplink::send("PONG :{$_i['text']}");
			break;

		# handle topic from server
		case '332':
			$topicchan = dbescape($_i['args'][1]);
			log::debug("got topic for $topicchan");
			$topicdata[$topicchan] = dbescape($_i['text']);
			break;
		case '333':
			$topicchan = dbescape($_i['args'][1]);
			log::debug('got topic metadata');
			$topicsetter = f::parse_hostmask($_i['args'][2]);
			$nick = dbescape($topicsetter->nick);
			$uts = dbescape($_i['args'][3]);

			if (array_key_exists($topicchan, $topicdata)) {
				$query = "INSERT INTO topic (uts, topic, by_nick, channel) VALUES ($uts, '{$topicdata[$topicchan]}', '$nick', '$topicchan')";
				log::debug("New topic query >> $query");
				$q = pg_query(ExtraServ::$db, $query);
				if ($q === false) {
					log::error('Query failed');
					log::error(pg_last_error());
					ExtraServ::$bot_handle->say($_i['reply_to'], 'Failed to store new topic');
				} else {
					log::debug("Stored new topic for $topicchan");
				}
				unset($topicdata[$topicchan]);
			} else {
				log::warning("Got 333 topic metadata for $topicchan before 332, ignoring");
			}
			break;

		# need op privileges
		case '482':
			ExtraServ::$bot_handle->say($_i['args'][1], "I'll need op privileges to do that");
			break;

		# handle topic from user
		case 'TOPIC':
			$topicchan = dbescape($_i['args'][0]);
			log::debug("Got TOPIC for $topicchan");

			$topic = dbescape($_i['text']);
			if (array_key_exists($topicchan, $cmd_globals->topic_nicks)) {
				$nick = dbescape($cmd_globals->topic_nicks[$topicchan]);
				unset($cmd_globals->topic_nicks[$topicchan]);
			} else {
				$nick = dbescape($_i['hostmask']->nick);
			}
			$uts = time();

			$query = "INSERT INTO topic (uts, topic, by_nick, channel) VALUES ($uts, '$topic', '$nick', '$topicchan')";
			log::debug("New topic query >> $query");
			$q = pg_query(ExtraServ::$db, $query);
			if ($q === false) {
				log::error('Query failed');
				log::error(pg_last_error());
				ExtraServ::$bot_handle->say($_i['args'][0], 'Failed to store new topic');
			} else {
				log::debug("Stored new topic for $topicchan");
			}
			break;

		# handle commands, etc.
		case 'PRIVMSG':
			$leader = '!';
			$_i['sent_to'] = $_i['args'][0];
			$_i['reply_to'] = $_i['args'][0];
			$_i['handle'] = ExtraServ::$bot_handle;
			$in_pm = false;
			foreach (ExtraServ::$handles as $handle) {
				if ($_i['reply_to'] == $handle->nick) {
					log::trace('Received private message');
					$_i['reply_to'] = $_i['hostmask']->nick;
					$_i['handle'] = $handle;
					$in_pm = true;
					$_i['in_pm'] = true;
					break;
				}
			}
	
			# Normal commands
			$_i['text'] = trim($_i['text']);
			if (substr($_i['text'], 0, 1) == $leader) {
				log::trace('Detected command');
				$ucmd = explode(' ', $_i['text'], 2);
				$uarg = null;
				if (count($ucmd) > 1)
					$uarg = $ucmd[1];
				$ucmd = substr($ucmd[0], 1);

				$cmdfunc = "cmd_$ucmd";
				if (f::EXISTS($cmdfunc)) {
					f::CALL($cmdfunc, array($ucmd, $uarg, $_i, $cmd_globals));
				} elseif(is_admin($_i['hostmask']->user)) {
					switch ($ucmd) {
						# operational functions
						case 'esreload':
						case 'es-reload':
							log::notice('Got !es-reload, reloading nextrastout()');
							if ($uarg == '-hup') {
								log::debug('Doing hup option on es-reload');
								config::reload_all();
								$_i['handle']->say($_i['reply_to'], 'Reloaded config');
								f::ALIAS_INIT();
								ExtraServ::$bot_handle->update_conf_channels();
							}
							$_i['handle']->say($_i['reply_to'], 'Reloading nextrastout');
							f::RELOAD('nextrastout');
							return 0;
						case 'reload':
							log::notice('Got !reload');
							if (f::EXISTS($uarg)) {
								if (f::IS_ALIAS($uarg)) {
									$uarg = f::RESOLVE_ALIAS($uarg);
								}
								f::RELOAD($uarg);
								$_i['handle']->say($_i['reply_to'], "Reloading f::$uarg()");
							} else {
								$_i['handle']->say($_i['reply_to'], "Function $uarg does not exist");
							}
							break;
						case 'creload':
							log::notice('Got !creload');
							$cmdfunc = "cmd_$uarg";
							if (f::EXISTS($cmdfunc)) {
								if (f::IS_ALIAS($cmdfunc)) {
									$cmdfunc = f::RESOLVE_ALIAS($cmdfunc);
								}
								f::RELOAD($cmdfunc);
								$_i['handle']->say($_i['reply_to'], "Reloading f::$cmdfunc()");
							} else {
								$_i['handle']->say($_i['reply_to'], "Function $cmdfunc does not exist");
							}
							break;
						case 'hup':
							log::notice('Got !hup');
							config::reload_all();
							$_i['handle']->say($_i['reply_to'], 'Reloaded config');
							f::ALIAS_INIT();
							ExtraServ::$bot_handle->update_conf_channels();
							break;
						case 'reload-all':
							log::notice('Got !reload-all');
							$_i['handle']->say($_i['reply_to'], 'Marking all functions for reloading and reloading conf');
							f::RELOAD_ALL();
							config::reload_all();
							ExtraServ::$bot_handle->update_conf_channels();
							break;
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
						case 'set-tz':
							log::notice('Got !set-tz');
							ExtraServ::$output_tz = $uarg;
							$_i['handle']->say($_i['reply_to'], 'Changed output timezone');
							break;
						case 'es-join':
							log::notice('Got !es-join');
							ExtraServ::$bot_handle->join($uarg);
							break;
						case 'es-part':
							log::notice('Got !es-part');
							ExtraServ::$bot_handle->part($uarg);
							break;
						case 'chanserv':
							log::notice('Got !chanserv');
							ExtraServ::$bot_handle->say('ChanServ', $uarg);
							break;
						case 'nickserv':
							log::notice('Got !nickserv');
							ExtraServ::$bot_handle->say('NickServ', $uarg);
							break;
						default:
							log::trace('Not an admin command');
					}
				} else {
					log::debug('Not an admin, and not a known command');
				}
			} # --- end commands

			# other privmsg replies
			else {
				$fsw = explode(' ', $_i['text'], 2);
				switch ($fsw[0]) {
					case 'seen':
					case 'karma':
						log::info('Got non-leader command');
						$cmdfunc = "cmd_{$fsw[0]}";
						f::CALL($cmdfunc, array($fsw[0], $fsw[1], $_i));
						break;
					default:
						log::trace('Not a non-leader command');
						if (f::nonpublic_stuff($_i)) {
							log::trace('Handled by nonpublic_stuff');
						} else {
							log::trace('Line unhandled');
						}
				}
			}
			break; # --- end privmsg handling
	}
}

ExtraServ::$bot_handle->del_all_channels();
exit(1);
