//<?php

log::trace('entered f::cmd_phone()');
list($_CMD, $_ARG, $_i) = $_ARGV;

$channel = $_i['sent_to'];
$nick = dbescape(strtolower($_i['hostmask']->nick));

$fc = substr($channel, 0, 1);
if (($fc != '#') && ($fc != '&')) {
	$inpm = true;
} else {
	$inpm = false;
	$pm_msg = ' [NOTE: Its recommended to use !phone in private message so that your phone number is not visible in the log]';
}

$help = "!phone <number> : update your phone number | !phone -v : view your phone number";

if ($_ARG == 'help') {
	log::debug('Got !phone help');
	$_i['handle']->say($_i['reply_to'], $help);
	return f::TRUE;
}

if ($_ARG == '-v') {
	log::debug("Looking up phone number for $nick");
	$q = Nextrastout::$db->pg_query("SELECT phone_number FROM phonebook WHERE nick='$nick'", 'phonebook lookup');
	if ($q === false) {
		$say = 'Query failed';
	} elseif (pg_num_rows($q) == 0) {
		$say = "No phone number stored for '$nick'";
	} else {
		$qr = pg_fetch_assoc($q);
		$say = "Currently stored phone number for $nick: {$qr['phone_number']}";
		if (!$inpm) {
			$say .= $pm_msg;
		}
	}
	$_i['handle']->say($_i['reply_to'], $say);
	return f::TRUE;
}

if (ctype_digit($_ARG) && (strlen($_ARG) == 10)) {
	log::debug("Updating phone number for $nick");
	$u = Nextrastout::$db->pg_upsert("UPDATE phonebook SET phone_number='$_ARG' WHERE nick='$nick'",
		"INSERT INTO phonebook (nick, phone_number) VALUES ('$nick', '$_ARG')",
		'update phonebook');
	if ($u === false) {
		$say = 'Query failed.';
	} else {
		$say = 'Updated phonebook.';
	}
	if (!$inpm) {
		$say .= $pm_msg;
	}
	$_i['handle']->say($_i['reply_to'], $say);
	return f::TRUE;
} else {
	$_i['handle']->say($_i['reply_to'], "Please specify a 10-digit number | $help");
	return f::FALSE;
}
