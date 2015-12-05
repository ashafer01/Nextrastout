<?php

### Utility functions depending on the Nextrastout class or other runtime resources

require_once 'log.php';
require_once 'procs.php';
require_once 'functions.php';

function smart_date_fmt($uts) {
	$tz = new DateTimeZone(Nextrastout::$output_tz);
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
	$dt->setTimezone(new DateTimeZone(Nextrastout::$output_tz));
	return $dt->format($fmt);
}

function local_strtotime($time_str) {
	$tz = date_default_timezone_get();
	date_default_timezone_set(Nextrastout::$output_tz);
	$ret = strtotime($time_str);
	date_default_timezone_set($tz);
	return $ret;
}

function tz_hour_offset($tz = null) {
	if ($tz == null) {
		$tz = Nextrastout::$output_tz;
	}
	$dt = new DateTime('now', new DateTimeZone($tz));
	$tzo = explode(':', $dt->format('P'));
	return (int) $tzo[0];
}

function pg_is_prepared($stmt_name) {
	$q = pg_query(Nextrastout::$db, 'SELECT name FROM pg_prepared_statements');
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
	return pg_escape_string(Nextrastout::$db, $str);
}

class QueryFailedException extends Exception { }

function es_pg_query($query, $ref = '[query]') {
	log::debug("$ref >>> $query");
	$q = pg_query(Nextrastout::$db, $query);
	if ($q === false) {
		log::error("$ref failed");
		log::error(pg_last_error());
		throw new QueryFailedException("$ref failed");
	} else {
		log::debug("$ref OK");
		return $q;
	}
}

function es_pg_upsert($update_query, $insert_query, $ref = '[query]') {
	log::debug("$ref [update] >>> $update_query");
	$u = es_pg_query($update_query, "$ref [update]");
	if (pg_affected_rows($u) == 0) {
		log::debug("No affected rows for $ref update, doing insert");
		es_pg_query($insert_query, "$ref [insert]");
	}
	return true;
}
