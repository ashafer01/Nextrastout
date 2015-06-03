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
	$d = (int) $diff->format('%d');
	if ($y > 0)
		$fmt = 'l, M jS Y \a\t G:i T';
	elseif ($m > 0 || $d > 7)
		$fmt = 'l, M jS \a\t G:i T';
	elseif ($d > 2)
		$fmt = 'l \a\t G:i T';
	elseif ($d == 1)
		$fmt = '\Y\e\s\t\e\r\d\a\y \a\t G:i T';
	else
		$fmt = '\T\o\d\a\y \a\t G:i:s T';
	return $dt->format($fmt);
}

function date_fmt($fmt, $uts) {
	$dt = new DateTime();
	$dt->setTimestamp($uts);
	$dt->setTimezone(new DateTimeZone(ExtraServ::$output_tz));
	return $dt->format($fmt);
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

class ES_MemcachedArrayObject extends NotifiedLinkedArrayObject {
	private $memcache_key;
	public function __construct($memcache_key, $data = null) {
		ExtraServ::add_memcache_key($mckey);
		$this->memcache_key = $memcache_key;
		$this->fill($data);
	}

	public function offsetSet($key, $value) {
		if (is_array($value)) {
			$value = new BubbleNotifyLinkedArrayObject($this, $value);
		}
		parent::offsetSet($key, $value);
	}

	public function writeNotify() {
		proc::memcache()->set($this->memcache_key, $this);
	}

	public function readNotify() {
		$this->fill(proc::memcache()->get($this->memcache_key));
	}
}

