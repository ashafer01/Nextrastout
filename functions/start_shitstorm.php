//<?php

if (!ExtraServ::$shitstorm) {
	ExtraServ::$shitstorm = true;
	proc::queue_sendall(proc::TYPE_SHITSTORM_STARTING, '*');
	log::trace('Sent shitstorm start message');
	return f::TRUE;
}
return f::FALSE;
