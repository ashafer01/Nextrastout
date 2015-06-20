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

	protected $items;
	protected $item_class;

	protected function __construct() {
		$my_class = get_class($this);
		$this->item_class = substr($my_class, 0, -11); // strip off "_collection"

		$mckey = self::$memcache_prefix . "_$my_class";
		$this->items = new ES_MemcachedArrayObject($mckey);
	}

	public function add($obj) {
		if (!$this->is_item_class($obj)) {
			throw new InvalidArgumentException("Items must be {$this->item_class} and a subclass of irc_info_item");
		}
		$this->items[$obj->name()] = $obj;
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
		return (is_a($obj, $this->item_class) && is_a($obj, 'irc_info_item'));
	}
}

abstract class Memcache_irc_info_item extends irc_info_item {
	protected $__memcache_key;
	public function __construct($name) {
		$class = get_class($this);
		$this->__memcache_key = Memcache_irc_info_collection::$memcache_prefix . "_{$class}_{$name}";
		$this->__name = "$name";
		$this->__props = new ES_MemcachedArrayObject($this->__memcache_key);
	}

	public function set_props($props) {
		if (!is_array($props) && !is_a($props, 'ArrayObject')) {
			throw new InvalidArgumentException('Must be array or ArrayObject');
		}
		if (is_a($props, 'ES_MemcachedArrayObject')) {
			$this->__props = $props;
		} else {
			$this->__props->fill($props);
			$this->__props->writeNotify();
		}
	}
}

abstract class irc_info_item {
	protected $__props;
	protected $__name;

	public function __construct($name) {
		$this->__name = "$name";
		$this->__props = array();
	}

	public function name() {
		return $this->__name;
	}

	public function __toString() {
		return $this->__name;
	}

	public function __get($key) {
		if (array_key_exists($key, $this->__props)) {
			return $this->__props[$key];
		} else {
			return null;
		}
	}

	public function __set($key, $val) {
		$this->__props[$key] = $val;
	}

	public function __isset($key) {
		return isset($this->__props[$key]);
	}

	public function __unset($key) {
		unset($this->__props[$key]);
	}

	public function set_props($props) {
		if (!is_array($props) && !is_a($props, 'ArrayObject')) {
			throw new InvalidArgumentException('Must be array or ArrayObject');
		}
		$this->__props = $props;
	}
}

class irc_nick_collection extends Memcache_irc_info_collection {
	public function quit($nick) {
	}

	public function rename($oldnick, $newnick) {
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

class irc_nick extends Memcache_irc_info_item {
	public function __construct($nick) {
		parent::__construct($nick);
		$this->usermodes = array();
		$this->channels = array();
	}

	public function has_mode($modechar) {
		return in_array($modechar, $this->usermodes);
	}

	public function add_mode($modechar) {
		$this->usermodes[] = $modechar;
	}

	public function remove_mode($modechar) {
		$this->usermodes = array_diff($this->usermodes, array($modechar));
	}

	public function join($channel) {
		if (!in_array($channel, $this->channels)) {
			
		}
	}

	public function part($channel) {
	}

	public function in_channel($channel) {
	}

	public function add_to_modelist($channel, $modechar) {
	}

	public function remove_from_modelist($channel, $modechar) {
	}
}

class irc_channel extends Memcache_irc_info_item {
	public function has_mode($modechar) {
	}
}
