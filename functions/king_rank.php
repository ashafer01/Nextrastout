//<?php

log::trace('entered f::king_rank()');

list($index) = $_ARGV;

switch ($index) {
	case 1: return 'King';
	case 2: return 'Prince';
	case 3: return 'Duke';
	case 4: return 'Earl';
	case 5: return 'Baron';
	default:
		$th = ord_suffix($index);
		return "$index$th";
}
