<?php
require_once 'utils.php';

class log {
	const FATAL = 0;
	const ERROR = 1;
	const WARNING = 2;
	const NOTICE = 3;
	const INFO = 4;
	const DEBUG = 5;
	const TRACE = 6;

	public static $level = log::TRACE;

	public static function level_to_string($level) {
		switch ($level) {
			case log::FATAL: return 'FATAL';
			case log::ERROR: return 'ERROR';
			case log::WARNING: return 'WARNING';
			case log::NOTICE: return 'NOTICE';
			case log::INFO: return 'INFO';
			case log::DEBUG: return 'DEBUG';
			case log::TRACE: return 'TRACE';
			default: return 'UNKNOWN';
		}
	}

	protected static function _do_log($level, $message) {
		if ($level <= log::$level) {
			$ts = utimestamp();
			$lvl = log::level_to_string($level);

			$lines = explode("\n", $message);
			foreach ($lines as $line) {
				# log to stdout
				$line = color_formatting::ansi($line);
				echo "[$ts] $lvl: $line\n";
			}
		}
	}

	public static function fatal($m) {
		self::_do_log(log::FATAL, "%r$m%0");
	}

	public static function error($m) {
		self::_do_log(log::ERROR, "%r$m%0");
	}

	public static function warning($m) {
		self::_do_log(log::WARNING, "%y$m%0");
	}

	public static function notice($m) {
		self::_do_log(log::NOTICE, "%y$m%0");
	}

	public static function info($m) {
		self::_do_log(log::INFO, $m);
	}

	public static function debug($m) {
		self::_do_log(log::DEBUG, "%K$m%0");
	}

	public static function trace($m) {
		self::_do_log(log::TRACE, "%K$m%0");
	}
}
