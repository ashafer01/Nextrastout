<?php
# Request format docs: https://www.twilio.com/docs/api/twiml/sms/twilio_request
ob_start();

date_default_timezone_set('UTC');

require_once __DIR__ . '/../lib/procs.php';
require_once __DIR__ . '/../lib/log.php';
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/utils.php';
require_once __DIR__ . '/../lib/Nextrastout.class.php';

proc::$name = 'twiliorecv';
log::$level = log::DEBUG;
$autoreply = null;

log::$static = new stdClass;
log::$static->file = fopen('/var/log/lighttpd/sms-dev.log', 'a');
if (log::$static->file === false) {
	header('HTTP/1.1 500 Server Error (1)');
	goto finish;
}
log::set_logger('smslog');

# Make sure this is a POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	header('HTTP/1.1 405 Method Not Allowed');
	log::error("Bad Request: Method not allowed {$_SERVER['REQUEST_METHOD']}");
	goto finish;
}

# Log the request
log::info('NEW REQUEST');
log::info(print_r($_POST, true));
$ts = time();

# Check for minimum required parameters
foreach (array('Body', 'To', 'From', 'MessageSid') as $key) {
	if (!array_key_exists($key, $_POST)) {
		header('HTTP/1.1 400 Missing Parameter');
		log::error("Bad Request: Missing parameter '$key'");
		goto finish;
	}
}

$conf = config::get_instance();
Nextrastout::dbconnect();
Nextrastout::load_conf();

$cleanpost = array();
foreach ($_POST as $k => $v) {
	$cleanpost[dbescape($k)] = dbescape($v);
}
$_POST = $cleanpost;

$from = substr($_POST['From'], 2);
$ircmessage = trim($_POST['Body']);
$mw = explode(' ', $ircmessage, 2);
$mw[] = null;
$fc = substr($mw[0], 0, 1);

if (($fc == '#') || ($fc == '&')) {
	$dchan = $mw[0];
	$ircmessage = $mw[1];
} else {
	$number_data = f::get_number_data($from);
	if (isset($number_data['last_send_uts']) && isset($number_data['last_from_chan'])) {
		if (($number_data['last_send_uts'] + 3600) > time()) {
			$dchan = $number_data['last_from_chan'];
		} else {
			$dchan = $conf->sms->default_channel;
		}
	}
}

if (array_key_exists('NumMedia', $_POST) && $_POST['NumMedia'] > 0) {
	log::debug('Got MMS message');
	$mms = 'TRUE';
	$media = array();
	for ($i = 0; $i < $_POST['NumMedia']; $i++) {
		$url = f::shorten($_POST["MediaUrl$i"]);
		if ($url === false) {
			$url = $_POST["MediaUrl$i"];
		}
		$media[] = $url;
	}

	$media = implode(' | ', $media);
	if ($ircmessage == null) {
		$ircmessage = '[MMS]';
	}
	$ircmessage = "$media | $ircmessage";
} else {
	if ($ircmessage == 'BLOCK') {
		log::notice("Got number block request for $from");
		$u = Nextrastout::$db->pg_upsert("UPDATE phone_numbers SET blocked=TRUE WHERE phone_number='$from'",
			"INSERT INTO phone_numbers (phone_number, blocked) VALUES ('$from', TRUE)",
			'block number');
		if ($u === false) {
			$autoreply = 'An error occurred; your number has not been blocked. Please try again in a few minutes.';
			goto finish;
		} else {
			log::info("Successfully blocked $from");
			$autoreply = 'Your number has been blocked. Send me UNBLOCK (case-sensitive) at any time to unblock your number.';
			goto finish;
		}
	} elseif ($ircmessage == 'UNBLOCK') {
		log::notice("Got number unblock request from $from");
		$u = Nextrastout::$db->pg_query("UPDATE phone_numbers SET blocked=FALSE WHERE phone_number='$from' AND blocked IS TRUE",
			'number unblock');
		if ($u === false) {
			$autoreply = 'An error occurred; your number has not been unblocked. Please try again in a few minutes.';
			goto finish;
		} elseif (pg_affected_rows($u) == 0) {
			log::info('Number not blocked (no affected rows)');
			$autoreply = 'Your number was not blocked.';
			goto finish;
		} else {
			log::info("Successfully unblocked $from");
			$autoreply = 'Your number has been unblocked. Welcome back!';
			goto finish;
		}
	}
	$mms = 'FALSE';
}

$q = Nextrastout::$db->pg_query("INSERT INTO sms (uts, message_sid, from_number, message, is_mms, dest_chan) VALUES ($ts, '{$_POST['MessageSid']}', '$from', '$ircmessage', $mms, '$dchan')",
	'add sms');
if ($q === false) {
	$autoreply = 'An error occurred';
}

finish:
if ($autoreply != null) {
	log::info("Replying with => $autoreply");
} else {
	log::debug('Not sending a reply');
}
log::debug('----- request finished -----');
ob_end_clean();
fclose(log::$static->file);
echo '<?xml version="1.0" encoding="UTF-8"?>',"\n";
?>
<Response>
<?php if ($autoreply != null): ?>
	<Message><![CDATA[<?php echo $autoreply; ?>]]></Message>
<?php endif; ?>
</Response>
