<?php
require_once __DIR__ . '/ini_parser.php';
require_once __DIR__ . '/Nextrastout.class.php';

class config {
	private static $base;
	private static $last_load;

	## singleton stuff
	private static $instance = null;
	public static function get_instance() {
		self::$base  = __DIR__ . '/../config/';
		if (self::$instance === null) {
			self::$instance = new config();
		}
		return self::$instance;
	}

	## ini stuff
	private $conf = null;
	public function reload() {
		$main = new IniParser(config::$base . 'Nextrastout.ini');
		$private = new IniParser(config::$base . 'private.ini');
		$this->conf = new ArrayObject(array_merge($main->parse()->getArrayCopy(), $private->parse()->getArrayCopy()), ArrayObject::ARRAY_AS_PROPS);
		self::$last_load = time();
	}

	public static function set_reload() {
		$ts = time();
		Nextrastout::$db->pg_query("UPDATE conf_reload SET reload_uts=$ts", 'set conf reload');
	}

	public static function reload_needed() {
		$q = Nextrastout::$db->pg_query("SELECT * FROM conf_reload", 'check conf reload', false);
		if ($q === false) {
			return false;
		} else {
			$qr = pg_fetch_assoc($q);
			if (self::$last_load < $qr['reload_uts']) {
				return true;
			} else {
				return false;
			}
		}
	}

	private function __construct() {
		$this->reload();
	}

	public function __get($key) {
		if (isset($this->conf->$key)) {
			return $this->conf->$key;
		}
		return new stdClass;
	}

	public function offsetExists($key) {
		return isset($this->conf->$key);
	}

	public static function reload_all() {
		config::get_instance()->reload();
		self::$lists = array();
		self::$json_objects = array();
	}

	## List management
	private static $lists = array();

	public static function get_list($name) {
		if (!array_key_exists($name, self::$lists)) {
			$lines = array_map('trim', file(self::$base . "$name.list"));
			$list = array();
			foreach ($lines as $line) {
				if ($line == null)
					continue;
				if (substr($line, 0, 1) == ';')
					continue;
				$list[] = $line;
			}
			self::$lists[$name] = $list;
		}
		return self::$lists[$name];
	}
	public static function unload_list($name) {
		if (array_key_exists($name, self::$lists)) {
			unset(self::$lists[$name]);
		}
	}
	public static function list_add_unique($name, $value) {
		if (!in_array($value, self::$lists[$name])) {
			self::$lists[$name][] = $value;
			file_put_contents(self::$base . "$name.list", "$value\n", FILE_APPEND);
		}
	}
	public static function list_remove($name, $value) {
		$rem_keys = array_keys(self::$lists[$name], $value);
		if (count($rem_keys) > 0) {
			foreach ($rem_keys as $key) {
				unset(self::$lists[$name][$key]);
			}
			file_put_contents(self::$base . "$name.list", implode("\n", self::$lists[$name])."\n");
		}
	}

	## misc read-only file types
	public static $json_objects = array();
	public static function get_json($name) {
		if (!array_key_exists($name, self::$json_objects)) {
			self::$json_objects[$name] = json_decode(file_get_contents(self::$base . "$name.json"));
		}
		return self::$json_objects[$name];
	}

	### Channels
	public static function channels() {
		return self::get_list('channel');
	}
	public static function store_channel($channel) {
		self::list_add_unique('channel', $channel);
	}
}
