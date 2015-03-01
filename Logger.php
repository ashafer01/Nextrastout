<?php

require_once 'lib/procs.php';
require_once 'lib/log.php';

proc::$name = 'logger';
log::$level = log::INFO;

proc::register_relay_queue(proc::$name, 345);
proc::$queue = msg_get_queue(345);

require_once 'lib/utils.php';
require_once 'lib/functions.php';
require_once 'lib/config.php';

$_nick = 'newLogger';
$_user = 'newLogger';
$_name = 'New Log Bot c/o alex';
$_host = 'localhost';
$_port = 9999;

$table = 'log';

$_channels = config::channels();
$_channels[] = '#geekboy';

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
	$_sql = pg_connect("host=localhost dbname=extraserv user=alex password=$dbpw application_name=Logger");
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

		if (($message = proc::queue_get(0, $msgtype, $fromproc)) !== null) {
			if ($msgtype != proc::TYPE_TIMEZONE && $msgtype != proc::TYPE_LOGLEVEL) {
				f::handle_ipc($msgtype, $message);
			}
		}

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

			if (($_i['cmd'] == 'PRIVMSG') && !in_array($_i['args'][0], $_channels)) {
				log::trace('Skipping PRIVMSG from un-joined channel');
				continue;
			}

			if ($_i['cmd'] == 'PRIVMSG') {
				$karma = f::parse_karma($_i['text']);
				foreach ($karma as $thing => $changes) {
					$query_params = array(
						$changes['++'],
						$changes['--'],
						$_i['args'][0],
						$handle->nick,
						$thing
					);
					$q = pg_query_params($_sql, 'UPDATE karma_cache SET up = up + $1, down = down + $2 WHERE channel=$3 AND nick=$4 AND thing=$5', $query_params);
					if ($q === false) {
						log::error('Failed to update karma cache');
					} elseif (pg_affected_rows($q) == 0) {
						# need to do insert
						$q = pg_query_params($_sql, 'INSERT INTO karma_cache (channel, nick, thing, up, down) VALUES ($3,$4,$5,$1,$2)', $query_params);
						if ($q === false) {
							log::error('Failed to insert new karma');
						} else {
							log::info("Inserted new karma for '$thing' (+{$changes['++']}; -{$changes['--']}; nick={$_i['prefix']})");
						}
					} else {
						log::info("Updated karma for '$thing' (+{$changes['++']}; -{$changes['--']}; nick={$_i['prefix']})");
					}
				}
			}

			$itext = pg_escape_string($_sql, $_i['text']);
			$iargs = pg_escape_string($_sql, implode(' ', $_i['args']));
			$icmd = pg_escape_string($_sql, $_i['cmd']);

			$handle->nick = strtolower($handle->nick);

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
