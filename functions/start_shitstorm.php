//<?php

if (!ExtraServ::$shitstorm) {
	ExtraServ::$shitstorm = true;
	proc::queue_sendall(proc::TYPE_SHITSTORM_STARTING, '*');
	log::debug('Sent shitstorm start message');
	return f::TRUE;
}
return f::FALSE;
