<?php

require_once 'log.php';
require_once 'utils.php';
require_once 'functions.php';
require_once 'config.php';

log::$level = log::INFO;

$_nick = 'newLogger';
$_user = 'newLogger';
$_name = 'New Log Bot c/o alex';
$_host = 'localhost';
$_port = 9999;

$table = 'newlog';

$_channels = config::channels();

date_default_timezone_set('UTC');

function send($text) {
	global $_irc;
	$ltext = color_formatting::escape($text);
	log::rawlog(log::INFO, "%G=> $ltext%0");
	fwrite($_irc, "$text\r\n");
}

function safe_feof($fp, &$start = null) {
	$start = microtime(true);
	return feof($fp);
}

while (true) {
	log::info('Connecting to IRC socket');
	$_irc = fsockopen($_host, $_port);
	if ($_irc === false) {
		log::error('Failed to open IRC socket, sleeping 30 seconds and retrying');
		sleep(30);
		continue;
	}

	log::info('Connecting to database');
	$dbpw = get_password('db');
	$_sql = pg_connect("host=localhost dbname=alex user=alex password=$dbpw");
	if ($_sql === false) {
		log::fatal('Failed to connect to database, exiting');
		exit(1);
	}

	log::info('Doing ident');
	send("NICK $_nick");
	send("USER $_user dot dot :$_name");

	log::info('Joining channels');
	foreach ($_channels as $chan) {
		send("JOIN $chan");
		usleep(500000); # 500ms
	}

	$_socket_start = null;
	$_socket_timeout = ini_get('default_socket_timeout');
	while (!safe_feof($_irc, $_socket_start) && (microtime(true) - $_socket_start) < $_socket_timeout) {
		$line = trim(fgets($_irc));
		if ($line == null) {
			continue;
		}
		$lline = color_formatting::escape($line);

		// Handle ping/pong
		if (substr($line, 0, 4) == 'PING') {
			log::rawlog(log::INFO, "%K<= $lline%0");
			send('PONG '.substr($line, 5));
			continue;
		}

		$_i = f::parse_line($line);
		$iprefix = pg_escape_string($_sql, $_i['prefix']);
		if (($handle = f::parse_hostmask($iprefix)) !== false) {
			if ($handle->nick == 'Global' || $handle->nick == $_nick) {
				log::rawlog(log::INFO, "%C<= $line%0");
				continue;
			}
			log::rawlog(log::INFO, "%c<= $lline%0");
			$itext = pg_escape_string($_sql, $_i['text']);
			$iargs = pg_escape_string($_sql, implode(' ', $_i['args']));
			$icmd = pg_escape_string($_sql, $_i['cmd']);

			$handle->nick = strtolower($nick);

			$uts = time();
			$query = "INSERT INTO $table (uts, nick, ircuser, irchost, command, args, message) VALUES ($uts, '{$handle->nick}', '{$handle->user}', '{$handle->host}', '$icmd', '$iargs', '$itext')";
			log::debug("== $query");
			$i = pg_query($_sql, $query);
			if ($i === false) {
				log::error(pg_last_error($_sql));
				log::fatal('Failed to log message, exiting');
				exit(1);
			}
		} else {
			log::rawlog(log::INFO, "%K<= $lline%0");
		}
	}

	log::error('Broken pipe, sleeping 30 seconds');
	fclose($_irc);
	unset($_irc);
	sleep(30);
}
