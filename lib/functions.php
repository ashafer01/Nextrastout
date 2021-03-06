<?php
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/Nextrastout.class.php';

define('FUNC_DIR', __DIR__ . '/../functions/');

class f {
	private static $reload = array();
	private static $functions = array();
	private static $aliases = array();

	const TRUE = '___true___';
	const FALSE = '___false___';
	const RELOAD_FAIL = '___reload_failure___';

	public static function LISTALL() {
		return array_merge(array_keys(self::$functions), array_keys(self::$aliases));
	}

	public static function EXISTS($func) {
		log::trace('f::EXISTS()');
		$func_file = FUNC_DIR."$func.php";
		$a = array_key_exists($func, self::$functions);
		$b = array_key_exists($func, self::$aliases);
		$c = is_readable($func_file);
		return ($a || $b || $c);
	}

	public static function ALIAS_INIT() {
		self::$aliases = array();
		$conf = config::get_instance();
		foreach ($conf->alias as $alias => $real) {
			f::ALIAS($alias, $real);
		}
	}

	public static function ALIAS($alias, $real) {
		if (f::EXISTS($real)) {
			self::$aliases[$alias] = $real;
		} else {
			log::warning("Ignoring function alias $alias => $real because $real does not exist");
		}
	}

	public static function RESOLVE_ALIAS($alias) {
		if (array_key_exists($alias, self::$aliases)) {
			return self::$aliases[$alias];
		} else {
			log::warning("Not an alias: RESOLVE_ALIAS($alias)");
			return null;
		}
	}

	public static function IS_ALIAS($alias) {
		return array_key_exists($alias, self::$aliases);
	}

	private static function _reload($func) {
		log::trace('f::_reload()');
		if (in_array($func, get_class_methods('f'))) {
			log::fatal("'$func' is a class method of f");
			throw new Exception("Function name '$func' is reserved");
		}
		if (array_key_exists($func, self::$aliases)) {
			$orig = $func;
			$func = self::$aliases[$func];
			log::debug("Mapping alias function '$orig' to '$func' for reload");
		}
		$func_file = FUNC_DIR."$func.php";
		log::debug("Looking for code in $func_file");
		if (file_exists($func_file)) { # not using EXISTS() so that we get more specific errors
			if (is_readable($func_file)) {
				# Check syntax of file
				$s_func_file = escapeshellarg($func_file);
				$ret = -1;
				ob_start();
				system("/usr/bin/php -l $s_func_file", $ret);
				$out = trim(ob_get_clean());
				if ($ret == 0) {
					log::notice($out);
					log::trace("Trying to reload function: $func()");
					self::$functions[$func] = null;
					$func_code = file_get_contents($func_file);
					if ($func_code !== false) {
						log::notice("Reloaded function: $func()");
						if (self::$functions[$func] == $func_code) {
							log::notice("No code change for $func()");
						}
						self::$functions[$func] = $func_code;
						self::$reload[$func] = time();
						return true;
					} else {
						log::fatal("Error while reading $func_file");
						throw new Exception("Failed to read function file $func_file");
					}
				} else {
					log::error("SYNTAX ERROR detected before reload of $func_file");
					log::error($out);
					return false;
				}
			} else {
				log::fatal("$func_file is not readable");
				throw new Exception("Function file $func_file is not readable");
			}
		} else {
			log::fatal("$func_file does not exist");
			throw new Exception("Function file $func_file does not exist");
		}
	}

	private static function _do_call($_FUNC_NAME, $_ARGV = null) {
		log::trace('f::_do_call()');
		if ($_ARGV === null) {
			$_ARGV = array();
		}
		$_CALLED_AS = $_FUNC_NAME;
		if (array_key_exists($_FUNC_NAME, self::$aliases)) {
			$_FUNC_NAME = self::$aliases[$_CALLED_AS];
			log::debug("Mapping alias function '$_CALLED_AS' to '$_FUNC_NAME' for call");
		}
		if (self::_needs_reload($_FUNC_NAME)) {
			log::info('Need to reload function');
			$___reload_ret = self::_reload($_FUNC_NAME);
			if ($___reload_ret === false) {
				log::error("Failed to reload function $_FUNC_NAME");
				return self::RELOAD_FAIL;
			}
		}
		if (array_key_exists($_FUNC_NAME, self::$functions)) {
			$___eval_ret = eval(self::$functions[$_FUNC_NAME]);
			if ($___eval_ret === false) {
				log::fatal("eval returned strict boolean false");
				throw new Exception("Parse error in eval for $_FUNC_NAME() (boolean false returned from eval())");
			} elseif ($___eval_ret === f::TRUE) {
				log::trace("Returning boolean true for $_FUNC_NAME()");
				return true;
			} elseif ($___eval_ret === f::FALSE) {
				log::trace("Returning boolean false for $_FUNC_NAME()");
				return false;
			} else {
				log::trace("Returning original return for $_FUNC_NAME()");
				return $___eval_ret;
			}
		} else {
			log::fatal("No key for function $_FUNC_NAME");
			throw new Exception("Function $_FUNC_NAME code not loaded");
		}
	}

	public static function __callStatic($func, $args) {
		return self::_do_call($func, $args);
	}

	public static function CALL($func, $args) {
		return self::_do_call($func, $args);
	}

	public static function RELOAD($func) {
		if (self::IS_ALIAS($func)) {
			$func = self::RESOLVE_ALIAS($func);
		}
		return self::_set_reload($func);
	}

	private static function _needs_reload($func) {
		$func = dbescape($func);
		if (!array_key_exists($func, self::$functions)) {
			return true;
		}
		if (!array_key_exists($func, self::$reload)) {
			return true;
		}
		$q = Nextrastout::$db->pg_query("SELECT reload_uts FROM func_reloads WHERE function='$func'",
			'check reload', false);
		if ($q === false) {
			return false;
		} elseif (pg_num_rows($q) == 0) {
			return false;
		} else {
			$qr = pg_fetch_assoc($q);
			if (self::$reload[$func] < $qr['reload_uts']) {
				# function is out of date
				return true;
			} else {
				return false;
			}
		}
	}

	private static function _set_reload($func) {
		log::info("Marking $func() for reloading");
		$func = dbescape($func);
		$ts = time();
		return Nextrastout::$db->pg_upsert("UPDATE func_reloads SET reload_uts=$ts WHERE function='$func'",
			"INSERT INTO func_reloads (function, reload_uts) VALUES ('$func', $ts)",
			'set reload', false);
	}

	public static function RELOAD_ALL() {
		log::info("Doing f::RELOAD_ALL");
		foreach (self::$reload as $func => $_) {
			if (!in_array($func, array('main', 'timer', 'nextrastout'))) {
				self::RELOAD($func);
			} else {
				log::debug("Skipping $func() for RELOAD_ALL");
			}
		}
	}
}
