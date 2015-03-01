//<?php

# main function for ExtraServ
log::trace('entered f::main()');

ExtraServ::dbconnect();
f::ALIAS_INIT();

# static data
$start_lists = array('b','e','I');
$handles_re = implode('|', array_keys(ExtraServ::$handles));
$mode_words = array(
	'o' => 'op',
	'h' => 'half-op',
	'v' => 'voice'
);

proc::ready();

$_socket_start = null;
$_socket_timeout = ini_get('default_socket_timeout');
while (!uplink::safe_feof($_socket_start) && (microtime(true) - $_socket_start) < $_socket_timeout) {
	if (($message = proc::queue_get(0, $msgtype, $fromproc)) !== null) {
		ES_SyncedArrayObject::dispatchMessage($msgtype, $message);
	}
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
		case 'AWAY':
			log::debug('Got AWAY');

			# slip AWAY's into the log
			$ts = time();
			$nick = $_i['prefix'];
			$q = pg_query_params(ExtraServ::$db, "INSERT INTO log (uts, nick, ircuser, irchost, command, args, message) VALUES ($1, $2, $3, $4, 'AWAY', '', $5)", array(
				$ts,
				$nick,
				uplink::get_user_by_nick($nick),
				uplink::$nicks[$nick]['host'],
				$_i['text']
			));
			if ($q === false) {
				log::error('Failed to add AWAY message to log');
				log::error(pg_last_error());
			}
			break;
		case 'EOB':
			log::debug('Got EOB');

			f::stop_shitstorm();

			# update stickymodes
			$query = 'SELECT channel, mode_flags, mode_k, mode_l FROM chan_register WHERE stickymodes IS TRUE';
			log::debug("get stickymodes query >>> $query");
			$q = pg_query(ExtraServ::$db, $query);
			if ($q === false) {
				log::fatal('query failed');
				log::fatal(pg_last_error());
				exit(21);
			} else {
				while ($qr = pg_fetch_assoc($q)) {
					$channel = $qr['channel'];
					if (array_key_exists($channel, uplink::$channels)) {
						log::debug("Updating stickymodes for channel $channel");
						$modes = str_split($qr['mode_flags'], 1);
						$adds = array();
						foreach ($modes as $c) {
							if (!array_key_exists($c, uplink::$channels[$channel])) {
								uplink::$channels[$channel][$c] = null;
								$adds[] = $c;
							}
						}
						$adds = array_chunk($adds, 4);
						foreach ($adds as $modechars) {
							$modechars = implode($modechars);
							ExtraServ::$serv_handle->send("MODE $channel +$modechars");
						}
						foreach (array('k','l') as $c) {
							if (in_array($c, $modes) && ($qr["mode_$c"] != null)) {
								ExtraServ::$serv_handle->send("MODE $channel +$c {$qr["mode_$c"]}");
								uplink::$channels[$channel][$c] = $qr["mode_$c"];
							}
						}
					} else {
						log::trace('Channel does not currently exist');
					}
				}
			}

			# update stickylists
			$query = 'SELECT channel, list_flags FROM chan_register WHERE stickylists IS TRUE';
			log::debug("get stickylists query >>> $query");
			$q = pg_query(ExtraServ::$db, $query);
			if ($q === false) {
				log::fatal('query failed');
				log::fatal(pg_last_error());
				exit(21);
			} else {
				$qn = 'stickylists_get_list';
				if (!pg_is_prepared($qn)) {
					$query = "SELECT value FROM chan_stickylists WHERE channel=$1 AND mode_list=$2";
					log::debug("preparing $qn >>> $query");
					$p = pg_prepare(ExtraServ::$db, $qn, $query);
					if ($p === false) {
						log::fatal('failed to prepare query');
						log::fatal(pg_last_error());
						exit(22);
					}
				} else {
					log::trace("$qn query already prepared");
				}

				while ($qr = pg_fetch_assoc($q)) {
					$list_flags = str_split($qr['list_flags'], 1);
					if (array_key_exists($qr['channel'], uplink::$channels)) {
						log::debug("Doing startup sticky lists for channel {$qr['channel']}");
						foreach ($start_lists as $c) {
							if (in_array($c, $list_flags)) {
								$e = pg_execute(ExtraServ::$db, $qn, array($qr['channel'], $c));
								if ($e === false) {
									log::fatal('failed to execute query');
									log::fatal(pg_last_error());
									exit(23);
								} else {
									while ($er = pg_fetch_assoc($e)) {
										if (!in_array($er['value'], uplink::$channels[$qr['channel']][$c]->getArrayCopy())) {
											ExtraServ::$serv_handle->send("MODE {$qr['channel']} +$c {$er['value']}");
											uplink::$channels[$qr['channel']][$c][] = $er['value'];
										}
									}
								}
							}
						}
					} else {
						log::trace('Channel does not currently exist');
					}
				}
			}
			break;
		case 'SERVER':
			log::trace('Started SERVER handling');
			$server = array(
				'name' => $_i['args'][0],
				'token' => $_i['args'][1],
				'desc' => $_i['text']
			);

			uplink::$network[$_i['args'][0]] = $server;
			if ($_i['prefix'] === null) { # this is the SERVER line for the uplink server
				f::start_shitstorm();
				uplink::$server = $server;
			}

			log::debug("Stored server {$server['name']}");
			break;
		case 'NICK':
			log::trace('Started NICK handling');
			# a server is telling us about a nick
			if ($_i['prefix'] == null || uplink::is_server($_i['prefix'])) {
				// NICK NickServ 2 1409083701 +io NickServ dot.cs.wmich.edu dot.cs.wmich.edu 0 :Nickname Services
				$newnick = strtolower($_i['args'][0]);
				uplink::$nicks[$newnick] = array(
					'nick' => $newnick,
					'hopcount' => $_i['args'][1]+1,
					'jointime' => $_i['args'][2],
					'mode' => str_split(substr($_i['args'][3], 1), 1),
					'user' => $_i['args'][4],
					'host' => $_i['args'][5],
					'server' => $_i['args'][6],
					'modtime' => $_i['args'][7],
					'realname' => $_i['text'],
					'channels' => array()
				);
				log::trace("Stored nick {$_i['args'][0]}");
				$user = dbescape($_i['args'][4]);

				if (ExtraServ::is_idented($user) && (f::get_user_setting($user, 'kill_second_user') == 't')) {
					log::info("Adding nick '$newnick' to death row because username is already idented");
					ExtraServ::$serv_handle->notice($newnick, 'Your username is configured to kill others joining with your username. Your connection will be killed shortly.');
					$realnick = uplink::get_nick_by_user($user); # since this function iterates the array in order, we will still find the first nick for this user
					ExtraServ::$serv_handle->notice($realnick, "Nickname '$newnick' has come online using your username; they will be killed shortly per your configuration.");
					ExtraServ::$death_row[$newnick] = array(
						'at_uts' => (time()+5),
						'reason' => 'Username enforcement'
					);
					break;
				}

				log::trace('Checking if username is registered');
				$q = pg_query(ExtraServ::$db, "SELECT count(*) FROM user_register WHERE ircuser='$user'");
				if ($q === false) {
					log::error('Failed to check if username is registered');
					log::error(pg_last_error());
				} else {
					$qr = pg_fetch_assoc($q);
					if ($qr['count'] > 0) {
						if (!ExtraServ::is_idented($user)) {
							log::debug('Username is registered, nagging for ident');
							$shn = ExtraServ::$serv_handle->nick;
							//ExtraServ::$serv_handle->notice($_i['args'][0], "Your username '$user' is registered. Please '/msg $shn IDENTIFY password' to verify your identity.");
							log::notice('Not sending ident request during dev');
						} else {
							log::debug('Username is already identified');
						}
					}
				}
				$owner = f::get_nick_owner($newnick);
				if ($owner === false) {
					log::error('Failed to get nick owner');
				} elseif ($owner == null) {
					log::debug('Nick is not associated');
				} elseif ($owner != $user) {
					log::notice('Invalid user of nickname');
					$nick = uplink::get_nick_by_user($owner);
					ExtraServ::$serv_handle->notice($nick, "User '$user' is using your nickname '$newnick'");
					ExtraServ::$serv_handle->notice($newnick, "Your nickname is owned by someone else. Your connection may be killed depending on the owner's settings.");
					if (f::get_user_setting($owner, 'kill_bad_nicks') == 't') {
						log::info("Putting nick '$newnick' on death row");
						ExtraServ::$death_row[$newnick] = array(
							'at_uts' => (time()+5),
							'reason' => 'Nickname enforcement'
						);
					}
				} else {
					log::trace('User of nickname is valid');
				}

			# user has changed their nick
			} elseif (uplink::is_nick(strtolower($_i['prefix']))) {
				$oldnick = strtolower($_i['prefix']);
				$newnick = strtolower($_i['args'][0]);
				if (ExtraServ::$death_row->offsetExists($oldnick)) {
					log::info("Death row nick '$oldnick' has been changed");
					unset(ExtraServ::$death_row[$oldnick]);
				}
				$owner = f::get_nick_owner($newnick);
				if ($owner === false) {
					log::error('Failed to get nick owner');
				} elseif ($owner == null) {
					log::trace('Nickname not associated with user');
				} elseif (ExtraServ::is_idented($owner)) {
					$user = uplink::get_user_by_nick($oldnick);
					if ($owner != $user) {
						log::notice('Invalid user of nickname');
						$onick = uplink::get_nick_by_user($owner);
						ExtraServ::$serv_handle->notice($onick, "User '$user' has just changed to your nickname '$newnick'");
						ExtraServ::$serv_handle->notice($newnick, "This nickname is owned by someone else. Your connection may be killed depending on the owner's settings.");
						if (f::get_user_setting($owner, 'kill_bad_nicks') == 't') {
							log::info("Putting nick '$newnick' on death row");
							ExtraServ::$death_row[$newnick] = array(
								'at_uts' => (time()+5),
								'reason' => 'Nickname enforcement'
							);
						}
					} else {
						log::debug('Nick is in use by its owner');
					}
				} else {
					log::debug('Owner of nick is not identified');
				}
				
				$nick_obj = uplink::$nicks[$oldnick]->getArrayCopy();
				unset(uplink::$nicks[$oldnick]);
				$nick_obj['nick'] = $newnick;
				uplink::$nicks[$newnick] = $nick_obj;
				uplink::rename_in_modelists($oldnick, $newnick);
				log::debug("Nick change {$oldnick} => {$newnick}");

			# unhandled input
			} else {
				log::notice('Input to NICK is not a handled condition');
			}
			log::trace('Finished NICK handling');
			break;
		case 'SJOIN':
			$chan = strtolower($_i['args'][1]);
			log::debug("Got SJOIN for $chan");

			# process modes
			$cmodes_str = substr($_i['args'][2], 1);
			if ($cmodes_str != null) {
				$cmodes = str_split($cmodes_str, 1);
				$modes_array = array();
				foreach ($cmodes as $modechar) {
					$modes_array[$modechar] = null;
				}
				end($modes_array);
				for ($i = count($_i['args'])-1; $i >= 3; $i--) {
					$modes_array[key($modes_array)] = $_i['args'][$i];
					prev($modes_array);
				}
				if (!array_key_exists($chan, uplink::$channels)) {
					uplink::$channels[$chan] = $modes_array;
				}
			} else {
				if (!array_key_exists($chan, uplink::$channels)) {
					uplink::$channels[$chan] = array();
				}
			}
			foreach (uplink::$chanmode_map as $c) {
				if (!array_key_exists($c, uplink::$channels[$chan])) {
					uplink::$channels[$chan][$c] = array();
				}
			}
			if (!array_key_exists('b', uplink::$channels[$chan]))
				uplink::$channels[$chan]['b'] = array(); # ban list
			if (!array_key_exists('e', uplink::$channels[$chan]))
				uplink::$channels[$chan]['e'] = array(); # ban exceptions
			if (!array_key_exists('I', uplink::$channels[$chan]))
				uplink::$channels[$chan]['I'] = array(); # invite exceptions

			# process names list
			$names = explode(' ', $_i['text']);
			$modesymbols = array_keys(uplink::$chanmode_map);
			foreach ($names as $name) {
				$name = strtolower($name);
				$chanmode = array();
				while (in_array(($c = substr($name, 0, 1)), $modesymbols)) {
					$chanmode[] = uplink::$chanmode_map[$c];
					$name = substr($name, 1);
				}
				foreach ($chanmode as $modechar) {
					uplink::$channels[$chan][$modechar][] = $name;
				}

				if (array_key_exists($name, uplink::$nicks)) {
					$user = uplink::get_user_by_nick($name);
					# check sticky lists
					if (array_key_exists($chan, ExtraServ::$chan_stickylists)) {
						log::debug("Channel $chan has sticky lists");
						foreach (ExtraServ::$chan_stickylists[$chan] as $c => $modenames) {
							if (!in_array($c, uplink::$chanmode_map)) {
								log::trace("skipped non-joinlist mode $c");
								continue;
							}
							if (in_array($name, $modenames) && !in_array($name, uplink::$channels[$chan][$c]->getArrayCopy())) {
								if (ExtraServ::is_idented($user)) {
									log::debug("User is identified, sending MODE +$c for sticky list");
									ExtraServ::$serv_handle->send("MODE $chan +$c $name");
									uplink::$channels[$chan][$c][] = $name;
								} else {
									log::debug('User is not identified');
									$shn = ExtraServ::$serv_handle->nick;
									ExtraServ::$serv_handle->notice($name, "You must identify to get {$mode_words[$c]} on $chan. '/msg $shn IDENTIFY password'");
								}
							}
						}
					}

					uplink::$nicks[$name]['channels'][] = $chan;
					log::trace("$name joins $chan");
				} else {
					log::notice("Got unknown nick '$name' in SJOIN");
				}
			}
			log::trace('Finished SJOIN handling');
			break;
		case 'JOIN':
			log::trace('Started JOIN handling');
			uplink::$nicks[$_i['prefix']]['channels'][] = $_i['args'][0];
			log::debug("{$_i['prefix']} joined {$_i['args'][0]}");
			break;
		case 'MODE':
			log::trace('Started MODE handling');
			$chan = strtolower($_i['args'][0]);
			$args = array_slice($_i['args'], 2);
			if (array_key_exists($chan, uplink::$channels)) {
				log::trace('Got channel mode change');
				$modes = str_split($_i['args'][1], 1);
				$op = null;
				$mode_changes = array();
				foreach ($modes as $c) {
					if ($c == '+' || $c == '-') {
						$op = $c;
						continue;
					}
					log::debug("Found $chan $op$c");
					if (array_key_exists($c, uplink::$channels[$chan]) && is_object(uplink::$channels[$chan][$c])) {
						# list modes are always pre-populated with arrays
						$param = array_shift($args);
						log::debug("Took param for $op$c => $param");
						if (array_key_exists("$op$c", $mode_changes)) {
							$mode_changes["$op$c"][] = $param;
						} else {
							$mode_changes["$op$c"] = array($param);
						}
					} elseif ($c == 'k' || $c == 'l') {
						# modes with parameters
						$param = array_shift($args);
						log::debug("Took param for $op$c => $param");
						$mode_changes["$op$c"] = $param;
					} else {
						# modes without params
						$mode_changes["$op$c"] = null;
					}
				}

				$modes_updated = false;
				$do_stickymodes = array_key_exists($chan, ExtraServ::$chan_stickymodes);

				if (array_key_exists($chan, ExtraServ::$chan_stickylists))
					$stickylist_flags = array_keys(ExtraServ::$chan_stickylists[$chan]->getArrayCopy());
				else
					$stickylist_flags = array();

				$slists_insert_values = array();
				$slists_delete_conds = array();
				foreach ($mode_changes as $modeop => $newval) {
					$op = substr($modeop, 0, 1);
					$modechar = substr($modeop, 1, 1);
					if (is_array($newval)) {
						$lnewval = implode(',', $newval);
					} else {
						$lnewval = $newval;
					}
					log::trace("Applying $op$modechar to $chan ($lnewval)");
					if ($op == '+') {
						if (is_array($newval)) {
							uplink::$channels[$chan][$modechar] = array_merge(uplink::$channels[$chan][$modechar]->getArrayCopy(), $newval);

							# update stickylists
							if (in_array($modechar, $stickylist_flags)) {
								foreach ($newval as $value) {
									if (!in_array($value, ExtraServ::$chan_stickylists[$chan][$modechar]->getArrayCopy())) {
										ExtraServ::$chan_stickylists[$chan][$modechar][] = $value;
										$ival = dbescape($value);
										$slists_insert_values[] = "('$chan', '$modechar', '$ival')";
									}
								}
							}
						} else {
							uplink::$channels[$chan][$modechar] = $newval;
							$modes_updated = true;
						}
					} else {
						if (is_array($newval)) {
							$modelist = uplink::$channels[$chan][$modechar]->getArrayCopy();
							foreach ($newval as $param) {
								if (($key = array_search($param, $modelist)) !== false) {
									unset(uplink::$channels[$chan][$modechar][$key]);

									# update stickylists
									if (in_array($modechar, $stickylist_flags)) {
										if (($key = array_search($param, ExtraServ::$chan_stickylists[$chan][$modechar]->getArrayCopy())) !== false) {
											unset(ExtraServ::$chan_stickylists[$chan][$modechar][$key]);
											$iparam = dbescape($param);
											$slists_delete_conds[] = "(channel='$chan' AND mode_list='$modechar' AND value='$iparam')";
										}
									}
								} else {
									log::notice("$modeop value not in list");
								}
							}
						} else {
							unset(uplink::$channels[$chan][$modechar]);
							$modes_updated = true;
						}
					}
				}

				if (count($slists_insert_values) > 0) {
					$values = implode(',', $slists_insert_values);
					$query = "INSERT INTO chan_stickylists VALUES $values";
					log::debug("stickylist additions query >>> $query");
					$i = pg_query(ExtraServ::$db, $query);
					if ($i === false) {
						log::error('stickylist additions query failed');
						log::error(pg_last_error());
						exit(24);
					} else {
						log::debug('stickylist additions query OK');
					}
				}

				if (count($slists_delete_conds) > 0) {
					$where = implode(' OR ', $slists_delete_conds);
					$query = "DELETE FROM chan_stickylists WHERE $where";
					log::debug("stickylist removals query >>> $query");
					$d = pg_query(ExtraServ::$db, $query);
					if ($d === false) {
						log::error('stickylist removals query failed');
						log::error(pg_last_error());
						exit(25);
					} else {
						log::debug('stickylist removals query OK');
					}
				}

				if ($do_stickymodes && $modes_updated) {
					$updates = array();
					$modes_array = array_filter(uplink::$channels[$chan]->getArrayCopy(), function($val) {
						return !is_array($val);
					});
					if (array_key_exists('k', $modes_array)) {
						$updates[] = "mode_k='{$modes_array['k']}'";
						unset($modes_array['k']);
					}
					if (array_key_exists('l', $modes_array)) {
						$updates[] = "mode_l='{$modes_array['l']}'";
						unset($modes_array['l']);
					}
					$updates[] = "mode_flags='" . implode(array_keys($modes_array)) . "'";

					$set = implode(', ', $updates);
					$query = "UPDATE chan_register SET $set WHERE channel='$chan'";
					log::debug("stickymodes update query >>> $query");
					$u = pg_query(ExtraServ::$db, $query);
					if ($u === false) {
						log::error('stickymodes update query failed');
						log::error(pg_last_error());
						exit(25);
					} else {
						log::debug('stickymodes update query OK');
					}
				}
			} else {
				log::trace('Got user mode change');
				$nick = strtolower($_i['args'][0]);
				$chars = str_split($_i['text'], 1);
				$op = null;
				$remove = array();
				foreach ($chars as $c) {
					if ($c == '+' || $c == '-') {
						$op = $c;
					} elseif ($op == '+') {
						uplink::$nicks[$nick]['mode'][] = $c;
						log::debug("Applied MODE $nick +$c");
					} elseif ($op == '-') {
						$remove[] = $c;
					} else {
						log::warning('Unhandled user mode character');
					}
				}
				if (count($remove) > 0) {
					uplink::$nicks[$nick]['mode'] = array_diff(uplink::$nicks[$nick]['mode']->getArrayCopy(), $remove);
					log::debug("Applied MODE $nick -" . implode($remove));
				}
			}
			break;
		case 'KICK':
			log::trace('Started KICK handling');
			$params = uplink::$nicks[$_i['args'][1]]->getArrayCopy();
			$params['channels'] = array_diff($params['channels']->getArrayCopy(), array($_i['args'][0]));
			uplink::$nicks[$_i['args'][1]] = $params;
			uplink::remove_from_modelists($_i['args'][1], $_i['args'][0]);
			log::debug("Removed {$_i['args'][1]} from {$_i['args'][0]} due to kick by {$_i['prefix']}");
			break;
		case 'QUIT':
			log::info('Deleting nick for QUIT');
			f::delete_nick($_i['prefix']);
			break;
		case 'SQUIT':
			// [Mon 2015.02.02 23:31:55.408885+00:00] [ responder] INFO: <= :extrastout.defiant.worf.co SQUIT yakko.cs.wmich.edu :Not enough arguments 
			if ($_i['prefix'] == ExtraServ::$hostname) {
				log::error("Server {$_i['args'][0]} is killing me: {$_i['text']}");
			} else {
				log::info("Server {$_i['prefix']} has quit ({$_i['text']})");
				unset(uplink::$network[$_i['prefix']]);
			}
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
					$_i['reply_to'] = $_i['prefix'];
					$_i['handle'] = $handle;
					$in_pm = true;
					break;
				}
			}

			# Check for serv commands in pm
			if ($in_pm) {
				$ucmd = explode(' ', $_i['text'], 2);
				$uarg = null;
				if (count($ucmd) > 1)
					$uarg = $ucmd[1];
				$ucmd = $ucmd[0];

				$cmdfunc = strtolower("serv_$ucmd");
				if (f::EXISTS($cmdfunc)) {
					f::CALL($cmdfunc, array($ucmd, $uarg, $_i));
					break;
				} else {
					log::trace('Not a serv command');
				}
			}

			# Normal commands
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
				} elseif(is_admin(uplink::get_user_by_nick($_i['prefix']))) {
					switch ($ucmd) {
						# manual/testing functions
						case 'mqt':
							log::trace('Got !mqt');
							proc::queue_sendall(42, $uarg);
							break;
						case 'dbt':
							pg_query(ExtraServ::$db, 'SELECT pg_sleep(15)');
							break;
						case 'serv':
							log::trace('Got !serv');
							ExtraServ::send($uarg);
							break;
						case 'es':
							log::trace('Got !es');
							ExtraServ::usend('ExtraServ', $uarg);
							break;
						case 'nes':
							log::trace('Got !nes');
							ExtraServ::usend('Nextrastout', $uarg);
							break;
						case 'servts':
							$ts = time();
							ExtraServ::send("$uarg $ts");
							break;
						case 'dumpconf':
							var_dump(config::get_instance());
							break;
						case 'dumpchans':
							if ($uarg == null)
								print_r(uplink::$channels);
							else
								print_r(uplink::$channels[$uarg]);
							break;
						case 'dumpnicks':
							if ($uarg == null)
								print_r(uplink::$nicks);
							else
								print_r(uplink::$nicks[$uarg]);
							break;
						case 'kill':
							$uarg = explode(' ', $uarg, 2);
							ExtraServ::$serv_handle->kill($uarg[0], $uarg[1]);
							break;
						case 'dumpstickies':
							echo "================== LISTS ============================\n";
							print_r(ExtraServ::$chan_stickylists);
							echo "================== MODES ============================\n";
							print_r(ExtraServ::$chan_stickymodes);
							break;
						case 'fakeident':
							ExtraServ::$ident[$uarg] = true;
							break;
						case 'dump1':
							proc::queue_sendall(proc::TYPE_COMMAND, 'DUMP1');
							break;

						# operational functions
						case 'es-reload':
							log::notice('Got !es-reload, reloading main()');
							$_i['handle']->say($_i['reply_to'], 'Reloading main');
							f::RELOAD('main');
							return 0;
						case 'procs-reload':
							log::notice('Got !procs-reload');
							$_i['handle']->say($_i['reply_to'], 'Telling other processes to reload');
							proc::queue_sendall(1, 'RELOAD');
							break;
						case 'reload':
							log::notice('Got !reload');
							if (f::EXISTS($uarg)) {
								f::RELOAD($uarg);
								proc::queue_sendall(2, $uarg);
								$_i['handle']->say($_i['reply_to'], "Reloading f::$uarg()");
							} else {
								$_i['handle']->say($_i['reply_to'], "Function $uarg does not exist");
							}
							break;
						case 'creload':
							log::notice('Got !creload');
							if (f::EXISTS("cmd_$uarg")) {
								f::RELOAD("cmd_$uarg");
								proc::queue_sendall(2, "cmd_$uarg");
								$_i['handle']->say($_i['reply_to'], "Reloading f::cmd_$uarg()");
							} else {
								$_i['handle']->say($_i['reply_to'], "Function cmd_$uarg does not exist");
							}
							break;
						case 'hup':
							log::notice('Got !hup');
							config::reload_all();
							$_i['handle']->say($_i['reply_to'], 'Reloaded config');
							proc::queue_sendall(1, 'HUP');
							break;
						case 'reload-all':
							log::notice('Got !reload-all');
							$_i['handle']->say($_i['reply_to'], 'Marking all functions for reloading and reloading conf');
							f::RELOAD_ALL();
							config::reload_all();
							proc::queue_sendall(1, 'RELOAD ALL');
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
							proc::queue_sendall(3, $uarg);
							break;
						case 'set-tz':
							log::notice('Got !set-tz');
							ExtraServ::$output_tz = $uarg;
							$_i['handle']->say($_i['reply_to'], 'Changed output timezone');
							proc::queue_sendall(4, $uarg);
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

return 1;
