//<?php

log::trace('entered f::serv_verifyphone()');
list($ucmd, $uarg, $_i) = $_ARGV;

$nick = $_i['prefix'];
$user = uplink::get_user_by_nick($nick);
if (!ExtraServ::is_idented($user)) {
	log::debug('Not identified');
	$_i['handle']->notice($_i['reply_to'], 'You must identify before using this function');
	return f::FALSE;
}

if ($uarg == null) {
	log::debug('No argument');
	$_i['handle']->notice($_i['reply_to'], 'Please specify a phone number');
	return f::FALSE;
}

if (!ctype_digit($uarg) || (strlen($uarg) != 10)) {
	log::debug('Invalid phone number');
	$_i['handle']->notice($_i['reply_to'], 'Please specify a valid 10-digit phone number');
	return f::FALSE;
}

$chars = str_split('0123456789');
$verification_code = '';
for ($i = 0; $i < 6; $i++) {
	$verification_code .= $chars[array_rand($chars)];
}

$to_number = $uarg;

$q = pg_query(ExtraServ::$db, "SELECT ircuser, verification_sent_uts, verified_uts FROM phone_verify WHERE phone_number='$to_number'");
if ($q === false) {
	log::error('Failed to look up existing registration');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Query failed');
	return f::FALSE;
} elseif (pg_num_rows($q) > 0) {
	$qr = pg_fetch_assoc($q);
	if ($qr['verified_uts'] != null) {
		log::info('Number already verified');
		$_i['handle']->notice($_i['reply_to'], 'That number has already been verified');
		return f::TRUE;
	} elseif (time() > ($qr['verification_sent_uts'] + ExtraServ::$conf->sms->verify_timeout)) {
		log::info('Old verification has expired');
		$iuser = pg_escape_literal(ExtraServ::$db, $user);
		$ts = time();
		$q = pg_query(ExtraServ::$db, "UPDATE phone_verify SET ircuser=$iuser, verification_code='$verification_code', verification_sent_uts=$ts WHERE phone_number='$to_number'");
		if ($q === false) {
			log::error('Failed to update expired verification with new code');
			log::error(pg_last_error());
			$_i['handle']->notice($_i['reply_to'], 'Query failed');
			return f::FALSE;
		} else {
			log::info("Sending verification code '$verification_code' to number '$to_number' for user '$user' (re-verification)");
			$success = f::infosms($to_number, "Your verification code is $verification_code");
			if ($success) {
				log::debug('Re-verification sms sent');
				$_i['handle']->notice($_i['reply_to'], 'Verification code has been sent.');
				return f::TRUE;
			} else {
				log::error('Failed to send verification sms after update');
				$_i['handle']->notice($_i['reply_to'], 'Failed to send verification code.');
				return f::FALSE;
			}
		}
	} else {
		if ($qr['ircuser'] == $user) {
			log::info('Unexpired verification for current user');
			$_i['handle']->notice($_i['reply_to'], 'Your verification code has already been sent. If you did not receive your code, please wait one hour for the old code to expire.');
			$_i['handle']->notice($_i['reply_to'], 'If you have received your verification code, use REGPHONE to supply the verification code and associate the number with one of your nicknames.');
		} else {
			log::info('Unexpired verification for other user');
			$_i['handle']->notice($_i['reply_to'], 'There is already a pending verification for that number.');
		}
		return f::FALSE;
	}
} else {
	$iuser = pg_escape_literal(ExtraServ::$db, $user);
	$ts = time();
	$query = "INSERT INTO phone_verify (phone_number, ircuser, verification_code, verification_sent_uts) VALUES ('$to_number', $iuser, '$verification_code', $ts)";
	log::debug("verifyphone query >>> $query");
	$q = pg_query(ExtraServ::$db, $query);
	if ($q === false) {
		log::error('Failed to insert phone verification code');
		log::error(pg_last_error());
		$_i['handle']->notice($_i['reply_to'], 'Query failed');
		return f::FALSE;
	}

	log::info("Sending verification code '$verification_code' to number '$to_number' for user '$user'");

	$success = f::infosms($to_number, "Your verification code is $verification_code");
	if ($success) {
		log::debug('Verification sms sent');
		$_i['handle']->notice($_i['reply_to'], 'Verification code has been sent.');
		return f::TRUE;
	} else {
		log::error('Failed to send verification sms');
		$_i['handle']->notice($_i['reply_to'], 'Failed to send verification code.');
		return f::FALSE;
	}
}
