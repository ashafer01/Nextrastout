//<?php

log::trace('Entered f::parse_logquery()');

$_ARGV[] = null;
list($param_string, $default_cond) = $_ARGV;
if ($default_cond === null) {
	$default_cond = 'likes';
}

$params = explode(' ', $param_string);

$ret = new stdClass;
$ret->likes = array();
$ret->notlikes = array();
$ret->req_wordbound = array();
$ret->exc_wordbound = array();
$ret->before = array();
$ret->after = array();
$ret->req_nicks = array();
$ret->exc_nicks = array();
$ret->req_re = array();
$ret->exc_re = array();

$current_var = $default_cond;
$current_index = 0;
foreach ($params as $param) {
	if ($param == null) {
		continue;
	}
	$fc = substr($param, 0 ,1);
	switch ($fc) {
		case '+':
			# new LIKE phrase
			$current_var = 'likes';
			$current_index = count($ret->likes);

			$ret->likes[$current_index] = array(substr($param, 1));
			break;
		case '-':
			# new NOT LIKE phrase
			$current_var = 'notlikes';
			$current_index = count($ret->notlikes);

			$ret->notlikes[$current_index] = array(substr($param, 1));
			break;
		case '=':
			# new required word bound phrase
			$current_var = 'req_wordbound';
			$current_index = count($ret->req_wordbound);

			$ret->req_wordbound[$current_index] = array(substr($param, 1));
			break;
		case '~':
			# new excluded word bound phrase
			$current_var = 'exc_wordbound';
			$current_index = count($ret->exc_wordbound);

			$ret->exc_wordbound[$current_index] = array(substr($param, 1));
			break;
		case '@':
			# new nick list
			$current_var = 'req_nicks';
			$current_index = count($ret->req_nicks);

			$ret->req_nicks[$current_index] = array(substr(strtolower($param), 1));
			break;
		case '^':
			# new exclude nick list
			$current_var = 'exc_nicks';
			$current_index = count($ret->exc_nicks);

			$ret->exc_nicks[$current_index] = array(substr(strtolower($param), 1));
			break;
		case '<':
			# new before time
			$current_var = 'before';
			$current_index = 0;

			$ret->before[0] = array(substr($param, 1));
			break;
		case '>':
			# new after time
			$current_var = 'after';
			$current_index = 0;

			$ret->after[0] = array(substr($param, 1));
			break;
		default:
			$fsc = substr($param, 0, 2);
			switch ($fsc) {
				case 'B+':
					# new required word bound phrase
					$current_var = 'req_wordbound';
					$current_index = count($ret->req_wordbound);

					$ret->req_wordbound[$current_index] = array(substr($param, 2));
					break;
				case 'B-':
					# new excluded word bound phrase
					$current_var = 'exc_wordbound';
					$current_index = count($ret->exc_wordbound);

					$ret->exc_wordbound[$current_index] = array(substr($param, 2));
					break;
				case 'R+':
					# new required regex
					$current_var = 'req_re';
					$current_index = count($ret->req_re);

					$ret->req_re[$current_index] = array(substr($param, 2));
					break;
				case 'R-':
					# new excluded regex
					$current_var = 'exc_re';
					$current_index = count($ret->exc_re);

					$ret->exc_re[$current_index] = array(substr($param, 2));
					break;
				default:
					# add word to current array
					$ret->{$current_var}[$current_index][] = $param;
					break;
			}
			break;
	}
}

return $ret;
