//<?php

log::trace('entered f::admin_commands()');
list($ucmd, $uarg, $_i, $_globals) = $_ARGV;

switch ($ucmd) {
	# operational functions
	case 'proc-reload':
		log::notice('Got !proc-reload');
		$func = proc::get_proc_func($uarg);
		if ($func == null) {
			$say = 'No such process';
		} else {
			f::RELOAD($func);
			proc::enable_reload($uarg);
			$say = "Reloading $uarg proc";
		}
		$_i['handle']->say($_i['reply_to'], $say);
		break;
	case 'freload':
		log::notice('Got !freload');
		if (f::EXISTS($uarg)) {
			if (f::IS_ALIAS($uarg)) {
				$uarg = f::RESOLVE_ALIAS($uarg);
			}
			f::RELOAD($uarg);
			$_i['handle']->say($_i['reply_to'], "Reloading f::$uarg()");
		} else {
			$_i['handle']->say($_i['reply_to'], "Function $uarg does not exist");
		}
		break;
	case 'creload':
		log::notice('Got !creload');
		$cmdfunc = "cmd_$uarg";
		if (f::EXISTS($cmdfunc)) {
			if (f::IS_ALIAS($cmdfunc)) {
				$cmdfunc = f::RESOLVE_ALIAS($cmdfunc);
			}
			f::RELOAD($cmdfunc);
			$_i['handle']->say($_i['reply_to'], "Reloading f::$cmdfunc()");
		} else {
			$_i['handle']->say($_i['reply_to'], "Function $cmdfunc does not exist");
		}
		break;
	case 'hup':
		log::notice('Got !hup');
		config::reload_all();
		$_i['handle']->say($_i['reply_to'], 'Reloaded config');
		f::ALIAS_INIT();
		config::set_reload();
		Nextrastout::$bot_handle->update_conf_channels();
		break;
	case 'dump-cd':
		print_r(Nextrastout::$cmd_cooldown);
		break;
	case 'rm-cd':
		unset(Nextrastout::$cmd_cooldown[$uarg]);
		$_i['handle']->say($_i['reply_to'], "Cleared cooldown for $uarg");
		break;
	case 'reload-all':
		log::notice('Got !reload-all');
		$_i['handle']->say($_i['reply_to'], 'Marking all functions for reloading and reloading conf');
		f::RELOAD_ALL();
		config::reload_all();
		config::set_reload();
		Nextrastout::$bot_handle->update_conf_channels();
		break;
	case 'loglevel':
		log::notice("Got !loglevel $uarg");
		log::$level = log::string_to_level($uarg);
		$_i['handle']->say($_i['reply_to'], 'Changed log level');
		break;
	case 'set-tz':
		log::notice('Got !set-tz');
		Nextrastout::$output_tz = $uarg;
		$_i['handle']->say($_i['reply_to'], 'Changed output timezone');
		break;
	case 'es-join':
		log::notice('Got !es-join');
		Nextrastout::$bot_handle->join($uarg);
		break;
	case 'es-part':
		log::notice('Got !es-part');
		Nextrastout::$bot_handle->part($uarg);
		break;
	case 'chanserv':
		log::notice('Got !chanserv');
		Nextrastout::$bot_handle->say('ChanServ', $uarg);
		break;
	case 'nickserv':
		log::notice('Got !nickserv');
		Nextrastout::$bot_handle->say('NickServ', $uarg);
		break;
	default:
		log::trace('Not an admin command');
}
