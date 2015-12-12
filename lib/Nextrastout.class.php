<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/client.php';
require_once __DIR__ . '/uplink.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/db.php';

class Nextrastout {
	public static $bot_handle = null;
	public static $conf = null;
	public static $db = null;

	public static $hostname = null;
	public static $output_tz = null;
	public static $prepared_queries = array();
	public static $cmd_cooldown = array();
	public static $start_time = 0;

	public static function dbconnect() {
		if (self::$db !== null) {
			self::$db->close();
		}
		self::$db = new db();
	}

	public static function debug_mode() {
		log::notice('Debug mode');
		ini_set('xdebug.collect_params', 3);
		ini_set('xdebug.var_max_display_children', 1);
		ini_set('xdebug.var_max_display_data', -1);
		ini_set('xdebug.var_max_display_depth', 0);
	}

	public static function init() {
		$conf = config::get_instance();
		self::$conf = $conf;
		self::$start_time = time();

		if ($conf->debug) {
			self::debug_mode();
		}

		log::$level = log::string_to_level($conf->loglevel);
		self::dbconnect();

		self::$hostname = $conf->hostname;
		self::$output_tz = $conf->output_tz;

		$c = uplink::connect();
		if (!$c) {
			return 1;
		}

		self::$bot_handle = new client($conf->handles->{$conf->bot_handle});
		self::$bot_handle->init();

		return 0;
	}
}
