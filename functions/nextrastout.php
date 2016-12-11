//<?php

Nextrastout::dbconnect();
f::ALIAS_INIT();

$topicdata = array();

$cmd_globals = new stdClass;
$cmd_globals->topic_nicks = array();

$cmd_globals->start_time = time();

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

	# Handle line
	switch ($_i['cmd']) {
		case 'ERROR':
			log::fatal('Got ERROR line');
			exit(proc::EXIT_ERROR_LINE);
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
				$q = Nextrastout::$db->pg_query("SELECT count(*) AS count from topic WHERE channel='$topicchan'", 'Check topic query');
				$doit = true;
				if (db::num_rows($q) > 0) {
					$qr = pg_fetch_assoc($q);
					if ($qr['count'] > 0) {
						log::debug('Not inserting server topic, we already have topics for this channel');
						$doit = false;
					}
				}
				if ($doit) {
					$q = Nextrastout::$db->pg_query("INSERT INTO topic (uts, topic, by_nick, channel) VALUES ($uts, '{$topicdata[$topicchan]}', '$nick', '$topicchan')", 'New topic query');
					if ($q === false) {
						Nextrastout::$bot_handle->say($topicchan, 'Failed to store new topic');
					} else {
						log::debug("Stored new topic for $topicchan");
					}
				}
				unset($topicdata[$topicchan]);
			} else {
				log::warning("Got 333 topic metadata for $topicchan before 332, ignoring");
			}
			break;

		# need op privileges
		case '482':
			Nextrastout::$bot_handle->say($_i['args'][1], "I'll need op privileges to do that");
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

			$q = Nextrastout::$db->pg_query("INSERT INTO topic (uts, topic, by_nick, channel) VALUES ($uts, '$topic', '$nick', '$topicchan')",
				'New topic query');
			if ($q === false) {
				Nextrastout::$bot_handle->say($_i['args'][0], 'Failed to store new topic');
			} else {
				log::debug("Stored new topic for $topicchan");
			}
			break;

		# handle commands, etc.
		case 'PRIVMSG':
			if (in_array($_i['hostmask']->user, Nextrastout::$conf->banned_users)) {
				log::info("Ignoring banned user {$_i['hostmask']->user}");
				break;
			}

			$leader = '!';
			$_i['sent_to'] = $_i['args'][0];
			$_i['reply_to'] = $_i['args'][0];
			$_i['handle'] = Nextrastout::$bot_handle;
			$in_pm = false;
			if ($_i['reply_to'] == $_i['handle']->nick) {
				log::trace('Received private message');
				$_i['reply_to'] = $_i['hostmask']->nick;
				$in_pm = true;
				$_i['in_pm'] = true;
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
					# check cooldown
					$iuser = $_i['hostmask']->user;
					$inick = $_i['hostmask']->nick;
					if (in_array($iuser, Nextrastout::$conf->cooldown_users) && array_key_exists($iuser, Nextrastout::$cmd_cooldown)) {
						$mycd = Nextrastout::$cmd_cooldown[$iuser];
						if (($mycd['last'] + $mycd['cooldown']) >= time()) {
							$lastdate = date('r', $mycd['last']);
							log::info("{$iuser} under {$mycd['cooldown']} second cooldown from $lastdate");

							# update array
							$wlevel = "warn{$mycd['warncount']}";
							if (isset(Nextrastout::$conf->cooldown->{$wlevel})) {
								$newcd = Nextrastout::$conf->cooldown->{$wlevel};
							} else {
								$newcd = $mycd['cooldown'];
							}
							Nextrastout::$cmd_cooldown[$iuser] = array(
								'last' => time(),
								'cooldown' => $newcd,
								'warncount' => $mycd['warncount']+1
							);
							log::info("Set cooldown to {$new_cd}s for $iuser");

							# Send warning messages
							$cd_str = duration_str($newcd);
							switch ($mycd['warncount']) {
								case 0:
									$_i['handle']->say($_i['reply_to'], "{$inick}: Cooldown of $cd_str in effect.");
									break;
								case 1:
									if ($newcd != $mycd['cooldown']) {
										$say = "Cooldown has been increased to $cd_str.";
									} else {
										$say = "Cooldown of $cd_str seconds is still in effect. Please wait.";
									}
									$_i['handle']->say($inick, $say);
									break;
								case 2:
									$_i['handle']->say($inick, "Cooldown has been increased to $cd_str. If you continue to spam, the time will be increased silently. Would you kindly stop?");
									break;
							}
							break;
						}
					}

					# Run command
					Nextrastout::$cmd_cooldown[$iuser] = array('last' => time(), 'cooldown' => Nextrastout::$conf->cooldown->initial, 'warncount' => 0);
					f::CALL($cmdfunc, array($ucmd, $uarg, $_i, $cmd_globals));
				} elseif(is_admin($_i['hostmask']->user)) {
					switch ($ucmd) {
						case 'es-reload':
							log::notice('Got !es-reload, reloading nextrastout()');
							if ($uarg == '-hup') {
								log::debug('Doing hup option on es-reload');
								config::reload_all();
								$_i['handle']->say($_i['reply_to'], 'Reloaded config');
								f::ALIAS_INIT();
								Nextrastout::$bot_handle->update_conf_channels();
							}
							$_i['handle']->say($_i['reply_to'], 'Reloading nextrastout');
							f::RELOAD('nextrastout');
							return proc::PROC_RERUN;
						default:
							f::admin_commands($ucmd, $uarg, $_i, $cmd_globals);
							break;
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
							if (in_array($_i['hostmask']->nick, Nextrastout::$conf->link->ignore)) {
								log::info("Skipping link handling for {$_i['hostmask']->nick}");
								break;
							}
							$foundUrl = false;
							$w = explode(' ', $_i['text']);
							$seenUrls = array();
							foreach ($w as $word) {
								$p = explode('://', $word, 2);
								if (($p[0] == 'http') || ($p[0] == 'https')) {
									if (filter_var($word, FILTER_VALIDATE_URL)) {
										$url = $word;
										if (in_array($url, $seenUrls)) {
											log::debug('Already posted this URL this message');
											continue;
										}
										log::debug("Found URL, requesting content >> $url");
										$foundUrl = true;

										$content = file_get_contents($url);
										$title = '';
										$t = explode('<title>', $content, 2);
										if (count($t) == 2) {
											log::debug('Found <title> tag in URL response');
											$t = explode('</title>', $t[1], 2);
											if (count($t) == 2) {
												log::debug('Found closing </title> tag');
												$title = ' - ' . html_entity_decode($t[0], ENT_QUOTES);
											}
										}

										if (strlen($url) > 20) {
											$shortUrl = f::shorten($url);
										} else {
											$shortUrl = $url;
										}

										log::debug("url      >> '$url'");
										log::debug("shortUrl >> '$shortUrl'");
										log::debug("title    >> '$title'");

										if (($shortUrl == $url) && (($title == '') || ($title == ' - '))) {
											log::debug('No meaningful output for link');
										} else {
											$_i['handle']->say($_i['reply_to'], $shortUrl . $title);
											$seenUrls[] = $url;
										}
									}
								}
							}
							if (!$foundUrl) {
								log::trace('Line unhandled');
							}
						}
				}
			}
			break; # --- end privmsg handling
	}
}

Nextrastout::$bot_handle->del_all_channels();
exit(proc::BROKEN_PIPE);
