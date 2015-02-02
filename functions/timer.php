//<?php

log::trace('entered f::timer()');

$count = 0;
while (true) {
	if ($count % 1000 == 0) {
		log::trace('Still alive.');
	}

	if (($message = proc::queue_get(0, $msgtype, $fromproc)) !== null) {
		log::debug("Got message from $fromproc (type=$msgtype): $message");
	}
	sleep(1);
	$count++;
}
