<?php

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/es_utils.php';

abstract class Memcache_irc_info_collection {
	protected static $instances = array();
	public static function get_instance() {
		$class = get_called_class();
		if (!array_key_exists($class, self::$instances)) {
			self::$instances[$class] = new $class();
		}
		return self::$instances[$class];
	}

	public static $memcache_prefix = 'ExtraServ';

	protected static $memcache_keys = array();

	protected $items;
	protected $item_class;

	protected function __construct() {
		$my_class = get_class($this);
		$this->item_class = substr($my_class, 0, -11); // strip off "_collection"

		$mckey = self::$memcache_prefix . '_' . $my_class;
		self::$memcache_keys[] = $mckey;

		$this->items = new ES_MemcachedArrayObject($mckey);
	}

	public function add($key, $obj) {
		if (!$this->is_item_class($obj)) {
			throw new InvalidArgumentException("Items must be {$this->item_class}");
		}
		$this->items[$key] = $obj;
	}

	public function get($key) {
		return $this->items[$key];
	}

	public function delete($key) {
		unset($this->items[$key]);
	}

	public function exists($key) {
		return array_key_exists($key, $this->items);
	}

	public function get_copy() {
		return $this->items;
	}

	protected function is_item_class($obj) {
		return is_a($obj, $this->item_class);
	}
}

abstract class irc_info_item {
	protected $props = array();

	public function __get($key) {
		if (array_key_exists($key, $this->props)) {
			return $this->props[$key];
		} else {
			return null;
		}
	}

	public function __set($key, $val) {
		$this->props[$key] = $val;
	}

	public function __isset($key) {
		return isset($this->props[$key]);
	}

	public function __unset($key) {
		unset($this->props[$key]);
	}

	public function set_props($props) {
		$this->props = $props;
	}
}

class irc_nick_collection extends Memcache_irc_info_collection {
	public function quit($nick) {
	}

	public function get_nick_by_user($user) {
		foreach ($this->items as $nick => $nick_obj) {
			if ($nick_obj->user == $user) {
				return $nick;
			}
		}
		return false;
	}
}

class irc_nick extends irc_info_item {
	protected $usermodes = array();

	public function has_mode($modechar) {
		return in_array($modechar, $this->usermodes);
	}
}

