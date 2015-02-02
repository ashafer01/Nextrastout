//<?php

log::trace('entered f::timer()');

$count = 0;
while (true) {
	if ($count % 1000 == 0) {
		log::trace('Still alive.');
	}
	sleep(1);
	$count++;
}
