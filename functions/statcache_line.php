//<?php

log::trace('entered f::statcache_line()');
list($_i) = $_ARGV;

$channel = dbescape($_i['sent_to']);
$nick = dbescape($_i['hostmask']->nick);

# check to see if this is the first use of the nick
#$q = pg_query(ExtraServ::$db, "SELECT 1 FROM statcache_firstuse WHERE channel='$channel' AND nick='$nick'");
#if ($q !== false) {
#	if (pg_num_rows($q) == 0) {
#		# this is to facilitate the rebuild script
#		if (array_key_exists('uts', $_i)) {
#			$time = $_i['uts'];
#		} else {
#			$time = time();
#		}
#
#		# store the timestamp
#		pg_query(ExtraServ::$db, "INSERT INTO statcache_firstuse (channel, nick, uts) VALUES ('$channel', '$nick', $time)");
#	}
#}

if ($_i['cmd'] != 'PRIVMSG') {
	return f::FALSE;
}

# update nick's line count
$q = pg_query(ExtraServ::$db, "UPDATE statcache_lines SET lines=lines+1 WHERE channel='$channel' AND nick='$nick'");
if (($q !== false) && (pg_affected_rows($q) == 0)) {
	pg_query(ExtraServ::$db, "INSERT INTO statcache_lines (channel, nick, lines) VALUES ('$channel', '$nick', 1)");
}

# update channel's line count
$q = pg_query(ExtraServ::$db, "UPDATE statcache_misc SET val=val+1 WHERE channel='$channel' AND stat_name='total lines'");
if (($q !== false) && (pg_affected_rows($q) == 0)) {
	pg_query(ExtraServ::$db, "INSERT INTO statcache_misc (channel, stat_name, val) VALUES ('$channel', 'total lines', 1)");
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
	$q = pg_query(ExtraServ::$db, "UPDATE statcache_words SET wc=wc+$wc WHERE channel='$channel' AND nick='$nick' AND word='$word'");
	if (($q !== false) && (pg_affected_rows($q) == 0)) {
		pg_query(ExtraServ::$db, "INSERT INTO statcache_words (channel, nick, word, wc) VALUES ('$channel','$nick', '$word', $wc)");
	}
}
