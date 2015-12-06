<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/Nextrastout.class.php';

class proc {
	private static $procs = array();

	public static $name = null;
	public static $func = null;

	const DEFAULT_STATUS = -10;
	const EXIT_OK = 0;
	const BROKEN_PIPE = 1;
	const EXIT_ERROR_LINE = 13;
	const EXIT_SIGINT = 42;

	const PROC_RERUN = 0;

	## process control

	public static function start($name, $func, $args = null) {
		if (strlen($name) > 10) {
			$name = substr($name, 0, 10);
		}
		if ($args === null) {
			$args = array();
		}
		if (self::get_proc_func($name) != null) {
			log::fatal("Proc name '$name' already taken");
			exit(107);
		}
		self::set_proc_func($name, $func);
		$pid = pcntl_fork();
		if ($pid === -1) {
			log::fatal('Fork failed');
			exit(1);
		} elseif ($pid === 0) {
			pcntl_signal(SIGINT, 'child_sigint');
			file_put_contents("pids/$name.pid", posix_getpid());
			setproctitle("Nextrastout [$name]");

			self::$name = $name;
			self::$procs = array();
			proc::$func = $func;

			while (true) {
				$_status = f::CALL($func, $args);
				if ($_status === proc::PROC_RERUN) {
					log::notice("proc function f::$func() returned 0, re-running it");
					continue;
				} elseif ($_status === f::RELOAD_FAIL) {
					log::fatal("failed to load proc function f::$func(), exiting");
					exit(241);
				} else {
					log::fatal("proc function f::$func() returned unhandled $_status, exiting");
					exit($_status);
				}
			} # end call loop
		} else {
			self::$procs[$name] = $func;
		}
	}

	public static function stop($name) {
		return self::_stopsignal($name, SIGTERM);
	}

	public static function kill($name) {
		return self::_stopsignal($name, SIGKILL);
	}

	# send a signal and delete the process
	private static function _stopsignal($name, $signal) {
		if (file_exists("pids/$name.pid")) {
			$child_pid = trim(file_get_contents("pids/$name.pid"));
			self::del_proc($name);
			if (posix_kill($child_pid, $signal)) {
				log::info("Signaled process $child_pid with $signal");
				return true;
			} else {
				log::error("Failed to signal process $child_pid");
				return false;
			}
		} else {
			self::del_proc($name);
			log::error("proc::_stopsignal($name, $signal) PID file does not exist");
			return false;
		}
	}

	public static function stop_all() {
		foreach (self::$procs as $name => $_) {
			if ($name != proc::$name) {
				proc::stop($name);
			}
		}
	}

	public static function kill_all() {
		foreach (self::$procs as $name => $_) {
			if ($name != proc::$name) {
				proc::kill($name);
			}
		}
	}

	public static function waitloop($name) {
		proc::wait_pidfile($name);
		$_status = proc::DEFAULT_STATUS;
		$responder_pid = proc::get_proc_pid($name);
		while (true) {
			$wait = pcntl_waitpid($responder_pid, $_status, WNOHANG);
			if (pcntl_wifexited($_status)) {
				$_status = pcntl_wexitstatus($_status);
			}

			pcntl_signal_dispatch();
			if ($_status === proc::DEFAULT_STATUS) {
				# no change
				usleep(10000);
				continue;
			} elseif ($_status === proc::EXIT_OK) {
				# clean exit
				close_all();
				exit(0);
			} elseif ($_status === proc::BROKEN_PIPE) {
				# loop has ended, probably broken pipe
				log::debug('Broken pipe, breaking wait loop');
				close_all();
				break;
			} elseif ($_status === proc::EXIT_ERROR_LINE) {
				# got ERROR line
				close_all();
				exit(proc::EXIT_ERROR_LINE);
			} elseif ($_status === proc::EXIT_SIGINT) {
				# got sigint in child process
				close_all();
				exit(proc::EXIT_SIGINT);
			} else {
				close_all();
				$intstatus = (int)$_status;
				if ($intstatus < 1 || $intstatus > 254) {
					echo "Unknown return '$_status' from main() is out of range for exit codes";
					exit(1);
				} else {
					exit($intstatus);
				}
			}
			log::debug('Reached end of wait loop');
		}
		log::debug('Returning from waitloop()');
	}

