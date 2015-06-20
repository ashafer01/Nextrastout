//<?php

ExtraServ::dbconnect();
f::ALIAS_INIT();

$handles_re = implode('|', array_keys(ExtraServ::$handles));

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
		case 'PRIVMSG':
			$leader = '!';
			$_i['sent_to'] = $_i['args'][0];
			$_i['reply_to'] = $_i['args'][0];
			$_i['handle'] = ExtraServ::$bot_handle;
			$in_pm = false;
			foreach (ExtraServ::$handles as $handle) {
				if ($_i['reply_to'] == $handle->nick) {
					log::trace('Received private message');
					#$_i['reply_to'] = $_i['prefix'];
					$_i['reply_to'] = $_i['hostmask']->nick;
					$_i['handle'] = $handle;
					$in_pm = true;
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
					f::CALL($cmdfunc, array($ucmd, $uarg, $_i));
				#} elseif(is_admin(uplink::get_user_by_nick($_i['prefix']))) {
				} elseif(is_admin($_i['hostmask']->user)) {
					switch ($ucmd) {
						# operational functions
						case 'esreload':
						case 'es-reload':
							log::notice('Got !es-reload, reloading main()');
							if ($uarg == '-hup') {
								log::debug('Doing hup option on es-reload');
								config::reload_all();
								$_i['handle']->say($_i['reply_to'], 'Reloaded config');
								ExtraServ::$bot_handle->update_conf_channels();
							}
							$_i['handle']->say($_i['reply_to'], 'Reloading nextrastout');
							f::RELOAD('nextrastout');
							return 0;
						case 'procs-reload':
							log::notice('Got !procs-reload');
							$_i['handle']->say($_i['reply_to'], 'Telling other processes to reload');
							break;
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
						case 'part':
							log::notice('Got !part');
							ExtraServ::$bot_handle->part($uarg);
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
				}
			}
			break; # --- end privmsg handling
	}
}
