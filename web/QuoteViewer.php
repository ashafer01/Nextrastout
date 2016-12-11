<?php

function error_row($error) {
	return "<tr><td colspan=\"4\">$error</td></tr>";
}

date_default_timezone_set('UTC');

require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/utils.php';
require_once __DIR__ . '/../lib/config.php';

$conf = config::get_instance();

$dbpw = get_password($conf->db->pwname);
$db = pg_connect("host={$conf->db->host} dbname={$conf->db->name} user={$conf->db->user} password=$dbpw application_name=QuoteDBWebViewer");
if ($db === false) {
	header('Content-Type: text/plain');
	die('DB connection failed');
}

if (isset($_GET['channel'])) {
	$channel = pg_escape_string($db, $_GET['channel']);
} else {
	$channel = null;
}

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Quote DB</title>
		<link rel="stylesheet" href="/nes/nes.css" type="text/css">
	</head>
	<body>
		<?php if ($channel != null): ?>
			<h2>Quote DB</h2>
			<table>
				<thead>
					<tr>
						<td>ID</td>
						<td>Quote</td>
						<td>Set By</td>
						<td>Set Time</td>
					</tr>
				</thead>
				<tbody>
					<?php

					$quotes = pg_query($db, "SELECT * FROM quotedb WHERE channel='$channel' ORDER BY id");
					if ($quotes === false) {
						echo error_row('Query failed');
					} elseif (pg_num_rows($quotes) == 0) {
						echo error_row('No quotes found');
					} else {

						echo "\n";
						while ($qr = pg_fetch_assoc($quotes)) {
							$quote = htmlentities($qr['quote']);
							echo "\t\t\t\t\t<tr><td>{$qr['id']}</td><td class=\"q\">$quote</td><td>{$qr['set_by']}</td><td>{$qr['set_time']}</td></tr>\n";
						}

					}
					?>
				</tbody>
			</table>
		<?php else: ?>
			<h2>Select Channel</h2>
			<form method="GET" action="?">
				<select id="channel" name="channel">
				<?php
					$q = pg_query($db, 'SELECT channel FROM quotedb GROUP BY channel ORDER BY channel');
					if ($q === false) {
						echo '<option value="-1">Query Failed</option>';
					} else {
						while ($qr = pg_fetch_assoc($q)) {
							if (strlen(trim($qr['channel'])) == 0) {
								continue;
							}
							if (in_array($qr['channel'], $conf->no_web_channels)) {
								continue;
							}
							if ($qr['channel'] == $conf->default_web_channel) {
								$selected = ' selected';
							} else {
								$selected = '';
							}
							$chan = htmlentities($qr['channel']);
							echo '<option value="',$chan,'"',$selected,'>',$chan,'</option>';
						}
					}
				?>
				</select>
				<input type="submit">
			</form>
		<?php endif; ?>
	</body>
</html>
