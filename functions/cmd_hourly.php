//<?php

log::trace('entered f::cmd_hourly()');
list($_CMD, $params, $_i) = $_ARGV;

if ((strlen($params) != 5) || !ctype_digit($params)) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a zip code');
	return f::FALSE;
}

$wu_api_key = Nextrastout::$conf->wundergroup->api_key;
$api_req_url = "http://api.wunderground.com/api/$wu_api_key/hourly/q/$params.json";
log::info("Making weather undergroup request >> $api_req_url");
$wu_json = file_get_contents($api_req_url);
$wu = json_decode($wu_json, true);

if (array_key_exists('error', $wu['response'])) {
	log::error('Weather undergroup API error');
	log::error($wu['response']['error']);
	$_i['handle']->say($_i['reply_to'], "Error: {$wu['response']['error']['description']}");
} elseif (array_key_exists('hourly_forecast', $wu)) {
	$hrs = array();
	for ($i = 0; $i < 12; $i++) {
		$thishr = $wu['hourly_forecast'][$i];
		$hrs[] = "{$thishr['FCTTIME']['civil']}: {$thishr['temp']['english']}°F {$thishr['condition']}";
	}
	$reply = implode(' | ', $hrs);
	$reply = str_replace('Thunderstorm', 'T-Storm', $reply);
	$_i['handle']->say($_i['reply_to'], $reply);
} else {
	$_i['handle']->say($_i['reply_to'], 'Unknown response');
}

return f::TRUE;
