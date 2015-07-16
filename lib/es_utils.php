<?php

### Utility functions depending on the ExtraServ class or other runtime resources

require_once 'log.php';
require_once 'procs.php';
require_once 'functions.php';

function smart_date_fmt($uts) {
	$tz = new DateTimeZone(ExtraServ::$output_tz);
	$dt = new DateTime();
	$dt->setTimestamp($uts);
	$dt->setTimezone($tz);
	$now = new DateTime();
	$now->setTimezone($tz);
	$diff = $now->diff($dt);
	$y = (int) $diff->format('%y');
	$m = (int) $diff->format('%m');
	$d = (int) $diff->format('%a');
	if ($y > 0)
		$fmt = 'l, M jS Y \a\t G:i T';
	elseif ($m > 0 || $d > 7)
		$fmt = 'l, M jS \a\t G:i T';
	elseif ($d > 1)
		$fmt = 'l \a\t G:i T';
	else {
		$my = clone $dt;
		$my->setTime(0, 0, 0);
		$mt = clone $now;
		$mt->setTime(0, 0, 0);
		$mdiff = $mt->diff($my);
		$mdiff = $mdiff->format('%R%a');
		if ($mdiff == -1)
			$fmt = '\Y\e\s\t\e\r\d\a\y \a\t G:i T';
		elseif ($mdiff == 0)
			$fmt = '\T\o\d\a\y \a\t G:i:s T';
		else
			$fmt = '\w\h\o \k\n\o\w\s';
	}
	return $dt->format($fmt);
}

function date_fmt($fmt, $uts) {
	$dt = new DateTime();
	$dt->setTimestamp($uts);
	$dt->setTimezone(new DateTimeZone(ExtraServ::$output_tz));
	return $dt->format($fmt);
}

function local_strtotime($time_str) {
	$tz = date_default_timezone_get();
	date_default_timezone_set(ExtraServ::$output_tz);
	$ret = strtotime($time_str);
	date_default_timezone_set($tz);
	return $ret;
}

function pg_is_prepared($stmt_name) {
	$q = pg_query(ExtraServ::$db, 'SELECT name FROM pg_prepared_statements');
	if ($q === false) {
		log::error('pg_is_prepared(): query failed');
		log::error(pg_last_error());
		return true;
	} else {
		log::debug('pg_is_prepared(): query ok');
		while ($row = pg_fetch_assoc($q)) {
			if ($row['name'] == $stmt_name) {
				log::debug("pg_is_prepared(): Statement $stmt_name is prepared");
				return true;
			}
		}
		log::debug("pg_is_prepared(): Statement $stmt_name is not prepared");
		return false;
	}
}

function dbescape($str) {
	return pg_escape_string(ExtraServ::$db, $str);
}

