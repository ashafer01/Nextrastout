//<?php

log::trace('entered f::log_where()');

$_ARGV[] = null;
$_ARGV[] = null;
$_ARGV[] = null;
list($params, $no_nicks, $date_limit, $leading_and) = $_ARGV;
if ($no_nicks === null) {
	$no_nicks = false;
}
if ($leading_and === null) {
	$leading_and = true;
}

if (is_string($params)) {
	$p = f::parse_logquery($params);
} elseif (is_object($params)) {
	$p = $params;
} else {
	$p = f::parse_logquery("$params");
}

$conds = array();

foreach ($p->likes as $phrasewords) {
	$phrase = dbescape(implode(' ', $phrasewords));
	$conds[] = "(message ILIKE '%$phrase%')";
}

foreach ($p->notlikes as $phrasewords) {
	$phrase = dbescape(implode(' ', $phrasewords));
	$conds[] = "(message NOT ILIKE '%$phrase%')";
}

foreach ($p->req_wordbound as $phrasewords) {
	$phrase = dbescape(preg_quote(implode(' ', $phrasewords)));
	$conds[] = "(message ~* '[[:<:]]{$phrase}[[:>:]]')";
}

foreach ($p->exc_wordbound as $phrasewords) {
	$phrase = dbescape(preg_quote(implode(' ', $phrasewords)));
	$conds[] = "(message !~* '[[:<:]]{$phrase}[[:>:]])";
}

foreach ($p->req_re as $rewords) {
	$rewords = dbescape(implode(' ', $rewords));
	$conds[] = "(message ~* '$rewords')";
}

foreach ($p->exc_re as $rewords) {
	$rewords = dbescape(implode(' ', $rewords));
	$conds[] = "(message !~* '$rewords')";
}

if (!$no_nicks) {
	if (count($p->req_nicks) > 0) {
		$nicks = array();
		foreach ($p->req_nicks as $nickgrp) {
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

	if (count($p->exc_nicks) > 0) {
		$nicks = array();
		foreach ($p->exc_nicks as $nickstr) {
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

if ($date_limit === null) {
	if (count($p->before) > 0) {
		$timestr = pg_escape_string(ExtraServ::$db, implode(' ', $p->before[0]));
		$uts = strtotime($timestr);
		if ($uts !== false) {
			$conds[] = "(uts < $uts)";
		}
	}

	if (count($p->after) > 0) {
		$timestr = pg_escape_string(ExtraServ::$db, implode(' ', $p->after[0]));
		$uts = strtotime($timestr);
		if ($uts !== false) {
			$conds[] = "(uts > $uts)";
		}
	}
} else {
	$before_uts = null;
	if (count($p->before) > 0) {
		$timestr = dbescape(implode(' ', $p->before[0]));
		$uts = strtotime($timestr);
		if ($uts !== false) {
			$before_uts = $uts;
		}
	}
	
	$after_uts = null;
	if (count($p->after) > 0) {
		$timestr = dbescape(implode(' ', $p->after[0]));
		$uts = strtotime($timestr);
		if ($uts !== false) {
			$after_uts = $uts;
		}
	}

	if (($before_uts !== null) && ($after_uts !== null)) {
		if (abs($before_uts - $after_uts) > $date_limit) {
			$after_uts = $before_uts - $date_limit;
		}
	} elseif (($before_uts === null) && ($after_uts !== null)) {
		$before_uts = $after_uts + $date_limit;
	} elseif (($after_uts === null) && ($before_uts !== null)) {
		$after_uts = $before_uts - $date_limit;
	} else {
		$before_uts = time();
		$after_uts = $before_uts - $date_limit;
	}

	$conds[] = "(uts < $before_uts)";
	$conds[] = "(uts > $after_uts)";
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

