//<?php

log::trace('Entered f::sms()');

# function sms($to, $message, $from, $from_chan=null, $quiet=false, $media_urls=array());
list($to, $message, $from) = $_ARGV;
array_shift($_ARGV);
array_shift($_ARGV);
array_shift($_ARGV);

# Optional arguments
$from_chan = array_shift($_ARGV);
$quiet = array_shift($_ARGV);
if ($quiet === null) {
	$quiet = false;
}
$media_urls = array_shift($_ARGV);
if ($media_urls === null) {
	$media_urls = array();
}

if (ctype_digit($to) && strlen($to) == 10) {
	# we got a phone number
	log::debug('Got phone number');
} else {
	# we got a nick
	log::debug('Got nick, looking up phone number');
	$to = dbescape($to);
	$q = pg_query(ExtraServ::$db, "SELECT phone_number FROM phone_register WHERE nick='$to'");
	if ($q === false) {
		log::error('Failed to look up phone number');
		log::error(pg_last_error());
		$_i['handle']->say($_i['reply_to'], 'Failed to look up phone number');
		return f::FALSE;
	} elseif (pg_num_rows($q) == 0) {
		log::debug('No phone number for nick');
		$_i['handle']->say($_i['reply_to'], 'Nickname not found');
		return f::FALSE;
	} else {
		$qr = pg_fetch_assoc($q);
		log::debug("Found phone number {$qr['phone_number']} for nick '$to'");
		$to = $qr['phone_number'];
	}
}

log::debug('Checking if outgoing number has been blocked');
$q = pg_query(ExtraServ::$db, "SELECT count(*) FROM blocked_numbers WHERE phone_number='$to'");
if ($q === false) {
	log::error('Failed to check for blocked outgoing number');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);
	if ($qr['count'] > 0) {
		log::notice('Attempted send to blocked number');
		$_i['handle']->say($_i['reply_to'], 'That number has been blocked');
	} else {
		log::trace('Number not blocked');
	}
}

$conf = config::get_instance();

$omsg = "<$from> $message";
$truncated = false;
if (strlen($omsg) > 160) {
	$truncated = true;
	$omsg = substr($omsg, 0, 160);
	$trunc_end = trim(substr($omsg, -20));
}
$omsg = urlencode($omsg);

log::debug('Checking if intro sms has been sent');
$mark_intro_sms = false;
$q = pg_query(ExtraServ::$db, "SELECT count(*) FROM phone_intro_sent WHERE phone_number='$to'");
if ($q === false) {
	log::error('Failed to check if intro sms was sent');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);
	if ($qr['count'] == 0) {
		# need to send intro SMS
		log::info("Appending SMS introduction for $to");
		$mark_intro_sms = true;
		$omsg .= ' [This message was relayed from the Internet by an automated service. Reply with "BLOCK" (case-sensitive) to block your number from this service.]';
	} else {
		log::trace('Intro SMS already sent');
	}
}

if (count($media_urls) > 0) {
	$msgtype = 'MMS';
} else {
	$msgtype = 'SMS';
}

$postfields = array("From={$conf->twilio->phone_number}&To=+1$to&Body=$omsg");
if (count($media_urls) > 10) {
	$media_urls = array_slice($media_urls, 0, 10);
}
foreach ($media_urls as $url) {
	$postfields[] = "MediaUrl=$url";
}
$postfields = implode('&', $postfields);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.twilio.com/2010-04-01/Accounts/{$conf->twilio->account_sid}/Messages.json");
curl_setopt($ch, CURLOPT_USERPWD, "{$conf->twilio->account_sid}:{$conf->twilio->auth_token}");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$json = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
switch ($code) {
	case 201:
		$response = json_decode($json, true);
		$reply = null;
		switch ($response['status']) {
			case 'failed':
				log::error("Twilio failed to send $msgtype");
				log::error(print_r($response, true));
				say("Failed to send $msgtype.");
				$_i['handle']->say($_i['reply_to'], "Failed to send $msgtype.");

				if ($mark_intro_sms) {
					log::error("Message containing intro for $to failed to send");
				}
				break;
			default:
				log::debug("$msgtype sent");
				if (!$quiet) {
					$reply = "$msgtype Sent.";
				}
				if ($from_chan != null) {
					$ts = time();
					$u = pg_query(ExtraServ::$db, "UPDATE phone_register SET last_send_uts=$ts, last_from_chan='$from_chan' WHERE phone_number='$to'");
					if ($u === false) {
						log::error('Failed to update last send info');
						log::error(pg_last_error());
						if (!$quiet)
							$reply .= ' Failed to update last send info.';
					} else {
						log::debug("Updated last_send_uts and last_from_chan for $to");
					}
				}

				if ($mark_intro_sms) {
					log::debug('Marking intro sms sent');
					$i = pg_query(ExtraServ::$db, "INSERT INTO phone_intro_sent (phone_number) VALUES ('$to')");
					if ($i === false) {
						log::error("Failed to mark intro sent for $to");
						log::error(pg_last_error());
						if (!$quiet) {
							$reply .= ' Failed to mark intro SMS sent.';
						}
					} else {
						log::debug('Marked intro sms sent');
					}
				}

				if (!$quiet && $truncated) {
					$reply .= " (Message was truncated at \"...$trunc_end\")";
				}
				if ($reply !== null) {
					$_i['handle']->say($_i['reply_to'], $reply);
				}
				break;
		}
		break;
	default:
		log::error("Twilio returned HTTP $code");
		log::error($json);
		$_i['handle']->say($_i['reply_to'], "Failed to send $msgtype.");
		break;
}

return f::TRUE;
