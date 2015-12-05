<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/uplink.php';

class Nextrastout {
	public static $bot_handle = null;
	public static $conf = null;
	public static $db = null;

	public static $hostname = null;
	public static $output_tz = null;
	public static $prepared_queries = array();
	public static $cmd_cooldown = array();

	public static function dbconnect() {
		log::info('Opening database connection');
		$conf = config::get_instance();
		$dbpw = get_password($conf->db->pwname);
		$proc = proc::$name;
		self::$db = pg_connect("host={$conf->db->host} dbname={$conf->db->name} user={$conf->db->user} password=$dbpw application_name=Nextrastout_$proc");
		if (self::$db === false) {
			log::fatal('Failed to connect to database, exiting');
			exit(17);
		}
	}

	public static function init() {
		$conf = config::get_instance();
		self::$conf = $conf;

		if ($conf->debug) {
			log::notice('Debug mode');
			ini_set('xdebug.collect_params', 3);
			ini_set('xdebug.var_max_display_children', 1);
			ini_set('xdebug.var_max_display_data', -1);
			ini_set('xdebug.var_max_display_depth', 0);
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
