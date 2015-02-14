<?php
require_once 'functions.php';
require_once 'utils.php';

class proc {
	private static $procs = array();
	private static $dups = array();

	public static $name = null;

	const PARENT_QUEUEID = 421;
	const MAX_MSG_SIZE = 4096;
	private static $mqid = 422;

	public static $parent_queue = null;
	public static $queue = null;

	private static $queues = array();

	const TYPE_COMMAND = 1;
	const TYPE_FUNC_RELOAD = 2;
	const TYPE_LOGLEVEL = 3;
	const TYPE_TIMEZONE = 4;
	const TYPE_PROCS_STARTED = 100;
	const TYPE_NEW_PROC_QUEUE = 184;
	const TYPE_NEW_RELAY_QUEUE = 901;

	## process control

	public static function start($name, $func, $args = null) {
		if (strlen($name) > 10) {
			$name = substr($name, 0, 10);
		}
		if ($args === null) {
			$args = array();
		}
		if (array_key_exists($name, self::$procs)) {
			log::notice('Proc name already taken');
			if (array_key_exists($name, self::$dups)) {
				$index = self::$dups[$name]++;
			} else {
				self::$dups[$name] = 0;
				$index = 0;
			}
			if (strlen("$index") + strlen($name) >= 10) {
				$name = substr_replace($name, $index, -1);
			} else {
				$name .= $index;
			}
		}
		$MQID = self::$mqid;
		self::$mqid++;
		self::$queues[$name] = msg_get_queue($MQID);
		log::info("Starting proc '$name' with function f::$func()");
		$pid = pcntl_fork();
		if ($pid === -1) {
			log::fatal('Fork failed');
			exit(1);
		} elseif ($pid === 0) {
			pcntl_signal(SIGINT, 'child_sigint');
			file_put_contents("pids/$name.pid", posix_getpid());
			setproctitle("ExtraServ [$name]");

			self::$procs = array();
			self::$dups = array();

			proc::$name = $name;
			proc::$parent_queue = msg_get_queue(proc::PARENT_QUEUEID);
			proc::$queue = msg_get_queue($MQID);
			proc::queue_sendall(proc::TYPE_NEW_PROC_QUEUE, $MQID); # tell other processes our queue id

			proc::queue_get_block(proc::TYPE_PROCS_STARTED);

			while (true) {
				$_status = f::CALL($func, $args);
				if ($_status === 0) {
					log::notice("proc function f::$func() returned 0, re-running it");
					continue;
				} elseif ($_status === true) {
					log::notice("proc function f::$func() returned true, exiting normally");
					exit(0);
				} elseif ($_status === false) {
					log::fatal("proc function f::$func() return false, exiting with error");
					exit(240);
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

	# delete metadata about a child process
	private static function del_proc($name) {
		if (array_key_exists($name, self::$procs)) {
			unset(self::$procs[$name]);
		}
		if (array_key_exists($name, self::$dups)) {
			unset(self::$dups[$name]);
		}
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

	## queue stuff

	# send a message to the parent queue to be relayed to siblings
	public static function queue_sendall($type, $msg) {
		log::debug("Sending message (type=$type)");
		$myproc = proc::$name;
		$msg = "$myproc::$msg";
		if (msg_send(proc::$parent_queue, $type, $msg, false) !== true) {
			log::error('Failed to send message to parent queue');
		}
	}

	# called in a parent process- relays message from its queue to all of its children's queues
	public static function queue_relay() {
		if (msg_receive(self::$parent_queue, 0, $msgtype, proc::MAX_MSG_SIZE, $message, false, MSG_IPC_NOWAIT|MSG_NOERROR) === true) {
			if ($msgtype == proc::TYPE_NEW_RELAY_QUEUE) {
				$name_id = explode(':', $message);
				self::$queues[$name_id[0]] = msg_get_queue($name_id[1]);
				log::debug("Stored new relay queue {$name_id[0]} => {$name_id[1]}");
			} else {
				$fields = explode('::', $message, 2);
				$from_proc = $fields[0];
				foreach (self::$queues as $procname => $mq) {
					if ($procname == $from_proc) {
						continue;
					}
					if (msg_send($mq, $msgtype, $message, false) === true) {
						log::debug("Relayed message of type $msgtype from proc '$from_proc' to proc '$procname'");
					} else {
						log::error("Failed to send message from proc '$from_proc' to proc '$procname'");
					}
				}
			}
		}
	}

	# get a message from the current process queue
	public static function queue_get($type, &$msgtype = null, &$fromproc = null, $flags = null) {
		if ($flags === null) {
			$flags = MSG_IPC_NOWAIT|MSG_NOERROR;
		}
		if (msg_receive(proc::$queue, $type, $i_msgtype, proc::MAX_MSG_SIZE, $message, false, $flags) === true) {
			$message = explode('::', $message, 2);
			if ($i_msgtype == proc::TYPE_NEW_PROC_QUEUE) {
				# notification of new queue
				log::debug("Recieved new queue notification {$message[0]} => {$message[1]}");
				self::$queues[$message[0]] = msg_get_queue((int) $message[1]);

				$fromproc = null;
				$msgtype = null;
				return null;
			} else {
				$fromproc = $message[0];
				$msgtype = $i_msgtype;
				return $message[1];
			}
		} else {
			$fromproc = null;
			$msgtype = null;
			return null;
		}
	}

	public static function queue_get_block($type, &$msgtype = null, &$fromproc = null) {
		return self::queue_get($type, $msgtype, $fromproc, MSG_NOERROR);
	}

	public static function register_relay_queue($name, $mqid) {
		$pmq = msg_get_queue(proc::PARENT_QUEUEID);
		if (!msg_send($pmq, proc::TYPE_NEW_RELAY_QUEUE, "$name:$mqid", false)) {
			log::error('Failed to submit new relay queue id');
		} else {
			log::debug('Sent new relay queue id');
		}
	}
}

function child_sigint() {
	log::notice('Got sigint in child process');
	proc::kill_all();
	exit(42);
}
