//<?php

log::trace('entered f::get_number_data()');
list($number) = $_ARGV;

if (!ctype_digit($number) || (strlen($number) != 10)) {
	log::notice('Invalid number passed to f::get_number_data()');
	return f::FALSE;
}

$q = Nextrastout::$db->pg_query("SELECT * FROM phone_numbers WHERE phone_number='$number'",
	'number data lookup');
if ($q === false) {
	$number_data = f::FALSE;
} elseif (pg_num_rows($q) == 0) {
	log::debug("Phone number $number is not known");
	$number_data = array(
		'last_send_uts' => null,
		'last_from_chan' => null,
		'intro_sent' => false,
		'blocked' => false
	);
} else {
	$number_data = pg_fetch_assoc($q);
	$numder_data['intro_sent'] = str_bool($number_data['intro_sent']);
	$number_data['blocked'] = str_bool($number_data['blocked']);
}

return $number_data;
