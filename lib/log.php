<?php
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/procs.php';
require_once __DIR__ . '/functions.php';

class log {
	const FATAL = 0;
	const ERROR = 1;
	const WARNING = 2;
	const NOTICE = 3;
	const INFO = 4;
	const DEBUG = 5;
	const TRACE = 6;

	public static $level = log::DEBUG;

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

	public static function string_to_level($level) {
		$level = strtoupper($level);
		switch ($level) {
			case 'FATAL': return log::FATAL;
			case 'ERROR': return log::ERROR;
			case 'WARNING': return log::WARNING;
			case 'NOTICE': return log::NOTICE;
			case 'INFO': return log::INFO;
			case 'DEBUG': return log::DEBUG;
			case 'TRACE': return log::TRACE;
			default: return 1000;
		}
	}

	public static $static = null;
	private static $logger_func = null;
	public static function set_logger($function) {
		self::$logger_func = $function;
	}

	protected static function _do_log($level, $message) {
		if ($level <= log::$level) {
			if (self::$logger_func != null) {
				$lines = explode("\n", $message);
				foreach ($lines as $line) {
					call_user_func_array(self::$logger_func, array($level, $line));
				}
			} else {
				$ts = utimestamp();
				$lvl = log::level_to_string($level);
				$procname = sprintf('%10s', proc::$name);

				$message = str_replace(array(chr(7), chr(15)), array('array()', '[]'), $message);

				$lines = explode("\n", $message);
				foreach ($lines as $line) {
					# log to stdout
					$line = color_formatting::ansi($line);
					echo "[$ts] [$procname] $lvl: $line\n";
				}
			}
		}
	}

	public static function rawlog($level, $message) {
		self::_do_log($level, $message);
	}

	private static function color_lines($format, $message) {
		return implode("\n", array_map(function($line) use ($format) {
			return "$format$line%0";
		}, explode("\n", $message)));
	}

	public static function fatal($m) {
		$m = color_formatting::escape($m);
		self::_do_log(log::FATAL, self::color_lines('%r', $m));
	}

	public static function error($m) {
		$m = color_formatting::escape($m);
		self::_do_log(log::ERROR, self::color_lines('%r', $m));
	}

	public static function warning($m) {
		$m = color_formatting::escape($m);
		self::_do_log(log::WARNING, self::color_lines('%y', $m));
	}

	public static function notice($m) {
		$m = color_formatting::escape($m);
		self::_do_log(log::NOTICE, self::color_lines('%y', $m));
	}

	public static function info($m) {
		$m = color_formatting::escape($m);
		self::_do_log(log::INFO, $m);
	}

	public static function debug($m) {
		$m = color_formatting::escape($m);
		self::_do_log(log::DEBUG, "%K$m%0");
	}

	public static function trace($m) {
		$m = color_formatting::escape($m);
		self::_do_log(log::TRACE, "%K$m%0");
	}
}
