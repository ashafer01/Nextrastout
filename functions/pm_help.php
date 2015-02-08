//<?php

log::trace('Entered f::pm_help()');
list($_CMD, $uarg, $_i) = $_ARGV;

switch (strtoupper($uarg)) {
	case '':
		$_i['handle']->notice($_i['reply_to'], 'This provides information about ExtraServ\'s service commands.');
		$_i['handle']->notice($_i['reply_to'], 'Check !help for information about bot commands.');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], '    REGISTER   Register your username and associate your current nickname');
		$_i['handle']->notice($_i['reply_to'], '    IDENTIFY   Identify yourself with your password');
		$_i['handle']->notice($_i['reply_to'], '     DEIDENT   Remove identification for this connection');
		$_i['handle']->notice($_i['reply_to'], '   ASSOCIATE   Associate your current nickname with your registered username');
		$_i['handle']->notice($_i['reply_to'], '     RECOVER   Recover one of your associated nicknames in use by someone else');
		$_i['handle']->notice($_i['reply_to'], '    VALIDATE   Determine if the user of a nickname is valid');
		$_i['handle']->notice($_i['reply_to'], '    REGPHONE   Register a mobile phone via text message');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Say "HELP <command>" for more information about a command');
		break;
	case 'REGISTER':
		$_i['handle']->notice($_i['reply_to'], 'Usage: REGISTER <password>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Registers your current username using the given password. You will need to');
		$_i['handle']->notice($_i['reply_to'], 'send this password with IDENTIFY whenever you connect to IRC. Passwords');
		$_i['handle']->notice($_i['reply_to'], 'must be at least 6 and no more than 72 characters in length. You may include');
		$_i['handle']->notice($_i['reply_to'], 'numbers, symbols, and whitespace, but they are not required.');
		break;
	case 'IDENTIFY':
		$_i['handle']->notice($_i['reply_to'], 'Usage: IDENTIFY <password>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Identify your current connection using the password you set with REGISTER.');
		$_i['handle']->notice($_i['reply_to'], 'You will not need to identify again until you reconnect.');
		break;
	case 'DEIDENT':
		$_i['handle']->notice($_i['reply_to'], 'Usage: DEIDENT');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Destroy your identification for this connection.');
		break;
	case 'ASSOCIATE':
		$_i['handle']->notice($_i['reply_to'], 'Usage: ASSOCIATE');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Associate your current nickname with your username if you have identfied, and');
		$_i['handle']->notice($_i['reply_to'], 'the nickname isn\'t already associated with another user.');
		break;
	case 'RECOVER':
		$_i['handle']->notice($_i['reply_to'], 'Usage: RECOVER <nickname>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Recover a nickname associated with your username that is in use by someone else.');
		$_i['handle']->notice($_i['reply_to'], 'Their nickname will be changed to something else, and yours will be changed to');
		$_i['handle']->notice($_i['reply_to'], 'the specified nickname.');
		break;
	case 'VALIDATE':
		$_i['handle']->notice($_i['reply_to'], 'Usage: VALIDATE <nickname> [{API}]');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Determine if the given nickname\'s current usage is valid. Add the API flag to');
		$_i['handle']->notice($_i['reply_to'], 'get a reply suitable for automation; it will be comprised of one or more of the');
		$_i['handle']->notice($_i['reply_to'], 'following flags separated by a space:');
		$_i['handle']->notice($_i['reply_to'], '     ERROR   An error ocurred');
		$_i['handle']->notice($_i['reply_to'], '   NOASSOC   The nickname is not associated with any user');
		$_i['handle']->notice($_i['reply_to'], '    ONLINE   The nickname is currently online');
		$_i['handle']->notice($_i['reply_to'], '   OFFLINE   The nickname is currently offline');
		$_i['handle']->notice($_i['reply_to'], '     VALID   The current user of the nickname matches its owner');
		$_i['handle']->notice($_i['reply_to'], '   INVALID   The current user of the nickname DOES NOT match its owner');
		break;
	default:
		$_i['handle']->notice($_i['reply_to'], "Unknown command: $uarg");
		break;
}
