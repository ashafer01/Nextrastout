<?php

### Utility functions depending on the ExtraServ class or other runtime resources

require_once 'log.php';
require_once 'procs.php';

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

function pg_table_pkeys($table) {
	static $pkeys = array();
	if (array_key_exists($table, $pkeys)) {
		return $pkeys[$table];
	} else {
		$query = "SELECT split_part(rtrim(indexdef, ')'), '(', 2) AS pkeys FROM pg_indexes WHERE right(indexname, 5)='_pkey' AND tablename=$1";
		$q = pg_query_params(ExtraServ::$db, $query, array($table));
		if ($q === false) {
			log::error('pg_table_pkeys(): query failed');
			log::error(pg_last_error());
			return false;
		} elseif (pg_num_rows($q) == 0) {
			log::debug('pg_table_pkeys(): No primary keys for table');
			$pkeys[$table] = array();
		} else {
			log::debug('pg_table_pkeys(): query ok');
			$qr = pg_fetch_assoc($q);
			$pkeys[$table] = explode(', ', $qr['pkeys']);
		}
		return $pkeys[$table];
	}
}

function pg_insert_on_duplicate_key_update($table, $insert_cols, $insert_values, $updates) {
	$multi_values = false;
	$col_count = count($insert_cols);
	if (count($insert_values) != $col_count) {
		log::error('pg_insert_on_duplicate_key_update(): $insert_values must have the same number of elements as $insert_cols');
		return false;
	}

	if (count($updates) < 1) {
		log::error('pg_insert_on_duplicate_key_update(): At least one update must be supplied');
		return false;
	}

	$pkeys = pg_table_pkeys($table);
	foreach ($pkeys as $col) {
		if (!in_array($col, $insert_cols)) {
			log::error('pg_insert_on_duplicate_key_update(): All PRIMARY KEY columns must be in $insert_cols');
			return false;
		}
	}

	$vals = '(' . implode(', ', array_map('sqlify', $insert_values)) . ')';
	$query = "INSERT INTO $table ($cols) VALUES $vals";
	log::debug("pg_insert_on_duplicate_key_update(): insert query >>> $query");
	if (pg_send_query(ExtraServ::$db, $query)) {
		$q = pg_get_result(ExtraServ::$db);
		$err = pg_result_error_field($q, PGSQL_DIAG_SQLSTATE);
		if ($err === null || $err == '00000') {
			$n = pg_affected_rows($q);
			log::debug("pg_insert_on_duplicate_key_update(): insert OK (affected rows = $n)");
			return true;
		} elseif ($err == '23505') {
			log::debug('pg_insert_on_duplicate_key_update(): duplicate keys, doing update');
			$where = array();
			foreach ($pkeys as $pkey) {
				$i = 0;
				foreach ($insert_cols as $col) {
					if ($col == $pkey)
						break;
					$i++;
				}
				$val = sqlify($insert_values[$i]);
				$where[] = "($pkey = $val)";
			}
			$where = implode(' AND ', $where);

			$set = array();
			foreach ($updates as $key => $val) {
				$val = sqlify($val);
				$set[] = "$key = $val";
			}
			$set = implode(', ', $set);

			$query = "UPDATE $table SET $set WHERE $where";
			log::debug("pg_insert_on_duplicate_key_update(): update query >>> $query");
			$q = pg_query(ExtraServ::$db, $query);
			if ($q === false) {
				log::error('pg_insert_on_duplicate_key_update(): update query failed');
				log::error(pg_last_error());
				return false;
			} else {
				$n = pg_affected_rows($q);
				log::debug("pg_insert_on_duplicate_key_update(): update OK (affected rows = $n)");
				return true;
			}
		} else {
			log::error("pg_insert_on_duplicate_key_update(): insert query failed ($err)");
			return false;
		}
	} else {
		log::error('pg_insert_on_duplicate_key_update(): failed to send insert query');
		return false;
	}
}

class ES_NestedArrayObject extends ArrayObject {
	private $parent_key = null;
	private $parent = null;
	public function __construct($parent, $parent_key) {
		$this->parent = $parent;
		$this->parent_key = $parent_key;
	}

	public function bubbleSet($key_chain, $newval) {
		array_unshift($this->parent_key, $key_chain);
		$this->parent->bubbleSet($key_chain, $newval);
	}

	public function offsetSet($index, $newval) {
		parent::offsetSet($index, $newval);
		if ($index !== null) {
			$this->bubbleSet(array($index), $newval);
		} else {
			$this->bubbleSet(array(chr(15)), $newval);
		}
	}

	public function bubbleUnset($key_chain) {
		array_unshift($this->parent_key, $key_chain);
		$this->parent->bubbleUnset($key_chain, $newval);
	}

	public function offsetUnset($index) {
		parent::offsetUnset($index);
		$this->bubbleUnset(array($index));
	}

	public function nest($key) {
		$obj = new ES_NestedArrayObject($this, $key);
		$this->offsetSet($key, $obj);
		$this->bubbleSet(array($key), chr(7));
		return $obj;
	}
}

class ES_MasterArrayObject extends ES_NestedArrayObject {
	private $msgtype_set;
	private $msgtype_unset;
	public function __construct($msgtype_set, $msgtype_unset) {
		$this->msgtype_set = $msgtype_set;
		$this->msgtype_unset = $msgtype_unset;
	}

	public function bubbleSet($key_chain, $newval) {
		proc::queue_sendall($this->msgtype_set, implode(':', $key_chain) . '::' . $newval);
	}

	public function bubbleUnset($key_chain) {
		proc::queue_sendall($this->msgtype_unset, implode(':', $key_chain));
	}
}

class ES_SlaveArrayObject extends ArrayObject {
	private static $msgtype_set_map = array();
	private static $msgtype_unset_map = array();

	public static function dispatchMessage($msgtype, $message) {
		if (isset(self::$msgtype_set_map[$msgtype])) {
			$keychain_val = explode('::', $message, 2);
			$key_chain = explode(':', $keychain_val[0]);
			$val = $keychain_val[1];

			$lastkey = array_pop($key_chain);
			$obj = self::$msgtype_set_map[$msgtype];
			foreach ($key_chain as $key) {
				$obj = $obj[$key];
			}
			if ($lastkey === chr(15)) {
				$obj->offsetSet(null, $val);;
			} elseif ($val === chr(7)) {
				$obj->offsetSet($lastkey, new ArrayObject());
			} else {
				$obj->offsetSet($lastkey, $val);
			}
		} elseif (isset($self::$msgtype_unset_map[$msgtype])) {
			$key_chain = explode(':', $message);

			$lastkey = array_pop($key_chain);
			$obj = self::$msgtype_unset_map[$mgtype];
			foreach ($key_chain as $key) {
				$obj = $obj[$key];
			}
			$obj->offsetUnset($lastkey);
		}
	}

	public static function isChangeMessage($msgtype) {
		return (isset(self::$msgtype_set_map[$msgtype]) || isset(self::$msgtype_unset_map[$msgtype]));
	}

	private $msgtype_set;
	private $msgtype_unset;
	public function __construct($msgtype_set, $msgtype_unset, $initial_data = null) {
		if ($initial_data !== null) {
			parent::__construct($initial_data);
		}
		$this->msgtype_set = $msgtype_set;
		$this->msgtype_unset = $msgtype_unset;
		self::$msgtype_set_map[$msgtype_set] = $this;
		self::$msgtype_unset_map[$msgtype_unset] = $this;
	}
}

