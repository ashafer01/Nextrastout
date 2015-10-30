//<?php

log::trace('entered f::cmd_cid()');
list($_CMD, $params, $_i) = $_ARGV;

if (preg_match('/^(\d{10})$/', $params) !== 1) {
	$_i['handle']->say($_i['reply_to'], 'Please specify a 10-digit number');
	return f::FALSE;
}

$data = f::everyoneapi($params, array('name','cnam','line_provider','location','linetype'));

if ($data === false) {
	$say = 'API Error';
} else {
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
	if (!in_array('line_provider', $data->missed)) {
		$fields[] = "Line Provider: {$data->data->line_provider->name}";
	}
	$say = "Caller ID info for +1$params: " . implode(' | ', $fields);
}

$_i['handle']->say($_i['reply_to'], $say);
