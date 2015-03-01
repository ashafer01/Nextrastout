//<?php

# Find karma strings within $text and return an array of operations

list($text) = $_ARGV;

$ret = array();
$karma_patterns = array(
	'/\b([!-&*-~]+?)(\+\+|--)(?![!-~])/',
	'/\(([ -~]+?)\)(\+\+|--)(?![!-~])/'
);

foreach ($karma_patterns as $pattern) {
	$m = preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
	if ($m === false) {
		log::error('preg failed');
		continue;
	} elseif ($m === 0) {
		log::trace("No matches for pattern $pattern");
	} else {
		foreach ($matches as $match) {
			if (!array_key_exists($match[1], $ret)) {
				$ret[$match[1]] = array('++' => 0, '--' => 0);
			}
			$ret[$match[1]][$match[2]]++;
		}
	}
}

return $ret;
