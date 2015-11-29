//<?php

log::trace('entered f::statcache_line()');
list($_i) = $_ARGV;

$channel = dbescape($_i['sent_to']);
$nick = dbescape($_i['hostmask']->nick);

# prepare queries

$sname = 'select_statcache_first_use';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'SELECT 1 FROM statcache_firstuse WHERE channel=$1 AND nick=$2');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'insert_statcache_first_use';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'INSERT INTO statcache_firstuse (channel, nick, uts) VALUES ($1, $2, $3)');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'update_statcache_nick_line_count';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'UPDATE statcache_lines SET lines=lines+1 WHERE channel=$1 AND nick=$2');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'insert_statcache_nick_line_count';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'INSERT INTO statcache_lines (channel, nick, lines) VALUES ($1, $2, 1)');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'update_statcache_channel_line_count';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'UPDATE statcache_misc SET val=val+1 WHERE channel=$1 AND stat_name=\'total lines\'');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'insert_statcache_channel_line_count';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'INSERT INTO statcache_misc (channel, stat_name, val) VALUES ($1, \'total lines\', 1)');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'update_statcache_words';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'UPDATE statcache_words SET wc=wc+$4 WHERE channel=$1 AND nick=$2 AND word=$3');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'insert_statcache_words';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'INSERT INTO statcache_words (channel, nick, word, wc) VALUES ($1,$2,$3,$4)');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'update_statcache_twowords';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'UPDATE statcache_twowords SET wc=wc+$4 WHERE channel=$1 AND nick=$2 AND twowords=$3');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'insert_statcache_twowords';
if (!in_array($sname, ExtraServ::$prepared_queries)) {
	$p = pg_prepare(ExtraServ::$db, $sname, 'INSERT INTO statcache_twowords (channel, nick, twowords, wc) VALUES ($1,$2,$3,$4)');
	if ($p !== false) {
		ExtraServ::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}

$channel_nick = array($channel, $nick);

# check to see if this is the first use of the nick
$q = pg_execute(ExtraServ::$db, 'select_statcache_first_use', $channel_nick);
if ($q !== false) {
	if (pg_num_rows($q) == 0) {
		# this is to facilitate the rebuild script
		if (array_key_exists('uts', $_i)) {
			$time = $_i['uts'];
		} else {
			$time = time();
		}

		# store the timestamp
		pg_execute(ExtraServ::$db, 'insert_statcache_first_use', array($channel, $nick, $time));
	}
}

if ($_i['cmd'] != 'PRIVMSG') {
	return f::FALSE;
}

# update nick's line count
$q = pg_execute(ExtraServ::$db, 'update_statcache_nick_line_count', $channel_nick);
if (($q !== false) && (pg_affected_rows($q) == 0)) {
	pg_execute(ExtraServ::$db, 'insert_statcache_nick_line_count', $channel_nick);
}

# update channel's line count
$qparams = array($channel);
$q = pg_execute(ExtraServ::$db, 'update_statcache_channel_line_count', $qparams);
if (($q !== false) && (pg_affected_rows($q) == 0)) {
	pg_execute(ExtraServ::$db, 'insert_statcache_channel_line_count', $qparams);
}

# update unique words list
$words = preg_split('/\W+/', strtolower($_i['text']));
$word_counts = array();
foreach ($words as $word) {
	if ($word == null) {
		continue;
	}
	if (!array_key_exists($word, $word_counts)) {
		$word_counts[$word] = 0;
	}
	$word_counts[$word]++;
}
foreach ($word_counts as $word => $wc) {
	$qparams = array($channel, $nick, $word, $wc);
	$q = pg_execute(ExtraServ::$db, 'update_statcache_words', $qparams);
	if (($q !== false) && (pg_affected_rows($q) == 0)) {
		pg_execute(ExtraServ::$db, 'insert_statcache_words', $qparams);
	}
}

# update twowords list
$N = 2;
$stopwords = config::get_list('stopwords');
$sequences = array();
$words = array_map(function($w) {
	return str_replace(chr(1), '', $w);
}, array_filter(array_map('trim', explode(' ', strtolower($_i['text']))), function($w) use ($stopwords) {
	if ($w == null) {
		return false;
	}
	if ($w == chr(1).'action') {
		return false;
	}
	if (in_array($w, $stopwords)) {
		return false;
	}
	return true;
}));
$words = array_values($words);

if (count($words) >= $N) {
	for ($i = 0; $i < count($words) - ($N-1); $i++) {
		$seq = array();
		for ($j = $i; $j < $i+$N; $j++) {
			$seq[] = $words[$j];
		}
		$seq = implode(' ', $seq);
		if (isset($sequences[$seq])) {
			$sequences[$seq]++;
		} else {
			$sequences[$seq] = 1;
		}
	}
	foreach ($sequences as $seq => $wc) {
		$qparams = array($channel, $nick, $seq, $wc);
		$q = pg_execute(ExtraServ::$db, 'update_statcache_twowords', $qparams);
		if (($q !== false) && (pg_affected_rows($q) == 0)) {
			pg_execute(ExtraServ::$db, 'insert_statcache_twowords', $qparams);
		}
	}
}
