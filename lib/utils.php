<?php

require_once 'log.php';
require_once 'config.php';

function php_error_message($prefix, $e) {
	return "$prefix: {$e->getMessage()}\nStack Trace:\n{$e->getTraceAsString()}\n";
}

function error_logger($errno, $errstr, $errfile, $errline) {
	$e = new ErrorException($errstr, 0, $errno, $errline);
	switch ($errno) {
		case E_PARSE:
			log::fatal(php_error_message('PHP Parse Error', $e));
			throw $e;
		case E_ERROR:
		case E_USER_ERROR:
			log::fatal(php_error_message('PHP Error', $e));
			throw $e;
		case E_RECOVERABLE_ERROR:
			log::fatal(php_error_message('PHP Recoverable Error', $e));
			throw $e;
		case E_CORE_ERROR:
			log::fatal(php_error_message('PHP Core Error', $e));
			throw $e;
		case E_COMPILE_ERROR:
			log::fatal(php_error_message('PHP Compile Error', $e));
			throw $e;
		case E_WARNING:
		case E_USER_WARNING:
			log::warning(php_error_message('PHP Warning', $e));
			break;
		case E_CORE_WARNING:
			log::warning(php_error_message('PHP Core Warning', $e));
			break;
		case E_COMPILE_WARNING:
			log::warning(php_error_message('PHP Compile Warning', $e));
			break;
		case E_NOTICE:
		case E_USER_NOTICE:
			log::notice(php_error_message('PHP Notice', $e));
			break;
		case E_STRICT:
			log::info(php_error_message('PHP Strict', $e));
			break;
		case E_DEPRECATED:
		case E_USER_DEPRECATED:
			log::notice(php_error_message('PHP Deprecated', $e));
			break;
		default:
			log::error('Unknown PHP Error');
			log::fatal(php_error_message('PHP Unknown', $e));
			throw $e;
	}
}

function utimestamp() {
	list($micro, $sec) = explode(' ', microtime());
	$micro = substr($micro, 2, -2);
	return date("D Y.m.d H:i:s.$micro+00:00");
}

function get_password($name) {
	return trim(file_get_contents("passwords/$name.password"));
}

function duration_str($seconds) {
	if ($seconds == 0)
		return '0 seconds';
	$ut = $seconds;
	$sm = 60;
	$sh = 60 * $sm;
	$sd = 24 * $sh;
	$sy = 365 * $sd;
	$y = floor($ut / $sy);
	$ys = $ut % $sy;
	$d = floor($ys / $sd);
	$hs = $ys % $sd;
	$h = floor($hs / $sh);
	$ms = $hs % $sh;
	$m = floor($ms / $sm);
	$rs = $ms % $sm;
	$s = ceil($rs);
	$ago = array(
		'year' => $y,
		'day' => $d,
		'hour' => $h,
		'minute' => $m,
		'second' => $s
	);
	$ago_strs = array();
	foreach ($ago as $word => $num) {
		if ($num <= 0)
			continue;
		if ($num > 1)
			$word .= 's';
		$ago_strs[] = "$num $word";
	}
	$ago_string = implode(', ', $ago_strs);
	if ($ago_string == null) 
		$ago_string = '0 seconds*';
	return $ago_string;
}

function tab_lines($text, $level = 1) {
	$ts = '';
	for ($i = 0; $i < $level; $i++) {
		$ts .= "\t";
	}
	$lines = explode("\n", $text);
	$ret = array();
	foreach ($lines as $line) {
		$ret[] = "$ts$line";
	}
	return implode("\n", $ret);
}

function single_quote($str) {
	return "'$str'";
}

function query_whitespace($query) {
	return trim(str_replace(array("\n","\t"), array(' ', ''), $query));
}

function smslog($level, $message) {
	$message = color_formatting::strip($message);
	$ms = microtime();
	$ms = explode(' ', $ms);
	$ms = substr($ms[0], 2, -2);
	$ts = date('Y-m-d H:i:s');
	$addr = str_pad($_SERVER['REMOTE_ADDR'], 15);
	fwrite(log::$static->file, "[$ts.$ms] [$addr] $message\n");
}

function sqlify($val) {
	if (($val === true) || (strtoupper($val) == 'TRUE'))
		$val = 'TRUE';
	elseif (($val === false) || (strtoupper($val) == 'FALSE'))
		$val = 'FALSE';
	elseif (($val === null) || (strtoupper($val) == 'NULL'))
		$val = 'NULL';
	elseif (is_numeric($val))
		;
	else {
		$val = dbescape($val);
		$val = "'$val'";
	}
	return $val;
}

function rainbow($string) {
	static $colors = array('0', '4', '8', '9', '11', '12', '13');
	$len = strlen($string);
	$newstr = '';
	$last = 255;
	$color = 0;

	for ($i = 0; $i < $len; $i++) {
		$char = substr($string, $i, 1);
		if ($char == ' ') {
			$newstr .= $char;
			continue;
		}
		while (($color = $colors[array_rand($colors)]) == $last) {}
		$last = $color;
		$newstr .= "\x03$color";
		$newstr .= $char;
	}

	return $newstr;
}

