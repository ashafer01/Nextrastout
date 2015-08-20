//<?php

log::trace('entered f::cmd_restorequote()');
list($_CMD, $params, $_i) = $_ARGV;

if (preg_match('/^(\d{10})$/', $params) !== 1) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a 10-digit number');
	return f::FALSE;
}

$account_sid = ExtraServ::$conf->everyoneapi->account_sid;
$auth_token = ExtraServ::$conf->everyoneapi->auth_token;
$url = "https://api.everyoneapi.com/v1/phone/+1$params?account_sid=$account_sid&auth_token=$auth_token&data=name,cnam,carrier,location,linetype";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$json = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($json);

switch (substr($status, 0, 1)) {
	case '2':
		$fields = array();
		if (in_array('name', $data->missed)) {
			if (!in_array('cnam', $data->missed)) {
				$fields[] = "Name: {$data->data->cnam}";
			}
		} else {
			$fields[] = "Name: {$data->data->name}";
		}
		$typefield = "Type: {$data->type}";
		if (!in_array('linetype', $data->missed)) {
			$typefield .= ", {$data->data->linetype}";
		}
		$fields[] = $typefield;
		if (!in_array('location', $data->missed)) {
			$fields[] = "Location: {$data->data->location->city}, {$data->data->location->state}";
		}
		if (!in_array('carrier', $data->missed)) {
			$fields[] = "Carrier: {$data->data->carrier->name}";
		}
		$say = "Caller ID info for +1$params: " . implode(' | ', $fields);
		break;
	case '4':
		$say = 'API Error';
		break;
	case '5':
		$say = 'Server Error';
		break;
	default:
		$say = 'Unknown response';
		break;
}

$_i['handle']->say($_i['reply_to'], $say);
