//<?php

log::trace('entered f::gen_setting_help()');
list($type, $_i) = $_ARGV;

$conf = config::get_instance();
$longest = 0;
foreach ($conf->settings->{$type} as $setting => $_) {
	$len = strlen($setting);
	if ($len > $longest) {
		$longest = $len;
	}
}
$fmt = "  %{$longest}s  %s";
$prefix_space = '';
for ($i = 0; $i < $longest+2; $i++) {
	$prefix_space .= ' ';
}
foreach ($conf->settings->{$type} as $setting => $desc) {
	$line = sprintf($fmt, $setting, $desc);
	$lines = explode("\n", wordwrap($line, 80));
	$_i['handle']->notice($_i['reply_to'], array_shift($lines));
	foreach ($lines as $line) {
		$_i['handle']->notice($_i['reply_to'], "$prefix_space  $line");
	}
}
