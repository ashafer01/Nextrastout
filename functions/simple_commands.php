//<?php

log::trace('entered f::simple_commands()');
list($_CMD, $_ARG, $_i) = $_ARGV;

switch ($_CMD) {
	case 'test':
		log::info('Got !test');
		$reply = "Hello, {$_i['hostmask']->nick}";
		if ($_ARG != null) {
			$reply .= " - $_ARG";
		}
		$_i['handle']->say($_i['reply_to'], $reply);
		return f::TRUE;
	case 'help':
		log::debug('Got !help');
		$_i['handle']->say($_i['reply_to'], ExtraServ::$conf->wiki_url);
		return f::TRUE;
	case 'rand':
		$randcmds = array('randquote', 'randcaps');
		$f = $randcmds[array_rand($randcmds)];
		$_ARGV[0] = $f;
		f::CALL("cmd_$f", $_ARGV);
		return f::TRUE;
	default:
		return f::FALSE;
}
