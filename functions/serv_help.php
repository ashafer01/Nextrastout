//<?php

log::trace('Entered f::serv_help()');
list($_CMD, $uarg, $_i) = $_ARGV;

switch (strtoupper($uarg)) {
	case '':
		$_i['handle']->notice($_i['reply_to'], 'This provides information about ExtraServ\'s service commands.');
		$_i['handle']->notice($_i['reply_to'], 'Check !help for a wiki link including docs for bot commands.');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], '    REGISTER   Register your username');
		$_i['handle']->notice($_i['reply_to'], '    IDENTIFY   Identify yourself with your password');
		$_i['handle']->notice($_i['reply_to'], '     DEIDENT   Remove identification for this connection');
		$_i['handle']->notice($_i['reply_to'], '   ASSOCIATE   Associate your current nickname with your registered username');
		$_i['handle']->notice($_i['reply_to'], '     SETPASS   Change your password (must be identified)');
		$_i['handle']->notice($_i['reply_to'], '     RECOVER   Recover one of your associated nicknames in use by someone else');
		$_i['handle']->notice($_i['reply_to'], '    VALIDATE   Determine if the user of a nickname is valid');
		$_i['handle']->notice($_i['reply_to'], '     REGCHAN   Register a channel');
		$_i['handle']->notice($_i['reply_to'], '          OP   Get channel operator privileges on a channel you own');
		$_i['handle']->notice($_i['reply_to'], ' STICKYMODES   Make simple modes on a registered channel "sticky"');
		$_i['handle']->notice($_i['reply_to'], ' STICKYLISTS   Make mode lists on a registered channel "sticky"');
		$_i['handle']->notice($_i['reply_to'], ' VERIFYPHONE   Verify your phone number and associate with your username');
		$_i['handle']->notice($_i['reply_to'], '    REGPHONE   Associate a verified phone number with a nickname');
		$_i['handle']->notice($_i['reply_to'], '    REGEMAIL   Register an email addresss');
		$_i['handle']->notice($_i['reply_to'], '         SET   Change various settings');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Say "HELP <command>" for more information about a command');
		break;
	case 'REGISTER':
		$_i['handle']->notice($_i['reply_to'], 'Usage: REGISTER <password>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Registers your current username using the given password. You will need to');
		$_i['handle']->notice($_i['reply_to'], 'send this password with IDENTIFY whenever you connect to IRC. Passwords');
		$_i['handle']->notice($_i['reply_to'], 'must be at least 4 and no more than 72 characters in length. You may include');
		$_i['handle']->notice($_i['reply_to'], 'numbers, symbols, and whitespace, but they are not required.');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Your current nickname will automatically be associated with your username on');
		$_i['handle']->notice($_i['reply_to'], 'successful registration. You will also automatically be marked as identified.');
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
		$_i['handle']->notice($_i['reply_to'], 'Associates your current nickname with your username if you are identfied and');
		$_i['handle']->notice($_i['reply_to'], 'the nickname isn\'t already associated with another user.');
		break;
	case 'SETPASS':
		$_i['handle']->notice($_i['reply_to'], 'Usage: SETPASS <password>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Change your password. Has the same restrictions as REGISTER- 6 to 72 characters,');
		$_i['handle']->notice($_i['reply_to'], 'all letters, numbers, symbols, and whitespace are allowed.');
		$_i['handle']->notice($_i['reply_to'], 'You must already be identified to use this function.');
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
		$_i['handle']->notice($_i['reply_to'], '     IDENT   The user is identified');
		$_i['handle']->notice($_i['reply_to'], '   NOIDENT   The user IS NOT identified');
		break;
	case 'REGCHAN':
		$_i['handle']->notice($_i['reply_to'], 'Usage: REGCHAN <channel>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Register as the owner of the specified channel. You must be joined to');
		$_i['handle']->notice($_i['reply_to'], 'the channel and be a channel operator to use this command.');
		break;
	case 'OP':
		$_i['handle']->notice($_i['reply_to'], 'Usage: OP <channel>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Request that ExtraServ give you channel operator privileges on the specified');
		$_i['handle']->notice($_i['reply_to'], 'channel. You must own the channel or be a server operator for the request to');
		$_i['handle']->notice($_i['reply_to'], 'be granted.');
		break;
	case 'STICKYMODES':
		$_i['handle']->notice($_i['reply_to'], 'Usage: STICKYMODES <channel>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Make changes to simple mode flags by channel operators (@) "sticky" meaning');
		$_i['handle']->notice($_i['reply_to'], 'they will be re-applied on server start. This does not apply to list modes.');
		$_i['handle']->notice($_i['reply_to'], 'See also: STICKYLISTS');
		$_i['handle']->notice($_i['reply_to'], 'Note: mode changes made by ExtraServ will not be sticky');
		$_i['handle']->notice($_i['reply_to'], 'Note: Channel keys (+k) must be stored unencrypted in the database');
		break;
	case 'STICKYLISTS':
		$_i['handle']->notice($_i['reply_to'], 'Usage: STICKYLISTS <modes> <channel>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'When a channel operator (@) changes the mode, that change will be stored and');
		$_i['handle']->notice($_i['reply_to'], 'then re-applied as needed. Ban lists and exception lists will be restored on');
		$_i['handle']->notice($_i['reply_to'], 'server start. Op, half-op, and voice lists will be restored when one of the');
		$_i['handle']->notice($_i['reply_to'], 'users on the stored list re-joins the channel.');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'List all desired modes characters together. Possible modes are listed below:');
		$_i['handle']->notice($_i['reply_to'], '   b   Ban list - a list of hostmasks that cannot join the channel');
		$_i['handle']->notice($_i['reply_to'], '   e   Ban exception - allows whitelists by banning *!*@*');
		$_i['handle']->notice($_i['reply_to'], '   I   Invite exception - matching hostmasks do not need to be invited');
		$_i['handle']->notice($_i['reply_to'], '       to join +i channels');
		$_i['handle']->notice($_i['reply_to'], '   o   Channel operator list');
		$_i['handle']->notice($_i['reply_to'], '   h   Half-op list');
		$_i['handle']->notice($_i['reply_to'], '   v   Voice list');
		$_i['handle']->notice($_i['reply_to'], '   *   All of the above');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Examples:');
		$_i['handle']->notice($_i['reply_to'], '   STICKYLISTS ohvI #test');
		$_i['handle']->notice($_i['reply_to'], '   STICKYLISTS beo #test');
		$_i['handle']->notice($_i['reply_to'], '   STICKYLISTS v #test');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'See also: STICKYMODES');
		$_i['handle']->notice($_i['reply_to'], 'Note: mode changes made by ExtraServ will not be sticky');
		break;
	case 'VERIFYPHONE':
		$_i['handle']->notice($_i['reply_to'], 'Usage: VERIFYPHONE <phone number>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], '*** NOT YET IMPLEMENTED');
		$_i['handle']->notice($_i['reply_to'], 'Send a verification code to the specified number. Once verified');
		$_i['handle']->notice($_i['reply_to'], 'the number will be associated with your username.');
		$_i['handle']->notice($_i['reply_to'], 'Before it can be used, you must associate with one of your');
		$_i['handle']->notice($_i['reply_to'], 'nicknames using REGPHONE.');
		break;
	case 'REGPHONE':
		$_i['handle']->notice($_i['reply_to'], 'Usage: REGPHONE <nick> <phone number>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], '*** NOT YET IMPLEMENTED');
		$_i['handle']->notice($_i['reply_to'], 'Associate a phone number with one of your nicknames. You can');
		$_i['handle']->notice($_i['reply_to'], 'only have one number per nick, but you can have as many nicks');
		$_i['handle']->notice($_i['reply_to'], 'as you like. The phone number must already be associated with');
		$_i['handle']->notice($_i['reply_to'], 'your username by verifying it with VERIFYPHONE.');
		break;
	case 'REGEMAIL':
		$_i['handle']->notice($_i['reply_to'], 'Usage: REGEMAIL <email address>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], '*** NOT YET IMPLEMENTED');
		$_i['handle']->notice($_i['reply_to'], 'Register an email address for use with image uploading.');
		break;
	case 'SET':
		$_i['handle']->notice($_i['reply_to'], 'Usage: SET <type> <...>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], '*** NOT YET IMPLEMENTED');
		$_i['handle']->notice($_i['reply_to'], 'SET changes various settings. Each type has its own help page');
		$_i['handle']->notice($_i['reply_to'], 'describing all configurable options, just say "HELP SET <type>"');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Types:');
		$_i['handle']->notice($_i['reply_to'], '    PHONE  Phone-related settings');
		$_i['handle']->notice($_i['reply_to'], '     CHAN  Channel-related settings');
		$_i['handle']->notice($_i['reply_to'], '  PROFILE  General purpose public profile information');
		break;
	case 'SET PROFILE':
		$_i['handle']->notice($_i['reply_to'], 'Usage: SET PROFILE <field name> = <value>');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], '*** NOT YET IMPLEMENTED');
		$_i['handle']->notice($_i['reply_to'], 'SET PROFILE allows you to store arbitrary information attached');
		$_i['handle']->notice($_i['reply_to'], 'to your username/nicknames. This is meant to be free-form and');
		$_i['handle']->notice($_i['reply_to'], 'can be used for things like usernames on social websites, contact');
		$_i['handle']->notice($_i['reply_to'], 'information, biographical information, anything. This is a');
		$_i['handle']->notice($_i['reply_to'], 'completely opt-in feature. All information stored will be public');
		$_i['handle']->notice($_i['reply_to'], 'to anyone on IRC.');
		$_i['handle']->notice($_i['reply_to'], 'Field names are limited to 48 characters, values are limited by');
		$_i['handle']->notice($_i['reply_to'], 'the length of an IRC message. You may store more than one value');
		$_i['handle']->notice($_i['reply_to'], 'per field name. The same field name and value pair cannot be');
		$_i['handle']->notice($_i['reply_to'], 'stored more than once across all users.');
		$_i['handle']->notice($_i['reply_to'], ' ');
		$_i['handle']->notice($_i['reply_to'], 'Examples:');
		$_i['handle']->notice($_i['reply_to'], '   SET PROFILE steam name = mysteamname');
		$_i['handle']->notice($_i['reply_to'], '   SET PROFILE favorite color = blue');
		$_i['handle']->notice($_i['reply_to'], '   SET PROFILE about me = It all started when I was born...');
		break;
	default:
		$_i['handle']->notice($_i['reply_to'], "Unknown help topic: $uarg");
		break;
}
