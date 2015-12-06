//<?php

log::trace("Entered f::$_CALLED_AS()");

# function sms($to, $message, $from, $from_chan=null, $media_urls=array());
if ($_CALLED_AS == 'sms') {
	list($to, $message, $from) = $_ARGV;
	array_shift($_ARGV);
	array_shift($_ARGV);
	array_shift($_ARGV);
	$infosms = false;

# function infosms($to, $message, $from_chan=null, $media_urls=array());
} elseif ($_CALLED_AS == 'infosms') {
	list($to, $message) = $_ARGV;
	array_shift($_ARGV);
	array_shift($_ARGV);
	$from = Nextrastout::$bot_handle->nick;
	$infosms = true;
}


# Optional arguments
$from_chan = array_shift($_ARGV);
if ($from_chan === null) {
	Nextrastout::$conf->sms->default_channel;
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
	$q = Nextrastout::$db->pg_query("SELECT phone_number FROM phonebook WHERE nick='$to'",
		'phonebook lookup');
	if ($q === false) {
		if ($infosms) {
			return f::FALSE;
		} else {
			return 'Failed to look up phone number';
		}
	} elseif (pg_num_rows($q) == 0) {
		log::debug('No phone number for nick');
		if ($infosms) {
			return null;
		} else {
			return 'Nickname not found';
		}
	} else {
		$qr = pg_fetch_assoc($q);
		log::debug("Found phone number {$qr['phone_number']} for nick '$to'");
		$to = $qr['phone_number'];
	}
}

$number_data = f::get_number_data($to);
if ($number_data === false) {
	if ($infosms) {
		return f::FALSE;
	} else {
		return 'Query failed';
	}
}

if (!$infosms && $number_data['blocked']) {
	return 'That number is blocked';
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

if (!$number_data['intro_sent']) {
	# need to send intro SMS
	log::info("Appending SMS introduction for $to");
	$mark_intro_sms = true;
	$omsg .= ' [This message was relayed from the Internet by an automated service. Reply with "BLOCK" (case-sensitive) to block your number from this service.]';
} else {
	log::trace('Intro SMS already sent');
	$mark_intro_sms = false;
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
				if ($infosms) {
					return f::FALSE;
				} else {
					return "Failed to send $msgtype.";
				}

				if ($mark_intro_sms) {
					log::error("Message containing intro for $to failed to send");
				}
				break;
			default:
				log::debug("$msgtype sent");
				$reply = "$msgtype Sent.";
				$inforeply = f::TRUE;
				$ts = time();
				if ($from_chan != null) {
					$u = Nextrastout::$db->pg_upsert("UPDATE phone_numbers SET last_send_uts=$ts, last_from_chan='$from_chan' WHERE phone_number='$to'",
						"INSERT INTO phone_numbers (phone_number, last_send_uts, last_from_chan) VALUES ('$to', $ts, '$from_chan')",
						'last send info');
					if ($u === false) {
						$reply .= ' Failed to update last send info.';
						$inforeply = f::FALSE;
					} else {
						log::debug("Updated last_send_uts and last_from_chan for $to");
					}
				}

				if ($mark_intro_sms) {
					log::debug('Marking intro sms sent');
					$u = Nextrastout::$db->pg_upsert("UPDATE phone_numbers SET intro_sent=TRUE WHERE phone_number='$to'",
						"INSERT INTO phone_numbers (phone_number, last_send_uts, last_from_chan, intro_sent) VALUES ('$to', $ts, '$from_chan', TRUE)",
						'mark intro sms sent');
					if ($i === false) {
						$reply .= ' Failed to mark intro SMS sent.';
						$inforeply = f::FALSE;
					} else {
						log::debug('Marked intro sms sent');
					}
				}

				if ($truncated) {
					$reply .= " (Message was truncated at \"...$trunc_end\")";
				}
				if ($infosms) {
					return $inforeply;
				} else {
					return $reply;
				}
		}
		break;
	default:
		log::error("Twilio returned HTTP $code");
		log::error($json);
		if ($infosms) {
			return f::FALSE;
		} else {
			return "Failed to send $msgtype.";
		}
}

return f::TRUE;
