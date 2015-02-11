<?php
# Request format docs: https://www.twilio.com/docs/api/twiml/sms/twilio_request
chdir(dirname(__FILE__));
ob_start();

require_once 'lib/procs.php';
require_once 'lib/log.php';

proc::$name = 'twiliorecv';
log::$level = log::DEBUG;

log::$static = new stdClass;
log::$static->file = fopen('/var/log/lighttpd/sms.log', 'a');
if (log::$static->file === false) {
	header('HTTP/1.1 500 Server Error (1)');
	goto finish;
}
log::set_logger('smslog');

require_once 'lib/config.php';
require_once 'lib/functions.php';
require_once 'lib/utils.php';

date_default_timezone_set('UTC');

# Make sure this is a POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	header('HTTP/1.1 405 Method Not Allowed');
	log::error("Bad Request: Method not allowed {$_SERVER['REQUEST_METHOD']}");
	goto finish;
}

# Log the request
log::info('NEW REQUEST');
log::info(print_r($_POST, true));

# Check for minimum required parameters
foreach (array('Body', 'To', 'From', 'MessageSid') as $key) {
	if (!array_key_exists($key, $_POST)) {
		header('HTTP/1.1 400 Missing Parameter');
		log::error("Bad Request: Missing parameter '$key'");
		goto finish;
	}
}

$conf = config::get_instance();

$autoreply = null;

log::info('Opening database connection');
$dbpw = get_password($conf->db->pwname);
$proc = proc::$name;
$db = pg_connect("host={$conf->db->host} dbname={$conf->db->name} user={$conf->db->user} password=$dbpw application_name=ExtraServ_$proc");
if ($db === false) {
	log::error('Failed to connect to database');
	$autoreply = 'An error occurred';
	goto finish;
}

$cleanpost = array();
foreach ($_POST as $k => $v) {
	$cleanpost[pg_escape_string($db, $k)] = pg_escape_string($db, $v);
}
$_POST = $cleanpost;

$ircmessage = trim($_POST['Body']);

if (array_key_exists('NumMedia', $_POST) && $_POST['NumMedia'] > 0) {
	log::debug('Got MMS message');
	$mw = explode(' ', $ircmessage, 2);
	$fc = substr($mw[0], 0, 1);
	$dchan = null;
	if (($fc == '#') || ($fc == '&')) {
		$dchan = $mw[0];
	}

	$mms = 'TRUE';
	$media = array();
	for ($i = 0; $i < $_POST['NumMedia']; $i++) {
		$url = shortlink($_POST["MediaUrl$i"]);
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
	if ($dchan !== null) {
		$ircmessage = "$dchan $ircmessage";
	}
} else {
	$mms = 'FALSE';
}

$ts = time();
$from = substr($_POST['From'], 2);
$query = "INSERT INTO newsms (uts, message_sid, from_number, message, is_mms) VALUES ($ts, '{$_POST['MessageSid']}', '$from', '$ircmessage', $mms)";
log::debug($query);
$q = pg_query($db, $query);
if ($q === false) {
	log::error('Query failed');
	log::error(pg_last_error());
	$autoreply = 'An error occurred';
	goto finish;
}

finish:
ob_end_clean();
fclose(log::$static->file);
echo '<?xml version="1.0" encoding="UTF-8"?>',"\n";
?>
<Response>
<?php if ($autoreply != null): ?>
	<Message><![CDATA[<?php echo $autoreply; ?>]]></Message>
<?php endif; ?>
</Response>
