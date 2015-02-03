<?php
require_once 'ini_parser.php';

class config {
	private static $base = 'config/';

	## singleton stuff
	private static $instance = null;
	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new config();
		}
		return self::$instance;
	}

	## ini stuff
	private $conf = null;
	public function reload() {
		$parser = new IniParser(config::$base . 'ExtraServ.ini');
		$this->conf = $parser->parse();
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

	## List management
	private static $lists = array();

	private static function get_list($name) {
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
	private static function unload_list($name) {
		if (array_key_exists($name, self::$lists)) {
			unset(self::$lists[$name]);
		}
	}
	private static function append_list_unique($name, $value) {
		if (!in_array($value, self::$lists[$name])) {
			self::$lists[$name][] = $value;
			file_put_contents(self::$base . "$name.list", "$value\n", FILE_APPEND);
		}
	}

	### Channels
	public static function channels() {
		return self::get_list('channel');
	}
	public static function store_channel($channel) {
		self::append_list_unique('channel', $channel);
	}
}
