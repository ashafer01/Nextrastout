//<?php

log::trace('entered f::handle_ipc()');
list($msgtype, $message) = $_ARGV;

switch ($msgtype) {
	case proc::TYPE_COMMAND:
		# simple command
		switch ($message) {
			case 'RELOAD':
				log::info('Got RELOAD, returning 0');
				f::RELOAD(proc::$func);
				return 0;
			case 'HUP':
				log::info('Got HUP, reloading config');
				config::reload_all();
				break;
			case 'RELOAD ALL':
				log::info('Got RELOAD ALL');
				f::RELOAD_ALL();
				config::reload_all();
				break;
			case 'DUMP1':
				log::debug('Got DUMP1');
				var_dump(ExtraServ::$death_row);
				break;
			default:
				log::warning("Unknown simple IPC command: $message");
		}
		return f::TRUE;
	case proc::TYPE_FUNC_RELOAD:
		# function reload
		log::info("Got reload IPC message for f::$message()");
		f::RELOAD($message);
		return f::TRUE;
	case proc::TYPE_LOGLEVEL:
		# log level change
		log::info("Got log level change to $message");
		log::$level = log::string_to_level($message);
		return f::TRUE;
	case proc::TYPE_TIMEZONE:
		# output tz change
		if (class_exists('ExtraServ', false)) {
			log::info("Got output timezone change to $message");
			ExtraServ::$output_tz = $message;
			return f::TRUE;
		} else {
			log::debug('ExtraServ class is not defined for timezone change');
			return f::FALSE;
		}
	default:
		return f::FALSE;
}
