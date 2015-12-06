<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/procs.php';

class QueryFailedException extends Exception { }

class db {
	protected $conf;
	protected $db;

	public function __construct() {
		log::info('Opening database connection');
		$this->conf = config::get_instance();
		$dbpw = get_password($this->conf->db->pwname);
		$proc = proc::$name;
		$this->db = pg_connect("host={$this->conf->db->host} dbname={$this->conf->db->name} user={$this->conf->db->user} password=$dbpw application_name=Nextrastout_$proc");
		if ($this->db === false) {
			log::fatal('Failed to connect to database, exiting');
			exit(17);
		}
	}

	public function get_conn() {
		return $this->db;
	}

	public function escape($str) {
		return pg_escape_string($this->db, $str);
	}

	public function pg_query($query, $ref = '[query]', $dolog=true) {
		if ($dolog) log::debug("$ref >>> $query");
		$q = pg_query($this->db, $query);
		if ($q === false) {
			log::error("$ref failed");
			log::error(pg_last_error());
		} else {
			if ($dolog) log::debug("$ref OK");
		}
		return $q;
	}

	public function pg_upsert($update_query, $insert_query, $ref = '[upsert]', $dolog=true) {
		$u = $this->pg_query($update_query, "$ref [update]", $dolog);
		if (pg_affected_rows($u) == 0) {
			if ($dolog) log::debug("No affected rows for $ref update, doing insert");
			$this->pg_query($insert_query, "$ref [insert]", $dolog);
		}
		return true;
	}

	public function pg_prepare($name, $query, $dolog=true) {
		if (!in_array($name, Nextrastout::$prepared_queries)) {
			if ($dolog) log::debug("Preparing '$name' >> $query");
			$p = pg_prepare($this->db, $name, $query);
			if ($p === false) {
				log::error("Failed to prepare '$name' query");
				log::error(pg_last_error());
			} else {
				Nextrastout::$prepared_queries[] = $name;
			}
			return $p;
		} else {
			if ($dolog) log::debug("Query '$name' already prepared");
		}
	}

	public function pg_execute($name, $params, $dolog = true) {
		if ($dolog) log::debug("Executing query '$name'");
		$e = pg_execute($this->db, $name, $params);
		if ($e === false) {
			log::error("Failed to execute '$name'");
			log::error(pg_last_error());
		}
		return $e;
	}

	public function close() {
		return pg_close($this->db);
	}

	public static function num_rows($q) {
		if ($q === false) {
			return 0;
		} else {
			return pg_num_rows($q);
		}
	}

	public static function fetch_assoc($q) {
		if ($q === false) {
			throw new QueryFailedException();
		} else {
			return pg_fetch_assoc($q);
		}
	}
}
