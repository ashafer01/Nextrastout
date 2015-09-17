//<?php

log::trace('entered f::cmd_translate()');
list($_CMD, $params, $_i) = $_ARGV;

$query = rawurlencode($params);

$qcount = strlen($params);

$q = pg_query(ExtraServ::$db, "SELECT value FROM keyval WHERE key='translate_count'");
if ($q === false) {
	log::error('Translate count query failed');
	log::error(pg_last_error());
	$_i['handle']->say($_i['reply_to'], 'Query failed');
	return f::FALSE;
} else {
	$qr = pg_fetch_assoc($q);
	$newcount = $qr['value'] + $qcount;
	log::info("Current translate count: {$qr['value']}");
	if ($newcount > 500000) {
		$_i['handle']->say($_i['reply_to'], 'Limit exceeded');
		return f::FALSE;
	} else {
		$q = pg_query(ExtraServ::$db, "UPDATE keyval SET value='$newcount' WHERE key='translate_count'");
		if ($q === false) {
			log::error('Update translate count query failed');
			log::error(pg_last_error());
			$_i['handle']->say($_i['reply_to'], 'Query failed');
			return f::FALSE;
		} else {
			log::info("Updated translate count to $newcount");
		}
	}
}


$key = ExtraServ::$conf->google->api_key;
$url = "https://www.googleapis.com/language/translate/v2?key=$key&target=en&format=text&q=$query";

log::debug("translate url >> $url");

$c = curl_init();
curl_setopt($c, CURLOPT_URL, $url);
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

$json = curl_exec($c);
$status = curl_getinfo($c, CURLINFO_HTTP_CODE);
log::debug("Got HTTP $status from translate request");
curl_close($c);

$data = json_decode($json);
$major_status = substr($status, 0, 1);

if ($major_status == 2) {
	$tr = $data->data->translations[0];

	$langs = config::get_json('languages');
	$fromlang = '??';
	foreach ($langs as $lang) {
		if ($lang->language == $tr->detectedSourceLanguage) {
			$fromlang = $lang->name;
			break;
		}
	}

	$say = "{$_i['hostmask']->nick}: From $fromlang to English: {$tr->translatedText}";
} else {
	$say = "{$_i['hostmask']->nick}: API Error";
	log::debug(print_r($data, true));
}

$_i['handle']->say($_i['reply_to'], $say);
