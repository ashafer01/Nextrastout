//<?php

if (ExtraServ::$shitstorm) {
	ExtraServ::$shitstorm = false;
	proc::queue_sendall(proc::TYPE_SHITSTORM_OVER, '*');
	log::debug('Sent shitstorm stop message');
	return f::TRUE;
}
return f::FALSE;