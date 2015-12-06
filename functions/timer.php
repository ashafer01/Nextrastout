//<?php

Nextrastout::dbconnect();
f::ALIAS_INIT();

while (true) {
	# Check for reload
	if (proc::reload_needed()) {
		log::notice('Reloading timer proc');
		proc::disable_reload();
		return proc::PROC_RERUN;
	}

	# Check for new SMS
	$q = Nextrastout::$db->pg_query("SELECT sms.message_sid, sms.from_number, sms.message, sms.dest_chan, phonebook.nick FROM sms FULL JOIN phonebook ON phonebook.phone_number=sms.from_number WHERE posted IS FALSE ORDER BY uts",
		'check for new sms', false);
	while ($qr = db::fetch_assoc($q)) {
		if ($qr['nick'] == null) {
			log::debug('Got SMS from unregistered number');
			$cid = f::everyoneapi($qr['from_number'], array('name'));
			if (in_array('name', $cid->missed)) {
				$from = $qr['from_number'];
			} else {
				$from = "{$cid->data->name} ({$qr['from_number']})";
			}
		} else {
			$from = $qr['nick'];
			log::debug("Got SMS from registered number ($from)");
		}
		Nextrastout::$bot_handle->say($qr['dest_chan'], "<$from> {$qr['message']}");
		$u = Nextrastout::$db->pg_query("UPDATE sms SET posted=TRUE WHERE message_sid='{$qr['message_sid']}'",
			'mark sms posted', false);
		if ($u === false) {
			log::fatal('Failed to mark SMS as posted, exiting timer proc to prevent spam');
			exit(101);
		}
	}

	sleep(1);
}

log::debug('Reached end of timer proc');
