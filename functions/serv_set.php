//<?php

log::trace('entered f::serv_set()');
list($ucmd, $uarg, $_i) = $_ARGV;

if ($uarg == null) {
	log::debug('No arguments');
	$_i['handle']->notice($_i['reply_to'], 'Please supply a type');
	return f::FALSE;
}

$nick = strtolower($_i['prefix']);
$user = uplink::$nicks[$nick]['user'];

$uarg = explode(' ', $uarg, 2);
$type = strtoupper(array_shift($uarg));
$uarg = array_shift($uarg);
switch ($type) {
	case 'PROFILE':
		log::debug('Got SET PROFILE');
		if ($uarg == null) {
			log::debug('No arguments, getting top field names');
			$query = 'SELECT key, count(*) FROM user_profile GROUP BY key ORDER BY count DESC LIMIT 5';
			log::debug("top field names query >>> $query");
			$q = pg_query(ExtraServ::$db, $query);
			if ($q === false) {
				log::error('top profile field names query faield');
				log::error(pg_last_error());
				$_i['handle']->notice($_i['reply_to'], 'Query failed');
				return f::FALSE;
			} elseif (pg_num_rows($q) == 0) {
				log::debug('No entries in profile');
				$_i['handle']->notice($_i['reply_to'], 'No field names found');
				return f::FALSE;
			} else {
				log::debug('query ok');
				$field_strs = array();
				while ($qr = pg_fetch_assoc($q)) {
					$fcount = number_format($qr['count']);
					$field_strs[] = "{$qr['key']} ($fcount)";
				}
				$_i['handle']->notice($_i['reply_to'], 'Top field names: ' . implode(', ', $field_strs));
				return f::TRUE;
			}
		}

		$fnv = explode('=', $uarg, 2);
		$fnv = array_map('trim', $fnv);

		$fieldname = dbescape(substr($fnv[0], 0, 48));
		$value = dbescape($fnv[1]);

		if (strlen($value) == 0) {
			log::debug('No value for profile entry');
			$_i['handle']->notice($_i['reply_to'], 'Please supply a value');
			return f::FALSE;
		}

		$md5sum = md5($fieldname . $value);

		$query = "INSERT INTO user_profile (ircuser, key, value, md5sum) VALUES ('$user', '$fieldname', '$value', '$md5sum')";
		log::debug("set profile query >>> $query");
		if (pg_send_query(ExtraServ::$db, $query)) {
			$q = pg_get_result(ExtraServ::$db);
			$err = pg_result_error_field($q, PGSQL_DIAG_SQLSTATE);
			if ($err === null || $err == '00000') {
				log::debug('query ok');
				$_i['handle']->notice($_i['reply_to'], "Stored new profile value for \"$fieldname\"");
				return f::TRUE;
			} elseif ($err == '23505') {
				log::debug('dupe');
				$_i['handle']->notice($_i['reply_to'], 'That field name and value already exist.');
				return f::FALSE;
			} else {
				log::error('set profile query failed');
				log::error(pg_result_error($q));
				$_i['handle']->notice($_i['reply_to'], 'Query failed');
				return f::FALSE;
			}
		} else {
			log::error('failed to send set profile query');
			log::error(pg_last_error());
			$_i['handle']->notice($_i['reply_to'], 'Query failed');
			return f::FALSE;
		}
		break;
}
