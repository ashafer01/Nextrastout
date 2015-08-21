//<?php

log::trace('entered f::everyoneapi()');
list($number, $datapoints) = $_ARGV;

$datapoints = implode(',', $datapoints);

$account_sid = ExtraServ::$conf->everyoneapi->account_sid;
$auth_token = ExtraServ::$conf->everyoneapi->auth_token;
$url = "https://api.everyoneapi.com/v1/phone/+1$number?account_sid=$account_sid&auth_token=$auth_token&data=$datapoints";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$json = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
switch (substr($status, 0, 1)) {
	case '2':
		return json_decode($json);
	default:
		log::error("Got $status response from EveryoneAPI");
		return f::FALSE;
}