	public static function enable_reload($name=null) {
		if ($name == null) {
			$name = self::$name;
		}
		Nextrastout::$db->pg_upsert("UPDATE proc_reloads SET do_reload=TRUE WHERE proc='$name'",
			"INSERT INTO proc_reloads (proc, do_reload) VALUES ('$name', TRUE)",
			'enable proc reload');
	}

	public static function disable_reload($name=null) {
		if ($name == null) {
			$name = self::$name;
		}
		Nextrastout::$db->pg_upsert("UPDATE proc_reloads SET do_reload=FALSE WHERE proc='$name'",
			"INSERT INTO proc_reloads (proc, do_reload) VALUES ('$name', FALSE)",
			'disable proc reload');
	}

	public static function reload_needed($name=null) {
		if ($name == null) {
			$name = self::$name;
		}
		$q = Nextrastout::$db->pg_query("SELECT do_reload FROM proc_reloads WHERE proc='$name'",
			'check proc reload', false);
		if (($q === false) || (pg_num_rows($q) == 0)) {
			return false;
		} else {
			$qr = pg_fetch_assoc($q);
			return str_bool($qr['do_reload']);
		}
	}

	# delete metadata about a child process
	private static function del_proc($name) {
		if (array_key_exists($name, self::$procs)) {
			unset(self::$procs[$name]);
		}
		self::del_proc_func($name);
		if (file_exists("pids/$name.pid")) {
			log::debug("Deleting pids/$name.pid");
			unlink("pids/$name.pid");
		}
	}

	## PID stuff

	# wait for a pidfile to be created- used for syncronization
	public static function wait_pidfile($name) {
		if (array_key_exists($name, self::$procs)) {
			while(!file_exists("pids/$name.pid"));
		} else {
			throw new Exception("No proc named '$name'");
		}
	}

	# get the PID of a process by name
	public static function get_proc_pid($name) {
		if (array_key_exists($name, self::$procs)) {
			return (int)trim(file_get_contents("pids/$name.pid"));
		}
		return false;
	}

	## proc func db stuff

	public static function get_proc_func($name) {
		$name = dbescape($name);
		$q = Nextrastout::$db->pg_query("SELECT func FROM proc_funcs WHERE proc='$name'",
			'proc func lookup');
		$qr = db::fetch_assoc($q);
		if ($qr === false) {
			return null;
		} else {
			return $qr['func'];
		}
	}

	public static function set_proc_func($name, $func) {
		$name = dbescape($name);
		$func = dbescape($func);
		$i = Nextrastout::$db->pg_query("INSERT INTO proc_funcs (proc,func) VALUES ('$name','$func')",
			'set proc func');
		if ($i === false) {
			log::fatal('Set proc func failed');
			exit(107);
		}
		return true;
	}

	public static function del_proc_func($name) {
		$name = dbescape($name);
		$d = Nextrastout::$db->pg_query("DELETE FROM proc_funcs WHERE proc='$name'",
			'delete proc func');
		if ($d === false) {
			log::fatal('Failed to delete proc func');
			exit(107);
		}
		return true;
	}

	public static function flush_proc_funcs() {
		$q = Nextrastout::$db->pg_query('TRUNCATE proc_funcs',
			'flush proc funcs');
		if ($q === false) {
			log::fatal('Failed to flush proc funcs');
			exit(107);
		}
		return true;
	}
}

function child_sigint() {
	log::notice('Got sigint in child process');
	proc::kill_all();
	exit(proc::EXIT_SIGINT);
}
