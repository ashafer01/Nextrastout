//<?php

log::trace('entered f::serv_regphone()');
list($ucmd, $uarg, $_i) = $_ARGV;

$usage = 'Usage: REGPHONE <phone number> [verification code]';

$nick = $_i['prefix'];
$user = uplink::get_user_by_nick($nick);
if (!ExtraServ::is_idented($user)) {
	log::debug('Not identified');
	$_i['handle']->notice($_i['reply_to'], 'You must identify before using this function');
	return f::FALSE;
}

$inick = pg_escape_literal(ExtraServ::$db, $nick);
$iuser = pg_escape_literal(ExtraServ::$db, $user);

$q = pg_query(ExtraServ::$db, "SELECT count(*) FROM user_nick_map WHERE ircuser=$iuser AND nick=$inick");
if ($q === false) {
	log::error('Failed to query user_nick_map');
	log::error(pg_last_error());
	$_i['handle']->notice($_i['reply_to'], 'Query failed');
	return f::FALSE;
}
$qr = pg_fetch_assoc($q);
if ($qr['count'] == 0) {
	log::info('Nick not associated');
	$_i['handle']->notice($_i['reply_to'], 'Your nickname must be associated with your username to register a phone.');
	return f::FALSE;
}

if ($uarg == null) {
	log::debug('No argument');
	$_i['handle']->notice($_i['reply_to'], $usage);
	return f::FALSE;
}

$uargs = explode(' ', $uarg);
$uargc = count($uargs);

if (!ctype_digit($uargs[0]) || (strlen($uargs[0]) != 10)) {
	log::debug('Invalid number');
	$_i['handle']->notice($_i['reply_to'], 'Please specify a valid 10-digit phone number');
	return f::FALSE;
}

$reg_number = dbescape($uargs[0]);
$verification_code = null;
if ($uargc > 1) {
	$verification_code = dbescape($uargs[1]);
}

# check verification
$q = pg_query(ExtraServ::$db, "SELECT ircuser, verification_code, verification_sent_uts, verified_uts FROM phone_verify WHERE phone_number='$reg_number'");
if ($q === false) {
	log::error('Failed to check verification');
	log::error(pg_last_error());
} elseif (pg_num_rows($q) == 0) {
	log::info('Number not verified');
	$_i['handle']->notice($_i['reply_to'], 'That phone number has not been verified. Please use VERIFYPHONE to get a verification code.');
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);
	if ($qr['ircuser'] != $user) {
		log::info('Bad user for phone registration');
		$_i['handle']->notice($_i['reply_to'], 'User mismatch for verification.');
		return f::FALSE;
	}
	if ($qr['verified_uts'] == null) {
		log::debug('Number not yet verified');
		if (time() > ($qr['verification_sent_uts'] + ExtraServ::$conf->sms->verify_timeout)) {
			log::info('Verification has expired');
			$_i['handle']->notice($_i['reply_to'], 'Phone verification has expired.');
			return f::FALSE;
		} else {
			log::debug('Verification is not expired');
			if ($verification_code === null) {
				log::debug('No verification code specified');
				$_i['handle']->notice($_i['reply_to'], 'Phone number not yet verified; please specify your verification code.');
				return f::FALSE;
			} elseif ($qr['verification_code'] != $verification_code) {
				log::info('Verification code mismatch');
				$_i['handle']->notice($_i['reply_to'], 'Verification code is not correct.');
				return f::FALSE;
			} else {
				log::info('OK to register number');
				$ts = time();
				$q = pg_query(ExtraServ::$db, "UPDATE phone_verify SET verified_uts=$ts WHERE phone_number='$reg_number'");
				if ($q === false) {
					log::error('Failed to mark number verified');
					log::error(pg_last_error());
					$_i['handle']->notice($_i['reply_to'], 'Query failed');
					return f::FALSE;
				}
				$q = pg_query(ExtraServ::$db, "SELECT count(*) FROM phone_register WHERE phone_number='$reg_number' OR nick=$inick");
				if ($q === false) {
					log::error('Failed to look up existing regitration');
					log::error(pg_last_error());
					$_i['handle']->notice($_i['reply_to'], 'Query failed');
					return f::FALSE;
				}
				$qr = pg_fetch_assoc($q);
				if ($qr['count'] > 0) {
					log::info('Number/nick already registered');
					$_i['handle']->notice($_i['reply_to'], 'Your phone number or nickname is already registered.');
					$_i['handle']->notice($_i['reply_to'], 'If you wish to change the nickname associated with the');
					$_i['handle']->notice($_i['reply_to'], 'number, use DELPHONE to delete the registration, and');
					$_i['handle']->notice($_i['reply_to'], 'then REGPHONE again.');
					return f::FALSE;
				}
				$q = pg_query(ExtraServ::$db, "INSERT INTO phone_register (phone_number, nick) VALUES ('$reg_number', $inick)");
				if ($q === false) {
					log::error('Failed to insert phone registration');
					log::error(pg_last_error());
					$_i['handle']->notice($_i['reply_to'], 'Query failed');
					return f::FALSE;
				}

				log::info('Phone registration ok');
				$_i['handle']->notice($_i['reply_to'], 'Phone number has been registered to your nickname');
				return f::TRUE;
			}
		}
	} else {
		log::debug('Number is already verified');
		$q = pg_query(ExtraServ::$db, "SELECT count(*) FROM phone_register WHERE phone_number='$reg_number' OR nick=$inick");
		if ($q === false) {
			log::error('Failed to look up existing regitration');
			log::error(pg_last_error());
			$_i['handle']->notice($_i['reply_to'], 'Query failed');
			return f::FALSE;
		}
		$qr = pg_fetch_assoc($q);
		if ($qr['count'] > 0) {
			log::info('Number/nick already registered');
			$_i['handle']->notice($_i['reply_to'], 'Your phone number or nickname is already registered.');
			$_i['handle']->notice($_i['reply_to'], 'If you wish to change the nickname associated with the');
			$_i['handle']->notice($_i['reply_to'], 'number, use DELPHONE to delete the registration, and');
			$_i['handle']->notice($_i['reply_to'], 'then REGPHONE again.');
			return f::FALSE;
		}
		$q = pg_query(ExtraServ::$db, "INSERT INTO phone_register (phone_number, nick) VALUES ('$reg_number', $inick)");
		if ($q === false) {
			log::error('Failed to insert phone registration');
			log::error(pg_last_error());
			$_i['handle']->notice($_i['reply_to'], 'Query failed');
			return f::FALSE;
		}

		log::info('Phone registration ok');
		$_i['handle']->notice($_i['reply_to'], 'Phone number has been registered to your nickname');
		return f::TRUE;
	}
}

