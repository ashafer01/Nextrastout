//<?php

log::trace('entered f::cmd_hourly()');
list($_CMD, $params, $_i) = $_ARGV;

if ((strlen($params) != 5) || !ctype_digit($params)) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a zip code');
	return f::FALSE;
}

$features = array('forecast', 'alerts', 'conditions');

$wu_api_key = Nextrastout::$conf->wundergroup->api_key;
$url_features = implode('/', $features);
$api_req_url = "http://api.wunderground.com/api/$wu_api_key/$url_features/q/$params.json";
log::info("Making weather underground request >> $api_req_url");
$wu_json = file_get_contents($api_req_url);
$wu = json_decode($wu_json, true);

if (array_key_exists('error', $wu['response'])) {
	log::error('Weather undergroup API error');
	log::error($wu['response']['error']);
	$_i['handle']->say($_i['reply_to'], "Error: {$wu['response']['error']['description']}");
	return f::TRUE;
}

foreach ($features as $feature) {
	if (!array_key_exists($feature, $wu['response']['features'])) {
		log::error("Missing feature key for $feature in response");
		$_i['handle']->say($_i['reply_to'], 'Unknown response');
		return f::TRUE;
	} elseif ($wu['response']['features'][$feature] != 1) {
		log::error("Feature $feature is not 1");
		$_i['handle']->say($_i['reply_to'], 'Unknown response');
		return f::TRUE;
	}
}

$forecast = $wu['forecast']['txt_forecast'];
$alerts = $wu['alerts'];
$cur = $wu['current_observation'];

$sayparts = array();

$stmt_types = array('SPE', 'PUB', 'REP', 'REC', 'VOL', 'SVR', 'WAT', 'HUR');

foreach ($alerts as $alert) {
	$alert_text = "\x0304{$alert['description']}\x03 until {$alert['expires']}";
	if (in_array($alert['type'], $stmt_types)) {
		$msg_url = f::shorten($alert['message']);
		$alert_text .= " $msg_url";
	}
	$sayparts[] = $alert_text;
}

$beaufort_scale = array(
	7 => 'light breeze',
	12 => 'gentle breeze',
	18 => 'moderate breeze',
	24 => 'fresh breeze',
	31 => 'strong breeze',
	38 => 'high winds',
	46 => 'gale winds',
	54 => 'strong gale winds',
	63 => 'storm winds',
	72 => 'violent storm winds',
	9999 => 'hurricane force winds',
);

$current_cond = "{$cur['temp_f']}°F and {$cur['weather']}, humidity at {$cur['relative_humidity']}";
$feelslike_diff = abs($cur['feelslike_f'] - $cur['temp_f']);
log::debug("Feelslike diff = $feelslike_diff");
if ($feelslike_diff > 3) {
	$current_cond .= ", feels like {$cur['feelslike_f']}°F";
}
log::debug("Wind_mph = {$cur['wind_mph']}");
if ($cur['wind_mph'] > 4) {
	$i = 0;
	$n = count($beaufort_scale);
	reset($beaufort_scale);
	do {
		$speed = key($beaufort_scale);
		$wind_word = current($beaufort_scale);
		next($beaufort_scale);
		$i += 1;
	} while (($cur['wind_mph'] > $speed) && ($i < $n));
	$wind = lcfirst($cur['wind_string']);
	$current_cond .= ", $wind_word $wind";
}

$sayparts[] = $current_cond;

for ($i = 1; $i < 3; $i++) {
	$cf = $forecast['forecastday'][$i];
	$sayparts[] = "{$cf['title']}: {$cf['fcttext']}";
}

$say = "{$_i['hostmask']->nick}: Weather for {$cur['display_location']['full']}: " . implode(' | ', $sayparts);

$_i['handle']->say($_i['reply_to'], $say);
return f::TRUE;
