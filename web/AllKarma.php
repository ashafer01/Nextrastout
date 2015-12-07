<!DOCTYPE html>
<html>
	<head>
		<title>All Karma</title>
		<link rel="stylesheet" href="/nes/nes.css" type="text/css">
	</head>
	<body>
	<?php if (isset($_GET['nick'])): ?>
		<h2>All Karma Items for <?php echo htmlentities($_GET['nick']); ?></h2>
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
				function noop($_, $__) {
					return null;
				}
				require_once __DIR__ . '/../lib/Nextrastout.class.php';
				require_once __DIR__ . '/../lib/utils.php';
				require_once __DIR__ . '/../lib/log.php';
				require_once __DIR__ . '/../lib/config.php';

				$conf = config::get_instance();
				log::set_logger('noop');

				Nextrastout::dbconnect();
				$db = Nextrastout::$db->get_conn();

				$nick = dbescape($_GET['nick']);
				$q = pg_query($db, "SELECT thing, up, down, up - down AS net FROM karma_cache WHERE nick='$nick' AND channel='{$conf->web_channel}' ORDER BY net DESC, up DESC, down DESC, thing");
				if ($q === false) {
					echo "Query failed";
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
		<p>Enter nickname to view all karma items</p>
		<form method="get" action="?">
			<input type="text" id="nick" name="nick">
			<input type="submit">
		</form>
	<?php endif; ?>
	</body>
</html>
