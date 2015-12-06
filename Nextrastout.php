<?php

date_default_timezone_set('UTC');

require_once __DIR__ . '/lib/utils.php';
require_once __DIR__ . '/lib/log.php';
require_once __DIR__ . '/lib/procs.php';
require_once __DIR__ . '/lib/uplink.php';
require_once __DIR__ . '/lib/Nextrastout.class.php';

set_error_handler('error_logger', E_ALL);

function is_admin($nick) {
	return in_array($nick, Nextrastout::$conf->admins);
}

pcntl_signal(SIGINT, 'parent_sigint');

setproctitle('Nextrastout [parent]');
proc::$name = 'parent';

$_status = proc::DEFAULT_STATUS;
while (true) {
	log::debug('Started init loop');
	$_status = Nextrastout::init();
	if ($_status === 1) {
		log::error("Failed to connect to uplink server, sleeping 30 seconds and retrying");
		sleep(30);
		continue;
	}

	proc::start('responder', 'nextrastout');
	proc::waitloop('responder');

	log::debug('Reached end of init loop');
}

log::debug('Reached end of file');
close_all();

function close_all() {
	uplink::close();
	Nextrastout::$db->close();
	log::debug('stopping children');
	proc::stop_all();
}

function parent_sigint() {
	log::fatal('Got sigint in parent, killing children');
	Nextrastout::$bot_handle->quit('Got SIGINT');
	close_all();
	exit(42);
}
