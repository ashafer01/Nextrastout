<?php

function error_row($error) {
	return "<tr><td colspan=\"4\">$error</td></tr>";
}

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Quote DB</title>
		<style>
			body {
				font-family: sans-serif;
			}

			table {
				margin: 20px;
			}

			td {
				padding: 6px;
			}

			td.q {
				font-family: monospace;
			}

			table, th, td {
				border: 1px solid #000;
				border-collapse: collapse;
			}

			thead {
				background-color: #ccc;
			}

			tbody tr:nth-child(odd) {
				background-color: #eee;
			}

			thead {
				font-weight: bold;
			}
		</style>
	</head>
	<body>
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

				chdir(dirname(realpath(__FILE__)));

				require_once 'lib/functions.php';
				require_once 'lib/utils.php';

				date_default_timezone_set('UTC');

				$dbpw = get_password('db');
				$db = pg_connect("host=localhost dbname=extraserv user=alex password=$dbpw application_name=QuoteDBWebViewer");
				if ($db === false) {
					echo error_row('DB connection failed');
				} else {
					$quotes = pg_query($db, "SELECT * FROM quotedb ORDER BY id");
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
				}
				?>
			</tbody>
		</table>
	</body>
</html>
