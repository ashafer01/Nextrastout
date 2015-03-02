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

class ES_NestedArrayObject extends ArrayObject {
	private $parent_key = null;
	private $parent = null;
	public function __construct($parent, $parent_key, $initial_data = null, $local_init = false) {
		$this->parent = $parent;
		$this->parent_key = $parent_key;
		if ($initial_data !== null) {
			if ($local_init) {
				log::debug('ES_NestedArrayObject Doing local init');
				foreach ($initial_data as $key => $val) {
					$this->localOffsetSet($key, $val);
				}
			} else {
				$ct = count($initial_data);
				$shit_started = false;
				if ($ct > 0) {
					$shit_started = f::start_shitstorm();
				}
				foreach ($initial_data as $key => $val) {
					$this->offsetSet($key, $val);
				}
				if ($shit_started) {
					f::stop_shitstorm();
				}
			}
		}
	}

	public function nest($key, $initial_data = null) {
		$this->bubbleSet(array($key), chr(7));
		$obj = new ES_NestedArrayObject($this, $key, $initial_data);
		parent::offsetSet($key, $obj);
		return $obj;
	}

	public function localNest($key, $initial_data = null) {
		$obj = new ES_NestedArrayObject($this, $key, $initial_data, true);
		parent::offsetSet($key, $obj);
		return $obj;
	}

	public function bubbleSet($key_chain, $newval) {
		array_unshift($key_chain, $this->parent_key);
		$this->parent->bubbleSet($key_chain, $newval);
	}

	public function offsetSet($index, $newval) {
		if ($index === null) {
			$bubble_index = chr(15);
		} else {
			$bubble_index = $index;
		}
		if (!is_array($newval)) {
			parent::offsetSet($index, $newval);
			$this->bubbleSet(array($bubble_index), $newval);
		} else {
			log::trace('Got array, doing nest');
			$this->nest($index, $newval);
		}
	}

	public function localOffsetSet($index, $newval) {
		if (!is_array($newval)) {
			parent::offsetSet($index, $newval);
		} else {
			$this->localNest($index, $newval);
		}
	}

	public function bubbleUnset($key_chain) {
		array_unshift($key_chain, $this->parent_key);
		$this->parent->bubbleUnset($key_chain);
	}

	public function offsetUnset($index) {
		parent::offsetUnset($index);
		$this->bubbleUnset(array($index));
	}

	public function localOffsetUnset($index) {
		parent::offsetUnset($index);
	}

	public function getArrayCopy() {
		$ret = array();
		foreach ($this as $key => $val) {
			if (is_a($val, 'ArrayObject')) {
				$val = $val->getArrayCopy();
			}
			$ret[$key] = $val;
		}
		return $ret;
	}
}

class ES_SyncedArrayObject extends ES_NestedArrayObject {
	private $msgtype_set;
	private $msgtype_unset;
	public function __construct($msgtype_set, $msgtype_unset) {
		$this->msgtype_set = $msgtype_set;
		$this->msgtype_unset = $msgtype_unset;
		self::$msgtype_set_map[$msgtype_set] = $this;
		self::$msgtype_unset_map[$msgtype_unset] = $this;
	}

	public function bubbleSet($key_chain, $newval) {
		if (is_a($newval, 'ArrayObject')) {
			$obj = $this;
			$lastkey = array_pop($key_chain);
			foreach ($key_chain as $key) {
				$obj = $obj[$key];
			}
			$obj->nest($lastkey, $newval);
		} else {
			proc::queue_sendall($this->msgtype_set, implode(':', $key_chain) . '::' . $newval);
		}
	}

	public function bubbleUnset($key_chain) {
		proc::queue_sendall($this->msgtype_unset, implode(':', $key_chain));
	}

	private static $msgtype_set_map = array();
	private static $msgtype_unset_map = array();

	public static function dispatchMessage($msgtype, $message) {
		if (isset(self::$msgtype_set_map[$msgtype])) {
			log::trace("Got set message >>> $message");
			$keychain_val = explode('::', $message, 2);
			$key_chain = explode(':', $keychain_val[0]);
			$val = $keychain_val[1];

			$lastkey = array_pop($key_chain);
			$obj = self::$msgtype_set_map[$msgtype];
			foreach ($key_chain as $key) {
				$obj = $obj[$key];
			}
			if ($lastkey === chr(15)) {
				log::trace('Doing append');
				$obj->localOffsetSet(null, $val);
			} elseif ($val === chr(7)) {
				if (!$obj->offsetExists($lastkey)) {
					log::trace("New array: $lastkey");
					$obj->localOffsetSet($lastkey, new ES_NestedArrayObject($obj, $lastkey));
				} else {
					log::trace('key for new array already exists');
				}
			} else {
				log::trace("Normal set: $lastkey");
				$obj->localOffsetSet($lastkey, $val);
			}
			return true;
		} elseif (isset(self::$msgtype_unset_map[$msgtype])) {
			log::trace("Got unset message >>> $message");
			$key_chain = explode(':', $message);

			$lastkey = array_pop($key_chain);
			$obj = self::$msgtype_unset_map[$msgtype];
			foreach ($key_chain as $key) {
				$obj = $obj[$key];
			}
			$obj->localOffsetUnset($lastkey);
			return true;
		} else {
			return false;
		}
	}
}

