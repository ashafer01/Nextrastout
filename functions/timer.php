//<?php

log::trace('entered f::timer()');

ExtraServ::dbconnect();
f::ALIAS_INIT();
$conf = config::get_instance();

proc::ready();

$count = 0;
while (true) {
	if ($count % 1000 == 0) {
		log::trace('Still alive.');
	}

	if (($message = proc::queue_get(0, $msgtype, $fromproc)) !== null) {
		if ($msgtype == proc::TYPE_SHITSTORM_STARTING) {
			log::debug('Entering shitstorm loop');
			while (true) {
				$message = proc::queue_get_block(0, $ss_msgtype);
				if ($ss_msgtype == proc::TYPE_SHITSTORM_OVER) {
					log::debug('Shitstorm is over');
					break;
				}
				if (!ES_SyncedArrayObject::dispatchMessage($ss_msgtype, $message)) {
					f::handle_ipc($ss_msgtype, $message);
				}
			}
		}
		$ret = f::handle_ipc($msgtype, $message);
		if (is_int($ret)) {
			return $ret;
		}

		$ret = ES_SyncedArrayObject::dispatchMessage($msgtype, $message);
	}

	# check for new sms message
	$query = query_whitespace("
	SELECT
		phone_register.nick,
		phone_register.gravity_enable,
		phone_register.last_from_chan,
		phone_register.last_send_uts,
		phone_register.default_chan,
		sms.from_number,
		sms.message,
		sms.message_sid
	FROM sms FULL JOIN phone_register ON phone_register.phone_number=sms.from_number
	WHERE sms.posted IS FALSE
	ORDER BY uts
	");
	$q = pg_query(ExtraServ::$db, $query);
	if ($q === false) {
		log::error('Check sms query failed');
		log::error(pg_last_error());
	} else {
		while ($qr = pg_fetch_assoc($q)) {
			$message = $qr['message'];
			$did_send = false;
			$error = false;
			if ($qr['nick'] == null) {
				log::info('Got SMS from unregistered number, sending to sms.default_channel');
				$reply = "<{$qr['from_number']}> {$qr['message']}";
				ExtraServ::$bot_handle->say($conf->sms->default_channel, $reply);
				$did_send = true;
			} else {
				log::debug("Got SMS from registered number ({$qr['nick']})");
				$reply = "<{$qr['nick']}> {$qr['message']}";
				$mw = explode(' ', $message, 2);
				$fc = substr($mw[0], 0, 1);
				if ($fc == '#' || $fc == '&') {
					log::debug('Got a destination channel');
					$dchan = $mw[0];
					$message = $mw[1];
					if (array_key_exists($dchan, uplink::$channels)) {
						if (array_key_exists($qr['nick'], uplink::$nicks)) {
							if (in_array($dchan, uplink::$nicks[$qr['nick']]['channels']->getArrayCopy())) {
								log::debug('destination channel OK');
								ExtraServ::$bot_handle->say($dchan, "<{$qr['nick']}> $message");
								$did_send = true;
							} else {
								log::debug('Nick not joined to destination channel');
								f::infosms($qr['from_number'], 'The nickname associated with this number must be joined to the specified destination channel.');
								$error = true;
							}
						} else {
							log::debug('Nick not online');
							f::infosms($qr['from_number'], 'The nickname associated with this number must be online to specify a destination channel.');
							$error = true;
						}
					} else {
						log::debug('Destination channel does not exist');
						f::infosms($qr['from_number'], "Channel $dchan does not exist");
						$error = true;
					}
				} elseif ($qr['gravity_enable'] == 't') {
					log::debug('No destination channel, gravity is enabled');
					if ($qr['last_from_chan'] != null) {
						if (time() < ($qr['last_send_uts'] + 3600)) {
							log::debug('Gravity still active');
							ExtraServ::$bot_handle->say($qr['last_from_chan'], $reply);
							$did_send = true;
						} else {
							log::debug('Gravity is inactive');
						}
					} else {
						log::debug('No gravity channel');
					}
				} else {
					log::debug('No destination channel, gravity is disabled');
				}
			}

			if (!$error && !$did_send) {
				log::debug('Message not handled yet, doing defaults');
				if ($qr['default_chan'] != null) {
					log::debug('Sending to user\'s default channel');
					ExtraServ::$bot_handle->say($qr['default_chan'], $reply);
				} else {
					log::debug('User does not have default channel, sending to global default channel');
					ExtraServ::$bot_handle->say($conf->sms->default_channel, $reply);
				}
			}

			$q = pg_query(ExtraServ::$db, "UPDATE sms SET posted=TRUE WHERE message_sid='{$qr['message_sid']}'");
			if ($q === false) {
				log::error('Failed to mark SMS posted!');
				log::error(pg_last_error());
				return f::FALSE;
			} else {
				log::debug('Marked message posted');
			}
		}
	}

	sleep(1);
	$count++;
}
