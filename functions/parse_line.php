//<?php

log::trace('entered f::parse_line()');
list($line) = $_ARGV;

$_i = array();
$_i['prefix'] = null;
$_i['cmd'] = null;
$_i['args'] = array();
$_i['text'] = '';

$lwords = explode(' ', $line);
if (substr($lwords[0], 0, 1) == ':') {
	$_i['prefix'] = substr(array_shift($lwords), 1);
}
$_i['cmd'] = array_shift($lwords);
$twords = array();
$ontext = false;
foreach ($lwords as $w) {
	if (!$ontext) {
		$w = trim($w);
		if ($w == null)
			continue;
		if (substr($w, 0, 1) == ':') {
			$ontext = true;
			$twords[] = substr($w, 1);
		} else {
			$_i['args'][] = $w;
		}
	} else {
		$twords[] = $w;
	}
}
$_i['text'] = implode(' ', $twords);
if (!mb_check_encoding($_i['text'], 'UTF-8')) {
	$_i['text'] = mb_convert_encoding($_i['text'], 'UTF-8', 'UTF-8');
}

return $_i;
