<?php

require_once __DIR__ . '/lib/utils.php';
require_once __DIR__ . '/lib/log.php';
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/lib/client.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/procs.php';
require_once __DIR__ . '/lib/es_utils.php';

set_error_handler('error_logger', E_ALL);

function is_admin($nick) {
	return in_array($nick, Nextrastout::$conf->admins);
}

date_default_timezone_set('UTC');
pcntl_signal(SIGINT, 'parent_sigint');

setproctitle('Nextrastout [parent]');
proc::$name = 'parent';

$_status = -10;
while (true) {
	log::debug('Started init loop');
	$_status = Nextrastout::init();
	if ($_status === 1) {
		log::error("Failed to connect to uplink server, sleeping 30 seconds and retrying");
		sleep(30);
		continue;
	}
	$_status = -10;

	proc::start('responder', 'nextrastout');

	proc::wait_pidfile('responder');

	$responder_pid = proc::get_proc_pid('responder');
	while (true) {
		$wait = pcntl_waitpid($responder_pid, $_status, WNOHANG);
		if (pcntl_wifexited($_status)) {
			$_status = pcntl_wexitstatus($_status);
		}

		pcntl_signal_dispatch();
		if ($_status === -10) {
			# no change
			usleep(10000);
			continue;
		} elseif ($_status === 0) {
			# clean exit
			close_all();
			exit(0);
		} elseif ($_status === 1) {
			# loop has ended, probably broken pipe
			log::debug('Broken pipe, breaking wait loop');
			close_all();
			break;
		} elseif ($_status === 2) {
			# stopping gracefully
			foreach (Nextrastout::$handles as $pc) {
				$pc->quit('Stopping');
			}
			log::debug('Stopping');
			break 2;
		} elseif ($_status === 3) {
			# manual reconnect
			foreach (Nextrastout::$handles as $pc) {
				$pc->quit('Reconnecting');
			}
			close_all();
			continue 2;
		} elseif ($_status === 13) {
			# got ERROR line
			close_all();
			exit(13);
		} elseif ($_status === 42) {
			# got sigint in child process
			close_all();
			exit(42);
		} else {
			log::fatal("Unknown return '$_status' from main()");
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
	log::debug('Reached end of init loop');
}

log::debug('Reached end of file');
close_all();

function close_all() {
	uplink::close();
	pg_close(Nextrastout::$db);
	log::debug('stopping children');
	proc::stop_all();
}

function parent_sigint() {
	log::fatal('Got sigint in parent, killing children');
	foreach (Nextrastout::$handles as $pc) {
		$pc->quit('Got SIGINT');
	}
	close_all();
	exit(42);
}

class Nextrastout {
	const HYBRID_TOKEN = "0ES";
	public static $hostname = null;
	public static $info = null;
	public static $output_tz = null;
	public static $conf = null;

	public static $db = null;
	public static $handles = null;
	public static $bot_handle = null;

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
		self::$info = $conf->info;
		self::$output_tz = $conf->output_tz;

		$c = uplink::connect();
		if (!$c) {
			return 1;
		}

		if (self::$handles === null) {
			self::$handles = array();
			foreach ($conf->handles as $key => $params) {
				self::$handles[$key] = new client($params);
			}
			self::$bot_handle = self::$handles[$conf->bot_handle];
		}

		# init my handles
		foreach (self::$handles as $handle) {
			$handle->init();
		}

		self::$bot_handle->update_conf_channels();

		return 0;
	}

	# send a command for a nick
	public static function usend($nick, $command) {
		return uplink::send($command);
	}

	public static function send($command) {
		return uplink::send($command);
	}

	public static function sjoin($nick, $channel) {
		return self::send("JOIN $channel");
	}
}

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
