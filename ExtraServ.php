<?php

require_once 'utils.php';
require_once 'log.php';
require_once 'functions.php';
require_once 'pseudoclient.php';
require_once 'config.php';
require_once 'procs.php';

function is_admin($nick) {
	static $admin_nicks = array(
		'alex'
	);
	return in_array($nick, $admin_nicks);
}

function parent_sigint() {
	log::fatal('Got sigint in parent, killing children');
	foreach (ExtraServ::$handles as $pc) {
		$pc->quit('Got SIGINT');
	}
	proc::stop_all();
	exit(42);
}

class ExtraServ {
	public static $hostname = 'extrastout.defiant.worf.co';
	public static $password = null;
	public static $info = 'Extended Services for hybrid (c/o alex)';
	public static $token = '0ES'; # for hybrid

	public static $handles = null;
	public static $serv_handle = null;
	public static $bot_handle = null;

	public static $db = null;

	public static $output_tz = 'America/Detroit';

	public static function init() {
		self::$password = get_password('uplink');
		$conf = config::get_instance();

		$c = uplink::connect();
		if (!$c) {
			return 1;
		}

		$dbpw = get_password('db');
		self::$db = pg_connect("host=localhost dbname=alex user=alex password=$dbpw");
		if (self::$db === false) {
			log::fatal('Failed to connect to database, exiting');
			exit(1);
		}

		if (self::$handles === null) {
			self::$handles = array();
			foreach ($conf->handles as $key => $params) {
				self::$handles[$key] = new pseudoclient($params);
			}
			self::$serv_handle = self::$handles[$conf->serv->handle];
			self::$bot_handle = self::$handles[$conf->bot->handle];
		}

		# Identify to the uplink server
		$my = 'ExtraServ';
		$ts = time();
		uplink::send("PASS {$my::$password} :TS");
		uplink::send("CAPAB :EX IE HOPS SVS");
		uplink::send("SID {$my::$hostname} 1 {$my::$token} :{$my::$info}");
		uplink::send("SERVER {$my::$hostname} 1 :{$my::$info}");
		uplink::send("SVINFO 6 5 0 :$ts");

		# init my handles
		foreach (self::$handles as $handle) {
			$handle->init();
		}

		foreach (config::channels() as $channel) {
			self::$bot_handle->join($channel);
		}

		return 0;
	}

	# send a server command
	public static function send($command) {
		$hn = ExtraServ::$hostname;
		return uplink::send(":$hn $command");
	}

	# send a command for a nick
	public static function usend($nick, $command) {
		return uplink::send(":$nick $command");
	}

	# Join one of my handles to a channel
	public static function sjoin($nick, $channel) {
		$ts = time();
		return self::send("SJOIN $ts $channel + :$nick");
	}

	# Join another server's nick to a channel
	public static function svsjoin($nick, $channel) {
		$ts = time();
		return self::send("SVSJOIN $nick $channel $ts");
	}

	# Change the nick of a user on another server
	public static function svsnick($oldnick, $newnick) {
		$ts = time();
		return self::send("SVSNICK $oldnick $newnick $ts");
	}
}

class uplink {
	public static $host = 'localhost';
	public static $port = 9998;

	protected static $socket;

	public static $server;
	public static $network;
	public static $nicks;

	public static function connect() {
		log::notice('>> Trying uplink to '.self::$host.':'.self::$port);
		self::$socket = fsockopen(self::$host, self::$port, $errno, $errstr);
		if (self::$socket === false) {
			log::error('Failed to connect to uplink server '.self::$host.':'.self::$port."$errstr ($errno)");
			return false;
		}
		return true;
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

	public static function is_nick($nick) {
		return array_key_exists($nick, self::$nicks);
	}

	public static function is_server($server) {
		return array_key_exists($server, self::$network);
	}
}

error_reporting(E_ALL);
date_default_timezone_set('UTC');
pcntl_signal(SIGINT, 'parent_sigint');

setproctitle('ExtraServ [parent]');
proc::$name = 'parent';

$_status = -10;
while (true) {
	log::debug('Started init loop');
	$_status = ExtraServ::init();
	if ($_status === 1) {
		log::error("Failed to connect to uplink server, sleeping 30 seconds and retrying");
		sleep(30);
		continue;
	}
	$_status = -10;

	proc::start('responder', 'main');
	proc::start('timer', 'timer');

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
			sleep(1);
			continue;
		} elseif ($_status === 0) {
			# clean exit
			proc::stop_all();
			exit(0);
		} elseif ($_status === 1) {
			# loop has ended, probably broken pipe
			log::debug('breaking wait loop');
			break;
		} elseif ($_status === 2) {
			# stopping gracefully
			log::notice('Closing uplink socket');
			fclose(uplink::$socket);
			log::debug('stopping children');
			proc::stop_all();
			log::debug('breaking wait loop and init loop');
			break 2;
		} elseif ($_status === 3) {
			# manual reconnect
			log::notice('Closing uplink socket');
			fclose(uplink::$socket);
			log::debug('stopping children');
			proc::stop_all();
			log::debug('continuing wait loop and init loop');
			continue 2;
		} elseif ($_status === 42) {
			# got sigint in child process
			proc::stop_all();
			exit(42);
		} else {
			log::fatal("Unknown return '$_status' from main()");
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
?>
