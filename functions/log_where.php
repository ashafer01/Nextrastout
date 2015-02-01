//<?php

log::trace('entered f::log_where()');

$_ARGV[] = null;
$_ARGV[] = null;
list($param_string, $no_nicks, $leading_and) = $_ARGV;
if ($no_nicks === null) {
	$no_nicks = false;
}
if ($leading_and === null) {
	$leading_and = true;
}

$params = explode(' ', $param_string);
$likes = array();
$notlikes = array();
$req_wordbound = array();
$exc_wordbound = array();
$before = array();
$after = array();
$req_nicks = array();
$exc_nicks = array();
$req_re = array();
$exc_re = array();

$current_var = 'likes';
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
			$current_index = count($likes);

			$likes[$current_index] = array(substr($param, 1));
			break;
		case '-':
			# new NOT LIKE phrase
			$current_var = 'notlikes';
			$current_index = count($notlikes);

			$notlikes[$current_index] = array(substr($param, 1));
			break;
		case '=':
			# new required word bound phrase
			$current_var = 'req_wordbound';
			$current_index = count($req_wordbound);

			$req_wordbound[$current_index] = array(substr($param, 1));
			break;
		case '~':
			# new excluded word bound phrase
			$current_var = 'exc_wordbound';
			$current_index = count($exc_wordbound);

			$exc_wordbound[$current_index] = array(substr($param, 1));
			break;
		case '@':
			# new nick list
			if ($no_nicks) {
				log::info('Ignoring nicks param in log_where() because no_nicks=true');
			}
			$current_var = 'req_nicks';
			$current_index = count($req_nicks);

			$req_nicks[$current_index] = array(substr($param, 1));
			break;
		case '^':
			# new exclude nick list
			if ($no_nicks) {
				log::info('Ignoring exclude nicks param in log_where() because no_nicks=true');
			}
			$current_var = 'exc_nicks';
			$current_index = count($exc_nicks);

			$exc_nicks[$current_index] = array(substr($param, 1));
			break;
		case '<':
			# new before time
			$current_var = 'before';
			$current_index = 0;

			$before[0] = array(substr($param, 1));
			break;
		case '>':
			# new after time
			$current_var = 'after';
			$current_index = 0;

			$after[0] = array(substr($param, 1));
			break;
		default:
			$fsc = substr($param, 0, 2);
			switch ($fsc) {
				case 'B+':
					# new required word bound phrase
					$current_var = 'req_wordbound';
					$current_index = count($req_wordbound);

					$req_wordbound[$current_index] = array(substr($param, 2));
					break;
				case 'B-':
					# new excluded word bound phrase
					$current_var = 'exc_wordbound';
					$current_index = count($exc_wordbound);

					$exc_wordbound[$current_index] = array(substr($param, 2));
					break;
				case 'R+':
					# new required regex
					$current_var = 'req_re';
					$current_index = count($req_re);

					$req_re[$current_index] = array(substr($param, 2));
					break;
				case 'R-':
					# new excluded regex
					$current_var = 'exc_re';
					$current_index = count($exc_re);

					$exc_re[$current_index] = array(substr($param, 2));
					break;
				default:
					# add word to current array
					${$current_var}[$current_index][] = $param;
					break;
			}
			break;
	}
}

$conds = array();

foreach ($likes as $phrasewords) {
	$phrase = dbescape(implode(' ', $phrasewords));
	$conds[] = "(message ILIKE '%$phrase%')";
}

foreach ($notlikes as $phrasewords) {
	$phrase = dbescape(implode(' ', $phrasewords));
	$conds[] = "(message NOT ILIKE '%$phrase%')";
}

foreach ($req_wordbound as $phrasewords) {
	$phrase = dbescape(preg_quote(implode(' ', $phrasewords)));
	$conds[] = "(message ~* '[[:<:]]{$phrase}[[:>:]]')";
}

foreach ($exc_wordbound as $phrasewords) {
	$phrase = dbescape(preg_quote(implode(' ', $phrasewords)));
	$conds[] = "(message !~* '[[:<:]]{$phrase}[[:>:]])";
}

foreach ($req_re as $rewords) {
	$rewords = dbescape(implode(' ', $rewords));
	$conds[] = "(message ~* '$rewords')";
}

foreach ($exc_re as $rewords) {
	$rewords = dbescape(implode(' ', $rewords));
	$conds[] = "(message !~* '$rewords')";
}

if (!$no_nicks) {
	if (count($req_nicks) > 0) {
		$nicks = array();
		foreach ($req_nicks as $nickgrp) {
			foreach ($nickgrp as $nickstr) {
				$nicklist = explode(',', strtolower($nickstr));
				$nicks = array_merge($nicks, $nicklist);
			}
		}
		$nicks = array_unique($nicks);
		$nicks = array_map('dbescape', $nicks);
		$nicks = array_map('single_quote', $nicks);
		$nicks = implode(',', $nicks);
		$conds[] = "nick IN ($nicks)";
	}

	if (count($exc_nicks) > 0) {
		$nicks = array();
		foreach ($exc_nicks as $nickstr) {
			$nicklist = explode(',', strtolower($nickstr));
			$nicks = array_merge($nicks, $nicklist);
		}
		$nicks = array_unique($nicks);
		$nicks = array_map('dbescape', $nicks);
		$nicks = array_map('single_quote', $nicks);
		$nicks = implode(',', $nicks);
		$conds[] = "nick NOT IN ($nicks)";
	}
}

if (count($before) > 0) {
	$timestr = pg_escape_string(ExtraServ::$db, implode(' ', $before[0]));
	$uts = strtotime($timestr);
	$conds[] = "(uts < $uts)";
}

if (count($after) > 0) {
	$timestr = pg_escape_string(ExtraServ::$db, implode(' ', $after[0]));
	$uts = strtotime($timestr);
	$conds[] = "(uts > $uts)";
}

if (count($conds) == 0) {
	return null;
}
$query = implode(' AND ', $conds);
if ($leading_and) {
	return " AND $query";
} else {
	return $query;
}

