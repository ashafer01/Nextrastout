<?php

class config {
	private static $base = 'config/';

	public static function RELOAD_ALL() {
		reset(self::$configs);
		while ($key = key(self::$configs)) {
			self::$configs[$key] = array();
			next(self::$configs);
		}
	}

	private static $configs = array(
		'lists' => array()
	);

	## List management

	private static function get_list($name) {
		if (!array_key_exists($name, self::$configs['lists'])) {
			self::$configs['lists'][$name] = array_map('trim', file(self::$base . "$name.list"));
		}
		return self::$configs['lists'][$name];
	}
	private static function unload_list($name) {
		if (array_key_exists($name, self::$configs['lists'])) {
			unset(self::$configs['lists'][$name]);
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
