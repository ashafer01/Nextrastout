<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/log.php';

class uplink {
	public static $host = null;
	public static $port = null;

	protected static $socket;

	public static function connect() {
		$conf = config::get_instance();
		self::$host = $conf->uplink->host;
		self::$port = $conf->uplink->port;

		log::notice('>> Trying uplink to '.self::$host.':'.self::$port);
		self::$socket = fsockopen(self::$host, self::$port, $errno, $errstr);
		if (self::$socket === false) {
			log::error('Failed to connect to uplink server '.self::$host.':'.self::$port." - $errstr ($errno)");
			return false;
		}
		return true;
	}

	public static function close() {
		log::notice('Closing uplink socket');
		fclose(self::$socket);
	}

	public static function safe_feof(&$start = null) {
		$start = microtime(true);
		return feof(self::$socket);
	}

	public static function send($line) {
		$lline = color_formatting::escape($line);
		log::rawlog(log::INFO, "%G=> $lline%0");
		return fwrite(self::$socket, "$line\r\n");
	}

	public static function readline() {
		return trim(fgets(uplink::$socket));
	}
}
