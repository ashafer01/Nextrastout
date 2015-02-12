//<?php

log::trace("Entered f::$_FUNC_NAME()");

# function sms($to, $message, $from, $from_chan=null, $media_urls=array());
if ($_FUNC_NAME == 'sms') {
	list($to, $message, $from) = $_ARGV;
	array_shift($_ARGV);
	array_shift($_ARGV);
	array_shift($_ARGV);

# function infosms($to, $message, $from_chan=null, $media_urls=array());
} elseif ($_FUNC_NAME == 'infosms') {
	list($to, $message) = $_ARGV;
	array_shift($_ARGV);
	array_shift($_ARGV);
	$from = ExtraServ::$serv_handle->nick;
}


# Optional arguments
$from_chan = array_shift($_ARGV);
$media_urls = array_shift($_ARGV);
if ($media_urls === null) {
	$media_urls = array();
}

$sms_enable = true;
$mms_enable = true;
if (ctype_digit($to) && strlen($to) == 10) {
	# we got a phone number
	log::debug('Got phone number');
} else {
	# we got a nick
	log::debug('Got nick, looking up phone number');
	$to = dbescape($to);
	$q = pg_query(ExtraServ::$db, "SELECT phone_number, sms_enable, mms_enable FROM phone_register WHERE nick='$to'");
	if ($q === false) {
		log::error('Failed to look up phone number');
		log::error(pg_last_error());
		return 'Failed to look up phone number';
	} elseif (pg_num_rows($q) == 0) {
		log::debug('No phone number for nick');
		return 'Nickname not found';
	} else {
		$qr = pg_fetch_assoc($q);
		log::debug("Found phone number {$qr['phone_number']} for nick '$to'");
		$to = $qr['phone_number'];
		if ($qr['sms_enable'] == 'f')
			$sms_enable = false;
		if ($qr['mms_enable'] == 'f')
			$mms_enable = false;
	}
}

if ($_FUNC_NAME != 'infosms') {
	if (!$sms_enable) {
		log::debug('User has disabled non-info sms for this number');
		return 'That user does not wish to receive SMS messages';
	}
} else {
	log::debug('Sending infosms');
}

log::debug('Checking if outgoing number has been blocked');
$q = pg_query(ExtraServ::$db, "SELECT count(*) FROM blocked_numbers WHERE phone_number='$to'");
if ($q === false) {
	log::error('Failed to check for blocked outgoing number');
	log::error(pg_last_error());
	return 'Query failed';
} else {
	$qr = pg_fetch_assoc($q);
	if ($qr['count'] > 0) {
		log::notice('Attempted send to blocked number');
		return 'That number has been blocked';
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
	return 'Query failed';
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
	if (!$mms_enabled) {
		log::debug('User has disabled MMS for this number');
		return 'That user does not wish to receive MMS messages';
	}
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
				return "Failed to send $msgtype.";

				if ($mark_intro_sms) {
					log::error("Message containing intro for $to failed to send");
				}
				break;
			default:
				log::debug("$msgtype sent");
				$reply = "$msgtype Sent.";
				if ($from_chan != null) {
					$ts = time();
					$u = pg_query(ExtraServ::$db, "UPDATE phone_register SET last_send_uts=$ts, last_from_chan='$from_chan' WHERE phone_number='$to'");
					if ($u === false) {
						log::error('Failed to update last send info');
						log::error(pg_last_error());
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
						$reply .= ' Failed to mark intro SMS sent.';
					} else {
						log::debug('Marked intro sms sent');
					}
				}

				if ($truncated) {
					$reply .= " (Message was truncated at \"...$trunc_end\")";
				}
				return $reply;
		}
		break;
	default:
		log::error("Twilio returned HTTP $code");
		log::error($json);
		return "Failed to send $msgtype.";
}

return f::TRUE;
