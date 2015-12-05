//<?php

log::trace('entered f::statcache_line()');
list($_i) = $_ARGV;

$channel = dbescape($_i['sent_to']);
$nick = dbescape($_i['hostmask']->nick);

# prepare queries

$sname = 'select_statcache_first_use';
if (!in_array($sname, Nextrastout::$prepared_queries)) {
	$p = pg_prepare(Nextrastout::$db, $sname, 'SELECT 1 FROM statcache_firstuse WHERE channel=$1 AND nick=$2');
	if ($p !== false) {
		Nextrastout::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'insert_statcache_first_use';
if (!in_array($sname, Nextrastout::$prepared_queries)) {
	$p = pg_prepare(Nextrastout::$db, $sname, 'INSERT INTO statcache_firstuse (channel, nick, uts) VALUES ($1, $2, $3)');
	if ($p !== false) {
		Nextrastout::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'update_statcache_nick_line_count';
if (!in_array($sname, Nextrastout::$prepared_queries)) {
	$p = pg_prepare(Nextrastout::$db, $sname, 'UPDATE statcache_lines SET lines=lines+1 WHERE channel=$1 AND nick=$2');
	if ($p !== false) {
		Nextrastout::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'insert_statcache_nick_line_count';
if (!in_array($sname, Nextrastout::$prepared_queries)) {
	$p = pg_prepare(Nextrastout::$db, $sname, 'INSERT INTO statcache_lines (channel, nick, lines) VALUES ($1, $2, 1)');
	if ($p !== false) {
		Nextrastout::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'update_statcache_channel_line_count';
if (!in_array($sname, Nextrastout::$prepared_queries)) {
	$p = pg_prepare(Nextrastout::$db, $sname, 'UPDATE statcache_misc SET val=val+1 WHERE channel=$1 AND stat_name=\'total lines\'');
	if ($p !== false) {
		Nextrastout::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'insert_statcache_channel_line_count';
if (!in_array($sname, Nextrastout::$prepared_queries)) {
	$p = pg_prepare(Nextrastout::$db, $sname, 'INSERT INTO statcache_misc (channel, stat_name, val) VALUES ($1, \'total lines\', 1)');
	if ($p !== false) {
		Nextrastout::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'update_statcache_words';
if (!in_array($sname, Nextrastout::$prepared_queries)) {
	$p = pg_prepare(Nextrastout::$db, $sname, 'UPDATE statcache_words SET wc=wc+$4 WHERE channel=$1 AND nick=$2 AND word=$3');
	if ($p !== false) {
		Nextrastout::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}
$sname = 'insert_statcache_words';
if (!in_array($sname, Nextrastout::$prepared_queries)) {
	$p = pg_prepare(Nextrastout::$db, $sname, 'INSERT INTO statcache_words (channel, nick, word, wc) VALUES ($1,$2,$3,$4)');
	if ($p !== false) {
		Nextrastout::$prepared_queries[] = $sname;
	} else {
		log::error(pg_last_error());
		return f::FALSE;
	}
}

$channel_nick = array($channel, $nick);

# check to see if this is the first use of the nick
$q = pg_execute(Nextrastout::$db, 'select_statcache_first_use', $channel_nick);
if ($q !== false) {
	if (pg_num_rows($q) == 0) {
		# this is to facilitate the rebuild script
		if (array_key_exists('uts', $_i)) {
			$time = $_i['uts'];
		} else {
			$time = time();
		}

		# store the timestamp
		pg_execute(Nextrastout::$db, 'insert_statcache_first_use', array($channel, $nick, $time));
	}
}

if ($_i['cmd'] != 'PRIVMSG') {
	return f::FALSE;
}

# update the time profile
if (array_key_exists('uts', $_i)) { # this is to facilitate the rebuild script
	$time = $_i['uts'];
} else {
	$time = time();
}
$d_col = 'd_' . date('D', $time);
$h_col = 'h_' . date('G', $time);
$q = pg_query(Nextrastout::$db, "UPDATE statcache_timeprofile SET $d_col=$d_col+1, $h_col=$h_col+1 WHERE nick='$nick' AND channel='$channel'");
if (($q !== false) && (pg_affected_rows($q) == 0)) {
	pg_query(Nextrastout::$db, "INSERT INTO statcache_timeprofile (channel, nick, $d_col, $h_col) VALUES ('$channel', '$nick', 1, 1)");
}

# update nick's line count
$q = pg_execute(Nextrastout::$db, 'update_statcache_nick_line_count', $channel_nick);
if (($q !== false) && (pg_affected_rows($q) == 0)) {
	pg_execute(Nextrastout::$db, 'insert_statcache_nick_line_count', $channel_nick);
}

# update channel's line count
$qparams = array($channel);
$q = pg_execute(Nextrastout::$db, 'update_statcache_channel_line_count', $qparams);
if (($q !== false) && (pg_affected_rows($q) == 0)) {
	pg_execute(Nextrastout::$db, 'insert_statcache_channel_line_count', $qparams);
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
	$q = pg_execute(Nextrastout::$db, 'update_statcache_words', $qparams);
	if (($q !== false) && (pg_affected_rows($q) == 0)) {
		pg_execute(Nextrastout::$db, 'insert_statcache_words', $qparams);
	}
}
