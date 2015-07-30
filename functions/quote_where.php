//<?php

log::trace('entered f::quote_where()');
list($params) = $_ARGV;

if (is_string($params)) {
	$p = f::parse_logquery($params);
} elseif (is_object($params)) {
	$p = $params;
} else {
	$p = f::parse_logquery("$params");
}

foreach ($p->likes as $phrasewords) {
	$phrase = dbescape(implode(' ', $phrasewords));
	$conds[] = "(quote ILIKE '%$phrase%')";
}

foreach ($p->notlikes as $phrasewords) {
	$phrase = dbescape(implode(' ', $phrasewords));
	$conds[] = "(quote NOT ILIKE '%$phrase%')";
}

foreach ($p->req_wordbound as $phrasewords) {
	$phrase = dbescape(preg_quote(implode(' ', $phrasewords)));
	$conds[] = "(quote ~* '[[:<:]]{$phrase}[[:>:]]')";
}

foreach ($p->exc_wordbound as $phrasewords) {
	$phrase = dbescape(preg_quote(implode(' ', $phrasewords)));
	$conds[] = "(quote !~* '[[:<:]]{$phrase}[[:>:]])";
}

foreach ($p->req_re as $rewords) {
	$rewords = dbescape(implode(' ', $rewords));
	$conds[] = "(quote ~* '$rewords')";
}

foreach ($p->exc_re as $rewords) {
	$rewords = dbescape(implode(' ', $rewords));
	$conds[] = "(quote !~* '$rewords')";
}

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
	$conds[] = "set_by IN ($nicks)";
}

if (count($p->exc_nicks) > 0) {
	$nicks = array();
	foreach ($p->exc_nicks as $nickgrp) {
		foreach ($nickgrp as $nickstr) {
			$nicklist = explode(',', strtolower($nickstr));
			$nicks = array_merge($nicks, $nicklist);
		}
	}
	$nicks = array_unique($nicks);
	$nicks = array_map('dbescape', $nicks);
	$nicks = array_map('single_quote', $nicks);
	$nicks = implode(',', $nicks);
	$conds[] = "set_by NOT IN ($nicks)";
}

if (count($conds) == 0) {
	return null;
}
return implode(' AND ', $conds);