function ord_suffix($number) {
	static $ends = array('th','st','nd','rd','th','th','th','th','th','th');
	if (($number % 100) >= 11 && ($number % 100) <= 13)
		return 'th';
	else
		return $ends[$number % 10];
}

function shortlink($url) {
	$conf = config::get_instance();

	$url = rawurlencode($url);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://api-ssl.bitly.com/v3/shorten?longUrl=$url&domain=j.mp&apiKey={$conf->bitly->api_key}&login={$conf->bitly->username}");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, false);
	$json = curl_exec($ch);
	curl_close($ch);
	$data = json_decode($json, true);
	$httpcode = $data['status_code'];
	switch ($httpcode) {
		case 200:
			return $data['data']['url'];
		default:
			log::error("bitly returned $httpcode");
			return false;
	}
}

function zip_to_tz($zipcode, &$shorttz = null) {
	log::trace('entered zip_to_tz()');
	if (!ctype_digit($zipcode) || strlen($zipcode) != 5) {
		log::error("Invalid zip code passed to zip_to_tz");
		return false;
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://maps.googleapis.com/maps/api/geocode/json?address=$zipcode");
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$json = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($code != 200) {
		log::error("google geocode api returned $code");
		log::error($json);
		return false;
	}

	$res = json_decode($json);

	$loc = $res->results[0]->geometry->location;
	$latlong = "{$loc->lat},{$loc->lng}";
	$timestamp = time();

	curl_setopt($ch, CURLOPT_URL, "https://maps.googleapis.com/maps/api/timezone/json?location=$latlong&timestamp=$timestamp");

	$json = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($code != 200) {
		log::error("google timezone api returned $code");
		log::error($json);
		return false;
	}

	$res = json_decode($json);
	curl_close($ch);

	$tzn = '';
	$words = explode(' ', $res->timeZoneName);
	foreach ($words as $w) {
		$tzn .= substr($w, 0, 1);
	}
	$shorttz = $tzn;

	return $res->timeZoneId;
}

class color_formatting {
	public static function escape($text) {
		return str_replace('%', '%!', $text);
	}

	public static function unescape($text) {
		return str_replace('%!', '%', $text);
	}

	public static function ansi($text) {
		# Standard ANSI colors
		static $search = null;
		static $replace = null;
		if ($search === null) {
			$map = array(
				'%0' => "\033[39m", # reset
				'%k' => "\033[30m", # black
				'%r' => "\033[31m", # red
				'%g' => "\033[32m", # green
				'%y' => "\033[33m", # yellow
				'%b' => "\033[34m", # blue
				'%m' => "\033[35m", # magenta
				'%c' => "\033[36m", # cyan
				'%l' => "\033[37m", # light gray
				'%K' => "\033[90m", # dark gray
				'%R' => "\033[91m", # light red
				'%G' => "\033[92m", # light green
				'%Y' => "\033[93m", # light yellow
				'%B' => "\033[94m", # light blue
				'%M' => "\033[95m", # light magenta
				'%C' => "\033[96m", # light cyan
				'%w' => "\033[97m", # white
				'%L' => "\033[97m"  # white (for consistency)
			);
			$search = array_keys($map);
			$replace = array_values($map);
		}
		$text = str_replace($search, $replace, $text);
		return color_formatting::unescape($text);
	}

	public static function irc($text) {
		# Control-C
		$cc = chr(3);

		static $search = null;
		static $replace = null;
		if ($search === null) {
			# in irc there is only one red, purple, and yellow unlike ANSI which has distinct light and normal versions for these
			$map = array(
				'%0' => $cc,       # reset
				'%k' => "{$cc}1",  # black
				'%r' => "{$cc}4",  # red
				'%g' => "{$cc}3",  # green
				'%y' => "{$cc}8",  # yellow
				'%b' => "{$cc}2",  # blue
				'%m' => "{$cc}6",  # magenta/purple
				'%c' => "{$cc}10", # cyan
				'%l' => "{$cc}15", # light gray
				'%K' => "{$cc}14", # dark gray
				'%R' => "{$cc}4",  # light red
				'%G' => "{$cc}9",  # light green
				'%Y' => "{$cc}8",  # light yellow
				'%B' => "{$cc}12", # light blue
				'%M' => "{$cc}6",  # light magenta/purple
				'%C' => "{$cc}11", # light cyan
				'%w' => "{$cc}0",  # white
				'%L' => "{$cc}0"   # white (for consistency)
			);
			$search = array_keys($map);
			$replace = array_values($map);
		}
		return color_formatting::unescape(str_replace($search, $replace, $text));
	}

	public static function strip($text) {
		static $codes = array(
			'%0',
			'%k',
			'%r',
			'%g',
			'%y',
			'%b',
			'%m',
			'%c',
			'%l',
			'%K',
			'%R',
			'%G',
			'%Y',
			'%B',
			'%M',
			'%C',
			'%w',
			'%L'
		);
		return color_formatting::unescape(str_replace($codes, '', $text));
	}
}
