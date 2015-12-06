//<?php

log::trace('entered f::statcache_line()');
list($_i) = $_ARGV;

$channel = dbescape($_i['args'][0]);
$nick = dbescape($_i['hostmask']->nick);

# prepare queries

$sname = 'select_statcache_first_use';
$p = Nextrastout::$db->pg_prepare($sname, 'SELECT 1 FROM statcache_firstuse WHERE channel=$1 AND nick=$2', false);
if ($p === false) {
	return f::FALSE;
}
$sname = 'insert_statcache_first_use';
$p = Nextrastout::$db->pg_prepare($sname, 'INSERT INTO statcache_firstuse (channel, nick, uts) VALUES ($1, $2, $3)', false);
if ($p === false) {
	return f::FALSE;
}
$sname = 'update_statcache_nick_line_count';
$p = Nextrastout::$db->pg_prepare($sname, 'UPDATE statcache_lines SET lines=lines+1 WHERE channel=$1 AND nick=$2', false);
if ($p === false) {
	return f::FALSE;
}
$sname = 'insert_statcache_nick_line_count';
$p = Nextrastout::$db->pg_prepare($sname, 'INSERT INTO statcache_lines (channel, nick, lines) VALUES ($1, $2, 1)', false);
if ($p === false) {
	return f::FALSE;
}
$sname = 'update_statcache_channel_line_count';
$p = Nextrastout::$db->pg_prepare($sname, 'UPDATE statcache_misc SET val=val+1 WHERE channel=$1 AND stat_name=\'total lines\'', false);
if ($p === false) {
	return f::FALSE;
}
$sname = 'insert_statcache_channel_line_count';
$p = Nextrastout::$db->pg_prepare($sname, 'INSERT INTO statcache_misc (channel, stat_name, val) VALUES ($1, \'total lines\', 1)', false);
if ($p === false) {
	return f::FALSE;
}
$sname = 'update_statcache_words';
$p = Nextrastout::$db->pg_prepare($sname, 'UPDATE statcache_words SET wc=wc+$4 WHERE channel=$1 AND nick=$2 AND word=$3', false);
if ($p === false) {
	return f::FALSE;
}
$sname = 'insert_statcache_words';
$p = Nextrastout::$db->pg_prepare($sname, 'INSERT INTO statcache_words (channel, nick, word, wc) VALUES ($1,$2,$3,$4)', false);
if ($p === false) {
	return f::FALSE;
}

$channel_nick = array($channel, $nick);

# check to see if this is the first use of the nick
$q = Nextrastout::$db->pg_execute('select_statcache_first_use', $channel_nick, false);
if ($q !== false) {
	if (pg_num_rows($q) == 0) {
		# this is to facilitate the rebuild script
		if (array_key_exists('uts', $_i)) {
			$time = $_i['uts'];
		} else {
			$time = time();
		}

		# store the timestamp
		Nextrastout::$db->pg_execute('insert_statcache_first_use', array($channel, $nick, $time), false);
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
Nextrastout::$db->pg_upsert("UPDATE statcache_timeprofile SET $d_col=$d_col+1, $h_col=$h_col+1 WHERE nick='$nick' AND channel='$channel'",
	"INSERT INTO statcache_timeprofile (channel, nick, $d_col, $h_col) VALUES ('$channel', '$nick', 1, 1)",
	'update statcache timeprofile', false);

# update nick's line count
$q = Nextrastout::$db->pg_execute('update_statcache_nick_line_count', $channel_nick, false);
if (($q !== false) && (pg_affected_rows($q) == 0)) {
	Nextrastout::$db->pg_execute('insert_statcache_nick_line_count', $channel_nick, false);
}

# update channel's line count
$qparams = array($channel);
$q = Nextrastout::$db->pg_execute('update_statcache_channel_line_count', $qparams, false);
if (($q !== false) && (pg_affected_rows($q) == 0)) {
	Nextrastout::$db->pg_execute('insert_statcache_channel_line_count', $qparams, false);
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
	$q = Nextrastout::$db->pg_execute('update_statcache_words', $qparams, false);
	if (($q !== false) && (pg_affected_rows($q) == 0)) {
		Nextrastout::$db->pg_execute('insert_statcache_words', $qparams, false);
	}
}
