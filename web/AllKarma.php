<?php
function noop($_, $__) {
	return null;
}
require_once __DIR__ . '/../lib/Nextrastout.class.php';
require_once __DIR__ . '/../lib/utils.php';
require_once __DIR__ . '/../lib/log.php';
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/procs.php';

proc::$name = 'webkarma';
$conf = config::get_instance();
log::set_logger('noop');

Nextrastout::dbconnect();
$db = Nextrastout::$db->get_conn();
?>
<!DOCTYPE html>
<html>
	<head>
		<title>All Karma</title>
		<link rel="stylesheet" href="/nes/nes.css" type="text/css">
	</head>
	<body>
	<?php if (isset($_GET['nick']) && isset($_GET['chan'])): ?>
		<h2>All Karma Items for <?php echo htmlentities($_GET['nick']); ?> in <?php echo htmlentities($_GET['chan']); ?></h2>
		<?php
			$nick = dbescape($_GET['nick']);
			$chan = dbescape($_GET['chan']);

			$totals = null;
			$q = pg_query($db, "SELECT sum(CASE WHEN up-down>0 THEN 1 ELSE 0 END) AS pos, sum(CASE WHEN up-down=0 THEN 1 ELSE 0 END) AS neutral, sum(CASE WHEN up-down<0 THEN 1 ELSE 0 END) AS neg FROM karma_cache WHERE nick='$nick' AND channel='$chan'");
			if ($q === false) {
				echo 'Query failed';
			} else {
				$totals = array_map('number_format', pg_fetch_assoc($q));
			}
		?>
	<?php if ($totals !== null): ?>
		<table>
			<thead>
				<tr>
					<th>Positive</th>
					<th>Neutral</th>
					<th>Negative</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo $totals['pos']; ?></td>
					<td><?php echo $totals['neutral']; ?></td>
					<td><?php echo $totals['neg']; ?></td>
				</tr>
			</tbody>
		</table>
	<?php endif; ?>
		<table>
			<thead>
				<tr>
					<th>Thing</th>
					<th>Net</th>
					<th>Up</th>
					<th>Down</th>
				</tr>
			</thead>
			<tbody>
			<?php
				$q = pg_query($db, "SELECT thing, up, down, up - down AS net FROM karma_cache WHERE nick='$nick' AND channel='$chan' ORDER BY net DESC, up DESC, down DESC, thing");
				if ($q === false) {
					echo 'Query failed';
				} else {
					while ($qr = pg_fetch_assoc($q)) {
						$thing = htmlentities($qr['thing']);
						$net = number_format($qr['net']);
						$up = number_format($qr['up']);
						$down = number_format($qr['down']);
						echo "<tr><td>$thing</td><td>$net</td><td>+$up</td><td>-$down</td></tr>\n";
					}
				}
			?>
			</tbody>
		</table>
	<?php else: ?>
		<p>Select channel and enter nickname to view all karma items</p>
		<form method="get" action="?">
			<select id="chan" name="chan">
			<?php
				$q = pg_query($db, 'SELECT channel FROM karma_cache GROUP BY channel ORDER BY channel');
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
			<input type="text" id="nick" name="nick">
			<input type="submit">
		</form>
	<?php endif; ?>
	</body>
</html>
